<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserFaceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserFaceRepository::class)]
#[ORM\Table(name: 'user_faces')]
#[ORM\UniqueConstraint(name: 'uniq_user_faces_user', columns: ['user_id'])]
#[ORM\Index(name: 'idx_user_faces_user', columns: ['user_id'])]
class UserFace
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 36)]
    private string $id;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    /** Sensitive biometric payload; do not expose raw embedding. */
    #[ORM\Column(type: Types::BLOB)]
    private mixed $embedding;

    #[ORM\ManyToOne(inversedBy: 'userFaces')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;

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

    public function getEmbedding(): mixed
    {
        return $this->embedding;
    }

    public function setEmbedding(mixed $embedding): self
    {
        $this->embedding = $embedding;

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
}
