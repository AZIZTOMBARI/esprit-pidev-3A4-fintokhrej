<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Service\FrontChatbotService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class ChatbotController extends AbstractController
{
    #[Route('/api/chatbot/message', name: 'app_front_chatbot_message', methods: ['POST'])]
    public function message(Request $request, FrontChatbotService $chatbotService, LoggerInterface $logger): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json([
                'ok' => false,
                'error' => 'Payload JSON invalide.',
            ], 400);
        }

        $message = trim((string) ($payload['message'] ?? ''));
        if ($message === '') {
            return $this->json([
                'ok' => false,
                'error' => 'Le message est obligatoire.',
            ], 422);
        }

        $history = is_array($payload['history'] ?? null) ? $payload['history'] : [];

        try {
            $answer = $chatbotService->ask($message, $history);
        } catch (\Throwable $exception) {
            $logger->error('Chatbot API call failed', [
                'exception' => $exception,
            ]);

            return $this->json([
                'ok' => false,
                'error' => 'Erreur interne du chatbot.',
            ], 500);
        }

        return $this->json([
            'ok' => true,
            'answer' => $answer,
        ]);
    }
}
