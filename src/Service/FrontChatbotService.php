<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class FrontChatbotService
{
    private const GROQ_ENDPOINT = 'https://api.groq.com/openai/v1/chat/completions';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly Connection $connection,
        private readonly string $groqApiKey,
        private readonly string $groqModel,
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $history
     */
    public function ask(string $question, array $history = []): string
    {
        $question = trim($question);
        if ($question == '') {
            return 'Je n\'ai pas recu de question. Tu peux me redonner plus de details ?';
        }

        if ($this->groqApiKey === '') {
            return $this->buildLocalFallbackAnswer($question);
        }

        $messages = [
            [
                'role' => 'system',
                'content' => $this->buildSystemPrompt(),
            ],
        ];

        foreach ($this->sanitizeHistory($history) as $message) {
            $messages[] = $message;
        }

        $messages[] = [
            'role' => 'user',
            'content' => $question,
        ];

        try {
            $response = $this->httpClient->request('POST', self::GROQ_ENDPOINT, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->groqApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $this->groqModel,
                    'temperature' => 0.25,
                    'max_tokens' => 700,
                    'messages' => $messages,
                ],
                'timeout' => 25,
            ]);

            $payload = $response->toArray(false);
            $groqError = $payload['error']['message'] ?? null;
            if (is_string($groqError) && trim($groqError) !== '') {
                return 'Le service IA a retourne une erreur: ' . trim($groqError);
            }

            $content = $payload['choices'][0]['message']['content'] ?? null;
            if (is_string($content) && trim($content) !== '') {
                return trim($content);
            }
        } catch (ExceptionInterface) {
            return 'Je rencontre un souci de connexion au service IA pour le moment. Reessaie dans quelques secondes.';
        }

        return 'Je n\'ai pas pu generer une reponse pour cette question. Tu peux reformuler ?';
    }

    /**
     * @param array<int, array<string, mixed>> $history
     * @return array<int, array{role:string, content:string}>
     */
    private function sanitizeHistory(array $history): array
    {
        $clean = [];
        foreach (array_slice($history, -8) as $message) {
            $role = (string) ($message['role'] ?? '');
            $content = trim((string) ($message['content'] ?? ''));
            if ($content === '') {
                continue;
            }

            if (!in_array($role, ['user', 'assistant'], true)) {
                continue;
            }

            $clean[] = [
                'role' => $role,
                'content' => mb_substr($content, 0, 1200),
            ];
        }

        return $clean;
    }

    private function buildSystemPrompt(): string
    {
        return implode("\n", [
            'Tu es Assistant Fin Tokhroj, un guide utile et concis pour une plateforme de sorties et lieux en Tunisie.',
            'Reponds en francais simple, ton chaleureux, et reste oriente action.',
            'Quand l\'utilisateur demande des infos pratiques (lieux, ville, horaires, prix), base-toi en priorite sur le contexte ci-dessous.',
            'Si une information manque, dis-le clairement et propose une etape suivante.',
            'N\'invente pas de promotions ni de disponibilites non presentes dans le contexte.',
            '',
            'Contexte applicatif:',
            $this->buildKnowledgeSnapshot(),
        ]);
    }

    private function buildKnowledgeSnapshot(): string
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, nom, ville, adresse, budget_min, budget_max, type, categorie FROM lieu ORDER BY id DESC LIMIT 40'
        );

        if ($rows === []) {
            return 'Aucun lieu disponible dans la base pour le moment.';
        }

        $lines = [];
        foreach ($rows as $row) {
            $lines[] = sprintf(
                '- %s | ville: %s | adresse: %s | budget: %s-%s TND | type: %s | categorie: %s',
                (string) ($row['nom'] ?? 'Lieu sans nom'),
                (string) ($row['ville'] ?? 'N/A'),
                (string) ($row['adresse'] ?? 'N/A'),
                (string) ($row['budget_min'] ?? 'N/A'),
                (string) ($row['budget_max'] ?? 'N/A'),
                (string) ($row['type'] ?? 'N/A'),
                (string) ($row['categorie'] ?? 'N/A'),
            );
        }

        return implode("\n", $lines);
    }

    private function buildLocalFallbackAnswer(string $question): string
    {
        $lower = mb_strtolower($question);

        if (str_contains($lower, 'horaire') || str_contains($lower, 'ouvert')) {
            return 'Je peux t\'aider sur les horaires des lieux, mais la cle API Groq n\'est pas configuree. Ajoute GROQ_API_KEY dans .env.local pour activer les reponses intelligentes.';
        }

        if (str_contains($lower, 'prix') || str_contains($lower, 'budget')) {
            return 'Pour les prix et budgets, active d\'abord GROQ_API_KEY dans .env.local, puis je pourrai te proposer des suggestions precises.';
        }

        return 'Assistant actif en mode local. Configure GROQ_API_KEY dans .env.local pour des reponses completees par IA.';
    }
}
