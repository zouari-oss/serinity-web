<?php

namespace App\Entity;

use App\Enum\ThreadStatus;
use App\Enum\ThreadType;
use App\Repository\ForumThreadRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: ForumThreadRepository::class)]
#[ORM\Table(name: 'threads')]
class ForumThread
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'threads')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Category $category = null;

    #[ORM\Column(name: 'user_id', length: 36)]
    private ?string $authorId = null;

    private ?string $authorUsername = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Title is required.')]
    #[Assert\Length(min: 5, max: 255, minMessage: 'Title must contain at least {{ limit }} characters.', maxMessage: 'Title cannot exceed {{ limit }} characters.')]
    private ?string $title = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank(message: 'Content is required.')]
    #[Assert\Length(min: 20, minMessage: 'Content must contain at least {{ limit }} characters.')]
    private ?string $content = null;

    #[ORM\Column(enumType: ThreadType::class)]
    private ThreadType $type = ThreadType::DISCUSSION;

    #[ORM\Column(enumType: ThreadStatus::class)]
    private ThreadStatus $status = ThreadStatus::OPEN;

    #[ORM\Column]
    private bool $isPinned = false;

    #[ORM\Column(
        type: Types::DATETIME_IMMUTABLE,
        options: ['default' => 'CURRENT_TIMESTAMP']
    )]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(
        type: Types::DATETIME_IMMUTABLE,
        options: ['default' => 'CURRENT_TIMESTAMP']
    )]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(nullable: true, length: 500)]
    private ?string $imageUrl = null;

    #[ORM\Column(
        name: 'likecount',
        type: Types::INTEGER,
        options: ['default' => '0']
    )]
    private int $likeCount = 0;

    #[ORM\Column(
        name: 'dislikecount',
        type: Types::INTEGER,
        options: ['default' => '0']
    )]
    private int $dislikeCount = 0;

    #[ORM\Column(
        name: 'followcount',
        type: Types::INTEGER,
        options: ['default' => '0']
    )]
    private int $followCount = 0;

    #[ORM\Column(
        name: 'repliescount',
        type: Types::INTEGER,
        options: ['default' => '0']
    )]
    private int $replyCount = 0;

    #[ORM\OneToMany(mappedBy: 'thread', targetEntity: Reply::class, orphanRemoval: true)]
    private Collection $replies;

    #[ORM\OneToMany(mappedBy: 'thread', targetEntity: PostInteraction::class, orphanRemoval: true)]
    private Collection $interactions;

    #[ORM\OneToMany(mappedBy: 'thread', targetEntity: Notification::class, orphanRemoval: true)]
    private Collection $notifications;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->replies = new ArrayCollection();
        $this->interactions = new ArrayCollection();
        $this->notifications = new ArrayCollection();
    }

    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): self
    {
        $this->category = $category;

        return $this;
    }

    public function getAuthorId(): ?string
    {
        return $this->authorId;
    }

    public function setAuthorId(string $authorId): self
    {
        $this->authorId = $authorId;

        return $this;
    }

    public function getAuthorUsername(): ?string
    {
        return $this->authorUsername;
    }

    public function setAuthorUsername(?string $authorUsername): self
    {
        $this->authorUsername = $authorUsername;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function getType(): ThreadType
    {
        return $this->type;
    }

    public function setType(ThreadType $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getStatus(): ThreadStatus
    {
        return $this->status;
    }

    public function setStatus(ThreadStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function isPinned(): bool
    {
        return $this->isPinned;
    }

    public function setIsPinned(bool $isPinned): self
    {
        $this->isPinned = $isPinned;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
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

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function setImageUrl(?string $imageUrl): self
    {
        $this->imageUrl = $imageUrl;

        return $this;
    }

    public function getLikeCount(): int
    {
        return $this->likeCount;
    }

    public function setLikeCount(int $likeCount): self
    {
        $this->likeCount = $likeCount;

        return $this;
    }

    public function getDislikeCount(): int
    {
        return $this->dislikeCount;
    }

    public function setDislikeCount(int $dislikeCount): self
    {
        $this->dislikeCount = $dislikeCount;

        return $this;
    }

    public function getFollowCount(): int
    {
        return $this->followCount;
    }

    public function setFollowCount(int $followCount): self
    {
        $this->followCount = $followCount;

        return $this;
    }

    public function getReplyCount(): int
    {
        return $this->replyCount;
    }

    public function setReplyCount(int $replyCount): self
    {
        $this->replyCount = $replyCount;

        return $this;
    }

    /**
     * @return Collection<int, Reply>
     */
    public function getReplies(): Collection
    {
        return $this->replies;
    }

    /**
     * @return Collection<int, PostInteraction>
     */
    public function getInteractions(): Collection
    {
        return $this->interactions;
    }
}
