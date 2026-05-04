<?php

namespace App\Entity\Sleep;

use App\Repository\Sleep\SommeilRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;
use App\Entity\User;

#[ORM\Entity(repositoryClass: SommeilRepository::class)]
#[ORM\Table(name: 'sommeil')]
class Sommeil
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    /** @phpstan-ignore-next-line Doctrine assigne automatiquement l'identifiant. */
    private ?int $id = null;

    #[ORM\Column(name: 'date_nuit', type: "date", nullable: true)]
    #[Assert\NotBlank(message: 'La date de nuit est obligatoire.')]
    #[Assert\LessThanOrEqual(value: 'today', message: 'La date de nuit ne peut pas être dans le futur.')]
    private ?\DateTimeInterface $dateNuit = null;

    #[ORM\Column(name: 'heure_coucher', type: "string", nullable: true)]
    #[Assert\NotBlank(message: "L'heure de coucher est obligatoire.")]
    private ?string $heureCoucher = null;

    #[ORM\Column(name: 'heure_reveil', type: "string", nullable: true)]
    #[Assert\NotBlank(message: "L'heure de réveil est obligatoire.")]
    private ?string $heureReveil = null;

    #[ORM\Column(type: "string", length: 50, nullable: true)]
    #[Assert\NotBlank(message: 'La qualité est obligatoire.')]
    #[Assert\Choice(
        choices: ['Excellente', 'Bonne', 'Moyenne', 'Mauvaise'],
        message: 'Choisissez une qualité valide.'
    )]
    private ?string $qualite = null;

    #[ORM\Column(type: "text", nullable: true)]
    #[Assert\Length(max: 1000, maxMessage: 'Le commentaire ne doit pas dépasser {{ limit }} caractères.')]
    private ?string $commentaire = null;

    #[ORM\Column(name: 'duree_sommeil', type: "float", nullable: true)]
    #[Assert\Positive(message: 'La durée doit être un nombre positif.')]
    #[Assert\Range(
        min: 0.5,
        max: 24,
        notInRangeMessage: 'La durée du sommeil doit être entre {{ min }} et {{ max }} heures.'
    )]
    private ?float $dureeSommeil = null;

    #[ORM\Column(type: "integer", nullable: true)]
    #[Assert\PositiveOrZero(message: 'Les interruptions ne peuvent pas être négatives.')]
    private ?int $interruptions = null;

    #[ORM\Column(name: 'humeur_reveil', type: "string", length: 50, nullable: true)]
    #[Assert\Choice(
        choices: ['😌 Reposé', '😄 Joyeux', '😐 Neutre', '😴 Fatigué', '⚡ Énergisé'],
        message: 'Choisissez une humeur valide.'
    )]
    private ?string $humeurReveil = null;

    #[ORM\Column(type: "string", length: 100, nullable: true)]
    #[Assert\Choice(
        choices: ['🏠 Normal', '🌿 Calme', '😊 Confortable'],
        message: 'Choisissez un environnement valide.'
    )]
    private ?string $environnement = null;

    #[ORM\Column(type: "float", nullable: true)]
    #[Assert\Range(
        min: 10,
        max: 40,
        notInRangeMessage: 'La température doit être entre {{ min }}°C et {{ max }}°C.'
    )]
    private ?float $temperature = null;

    #[ORM\Column(name: 'bruit_niveau', type: "string", length: 50, nullable: true)]
    #[Assert\Choice(
        choices: ['🔇 Silencieux', '🔉 Léger', '🔉 Modéré', '🔊 Fort'],
        message: 'Choisissez un niveau de bruit valide.'
    )]
    private ?string $bruitNiveau = null;

    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column(name: 'created_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $createdAt = null;

    #[Gedmo\Timestampable(on: 'update')]
    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    /** @var Collection<int, Reves> */
    #[ORM\OneToMany(mappedBy: "sommeil", targetEntity: Reves::class, cascade: ['persist', 'remove'])]
    private Collection $reves;

    public function __construct()
    {
        $this->reves = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDateNuit(): ?\DateTimeInterface
    {
        return $this->dateNuit;
    }

    public function setDateNuit(?\DateTimeInterface $value): static
    {
        $this->dateNuit = $value;
        return $this;
    }

    public function getHeureCoucher(): ?string
    {
        return $this->heureCoucher;
    }

    public function setHeureCoucher(?string $value): static
    {
        $this->heureCoucher = $value;
        return $this;
    }

    public function getHeureReveil(): ?string
    {
        return $this->heureReveil;
    }

    public function setHeureReveil(?string $value): static
    {
        $this->heureReveil = $value;
        return $this;
    }

    public function getQualite(): ?string
    {
        return $this->qualite;
    }

    public function setQualite(?string $value): static
    {
        $this->qualite = $value;
        return $this;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $value): static
    {
        $this->commentaire = $value;
        return $this;
    }

    public function getDureeSommeil(): ?float
    {
        return $this->dureeSommeil;
    }

    public function setDureeSommeil(?float $value): static
    {
        $this->dureeSommeil = $value;
        return $this;
    }

    public function getInterruptions(): ?int
    {
        return $this->interruptions;
    }

    public function setInterruptions(?int $value): static
    {
        $this->interruptions = $value;
        return $this;
    }

    public function getHumeurReveil(): ?string
    {
        return $this->humeurReveil;
    }

    public function setHumeurReveil(?string $value): static
    {
        $this->humeurReveil = $value;
        return $this;
    }

    public function getEnvironnement(): ?string
    {
        return $this->environnement;
    }

    public function setEnvironnement(?string $value): static
    {
        $this->environnement = $value;
        return $this;
    }

    public function getTemperature(): ?float
    {
        return $this->temperature;
    }

    public function setTemperature(?float $value): static
    {
        $this->temperature = $value;
        return $this;
    }

    public function getBruitNiveau(): ?string
    {
        return $this->bruitNiveau;
    }

    public function setBruitNiveau(?string $value): static
    {
        $this->bruitNiveau = $value;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeImmutable $value): static
    {
        $this->createdAt = $value;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $value): static
    {
        $this->updatedAt = $value;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getUserId(): ?string
    {
        return $this->user?->getId();
    }

    /** @return Collection<int, Reves> */
    public function getReves(): Collection
    {
        return $this->reves;
    }

    public function addReve(Reves $reve): static
    {
        if (!$this->reves->contains($reve)) {
            $this->reves->add($reve);
            $reve->setSommeil($this);
        }

        return $this;
    }

    public function removeReve(Reves $reve): static
    {
        if ($this->reves->removeElement($reve)) {
            if ($reve->getSommeil() === $this) {
                $reve->setSommeil(null);
            }
        }

        return $this;
    }

    public function isSommeilInsuffisant(): bool
    {
        return $this->dureeSommeil !== null && $this->dureeSommeil < 5;
    }

    public function getSleepStatusLabel(): ?string
    {
        return $this->isSommeilInsuffisant() ? 'Sommeil insuffisant' : null;
    }
}