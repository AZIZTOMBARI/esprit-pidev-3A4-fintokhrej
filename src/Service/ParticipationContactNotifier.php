<?php

namespace App\Service;

use App\Repository\AnnonceSortieRepository;
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
        private readonly AnnonceSortieRepository $annonceSortieRepository,
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

        // Recuperer les details de la sortie (utile uniquement en cas d acceptation)
        $sortie = $accepted ? $this->annonceSortieRepository->find($sortieId) : null;

        $payload = [
            'recipientName' => $recipientName,
            'sortieTitle' => $sortieTitle,
            'accepted' => $accepted,
            'mailerFromName' => $this->mailerFromName,
            'sortie' => $sortie,
        ];

        $email = (new Email())
            ->from(new Address($this->mailerFromEmail, $this->mailerFromName))
            ->to($emailAddress)
            ->subject($subject)
            ->html($this->twig->render('emails/participation_decision.html.twig', $payload))
            ->text($this->buildParticipationDecisionText($recipientName, $sortieTitle, $accepted, $sortie));

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

    private function buildParticipationDecisionText(?string $recipientName, string $sortieTitle, bool $accepted, $sortie): string
    {
        $greeting = $recipientName ? 'Bonjour '.$recipientName.',' : 'Bonjour,';

        if (!$accepted) {
            return $greeting."\n\n"
                .'Nous vous informons que votre demande de participation a la sortie "'.$sortieTitle.'" n a pas ete acceptee cette fois-ci.'."\n\n"
                .'Merci de votre interet.'."\n"
                .$this->mailerFromName;
        }

        $details = 'Details de la sortie :'."\n";
        if ($sortie) {
            if (method_exists($sortie, 'getDateSortie') && $sortie->getDateSortie()) {
                $details .= '- Date : '.$sortie->getDateSortie()->format('d/m/Y a H:i')."\n";
            }
            if (method_exists($sortie, 'getVille') && $sortie->getVille()) {
                $details .= '- Ville : '.$sortie->getVille()."\n";
            }
            if (method_exists($sortie, 'getLieuTexte') && $sortie->getLieuTexte()) {
                $details .= '- Lieu : '.$sortie->getLieuTexte()."\n";
            }
            if (method_exists($sortie, 'getPointRencontre') && $sortie->getPointRencontre()) {
                $details .= '- Point de rencontre : '.$sortie->getPointRencontre()."\n";
            }
            if (method_exists($sortie, 'getTypeActivite') && $sortie->getTypeActivite()) {
                $details .= '- Type : '.$sortie->getTypeActivite()."\n";
            }
            if (method_exists($sortie, 'getBudgetMax') && $sortie->getBudgetMax() !== null) {
                $details .= '- Budget max : '.$sortie->getBudgetMax().' TND'."\n";
            }
        }

        return $greeting."\n\n"
            .'Votre participation a "'.$sortieTitle.'" est confirmee.'."\n\n"
            .$details."\n"
            .'Merci,'."\n"
            .$this->mailerFromName;
    }
}