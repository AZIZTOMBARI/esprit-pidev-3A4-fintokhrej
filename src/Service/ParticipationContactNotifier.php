<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Notifier\Exception\TransportExceptionInterface as NotifierTransportExceptionInterface;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\TexterInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

class ParticipationContactNotifier
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly TexterInterface $texter,
        private readonly Environment $twig,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LoggerInterface $logger,
        private readonly string $mailerFromEmail,
        private readonly string $mailerFromName,
        private readonly string $smsFrom,
    ) {
    }

    public function sendParticipationDecision(
        string $contactPrefer,
        ?string $contactValue,
        int $sortieId,
        string $sortieTitle,
        bool $accepted,
        ?string $recipientName = null,
    ): void {
        $contactPrefer = strtoupper(trim($contactPrefer));
        $contactValue = trim((string) $contactValue);

        if ($contactValue === '') {
            return;
        }

        if ($contactPrefer === 'TELEPHONE') {
            $this->sendSmsDecision($contactValue, $sortieTitle, $accepted, $recipientName);

            return;
        }

        $this->sendEmailDecision($contactValue, $sortieId, $sortieTitle, $accepted, $recipientName);
    }

    private function sendEmailDecision(string $emailAddress, int $sortieId, string $sortieTitle, bool $accepted, ?string $recipientName): void
    {
        if (!filter_var($emailAddress, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $subject = $accepted
            ? 'Bonne nouvelle : votre participation est confirmee'
            : 'Mise a jour de votre demande de participation';

        $sortieUrl = $this->urlGenerator->generate('app_sorties_show', ['id' => $sortieId], UrlGeneratorInterface::ABSOLUTE_URL);
        $payload = [
            'recipientName' => $recipientName,
            'sortieTitle' => $sortieTitle,
            'accepted' => $accepted,
            'mailerFromName' => $this->mailerFromName,
            'sortieUrl' => $sortieUrl,
        ];

        $email = (new Email())
            ->from(new Address($this->mailerFromEmail, $this->mailerFromName))
            ->to($emailAddress)
            ->subject($subject)
            ->html($this->twig->render('emails/participation_decision.html.twig', $payload))
            ->text($this->buildParticipationDecisionText($recipientName, $sortieTitle, $accepted, $sortieUrl));

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface|\Throwable $exception) {
            $this->logger->warning('Echec envoi mail participation.', [
                'email' => $emailAddress,
                'sortie' => $sortieTitle,
                'accepted' => $accepted,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function sendSmsDecision(string $phoneNumber, string $sortieTitle, bool $accepted, ?string $recipientName): void
    {
        $phoneNumber = $this->normalizeSmsPhone($phoneNumber);
        if ($phoneNumber === null) {
            return;
        }

        $message = $accepted
            ? 'FinTokhroj : bonne nouvelle, votre participation a "'.$sortieTitle.'" est confirmee.'
            : 'FinTokhroj : votre demande pour "'.$sortieTitle.'" n a pas ete retenue cette fois.';

        if ($recipientName) {
            $message = $recipientName.', '.$message;
        }

        $sms = new SmsMessage($phoneNumber, $message);

        try {
            $this->texter->send($sms);
        } catch (NotifierTransportExceptionInterface|\Throwable $exception) {
            $this->logger->warning('Echec envoi SMS participation.', [
                'phone' => $phoneNumber,
                'sortie' => $sortieTitle,
                'accepted' => $accepted,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function normalizeSmsPhone(string $phoneNumber): ?string
    {
        $digits = preg_replace('/\D+/', '', $phoneNumber) ?? '';
        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '216')) {
            $digits = '+'.$digits;
        } elseif (strlen($digits) === 8) {
            $digits = '+216'.$digits;
        } elseif (!str_starts_with($phoneNumber, '+')) {
            $digits = '+'.$digits;
        } else {
            $digits = $phoneNumber;
        }

        return $digits;
    }

    private function buildParticipationDecisionText(?string $recipientName, string $sortieTitle, bool $accepted, string $sortieUrl): string
    {
        $greeting = $recipientName ? 'Bonjour '.$recipientName.',' : 'Bonjour,';
        $headline = $accepted
            ? 'Votre demande de participation a ete acceptee.'
            : 'Votre demande de participation a ete refusee.';
        $details = $accepted
            ? 'Vous pouvez maintenant vous preparer et suivre les prochaines mises a jour dans l application.'
            : 'Nous vous invitons a consulter d autres sorties disponibles ou a retenter votre chance plus tard.';

        return $greeting."\n\n"
            .$headline."\n"
            .'Sortie : "'.$sortieTitle."\"\n\n"
            .$details."\n\n"
            .'Acces direct : '.$sortieUrl."\n\n"
            .'Merci,'."\n"
            .$this->mailerFromName;
    }
}
