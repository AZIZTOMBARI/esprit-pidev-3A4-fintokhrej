<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\LieuRepository;

#[ORM\Entity(repositoryClass: LieuRepository::class)]
#[ORM\Table(name: 'lieu')]
class Lieu
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Offre::class, inversedBy: 'lieus')]
    #[ORM\JoinColumn(name: 'id_offre', referencedColumnName: 'id')]
    private ?Offre $offre = null;

    #[ORM\Column(length: 120)]
    private ?string $nom = null;

    #[ORM\Column(length: 80)]
    private ?string $ville = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $adresse = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $site_web = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $instagram = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $budgetMin = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $budgetMax = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 8, nullable: true)]
    private ?string $latitude = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 8, nullable: true)]
    private ?string $longitude = null;

    #[ORM\Column(length: 50)]
    private ?string $categorie = null;

    #[ORM\Column(length: 10)]
    private ?string $type = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $image_url = null;

    #[ORM\OneToOne(targetEntity: EvaluationLieu::class, mappedBy: 'lieu')]
    private ?EvaluationLieu $evaluationLieu = null;

    #[ORM\OneToMany(targetEntity: Evenement::class, mappedBy: 'lieu')]
    private Collection $evenements;

    #[ORM\OneToOne(targetEntity: LieuHoraire::class, mappedBy: 'lieu')]
    private ?LieuHoraire $lieuHoraire = null;

    #[ORM\OneToMany(targetEntity: LieuImage::class, mappedBy: 'lieu')]
    private Collection $lieuImages;

    #[ORM\OneToMany(targetEntity: Offre::class, mappedBy: 'lieu')]
    private Collection $offres;

    #[ORM\OneToMany(targetEntity: ReservationOffre::class, mappedBy: 'lieu')]
    private Collection $reservationOffres;

    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'lieus')]
    #[ORM\JoinTable(name: 'favori_lieu')]
    private Collection $users;

    public function __construct()
    {
        $this->evenements = new ArrayCollection();
        $this->lieuImages = new ArrayCollection();
        $this->offres = new ArrayCollection();
        $this->reservationOffres = new ArrayCollection();
        $this->users = new ArrayCollection();
    }

    // ==================== GETTERS / SETTERS ====================

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOffre(): ?Offre { return $this->offre; }
    public function setOffre(?Offre $offre): self { $this->offre = $offre; return $this; }

    public function getNom(): ?string { return $this->nom; }
    public function setNom(string $nom): self { $this->nom = $nom; return $this; }

    public function getVille(): ?string { return $this->ville; }
    public function setVille(string $ville): self { $this->ville = $ville; return $this; }

    public function getAdresse(): ?string { return $this->adresse; }
    public function setAdresse(?string $adresse): self { $this->adresse = $adresse; return $this; }

    public function getTelephone(): ?string { return $this->telephone; }
    public function setTelephone(?string $telephone): self { $this->telephone = $telephone; return $this; }

    public function getSiteWeb(): ?string { return $this->site_web; }
    public function setSiteWeb(?string $site_web): self { $this->site_web = $site_web; return $this; }

    public function getInstagram(): ?string { return $this->instagram; }
    public function setInstagram(?string $instagram): self { $this->instagram = $instagram; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }

    public function getBudgetMin(): ?string { return $this->budgetMin; }
    public function setBudgetMin(?string $budgetMin): self { $this->budgetMin = $budgetMin; return $this; }

    public function getBudgetMax(): ?string { return $this->budgetMax; }
    public function setBudgetMax(?string $budgetMax): self { $this->budgetMax = $budgetMax; return $this; }

    public function getLatitude(): ?string { return $this->latitude; }
    public function setLatitude(?string $latitude): self { $this->latitude = $latitude; return $this; }

    public function getLongitude(): ?string { return $this->longitude; }
    public function setLongitude(?string $longitude): self { $this->longitude = $longitude; return $this; }

    public function getCategorie(): ?string { return $this->categorie; }
    public function setCategorie(string $categorie): self { $this->categorie = $categorie; return $this; }

    public function getType(): ?string { return $this->type; }
    public function setType(string $type): self { $this->type = $type; return $this; }

    public function getImageUrl(): ?string { return $this->image_url; }
    public function setImageUrl(?string $image_url): self { $this->image_url = $image_url; return $this; }

    public function getEvaluationLieu(): ?EvaluationLieu { return $this->evaluationLieu; }
    public function setEvaluationLieu(?EvaluationLieu $evaluationLieu): self { $this->evaluationLieu = $evaluationLieu; return $this; }

    public function getEvenements(): Collection { return $this->evenements; }
    public function addEvenement(Evenement $evenement): self { /* ... */ return $this; }
    public function removeEvenement(Evenement $evenement): self { /* ... */ return $this; }

    public function getLieuHoraire(): ?LieuHoraire { return $this->lieuHoraire; }
    public function setLieuHoraire(?LieuHoraire $lieuHoraire): self { $this->lieuHoraire = $lieuHoraire; return $this; }

    public function getLieuImages(): Collection { return $this->lieuImages; }
    public function addLieuImage(LieuImage $lieuImage): self { /* ... */ return $this; }
    public function removeLieuImage(LieuImage $lieuImage): self { /* ... */ return $this; }

    public function getOffres(): Collection { return $this->offres; }
    public function addOffre(Offre $offre): self { /* ... */ return $this; }
    public function removeOffre(Offre $offre): self { /* ... */ return $this; }

    public function getReservationOffres(): Collection { return $this->reservationOffres; }
    public function addReservationOffre(ReservationOffre $reservationOffre): self { /* ... */ return $this; }
    public function removeReservationOffre(ReservationOffre $reservationOffre): self { /* ... */ return $this; }

    public function getUsers(): Collection { return $this->users; }
    public function addUser(User $user): self { /* ... */ return $this; }
    public function removeUser(User $user): self { /* ... */ return $this; }
}