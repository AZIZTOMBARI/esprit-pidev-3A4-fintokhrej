<?php

namespace App\Controller;

use Mailtrap\MailtrapClient;
use Mailtrap\Mime\MailtrapEmail;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Annotation\Route;

class MailingApiController extends AbstractController
{
    #[Route('/api/mailing/ban-alert', name: 'app_api_mailing_ban_alert', methods: ['POST'])]
    public function sendBanAlert(Request $request, LoggerInterface $logger): JsonResponse
    {
        $expectedApiKey = (string) ($_ENV['INTERNAL_MAILING_API_KEY'] ?? $_SERVER['INTERNAL_MAILING_API_KEY'] ?? 'dev-mailing-key');
        $receivedApiKey = trim((string) $request->headers->get('X-Internal-Api-Key', ''));

        if ($expectedApiKey === '' || $receivedApiKey !== $expectedApiKey) {
            return $this->json(['error' => 'API key invalide.'], Response::HTTP_FORBIDDEN);
        }

        $payload = json_decode((string) $request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json(['error' => 'Payload JSON invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $payloadTo = strtolower(trim((string) ($payload['to'] ?? '')));
        $overrideRecipient = strtolower(trim((string) ($_ENV['BAN_ALERT_RECIPIENT_OVERRIDE'] ?? $_SERVER['BAN_ALERT_RECIPIENT_OVERRIDE'] ?? '')));
        $to = $overrideRecipient !== '' ? $overrideRecipient : $payloadTo;
        $reason = trim((string) ($payload['reason'] ?? 'Violation des règles de la communauté'));
        $durationDays = max(1, min(365, (int) ($payload['duration_days'] ?? 1)));
        $prenom = trim((string) ($payload['prenom'] ?? ''));
        $nom = trim((string) ($payload['nom'] ?? ''));
        $sourceLogId = isset($payload['source_log_id']) ? (int) $payload['source_log_id'] : null;

        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['error' => 'Email destinataire invalide.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $displayName = trim($prenom.' '.$nom);
        if ($displayName === '') {
            $displayName = 'Utilisateur';
        }

        $subject = 'Alerte moderation - Votre compte est temporairement banni';
        $body = implode("\n", [
            'Bonjour '.$displayName.',',
            '',
            'Suite a une tentative de publication non conforme, votre compte a ete banni temporairement.',
            'Duree du ban: '.$durationDays.' jour(s).',
            'Motif: '.$reason,
            '',
            $sourceLogId ? 'Reference de moderation: #'.$sourceLogId : 'Reference de moderation: non specifiee',
            '',
            'Vous pourrez vous reconnecter a la fin de cette periode.',
            'Si vous pensez qu\'il s\'agit d\'une erreur, contactez l\'administrateur.',
            '',
            'Equipe Fintokhrej',
        ]);

        $from = (string) ($_ENV['MAILING_FROM_ADDRESS'] ?? $_SERVER['MAILING_FROM_ADDRESS'] ?? 'no-reply@fintokhrej.local');
        $mailtrapApiKey = trim((string) ($_ENV['MAILTRAP_API_KEY'] ?? $_SERVER['MAILTRAP_API_KEY'] ?? ''));
        if ($mailtrapApiKey === '') {
            return $this->json([
                'status' => 'failed',
                'message' => 'MAILTRAP_API_KEY est manquant.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        try {
            $mailtrap = MailtrapClient::initSendingEmails(apiKey: $mailtrapApiKey);

            $email = (new MailtrapEmail())
                ->from(new Address($from, 'Fintokhrej'))
                ->to(new Address($to))
                ->subject($subject)
                ->text($body);

            $mailtrap->send($email);
        } catch (\Throwable $e) {
            $logger->error('Mailing API ban alert failed', [
                'to' => $to,
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);

            return $this->json([
                'status' => 'failed',
                'message' => 'Envoi du mail impossible',
            ], Response::HTTP_BAD_GATEWAY);
        }

        $logger->info('Mailing API ban alert sent', [
            'to' => $to,
            'duration_days' => $durationDays,
            'reason' => $reason,
            'source_log_id' => $sourceLogId,
        ]);

        return $this->json([
            'status' => 'sent',
            'to' => $to,
            'duration_days' => $durationDays,
        ]);
    }

    #[Route('/api/mailing/moderation-alert', name: 'app_api_mailing_moderation_alert', methods: ['POST'])]
    public function sendModerationAlert(Request $request, LoggerInterface $logger): JsonResponse
    {
        $expectedApiKey = (string) ($_ENV['INTERNAL_MAILING_API_KEY'] ?? $_SERVER['INTERNAL_MAILING_API_KEY'] ?? 'dev-mailing-key');
        $receivedApiKey = trim((string) $request->headers->get('X-Internal-Api-Key', ''));

        if ($expectedApiKey === '' || $receivedApiKey !== $expectedApiKey) {
            return $this->json(['error' => 'API key invalide.'], Response::HTTP_FORBIDDEN);
        }

        $payload = json_decode((string) $request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json(['error' => 'Payload JSON invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $payloadTo = strtolower(trim((string) ($payload['to'] ?? '')));
        $overrideRecipient = strtolower(trim((string) ($_ENV['BAN_ALERT_RECIPIENT_OVERRIDE'] ?? $_SERVER['BAN_ALERT_RECIPIENT_OVERRIDE'] ?? '')));
        $to = $overrideRecipient !== '' ? $overrideRecipient : $payloadTo;

        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['error' => 'Email destinataire invalide.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $severity = (string) ($payload['severity'] ?? 'moderate');
        $score = (int) ($payload['score'] ?? 0);
        $action = (string) ($payload['action'] ?? 'create');
        $lieuId = (int) ($payload['lieu_id'] ?? 0);
        $userId = (int) ($payload['user_id'] ?? 0);
        $sourceLogId = isset($payload['source_log_id']) ? (int) $payload['source_log_id'] : null;
        $commentPreview = trim((string) ($payload['comment_preview'] ?? ''));
        $prenom = trim((string) ($payload['prenom'] ?? ''));
        $nom = trim((string) ($payload['nom'] ?? ''));
        $termsRaw = $payload['terms'] ?? [];
        $terms = is_array($termsRaw)
            ? array_values(array_filter(array_map(static fn ($term): string => trim((string) $term), $termsRaw), static fn (string $term): bool => $term !== ''))
            : [];

        $userLabel = trim($prenom.' '.$nom);
        if ($userLabel === '') {
            $userLabel = 'Utilisateur #'.$userId;
        }

        $subject = 'Alerte moderation - Avis toxique detecte';
        $body = implode("\n", [
            'Alerte automatique de moderation.',
            '',
            'Utilisateur: '.$userLabel,
            'Email utilisateur: '.($payloadTo !== '' ? $payloadTo : 'non specifie'),
            'User ID: '.$userId,
            'Lieu ID: '.$lieuId,
            'Action: '.$action,
            'Severite: '.$severity,
            'Score: '.$score,
            'Termes detectes: '.($terms !== [] ? implode(', ', $terms) : 'aucun terme detaille'),
            'Apercu du commentaire: '.($commentPreview !== '' ? $commentPreview : 'N/A'),
            $sourceLogId ? 'Reference moderation: #'.$sourceLogId : 'Reference moderation: non specifiee',
            '',
            'Equipe Fintokhrej',
        ]);

        $from = (string) ($_ENV['MAILING_FROM_ADDRESS'] ?? $_SERVER['MAILING_FROM_ADDRESS'] ?? 'no-reply@fintokhrej.local');
        $mailtrapApiKey = trim((string) ($_ENV['MAILTRAP_API_KEY'] ?? $_SERVER['MAILTRAP_API_KEY'] ?? ''));
        if ($mailtrapApiKey === '') {
            return $this->json([
                'status' => 'failed',
                'message' => 'MAILTRAP_API_KEY est manquant.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        try {
            $mailtrap = MailtrapClient::initSendingEmails(apiKey: $mailtrapApiKey);

            $email = (new MailtrapEmail())
                ->from(new Address($from, 'Fintokhrej'))
                ->to(new Address($to))
                ->subject($subject)
                ->text($body);

            $mailtrap->send($email);
        } catch (\Throwable $e) {
            $logger->error('Mailing API moderation alert failed', [
                'to' => $to,
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);

            return $this->json([
                'status' => 'failed',
                'message' => 'Envoi du mail impossible',
            ], Response::HTTP_BAD_GATEWAY);
        }

        $logger->info('Mailing API moderation alert sent', [
            'to' => $to,
            'severity' => $severity,
            'score' => $score,
            'source_log_id' => $sourceLogId,
        ]);

        return $this->json([
            'status' => 'sent',
            'to' => $to,
        ]);
    }
}
