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

    #[ORM\Column(
        name: 'created_at',
        type: Types::DATETIME_IMMUTABLE,
        options: ['default' => 'CURRENT_TIMESTAMP']
    )]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(
        name: 'updated_at',
        type: Types::DATETIME_IMMUTABLE,
        options: ['default' => 'CURRENT_TIMESTAMP']
    )]
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

        return $this->normalizeDecodedAiTags($decoded);
    }

    /**
     * @param array<mixed> $decoded
     *
     * @return array<string, mixed>|null
     */
    private function normalizeDecodedAiTags(array $decoded): ?array
    {
        // Legacy Java/Weka rows are stored as a plain list of {tag, score} objects.
        if (array_is_list($decoded)) {
            $labels = $this->normalizeLabels($decoded);
            if ($labels === []) {
                return null;
            }

            return [
                'top_label' => $labels[0]['label'],
                'labels' => $labels,
            ];
        }

        $labels = $this->normalizeLabels(is_array($decoded['labels'] ?? null) ? $decoded['labels'] : []);
        $topLabel = is_string($decoded['top_label'] ?? null) ? trim((string) $decoded['top_label']) : '';

        if ($topLabel === '' && $labels !== []) {
            $topLabel = $labels[0]['label'];
        }

        if ($topLabel === '' && $labels === []) {
            return null;
        }

        if ($topLabel !== '') {
            $decoded['top_label'] = $topLabel;
        }

        if ($labels !== []) {
            $decoded['labels'] = $labels;
        }

        return $decoded;
    }

    /**
     * @param array<mixed> $rows
     *
     * @return list<array{label: string, score: float}>
     */
    private function normalizeLabels(array $rows): array
    {
        $labels = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $label = is_string($row['label'] ?? null)
                ? trim((string) $row['label'])
                : (is_string($row['tag'] ?? null) ? trim((string) $row['tag']) : '');
            if ($label === '') {
                continue;
            }

            $score = is_numeric($row['score'] ?? null) ? (float) $row['score'] : null;
            if ($score === null) {
                continue;
            }

            $labels[] = [
                'label' => $label,
                'score' => $score,
            ];
        }

        return $labels;
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
