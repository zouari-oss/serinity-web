<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ExerciceFavoriteRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ExerciceFavoriteRepository::class)]
#[ORM\Table(name: 'exercice_favorite')]
#[ORM\UniqueConstraint(name: 'uniq_exercice_favorite', columns: ['user_id', 'favorite_type', 'item_id'])]
#[ORM\Index(name: 'idx_exercice_favorite_user', columns: ['user_id'])]
class ExerciceFavorite
{
    public const TYPE_EXERCICE = 'EXERCICE';
    public const TYPE_RESOURCE = 'RESOURCE';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(name: 'favorite_type', type: Types::STRING, length: 20)]
    private string $favoriteType;

    #[ORM\Column(name: 'item_id', type: Types::INTEGER)]
    private int $itemId;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
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

    public function getFavoriteType(): string
    {
        return $this->favoriteType;
    }

    public function setFavoriteType(string $favoriteType): self
    {
        $this->favoriteType = strtoupper(trim($favoriteType));

        return $this;
    }

    public function getItemId(): int
    {
        return $this->itemId;
    }

    public function setItemId(int $itemId): self
    {
        $this->itemId = $itemId;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
