<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SleepDreamRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SleepDreamRepository::class)]
#[ORM\Table(name: 'reves')]
#[ORM\Index(name: 'idx_reves_created', columns: ['created_at'])]
#[ORM\Index(name: 'idx_reves_type', columns: ['type_reve'])]
class SleepDream
{
    public const DREAM_TYPES = ['Normal', 'Lucide', 'Cauchemar'];
    public const MOODS = ['😄 Joyeux', '😢 Triste', '😨 Effrayé', '😌 Serein', '😐 Neutre'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?string $id = null;

    #[ORM\ManyToOne(inversedBy: 'dreams')]
    #[ORM\JoinColumn(name: 'sommeil_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'Veuillez associer une nuit de sommeil.')]
    private ?SleepSession $sommeilId = null;

    #[ORM\Column(name: 'titre', type: Types::STRING, length: 200)]
    #[Assert\NotBlank(message: 'Le titre est obligatoire.')]
    #[Assert\Length(min: 3, max: 200, minMessage: 'Le titre doit contenir au moins {{ limit }} caractères.', maxMessage: 'Le titre ne doit pas dépasser {{ limit }} caractères.')]
    private ?string $titre = null;

    #[ORM\Column(name: 'description', type: Types::TEXT)]
    #[Assert\NotBlank(message: 'La description est obligatoire.')]
    #[Assert\Length(min: 10, minMessage: 'La description doit contenir au moins {{ limit }} caractères.')]
    private ?string $description = null;

    #[ORM\Column(name: 'humeur', type: Types::STRING, length: 50, nullable: true)]
    #[Assert\Choice(choices: self::MOODS, message: 'Choisissez une humeur valide.')]
    private ?string $humeur = null;

    #[ORM\Column(name: 'type_reve', type: Types::STRING, length: 50, nullable: true)]
    #[Assert\NotBlank(message: 'Le type de rêve est obligatoire.')]
    #[Assert\Choice(choices: self::DREAM_TYPES, message: 'Type de rêve invalide.')]
    private ?string $typeReve = null;

    #[ORM\Column(name: 'intensite', type: Types::INTEGER, nullable: true)]
    #[Assert\NotNull(message: "L'intensité est obligatoire.")]
    #[Assert\Range(min: 1, max: 10, notInRangeMessage: "L'intensité doit être entre {{ min }} et {{ max }}.")]
    private ?int $intensite = null;

    #[ORM\Column(name: 'couleur', type: Types::BOOLEAN)]
    private bool $couleur = false;

    #[ORM\Column(name: 'emotions', type: Types::STRING, length: 200, nullable: true)]
    #[Assert\Length(max: 200, maxMessage: 'Les émotions ne doivent pas dépasser {{ limit }} caractères.')]
    private ?string $emotions = null;

    #[ORM\Column(name: 'symboles', type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 1000, maxMessage: 'Les symboles ne doivent pas dépasser {{ limit }} caractères.')]
    private ?string $symboles = null;

    #[ORM\Column(name: 'recurrent', type: Types::BOOLEAN)]
    private bool $recurrent = false;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getSommeilId(): ?SleepSession
    {
        return $this->sommeilId;
    }

    public function setSommeilId(?SleepSession $sommeilId): self
    {
        $this->sommeilId = $sommeilId;

        return $this;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(?string $titre): self
    {
        $this->titre = $titre === null ? null : trim($titre);

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description === null ? null : trim($description);

        return $this;
    }

    public function getHumeur(): ?string
    {
        return $this->humeur;
    }

    public function setHumeur(?string $humeur): self
    {
        $this->humeur = $humeur === null ? null : trim($humeur);

        return $this;
    }

    public function getTypeReve(): ?string
    {
        return $this->typeReve;
    }

    public function setTypeReve(?string $typeReve): self
    {
        $this->typeReve = $typeReve === null ? null : trim($typeReve);

        return $this;
    }

    public function getIntensite(): ?int
    {
        return $this->intensite;
    }

    public function setIntensite(?int $intensite): self
    {
        $this->intensite = $intensite;

        return $this;
    }

    public function getCouleur(): bool
    {
        return $this->couleur;
    }

    public function setCouleur(bool $couleur): self
    {
        $this->couleur = $couleur;

        return $this;
    }

    public function getEmotions(): ?string
    {
        return $this->emotions;
    }

    public function setEmotions(?string $emotions): self
    {
        $this->emotions = $emotions === null ? null : trim($emotions);

        return $this;
    }

    public function getSymboles(): ?string
    {
        return $this->symboles;
    }

    public function setSymboles(?string $symboles): self
    {
        $this->symboles = $symboles === null ? null : trim($symboles);

        return $this;
    }

    public function getRecurrent(): bool
    {
        return $this->recurrent;
    }

    public function setRecurrent(bool $recurrent): self
    {
        $this->recurrent = $recurrent;

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

    // Backward-compatible helpers used in current controllers/services.
    public function getSleepSession(): ?SleepSession
    {
        return $this->getSommeilId();
    }

    public function setSleepSession(?SleepSession $sleepSession): self
    {
        return $this->setSommeilId($sleepSession);
    }

    public function getTitle(): ?string
    {
        return $this->getTitre();
    }

    public function setTitle(?string $title): self
    {
        return $this->setTitre($title);
    }

    public function getContent(): ?string
    {
        return $this->getDescription();
    }

    public function setContent(?string $content): self
    {
        return $this->setDescription($content);
    }

    public function getDreamType(): ?string
    {
        return $this->getTypeReve();
    }

    public function setDreamType(?string $dreamType): self
    {
        return $this->setTypeReve($dreamType);
    }

    public function getUser(): ?User
    {
        return $this->sommeilId?->getUser();
    }
}
