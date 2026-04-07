<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\LieuHoraireRepository;

#[ORM\Entity(repositoryClass: LieuHoraireRepository::class)]
#[ORM\Table(name: 'lieu_horaire')]
class LieuHoraire
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

    #[ORM\OneToOne(targetEntity: Lieu::class, inversedBy: 'lieuHoraire')]
    #[ORM\JoinColumn(name: 'lieu_id', referencedColumnName: 'id', unique: true)]
    private ?Lieu $lieu = null;

    public function getLieu(): ?Lieu
    {
        return $this->lieu;
    }

    public function setLieu(?Lieu $lieu): self
    {
        $this->lieu = $lieu;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $jour = null;

    public function getJour(): ?string
    {
        return $this->jour;
    }

    public function setJour(string $jour): self
    {
        $this->jour = $jour;
        return $this;
    }

    #[ORM\Column(type: 'boolean', nullable: false)]
    private ?bool $ouvert = null;

    public function isOuvert(): ?bool
    {
        return $this->ouvert;
    }

    public function setOuvert(bool $ouvert): self
    {
        $this->ouvert = $ouvert;
        return $this;
    }

    #[ORM\Column(type: 'time', nullable: true)]
    private ?string $heure_ouverture_1 = null;

    public function getHeure_ouverture_1(): ?string
    {
        return $this->heure_ouverture_1;
    }

    public function setHeure_ouverture_1(?string $heure_ouverture_1): self
    {
        $this->heure_ouverture_1 = $heure_ouverture_1;
        return $this;
    }

    #[ORM\Column(type: 'time', nullable: true)]
    private ?string $heure_fermeture_1 = null;

    public function getHeure_fermeture_1(): ?string
    {
        return $this->heure_fermeture_1;
    }

    public function setHeure_fermeture_1(?string $heure_fermeture_1): self
    {
        $this->heure_fermeture_1 = $heure_fermeture_1;
        return $this;
    }

    #[ORM\Column(type: 'time', nullable: true)]
    private ?string $heure_ouverture_2 = null;

    public function getHeure_ouverture_2(): ?string
    {
        return $this->heure_ouverture_2;
    }

    public function setHeure_ouverture_2(?string $heure_ouverture_2): self
    {
        $this->heure_ouverture_2 = $heure_ouverture_2;
        return $this;
    }

    #[ORM\Column(type: 'time', nullable: true)]
    private ?string $heure_fermeture_2 = null;

    public function getHeure_fermeture_2(): ?string
    {
        return $this->heure_fermeture_2;
    }

    public function setHeure_fermeture_2(?string $heure_fermeture_2): self
    {
        $this->heure_fermeture_2 = $heure_fermeture_2;
        return $this;
    }

    public function getHeureOuverture1(): ?\DateTime
    {
        return $this->heure_ouverture_1;
    }

    public function setHeureOuverture1(?\DateTime $heure_ouverture_1): static
    {
        $this->heure_ouverture_1 = $heure_ouverture_1;

        return $this;
    }

    public function getHeureFermeture1(): ?\DateTime
    {
        return $this->heure_fermeture_1;
    }

    public function setHeureFermeture1(?\DateTime $heure_fermeture_1): static
    {
        $this->heure_fermeture_1 = $heure_fermeture_1;

        return $this;
    }

    public function getHeureOuverture2(): ?\DateTime
    {
        return $this->heure_ouverture_2;
    }

    public function setHeureOuverture2(?\DateTime $heure_ouverture_2): static
    {
        $this->heure_ouverture_2 = $heure_ouverture_2;

        return $this;
    }

    public function getHeureFermeture2(): ?\DateTime
    {
        return $this->heure_fermeture_2;
    }

    public function setHeureFermeture2(?\DateTime $heure_fermeture_2): static
    {
        $this->heure_fermeture_2 = $heure_fermeture_2;

        return $this;
    }

}
