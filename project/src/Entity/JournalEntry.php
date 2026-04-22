<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\JournalEntryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: JournalEntryRepository::class)]
#[ORM\Table(name: 'journal_entry')]
#[ORM\Index(name: 'idx_journal_user_created', columns: ['user_id', 'created_at'])]
class JournalEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?string $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank(message: 'Title is required.')]
    #[Assert\Length(
        min: 5,
        max: 255,
        minMessage: 'Title must contain at least {{ limit }} characters.',
        maxMessage: 'Title cannot exceed {{ limit }} characters.',
    )]
    #[Assert\Regex(
        pattern: '/.*[A-Za-zÀ-ÿ].*/u',
        message: 'Title must contain at least one letter.',
    )]
    #[Assert\Regex(
        pattern: "/^[A-Za-zÀ-ÿ0-9\\s'.,!?:()\\-]+$/u",
        message: 'Title contains invalid characters.',
    )]
    private string $title = '';

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Content is required.')]
    #[Assert\Length(
        min: 10,
        minMessage: 'Content must contain at least {{ limit }} characters.',
    )]
    private string $content = '';

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(name: 'ai_tags', type: Types::TEXT, nullable: true)]
    private ?string $aiTags = null;

    #[ORM\Column(name: 'ai_model_version', type: Types::STRING, length: 32, nullable: true)]
    private ?string $aiModelVersion = null;

    #[ORM\Column(name: 'ai_generated_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $aiGeneratedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = trim((string) $title);

        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(?string $content): self
    {
        $this->content = trim((string) $content);

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

    public function getAiTags(): ?string
    {
        return $this->aiTags;
    }

    public function setAiTags(?string $aiTags): self
    {
        $this->aiTags = $aiTags;

        return $this;
    }

    public function getAiModelVersion(): ?string
    {
        return $this->aiModelVersion;
    }

    public function setAiModelVersion(?string $aiModelVersion): self
    {
        $this->aiModelVersion = $aiModelVersion;

        return $this;
    }

    public function getAiGeneratedAt(): ?\DateTimeImmutable
    {
        return $this->aiGeneratedAt;
    }

    public function setAiGeneratedAt(?\DateTimeImmutable $aiGeneratedAt): self
    {
        $this->aiGeneratedAt = $aiGeneratedAt;

        return $this;
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

    /**
     * @return array<string, mixed>|null
     */
    public function getDecodedAiTags(): ?array
    {
        if ($this->aiTags === null || trim($this->aiTags) === '') {
            return null;
        }

        try {
            $decoded = json_decode($this->aiTags, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!is_array($decoded) || $decoded === []) {
            return null;
        }

        return $decoded;
    }

    public function getTopEmotionLabel(): ?string
    {
        $decoded = $this->getDecodedAiTags();
        if ($decoded === null) {
            return null;
        }

        $topLabel = $decoded['top_label'] ?? null;
        if (is_string($topLabel) && trim($topLabel) !== '') {
            return trim($topLabel);
        }

        $firstLabel = $decoded['labels'][0]['label'] ?? null;
        if (is_string($firstLabel) && trim($firstLabel) !== '') {
            return trim($firstLabel);
        }

        return null;
    }
}
