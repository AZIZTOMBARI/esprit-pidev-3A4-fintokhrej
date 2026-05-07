<?php

namespace App\Service;

use App\Entity\Evenement;
use App\Entity\Inscription;
use App\Entity\Paiement;
use App\Entity\Ticket;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class EvenementService
{
    public function __construct(private EntityManagerInterface $em) {}

    public function demanderInscription(
        Evenement $evenement,
        User $user,
        int $nbTickets = 1
    ): Inscription {
        $repoInscription = $this->em->getRepository(Inscription::class);
        if ($repoInscription->isUserAlreadyRegistered($user->getId(), $evenement->getId())) {
            throw new BadRequestHttpException('Vous etes deja inscrit a cet evenement.');
        }

        if (!$evenement->avoirPlacesPour($nbTickets)) {
            throw new BadRequestHttpException(
                sprintf('Il ne reste que %d place(s) disponible(s).', $evenement->getPlacesRestantes())
            );
        }

        if (!$evenement->estOuvert()) {
            throw new BadRequestHttpException('Les inscriptions a cet evenement sont fermees.');
        }

        $inscription = new Inscription();
        $inscription->setEvenement($evenement);
        $inscription->setUser($user);
        $inscription->setNbTickets($nbTickets);
        $inscription->setStatut(Inscription::STATUT_CONFIRMEE);
        $inscription->setPaiement(0.0);

        $this->em->persist($inscription);
        $this->em->flush();

        return $inscription;
    }

    public function accepterInscription(Inscription $inscription): Inscription
    {
        if ($inscription->getStatut() === Inscription::STATUT_PAYEE) {
            return $inscription;
        }

        $evenement = $inscription->getEvenement();
        if (!$evenement->avoirPlacesPour($inscription->getNbTickets())) {
            throw new BadRequestHttpException('Plus assez de places disponibles.');
        }

        $inscription->setStatut(Inscription::STATUT_CONFIRMEE);
        $this->em->flush();

        return $inscription;
    }

    public function refuserInscription(Inscription $inscription, string $motif = ''): Inscription
    {
        if ($inscription->getStatut() === Inscription::STATUT_PAYEE) {
            throw new BadRequestHttpException(
                'Impossible de refuser une inscription deja payee. Utilisez le remboursement.'
            );
        }

        $inscription->setStatut(Inscription::STATUT_REJETEE);
        $this->em->flush();

        return $inscription;
    }

    public function annulerInscription(Inscription $inscription): Inscription
    {
        if (in_array($inscription->getStatut(), [Inscription::STATUT_ANNULEE, Inscription::STATUT_REJETEE], true)) {
            throw new BadRequestHttpException('Cette inscription est deja annulee.');
        }

        $inscription->setStatut(Inscription::STATUT_ANNULEE);
        $this->em->flush();

        return $inscription;
    }

    public function effectuerPaiement(
        Inscription $inscription,
        string $methode = Paiement::METHODE_CARTE,
        ?string $nomCarte = null,
        ?string $quatreDerniers = null,
        bool $simulerEchec = false
    ): Paiement {
        if ($inscription->getStatut() !== Inscription::STATUT_CONFIRMEE) {
            throw new BadRequestHttpException('Seules les inscriptions confirmees peuvent etre payees.');
        }

        if ($inscription->isPaiementEffectue()) {
            throw new BadRequestHttpException('Le paiement a deja ete effectue.');
        }

        $isSuccessful = !$simulerEchec && (rand(1, 100) > 20);

        $paiement = new Paiement();
        $paiement->setInscription($inscription);
        $paiement->setMontant($inscription->getMontantTotal());
        $paiement->setMethode($methode);
        $paiement->setReferenceCode('REF-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)));
        $paiement->markPaidAt(new \DateTime());
        $paiement->setNomCarte($methode === Paiement::METHODE_CARTE ? $nomCarte : null);
        $paiement->setQuatreDerniers($methode === Paiement::METHODE_CARTE ? $quatreDerniers : null);

        if ($isSuccessful) {
            $paiement->setStatut(Paiement::STATUT_PAYE);
            $inscription->setStatut(Inscription::STATUT_PAYEE);
            $this->genererTickets($inscription);
        } else {
            $paiement->setStatut(Paiement::STATUT_ECHOUE);
        }

        $this->em->persist($paiement);
        $this->em->flush();

        return $paiement;
    }

    public function genererTickets(Inscription $inscription): void
    {
        if (!$inscription->getTickets()->isEmpty()) {
            return;
        }

        $nbTickets = $inscription->getNbTickets();
        for ($i = 0; $i < $nbTickets; $i++) {
            $ticket = new Ticket();
            $ticket->setInscription($inscription);
            $ticket->setDate(new \DateTime());
            $ticket->setNumeroTicket($this->genererNumeroTicket($inscription, $i + 1));
            $ticket->setCodeValidation($this->genererCodeValidation($inscription, $i + 1));
            $this->em->persist($ticket);
        }

        $this->em->flush();
    }

    public function rembourserInscription(Inscription $inscription, string $motif = ''): Paiement
    {
        $paiement = $inscription->getPaiementPrincipal();

        if (!$paiement || !$paiement->estReussi()) {
            throw new BadRequestHttpException('Aucun paiement a rembourser.');
        }

        $paiement->setStatut(Paiement::STATUT_REMBOURSE);
        $inscription->setStatut(Inscription::STATUT_ANNULEE);

        $this->em->flush();

        return $paiement;
    }

    public function getInscriptionsEnAttente(Evenement $evenement = null): array
    {
        $repo = $this->em->getRepository(Inscription::class);

        return $repo->findInscriptionsEnAttente($evenement);
    }

    public function getStatistiquesEvenement(Evenement $evenement): array
    {
        $inscriptions = $evenement->getInscriptions();

        return [
            'total_demandes' => $inscriptions->count(),
            'confirmees' => $inscriptions->filter(fn (Inscription $i) => $i->getStatut() === Inscription::STATUT_CONFIRMEE)->count(),
            'payees' => $inscriptions->filter(fn (Inscription $i) => $i->getStatut() === Inscription::STATUT_PAYEE)->count(),
            'en_attente' => $inscriptions->filter(fn (Inscription $i) => $i->getStatut() === Inscription::STATUT_EN_ATTENTE)->count(),
            'rejetees' => $inscriptions->filter(fn (Inscription $i) => $i->getStatut() === Inscription::STATUT_REJETEE)->count(),
            'annulees' => $inscriptions->filter(fn (Inscription $i) => $i->getStatut() === Inscription::STATUT_ANNULEE)->count(),
            'non_payees' => $inscriptions->filter(
                fn (Inscription $i) => $i->getStatut() === Inscription::STATUT_CONFIRMEE && !$i->isPaiementEffectue()
            )->count(),
            'places_restantes' => $evenement->getPlacesRestantes(),
            'taux_remplissage' => $evenement->getTauxRemplissage(),
        ];
    }

    private function genererNumeroTicket(Inscription $inscription, int $index): string
    {
        $eventId = $inscription->getEvenement()?->getId() ?? 'EVT';
        $inscriptionId = $inscription->getId() ?? 'INS';
        $dateCode = (new \DateTime())->format('Ymd');

        return sprintf('EVT-%s-INS-%s-%s-%02d', $eventId, $inscriptionId, $dateCode, $index);
    }

    private function genererCodeValidation(Inscription $inscription, int $index): string
    {
        return strtoupper(substr(hash('sha256', sprintf(
            '%s-%s-%s-%s',
            $inscription->getId(),
            $inscription->getEvenement()?->getId(),
            $index,
            microtime(true)
        )), 0, 16));
    }
}
