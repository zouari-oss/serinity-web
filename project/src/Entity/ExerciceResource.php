<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ExerciceResourceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ExerciceResourceRepository::class)]
#[ORM\Table(name: 'exercice_resource')]
#[ORM\Index(name: 'idx_exercice_resource_exercice', columns: ['exercice_id'])]
class ExerciceResource
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'resources')]
    #[ORM\JoinColumn(name: 'exercice_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Exercice $exercice;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $title;

    #[ORM\Column(type: Types::STRING, length: 40)]
    private string $resourceType;

    #[ORM\Column(type: Types::STRING, length: 512)]
    private string $resourceUrl;

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

    public function getExercice(): Exercice
    {
        return $this->exercice;
    }

    public function setExercice(Exercice $exercice): self
    {
        $this->exercice = $exercice;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = trim($title);

        return $this;
    }

    public function getResourceType(): string
    {
        return $this->resourceType;
    }

    public function setResourceType(string $resourceType): self
    {
        $this->resourceType = trim($resourceType);

        return $this;
    }

    public function getResourceUrl(): string
    {
        return $this->resourceUrl;
    }

    public function setResourceUrl(string $resourceUrl): self
    {
        $this->resourceUrl = trim($resourceUrl);

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
