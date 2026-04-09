<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\AnnonceSortieRepository;

#[ORM\Entity(repositoryClass: AnnonceSortieRepository::class)]
#[ORM\Table(name: 'annonce_sortie')]
class AnnonceSortie
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'annonceSorties')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id')]
    private ?User $user = null;

    #[ORM\Column(length: 140)]
    private ?string $titre = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 80)]
    private ?string $ville = null;

    #[ORM\Column(length: 255)]
    private ?string $lieu_texte = null;

    #[ORM\Column(length: 255)]
    private ?string $point_rencontre = null;

    #[ORM\Column(length: 80)]
    private ?string $type_activite = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $date_sortie = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $budget_max = null;

    #[ORM\Column]
    private ?int $nb_places = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $image_url = null;

    #[ORM\Column(length: 20)]
    private ?string $statut = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $questions_json = null;

    #[ORM\OneToMany(targetEntity: ChatMessage::class, mappedBy: 'annonceSortie')]
    private Collection $chatMessages;

    #[ORM\OneToOne(targetEntity: ParticipationAnnonce::class, mappedBy: 'annonceSortie')]
    private ?ParticipationAnnonce $participationAnnonce = null;

    #[ORM\OneToMany(targetEntity: Poll::class, mappedBy: 'annonceSortie')]
    private Collection $polls;

    #[ORM\OneToMany(targetEntity: SortieMedia::class, mappedBy: 'annonceSortie')]
    private Collection $sortieMedias;

    #[ORM\OneToOne(targetEntity: SortieRecap::class, mappedBy: 'annonceSortie')]
    private ?SortieRecap $sortieRecap = null;

    #[ORM\OneToMany(targetEntity: SortieTask::class, mappedBy: 'annonceSortie')]
    private Collection $sortieTasks;

    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'annonceSorties')]
    #[ORM\JoinTable(name: 'chat_read_state')]
    private Collection $users;

    public function __construct()
    {
        $this->chatMessages = new ArrayCollection();
        $this->polls = new ArrayCollection();
        $this->sortieMedias = new ArrayCollection();
        $this->sortieTasks = new ArrayCollection();
        $this->users = new ArrayCollection();
    }

    // ==================== GETTERS / SETTERS ====================

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }

    public function getTitre(): ?string { return $this->titre; }
    public function setTitre(string $titre): self { $this->titre = $titre; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }

    public function getVille(): ?string { return $this->ville; }
    public function setVille(string $ville): self { $this->ville = $ville; return $this; }

    public function getLieuTexte(): ?string { return $this->lieu_texte; }
    public function setLieuTexte(string $lieu_texte): self { $this->lieu_texte = $lieu_texte; return $this; }

    public function getPointRencontre(): ?string { return $this->point_rencontre; }
    public function setPointRencontre(string $point_rencontre): self { $this->point_rencontre = $point_rencontre; return $this; }

    public function getTypeActivite(): ?string { return $this->type_activite; }
    public function setTypeActivite(string $type_activite): self { $this->type_activite = $type_activite; return $this; }

    public function getDateSortie(): ?\DateTimeInterface { return $this->date_sortie; }
    public function setDateSortie(\DateTimeInterface $date_sortie): self { $this->date_sortie = $date_sortie; return $this; }

    public function getBudgetMax(): ?string { return $this->budget_max; }
    public function setBudgetMax(?string $budget_max): self { $this->budget_max = $budget_max; return $this; }

    public function getNbPlaces(): ?int { return $this->nb_places; }
    public function setNbPlaces(int $nb_places): self { $this->nb_places = $nb_places; return $this; }

    public function getImageUrl(): ?string { return $this->image_url; }
    public function setImageUrl(?string $image_url): self { $this->image_url = $image_url; return $this; }

    public function getStatut(): ?string { return $this->statut; }
    public function setStatut(string $statut): self { $this->statut = $statut; return $this; }

    public function getQuestionsJson(): ?string { return $this->questions_json; }
    public function setQuestionsJson(?string $questions_json): self { $this->questions_json = $questions_json; return $this; }

    public function getChatMessages(): Collection { return $this->chatMessages; }
    public function getPolls(): Collection { return $this->polls; }
    public function getSortieMedias(): Collection { return $this->sortieMedias; }
    public function getSortieTasks(): Collection { return $this->sortieTasks; }
    public function getUsers(): Collection { return $this->users; }

    // Ajoute les méthodes add/remove si tu en as besoin pour les collections
}