<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\BadgeRecompenseRepository;

#[ORM\Entity(repositoryClass: BadgeRecompenseRepository::class)]
#[ORM\Table(name: 'badge_recompense')]
class BadgeRecompense
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

    #[ORM\Column(name: 'badge_code', type: 'string', nullable: false, unique: true)]
    private string $badge_code = '';

    public function getBadgeCode(): ?string
    {
        return $this->badge_code;
    }

    public function setBadgeCode(string $badge_code): self
    {
        $this->badge_code = $badge_code;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private string $titre = '';

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): self
    {
        $this->titre = $titre;
        return $this;
    }

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    #[ORM\Column(type: 'float', nullable: false)]
    private float $pourcentage = 0.0;

    public function getPourcentage(): ?float
    {
        return $this->pourcentage;
    }

    public function setPourcentage(float $pourcentage): self
    {
        $this->pourcentage = $pourcentage;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: false)]
    private int $duree_jours = 0;

    public function getDuree_jours(): ?int
    {
        return $this->duree_jours;
    }

    public function setDuree_jours(int $duree_jours): self
    {
        $this->duree_jours = $duree_jours;
        return $this;
    }

    public function getDureeJours(): ?int
    {
        return $this->duree_jours;
    }

    public function setDureeJours(int $duree_jours): static
    {
        $this->duree_jours = $duree_jours;

        return $this;
    }

}
