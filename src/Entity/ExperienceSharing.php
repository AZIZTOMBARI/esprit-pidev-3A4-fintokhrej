<?php

namespace App\Entity;

use App\Repository\ExperienceSharingRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ExperienceSharingRepository::class)]
#[ORM\Table(name: 'experience_sharing')]
class ExperienceSharing
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: AnnonceSortie::class, inversedBy: 'experienceSharing')]
    #[ORM\JoinColumn(name: 'sortie_id', referencedColumnName: 'id', unique: true, nullable: false, onDelete: 'CASCADE')]
    private ?AnnonceSortie $annonceSortie = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $activatedAt = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isOpen = true;

    #[ORM\Column(type: 'integer')]
    private int $mediaCount = 0;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastMediaAddedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastGeneratedAt = null;

    #[ORM\Column(type: 'string', length: 20)]
    private string $recapMode = 'fallback';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAnnonceSortie(): ?AnnonceSortie
    {
        return $this->annonceSortie;
    }

    public function setAnnonceSortie(?AnnonceSortie $annonceSortie): self
    {
        $this->annonceSortie = $annonceSortie;

        if ($annonceSortie && $annonceSortie->getExperienceSharing() !== $this) {
            $annonceSortie->setExperienceSharing($this);
        }

        return $this;
    }

    public function getActivatedAt(): ?\DateTimeImmutable
    {
        return $this->activatedAt;
    }

    public function setActivatedAt(\DateTimeImmutable $activatedAt): self
    {
        $this->activatedAt = $activatedAt;

        return $this;
    }

    public function isOpen(): bool
    {
        return $this->isOpen;
    }

    public function setIsOpen(bool $isOpen): self
    {
        $this->isOpen = $isOpen;

        return $this;
    }

    public function getMediaCount(): int
    {
        return $this->mediaCount;
    }

    public function setMediaCount(int $mediaCount): self
    {
        $this->mediaCount = $mediaCount;

        return $this;
    }

    public function getLastMediaAddedAt(): ?\DateTimeImmutable
    {
        return $this->lastMediaAddedAt;
    }

    public function setLastMediaAddedAt(?\DateTimeImmutable $lastMediaAddedAt): self
    {
        $this->lastMediaAddedAt = $lastMediaAddedAt;

        return $this;
    }

    public function getLastGeneratedAt(): ?\DateTimeImmutable
    {
        return $this->lastGeneratedAt;
    }

    public function setLastGeneratedAt(?\DateTimeImmutable $lastGeneratedAt): self
    {
        $this->lastGeneratedAt = $lastGeneratedAt;

        return $this;
    }

    public function getRecapMode(): string
    {
        return $this->recapMode;
    }

    public function setRecapMode(string $recapMode): self
    {
        $this->recapMode = $recapMode;

        return $this;
    }
}
