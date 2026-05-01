<?php

namespace App\Entity;

use App\Repository\TicketRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TicketRepository::class)]
#[ORM\Table(name: 'ticket')]
class Ticket
{
    public const STATUT_VALIDE = 'VALIDE';
    public const STATUT_UTILISE = 'UTILISE';
    public const STATUT_ANNULE = 'ANNULE';
    public const STATUTS_VALIDES = [self::STATUT_VALIDE, self::STATUT_UTILISE, self::STATUT_ANNULE];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Inscription::class, inversedBy: 'tickets')]
    #[ORM\JoinColumn(name: 'inscription_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Inscription $inscription = null;

    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(name: 'numero_ticket', type: 'string', length: 80, unique: true)]
    #[Assert\NotBlank]
    private ?string $numeroTicket = null;

    #[ORM\Column(type: 'string', length: 20)]
    #[Assert\Choice(choices: self::STATUTS_VALIDES)]
    private string $statut = self::STATUT_VALIDE;

    #[ORM\Column(name: 'code_validation', type: 'string', length: 32, unique: true)]
    #[Assert\NotBlank]
    private ?string $codeValidation = null;

    public function __construct()
    {
        $this->date = new \DateTime();
        $this->codeValidation = strtoupper(bin2hex(random_bytes(8)));
    }

    public function getId(): ?int { return $this->id; }
    public function getInscription(): ?Inscription { return $this->inscription; }
    public function setInscription(Inscription $inscription): self { $this->inscription = $inscription; return $this; }
    public function getDate(): ?\DateTimeInterface { return $this->date; }
    public function setDate(\DateTimeInterface $date): self { $this->date = $date; return $this; }
    public function getNumeroTicket(): ?string { return $this->numeroTicket; }
    public function setNumeroTicket(string $numeroTicket): self { $this->numeroTicket = $numeroTicket; return $this; }
    public function getCodeValidation(): ?string { return $this->codeValidation; }
    public function setCodeValidation(string $codeValidation): self { $this->codeValidation = strtoupper($codeValidation); return $this; }
    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $statut): self { $this->statut = $statut; return $this; }

    public function getNumeroTicketFinal(): string
    {
        return $this->numeroTicket ?? sprintf('TICKET-%s', $this->id ?? 'PENDING');
    }

    public function marquerCommeUtilise(): self
    {
        if ($this->statut === self::STATUT_VALIDE) {
            $this->statut = self::STATUT_UTILISE;
        }

        return $this;
    }

    public function estValide(): bool
    {
        return $this->statut === self::STATUT_VALIDE;
    }

    public function getStatutLabel(): string
    {
        return match ($this->statut) {
            self::STATUT_VALIDE => 'Valide',
            self::STATUT_UTILISE => 'Utilise',
            self::STATUT_ANNULE => 'Annule',
            default => $this->statut,
        };
    }

    public function getQrCodePayload(): string
    {
        $inscription = $this->getInscription();
        $evenement = $inscription?->getEvenement();
        $user = $inscription?->getUser();
        $paiement = $inscription?->getPaiementPrincipal();

        return implode("\n", array_filter([
            'Fintokhrej Ticket',
            'Ticket: '.$this->getNumeroTicketFinal(),
            'Validation: '.$this->getCodeValidation(),
            'Statut: '.$this->getStatutLabel(),
            'Evenement: '.($evenement?->getTitre() ?? 'N/A'),
            'Date: '.($evenement?->getDateDebut()?->format('d/m/Y H:i') ?? 'N/A'),
            'Participant: '.trim(($user?->getPrenom() ?? '').' '.($user?->getNom() ?? '')),
            'Email: '.($user?->getEmail() ?? 'N/A'),
            'Reference paiement: '.($paiement?->getReferenceCode() ?? 'N/A'),
        ], static fn (?string $value): bool => $value !== null && $value !== ''));
    }
}
