<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity]
class ChatGroupe
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: AnnonceSortie::class, fetch: 'LAZY')]
    #[ORM\JoinColumn(nullable: false)]
    private AnnonceSortie $annonce;

    #[ORM\OneToMany(mappedBy: 'chatGroupe', targetEntity: ChatGroupeMembre::class, cascade: ['remove'])]
    private Collection $membres;

    #[ORM\OneToMany(mappedBy: 'chatGroupe', targetEntity: ChatMessage::class, cascade: ['remove'])]
    private Collection $messages;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->membres = new ArrayCollection();
        $this->messages = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAnnonce(): AnnonceSortie
    {
        return $this->annonce;
    }

    public function setAnnonce(AnnonceSortie $annonce): self
    {
        $this->annonce = $annonce;
        return $this;
    }

    /**
     * @return Collection|ChatGroupeMembre[]
     */
    public function getMembres(): Collection
    {
        return $this->membres;
    }

    /**
     * @return Collection|ChatMessage[]
     */
    public function getMessages(): Collection
    {
        return $this->messages;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
