<?php

namespace App\Entity;

use App\Repository\PostInteractionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PostInteractionRepository::class)]
#[ORM\Table(name: 'postinteraction')]
#[ORM\UniqueConstraint(columns: ['thread_id', 'user_id'])]
class PostInteraction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'interactions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ForumThread $thread = null;

    #[ORM\Column(name: 'user_id', length: 36)]
    private ?string $userId = null;

    #[ORM\Column]
    private bool $follow = false;

    #[ORM\Column]
    private int $vote = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getThread(): ?ForumThread
    {
        return $this->thread;
    }

    public function setThread(?ForumThread $thread): self
    {
        $this->thread = $thread;

        return $this;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(string $userId): self
    {
        $this->userId = $userId;

        return $this;
    }

    public function isFollow(): bool
    {
        return $this->follow;
    }

    public function setFollow(bool $follow): self
    {
        $this->follow = $follow;

        return $this;
    }

    public function getVote(): int
    {
        return $this->vote;
    }

    public function setVote(int $vote): self
    {
        $this->vote = max(-1, min(1, $vote));

        return $this;
    }
}
