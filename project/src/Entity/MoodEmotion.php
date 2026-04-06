<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\MoodEmotionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MoodEmotionRepository::class)]
#[ORM\Table(name: 'emotion')]
class MoodEmotion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 40, unique: true)]
    private string $name;

    /** @var Collection<int, MoodEntry> */
    #[ORM\ManyToMany(targetEntity: MoodEntry::class, mappedBy: 'emotions')]
    private Collection $moodEntries;

    public function __construct()
    {
        $this->moodEntries = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /** @return Collection<int, MoodEntry> */
    public function getMoodEntries(): Collection
    {
        return $this->moodEntries;
    }
}
