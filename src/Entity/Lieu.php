<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\LieuRepository;

#[ORM\Entity(repositoryClass: LieuRepository::class)]
#[ORM\Table(name: 'lieu')]
class Lieu
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

    #[ORM\ManyToOne(targetEntity: Offre::class, inversedBy: 'lieus')]
    #[ORM\JoinColumn(name: 'id_offre', referencedColumnName: 'id')]
    private ?Offre $offre = null;

    public function getOffre(): ?Offre
    {
        return $this->offre;
    }

    public function setOffre(?Offre $offre): self
    {
        $this->offre = $offre;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $nom = null;

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): self
    {
        $this->nom = $nom;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $ville = null;

    public function getVille(): ?string
    {
        return $this->ville;
    }

    public function setVille(string $ville): self
    {
        $this->ville = $ville;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $adresse = null;

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(?string $adresse): self
    {
        $this->adresse = $adresse;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $telephone = null;

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): self
    {
        $this->telephone = $telephone;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $site_web = null;

    public function getSite_web(): ?string
    {
        return $this->site_web;
    }

    public function setSite_web(?string $site_web): self
    {
        $this->site_web = $site_web;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $instagram = null;

    public function getInstagram(): ?string
    {
        return $this->instagram;
    }

    public function setInstagram(?string $instagram): self
    {
        $this->instagram = $instagram;
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

    #[ORM\Column(type: 'decimal', nullable: true)]
    private ?float $budget_min = null;

    public function getBudget_min(): ?float
    {
        return $this->budget_min;
    }

    public function setBudget_min(?float $budget_min): self
    {
        $this->budget_min = $budget_min;
        return $this;
    }

    #[ORM\Column(type: 'decimal', nullable: true)]
    private ?float $budget_max = null;

    public function getBudget_max(): ?float
    {
        return $this->budget_max;
    }

    public function setBudget_max(?float $budget_max): self
    {
        $this->budget_max = $budget_max;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $categorie = null;

    public function getCategorie(): ?string
    {
        return $this->categorie;
    }

    public function setCategorie(string $categorie): self
    {
        $this->categorie = $categorie;
        return $this;
    }

    #[ORM\Column(type: 'decimal', nullable: true)]
    private ?float $latitude = null;

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function setLatitude(?float $latitude): self
    {
        $this->latitude = $latitude;
        return $this;
    }

    #[ORM\Column(type: 'decimal', nullable: true)]
    private ?float $longitude = null;

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function setLongitude(?float $longitude): self
    {
        $this->longitude = $longitude;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $type = null;

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $image_url = null;

    public function getImage_url(): ?string
    {
        return $this->image_url;
    }

    public function setImage_url(?string $image_url): self
    {
        $this->image_url = $image_url;
        return $this;
    }

    #[ORM\OneToOne(targetEntity: EvaluationLieu::class, mappedBy: 'lieu')]
    private ?EvaluationLieu $evaluationLieu = null;

    public function getEvaluationLieu(): ?EvaluationLieu
    {
        return $this->evaluationLieu;
    }

    public function setEvaluationLieu(?EvaluationLieu $evaluationLieu): self
    {
        $this->evaluationLieu = $evaluationLieu;
        return $this;
    }

    #[ORM\OneToMany(targetEntity: Evenement::class, mappedBy: 'lieu')]
    private Collection $evenements;

    /**
     * @return Collection<int, Evenement>
     */
    public function getEvenements(): Collection
    {
        if (!$this->evenements instanceof Collection) {
            $this->evenements = new ArrayCollection();
        }
        return $this->evenements;
    }

    public function addEvenement(Evenement $evenement): self
    {
        if (!$this->getEvenements()->contains($evenement)) {
            $this->getEvenements()->add($evenement);
        }
        return $this;
    }

    public function removeEvenement(Evenement $evenement): self
    {
        $this->getEvenements()->removeElement($evenement);
        return $this;
    }

    #[ORM\OneToOne(targetEntity: LieuHoraire::class, mappedBy: 'lieu')]
    private ?LieuHoraire $lieuHoraire = null;

    public function getLieuHoraire(): ?LieuHoraire
    {
        return $this->lieuHoraire;
    }

    public function setLieuHoraire(?LieuHoraire $lieuHoraire): self
    {
        $this->lieuHoraire = $lieuHoraire;
        return $this;
    }

    #[ORM\OneToMany(targetEntity: LieuImage::class, mappedBy: 'lieu')]
    private Collection $lieuImages;

    /**
     * @return Collection<int, LieuImage>
     */
    public function getLieuImages(): Collection
    {
        if (!$this->lieuImages instanceof Collection) {
            $this->lieuImages = new ArrayCollection();
        }
        return $this->lieuImages;
    }

    public function addLieuImage(LieuImage $lieuImage): self
    {
        if (!$this->getLieuImages()->contains($lieuImage)) {
            $this->getLieuImages()->add($lieuImage);
        }
        return $this;
    }

    public function removeLieuImage(LieuImage $lieuImage): self
    {
        $this->getLieuImages()->removeElement($lieuImage);
        return $this;
    }

    #[ORM\OneToMany(targetEntity: Offre::class, mappedBy: 'lieu')]
    private Collection $offres;

    /**
     * @return Collection<int, Offre>
     */
    public function getOffres(): Collection
    {
        if (!$this->offres instanceof Collection) {
            $this->offres = new ArrayCollection();
        }
        return $this->offres;
    }

    public function addOffre(Offre $offre): self
    {
        if (!$this->getOffres()->contains($offre)) {
            $this->getOffres()->add($offre);
        }
        return $this;
    }

    public function removeOffre(Offre $offre): self
    {
        $this->getOffres()->removeElement($offre);
        return $this;
    }

    #[ORM\OneToMany(targetEntity: ReservationOffre::class, mappedBy: 'lieu')]
    private Collection $reservationOffres;

    /**
     * @return Collection<int, ReservationOffre>
     */
    public function getReservationOffres(): Collection
    {
        if (!$this->reservationOffres instanceof Collection) {
            $this->reservationOffres = new ArrayCollection();
        }
        return $this->reservationOffres;
    }

    public function addReservationOffre(ReservationOffre $reservationOffre): self
    {
        if (!$this->getReservationOffres()->contains($reservationOffre)) {
            $this->getReservationOffres()->add($reservationOffre);
        }
        return $this;
    }

    public function removeReservationOffre(ReservationOffre $reservationOffre): self
    {
        $this->getReservationOffres()->removeElement($reservationOffre);
        return $this;
    }

    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'lieus')]
    #[ORM\JoinTable(
        name: 'favori_lieu',
        joinColumns: [
            new ORM\JoinColumn(name: 'lieu_id', referencedColumnName: 'id')
        ],
        inverseJoinColumns: [
            new ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id')
        ]
    )]
    private Collection $users;

    public function __construct()
    {
        $this->evenements = new ArrayCollection();
        $this->lieuImages = new ArrayCollection();
        $this->offres = new ArrayCollection();
        $this->reservationOffres = new ArrayCollection();
        $this->users = new ArrayCollection();
    }

    /**
     * @return Collection<int, User>
     */
    public function getUsers(): Collection
    {
        if (!$this->users instanceof Collection) {
            $this->users = new ArrayCollection();
        }
        return $this->users;
    }

    public function addUser(User $user): self
    {
        if (!$this->getUsers()->contains($user)) {
            $this->getUsers()->add($user);
        }
        return $this;
    }

    public function removeUser(User $user): self
    {
        $this->getUsers()->removeElement($user);
        return $this;
    }

    public function getSiteWeb(): ?string
    {
        return $this->site_web;
    }

    public function setSiteWeb(?string $site_web): static
    {
        $this->site_web = $site_web;

        return $this;
    }

    public function getBudgetMin(): ?string
    {
        return $this->budget_min;
    }

    public function setBudgetMin(?string $budget_min): static
    {
        $this->budget_min = $budget_min;

        return $this;
    }

    public function getBudgetMax(): ?string
    {
        return $this->budget_max;
    }

    public function setBudgetMax(?string $budget_max): static
    {
        $this->budget_max = $budget_max;

        return $this;
    }

    public function getImageUrl(): ?string
    {
        return $this->image_url;
    }

    public function setImageUrl(?string $image_url): static
    {
        $this->image_url = $image_url;

        return $this;
    }

}
