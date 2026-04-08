<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\PaiementRepository;

#[ORM\Entity(repositoryClass: PaiementRepository::class)]
#[ORM\Table(name: 'paiement')]
class Paiement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    #[ORM\ManyToOne(targetEntity: Inscription::class, inversedBy: 'paiements')]
    #[ORM\JoinColumn(name: 'inscription_id', referencedColumnName: 'id')]
    private ?Inscription $inscription = null;

    public function getInscription(): ?Inscription
    {
        return $this->inscription;
    }

    public function setInscription(?Inscription $inscription): self
    {
        $this->inscription = $inscription;
        return $this;
    }

    #[ORM\Column(type: 'decimal', nullable: false)]
    private ?float $montant = null;

    public function getMontant(): ?float
    {
        return $this->montant;
    }

    public function setMontant(float $montant): self
    {
        $this->montant = $montant;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $methode = null;

    public function getMethode(): ?string
    {
        return $this->methode;
    }

    public function setMethode(string $methode): self
    {
        $this->methode = $methode;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $statut = null;

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): self
    {
        $this->statut = $statut;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $reference_code = null;

    public function getReference_code(): ?string
    {
        return $this->reference_code;
    }

    public function setReference_code(string $reference_code): self
    {
        $this->reference_code = $reference_code;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $nom_carte = null;

    public function getNom_carte(): ?string
    {
        return $this->nom_carte;
    }

    public function setNom_carte(?string $nom_carte): self
    {
        $this->nom_carte = $nom_carte;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $quatre_derniers = null;

    public function getQuatre_derniers(): ?string
    {
        return $this->quatre_derniers;
    }

    public function setQuatre_derniers(?string $quatre_derniers): self
    {
        $this->quatre_derniers = $quatre_derniers;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: false)]
    private ?\DateTimeInterface $date_paiement = null;

    public function getDate_paiement(): ?\DateTimeInterface
    {
        return $this->date_paiement;
    }

    public function setDate_paiement(\DateTimeInterface $date_paiement): self
    {
        $this->date_paiement = $date_paiement;
        return $this;
    }

    public function getReferenceCode(): ?string
    {
        return $this->reference_code;
    }

    public function setReferenceCode(string $reference_code): static
    {
        $this->reference_code = $reference_code;

        return $this;
    }

    public function getNomCarte(): ?string
    {
        return $this->nom_carte;
    }

    public function setNomCarte(?string $nom_carte): static
    {
        $this->nom_carte = $nom_carte;

        return $this;
    }

    public function getQuatreDerniers(): ?string
    {
        return $this->quatre_derniers;
    }

    public function setQuatreDerniers(?string $quatre_derniers): static
    {
        $this->quatre_derniers = $quatre_derniers;

        return $this;
    }

    public function getDatePaiement(): ?\DateTime
    {
        return $this->date_paiement;
    }

    public function setDatePaiement(\DateTime $date_paiement): static
    {
        $this->date_paiement = $date_paiement;

        return $this;
    }

}
