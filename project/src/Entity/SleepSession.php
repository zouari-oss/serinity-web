<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SleepSessionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SleepSessionRepository::class)]
#[ORM\Table(name: 'sommeil')]
#[ORM\Index(name: 'idx_sommeil_user_date', columns: ['user_id', 'date_nuit'])]
#[ORM\Index(name: 'idx_sommeil_qualite', columns: ['qualite'])]
class SleepSession
{
    public const QUALITY_LABELS = [
        'Excellente',
        'Bonne',
        'Moyenne',
        'Mauvaise',
    ];
    public const WAKE_MOODS = ['😌 Reposé', '😄 Joyeux', '😐 Neutre', '😴 Fatigué', '⚡ Énergisé'];
    public const ENVIRONMENTS = ['🏠 Normal', '🌿 Calme', '😊 Confortable'];
    public const NOISE_LEVELS = ['🔇 Silencieux', '🔉 Léger', '🔉 Modéré', '🔊 Fort'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(name: 'date_nuit', type: Types::DATE_IMMUTABLE)]
    #[Assert\NotBlank(message: 'La date de nuit est obligatoire.')]
    #[Assert\LessThanOrEqual('today', message: 'La date de nuit ne peut pas être dans le futur.')]
    private ?\DateTimeImmutable $dateNuit = null;

    #[ORM\Column(name: 'heure_coucher', type: Types::STRING, length: 10)]
    #[Assert\NotBlank(message: "L'heure de coucher est obligatoire.")]
    private ?string $heureCoucher = null;

    #[ORM\Column(name: 'heure_reveil', type: Types::STRING, length: 10)]
    #[Assert\NotBlank(message: "L'heure de réveil est obligatoire.")]
    private ?string $heureReveil = null;

    #[ORM\Column(name: 'qualite', type: Types::STRING, length: 50)]
    #[Assert\NotBlank(message: 'La qualité est obligatoire.')]
    #[Assert\Choice(choices: self::QUALITY_LABELS, message: 'Choisissez une qualité valide.')]
    private ?string $qualite = null;

    #[ORM\Column(name: 'commentaire', type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 1000, maxMessage: 'Le commentaire ne doit pas dépasser {{ limit }} caractères.')]
    private ?string $commentaire = null;

    #[ORM\Column(name: 'duree_sommeil', type: Types::FLOAT, nullable: true)]
    #[Assert\Positive(message: 'La durée doit être un nombre positif.')]
    #[Assert\Range(min: 0.5, max: 24, notInRangeMessage: 'La durée du sommeil doit être entre {{ min }} et {{ max }} heures.')]
    private ?float $dureeSommeil = null;

    #[ORM\Column(name: 'interruptions', type: Types::INTEGER, nullable: true)]
    #[Assert\PositiveOrZero(message: 'Les interruptions ne peuvent pas être négatives.')]
    private ?int $interruptions = null;

    #[ORM\Column(name: 'humeur_reveil', type: Types::STRING, length: 50, nullable: true)]
    #[Assert\Choice(choices: self::WAKE_MOODS, message: 'Choisissez une humeur valide.')]
    private ?string $humeurReveil = null;

    #[ORM\Column(name: 'environnement', type: Types::STRING, length: 100, nullable: true)]
    #[Assert\Choice(choices: self::ENVIRONMENTS, message: 'Choisissez un environnement valide.')]
    private ?string $environnement = null;

    #[ORM\Column(name: 'temperature', type: Types::FLOAT, nullable: true)]
    #[Assert\Range(min: 10, max: 40, notInRangeMessage: 'La température doit être entre {{ min }}°C et {{ max }}°C.')]
    private ?float $temperature = null;

    #[ORM\Column(name: 'bruit_niveau', type: Types::STRING, length: 50, nullable: true)]
    #[Assert\Choice(choices: self::NOISE_LEVELS, message: 'Choisissez un niveau de bruit valide.')]
    private ?string $bruitNiveau = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, SleepDream> */
    #[ORM\OneToMany(mappedBy: 'sommeilId', targetEntity: SleepDream::class, orphanRemoval: true)]
    private Collection $dreams;

