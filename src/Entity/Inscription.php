<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\InscriptionRepository;

#[ORM\Entity(repositoryClass: InscriptionRepository::class)]
#[ORM\Table(name: 'inscription')]
class Inscription
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

    #[ORM\OneToOne(targetEntity: Evenement::class, inversedBy: 'inscription')]
    #[ORM\JoinColumn(name: 'event_id', referencedColumnName: 'id', unique: true)]
    private ?Evenement $evenement = null;

    public function getEvenement(): ?Evenement
    {
        return $this->evenement;
    }

    public function setEvenement(?Evenement $evenement): self
    {
        $this->evenement = $evenement;
        return $this;
    }

    #[ORM\OneToOne(targetEntity: User::class, inversedBy: 'inscription')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', unique: true)]
    private ?User $user = null;

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: false)]
    private ?\DateTimeInterface $date_creation = null;

    public function getDate_creation(): ?\DateTimeInterface
    {
        return $this->date_creation;
    }

    public function setDate_creation(\DateTimeInterface $date_creation): self
    {
        $this->date_creation = $date_creation;
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

    #[ORM\Column(type: 'float', nullable: false)]
    private ?float $paiement = null;

    public function getPaiement(): ?float
    {
        return $this->paiement;
    }

    public function setPaiement(float $paiement): self
    {
        $this->paiement = $paiement;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: false)]
    private ?int $nb_tickets = null;

    public function getNb_tickets(): ?int
    {
        return $this->nb_tickets;
    }

    public function setNb_tickets(int $nb_tickets): self
    {
        $this->nb_tickets = $nb_tickets;
        return $this;
    }

    #[ORM\OneToMany(targetEntity: Paiement::class, mappedBy: 'inscription')]
    private Collection $paiements;

    /**
     * @return Collection<int, Paiement>
     */
    public function getPaiements(): Collection
    {
        if (!$this->paiements instanceof Collection) {
            $this->paiements = new ArrayCollection();
        }
        return $this->paiements;
    }

    public function addPaiement(Paiement $paiement): self
    {
        if (!$this->getPaiements()->contains($paiement)) {
            $this->getPaiements()->add($paiement);
        }
        return $this;
    }

    public function removePaiement(Paiement $paiement): self
    {
        $this->getPaiements()->removeElement($paiement);
        return $this;
    }

    #[ORM\OneToMany(targetEntity: Ticket::class, mappedBy: 'inscription')]
    private Collection $tickets;

    public function __construct()
    {
        $this->paiements = new ArrayCollection();
        $this->tickets = new ArrayCollection();
    }

    /**
     * @return Collection<int, Ticket>
     */
    public function getTickets(): Collection
    {
        if (!$this->tickets instanceof Collection) {
            $this->tickets = new ArrayCollection();
        }
        return $this->tickets;
    }

    public function addTicket(Ticket $ticket): self
    {
        if (!$this->getTickets()->contains($ticket)) {
            $this->getTickets()->add($ticket);
        }
        return $this;
    }

    public function removeTicket(Ticket $ticket): self
    {
        $this->getTickets()->removeElement($ticket);
        return $this;
    }

    public function getDateCreation(): ?\DateTime
    {
        return $this->date_creation;
    }

    public function setDateCreation(\DateTime $date_creation): static
    {
        $this->date_creation = $date_creation;

        return $this;
    }

    public function getNbTickets(): ?int
    {
        return $this->nb_tickets;
    }

    public function setNbTickets(int $nb_tickets): static
    {
        $this->nb_tickets = $nb_tickets;

        return $this;
    }

}
