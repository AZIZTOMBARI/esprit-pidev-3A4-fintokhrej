<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\LieuRepository;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Service du chatbot front-end : interroge l'API Groq avec un contexte
 * enrichi des lieux disponibles en base de donnees.
 */
final class FrontChatbotService
{
    private const API_URL = 'https://api.groq.com/openai/v1/chat/completions';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LieuRepository $lieuRepository,
        private readonly LoggerInterface $logger,
        private readonly string $groqApiKey,
        private readonly string $groqModel,
    ) {}

    /**
     * @param array<int, array{role: string, content: string}> $history
     */
    public function ask(string $message, array $history = []): string
    {
        if (trim($this->groqApiKey) === '') {
            $this->logger->warning('Chatbot key is missing: GROQ_API_KEY is empty.');

            return "Le chatbot n'est pas configure pour le moment. Ajoute GROQ_API_KEY dans .env.local.";
        }

        $lieux = $this->lieuRepository->findAllForChatbot();
        $lieuxContext = $this->buildLieuxContext($lieux);

        $systemPrompt = <<<PROMPT
Tu es un assistant de voyage pour la plateforme "Fin Tokhroj" dediee a la decouverte de lieux et sorties en Tunisie.
Reponds toujours en francais, de facon concise, chaleureuse et utile.
Si l'utilisateur ecrit en arabe, reponds en arabe.

Voici les lieux disponibles sur la plateforme :
{$lieuxContext}

Regles :
- Propose des lieux pertinents selon la demande de l'utilisateur (ville, categorie, budget, ambiance).
- Si aucun lieu ne correspond, dis-le honnetement.
- Ne donne pas d'informations inventees.
PROMPT;

        $messages = [
            [
                'role' => 'system',
                'content' => $systemPrompt,
            ],
        ];

        foreach ($history as $entry) {
            if (!isset($entry['role'], $entry['content'])) {
                continue;
            }

            $role = (string) $entry['role'];
            if (!in_array($role, ['user', 'assistant'], true)) {
                continue;
            }

            $content = trim((string) $entry['content']);
            if ($content === '') {
                continue;
            }

            $messages[] = [
                'role' => $role,
                'content' => $content,
            ];
        }

        $messages[] = [
            'role' => 'user',
            'content' => $message,
        ];

        try {
            $response = $this->httpClient->request('POST', self::API_URL, [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->groqApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $this->groqModel,
                    'messages' => $messages,
                    'temperature' => 0.4,
                ],
                'timeout' => 30,
            ]);

            $data = $response->toArray();
        } catch (ExceptionInterface|\Throwable $exception) {
            $this->logger->error('Chatbot provider call failed', [
                'exception' => $exception,
            ]);

            return "Je n'arrive pas a contacter le service chatbot pour le moment. Reessaie dans un instant.";
        }

        $answer = trim((string) ($data['choices'][0]['message']['content'] ?? ''));

        if ($answer === '') {
            $this->logger->warning('Chatbot provider returned an empty answer.', [
                'response' => $data,
            ]);

            return "Je n'ai pas trouve de reponse exploitable pour le moment. Reessaie avec plus de details.";
        }

        return $answer;
    }

    /**
     * @param \App\Entity\Lieu[] $lieux
     */
    private function buildLieuxContext(array $lieux): string
    {
        if ($lieux === []) {
            return 'Aucun lieu disponible pour le moment.';
        }

        $lines = [];
        foreach ($lieux as $lieu) {
            $line = sprintf(
                '- %s | Ville: %s | Categorie: %s | Type: %s | Budget: %s-%s TND',
                $lieu->getNom(),
                $lieu->getVille(),
                $lieu->getCategorie()?->label() ?? 'N/A',
                $lieu->getType()?->label() ?? 'N/A',
                $lieu->getBudgetMin() ?? '?',
                $lieu->getBudgetMax() ?? '?',
            );

            if ($lieu->getAdresse()) {
                $line .= ' | '.$lieu->getAdresse();
            }

            $lines[] = $line;
        }

        return implode("\n", $lines);
    }
}
