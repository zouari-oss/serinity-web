<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\MoodEntryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MoodEntryRepository::class)]
#[ORM\Table(name: 'mood_entry')]
#[ORM\Index(name: 'idx_mood_entry_user_date', columns: ['user_id', 'entry_date'])]
class MoodEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?string $id = null;

    #[ORM\Column(name: 'entry_date', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $entryDate;

    #[ORM\Column(name: 'moment_type', type: Types::STRING, length: 16)]
    private string $momentType;

    #[ORM\Column(name: 'mood_level', type: Types::SMALLINT)]
    private int $moodLevel;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    #[ORM\ManyToOne(inversedBy: 'moodEntries')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    /** @var Collection<int, MoodEmotion> */
    #[ORM\ManyToMany(targetEntity: MoodEmotion::class, inversedBy: 'moodEntries')]
    #[ORM\JoinTable(name: 'mood_entry_emotion')]
    #[ORM\JoinColumn(name: 'mood_entry_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'emotion_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Collection $emotions;

    /** @var Collection<int, MoodInfluence> */
    #[ORM\ManyToMany(targetEntity: MoodInfluence::class, inversedBy: 'moodEntries')]
    #[ORM\JoinTable(name: 'mood_entry_influence')]
    #[ORM\JoinColumn(name: 'mood_entry_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'influence_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Collection $influences;

    public function __construct()
    {
        $this->emotions = new ArrayCollection();
        $this->influences = new ArrayCollection();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getEntryDate(): \DateTimeImmutable
    {
        return $this->entryDate;
    }

    public function setEntryDate(\DateTimeImmutable $entryDate): self
    {
        $this->entryDate = $entryDate;

        return $this;
    }

    public function getMomentType(): string
    {
        return $this->momentType;
    }

    public function setMomentType(string $momentType): self
    {
        $this->momentType = $momentType;

        return $this;
    }

    public function getMoodLevel(): int
    {
        return $this->moodLevel;
    }

    public function setMoodLevel(int $moodLevel): self
    {
        $this->moodLevel = $moodLevel;

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

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    /** @return Collection<int, MoodEmotion> */
    public function getEmotions(): Collection
    {
        return $this->emotions;
    }

    public function addEmotion(MoodEmotion $emotion): self
    {
        if (!$this->emotions->contains($emotion)) {
            $this->emotions->add($emotion);
        }

        return $this;
    }

    public function removeEmotion(MoodEmotion $emotion): self
    {
        $this->emotions->removeElement($emotion);

        return $this;
    }

    /** @return Collection<int, MoodInfluence> */
    public function getInfluences(): Collection
    {
        return $this->influences;
    }

    public function addInfluence(MoodInfluence $influence): self
    {
        if (!$this->influences->contains($influence)) {
            $this->influences->add($influence);
        }

        return $this;
    }

    public function removeInfluence(MoodInfluence $influence): self
    {
        $this->influences->removeElement($influence);

        return $this;
    }
}