    public function __construct()
    {
        $this->dreams = new ArrayCollection();
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getDateNuit(): ?\DateTimeImmutable
    {
        return $this->dateNuit;
    }

    public function setDateNuit(?\DateTimeImmutable $dateNuit): self
    {
        $this->dateNuit = $dateNuit;

        return $this;
    }

    public function getHeureCoucher(): ?string
    {
        return $this->heureCoucher;
    }

    public function setHeureCoucher(?string $heureCoucher): self
    {
        $this->heureCoucher = $heureCoucher === null ? null : trim($heureCoucher);

        return $this;
    }

    public function getHeureReveil(): ?string
    {
        return $this->heureReveil;
    }

    public function setHeureReveil(?string $heureReveil): self
    {
        $this->heureReveil = $heureReveil === null ? null : trim($heureReveil);

        return $this;
    }

    public function getQualite(): ?string
    {
        return $this->qualite;
    }

    public function setQualite(?string $qualite): self
    {
        $this->qualite = $qualite === null ? null : trim($qualite);

        return $this;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): self
    {
        $this->commentaire = $commentaire === null ? null : trim($commentaire);

        return $this;
    }

    public function getDureeSommeil(): ?float
    {
        return $this->dureeSommeil;
    }

    public function setDureeSommeil(?float $dureeSommeil): self
    {
        $this->dureeSommeil = $dureeSommeil;

        return $this;
    }

    public function getInterruptions(): ?int
    {
        return $this->interruptions;
    }

    public function setInterruptions(?int $interruptions): self
    {
        $this->interruptions = $interruptions;

        return $this;
    }

    public function getHumeurReveil(): ?string
    {
        return $this->humeurReveil;
    }

    public function setHumeurReveil(?string $humeurReveil): self
    {
        $this->humeurReveil = $humeurReveil === null ? null : trim($humeurReveil);

        return $this;
    }

    public function getEnvironnement(): ?string
    {
        return $this->environnement;
    }

    public function setEnvironnement(?string $environnement): self
    {
        $this->environnement = $environnement === null ? null : trim($environnement);

        return $this;
    }

    public function getTemperature(): ?float
    {
        return $this->temperature;
    }

    public function setTemperature(?float $temperature): self
    {
        $this->temperature = $temperature;

        return $this;
    }

    public function getBruitNiveau(): ?string
    {
        return $this->bruitNiveau;
    }

    public function setBruitNiveau(?string $bruitNiveau): self
    {
        $this->bruitNiveau = $bruitNiveau === null ? null : trim($bruitNiveau);

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /** @return Collection<int, SleepDream> */
    public function getDreams(): Collection
    {
        return $this->dreams;
    }

    public function getQualityLabel(): string
    {
        return $this->qualite ?? 'Moyenne';
    }

    public function setQualityLabel(string $label): self
    {
        $this->qualite = in_array($label, self::QUALITY_LABELS, true) ? $label : 'Moyenne';

        return $this;
    }

    public function getDurationHours(): float
    {
        return round((float) ($this->dureeSommeil ?? 0), 1);
    }

    public function setDurationHours(float $hours): self
    {
        $this->dureeSommeil = max(0.5, min(24, $hours));

        return $this;
    }

    public function isSleepInsufficient(): bool
    {
        return $this->dureeSommeil !== null && $this->dureeSommeil < 5;
    }

    // Backward-compatible accessors used across existing service/controller code.
    public function getSleepDate(): ?\DateTimeImmutable
    {
        return $this->getDateNuit();
    }

    public function setSleepDate(?\DateTimeImmutable $sleepDate): self
    {
        return $this->setDateNuit($sleepDate);
    }

    public function getBedTime(): ?string
    {
        return $this->getHeureCoucher();
    }

    public function setBedTime(?string $bedTime): self
    {
        return $this->setHeureCoucher($bedTime);
    }

    public function getWakeTime(): ?string
    {
        return $this->getHeureReveil();
    }

    public function setWakeTime(?string $wakeTime): self
    {
        return $this->setHeureReveil($wakeTime);
    }

    public function getNotes(): ?string
    {
        return $this->getCommentaire();
    }

    public function setNotes(?string $notes): self
    {
        return $this->setCommentaire($notes);
    }
}
