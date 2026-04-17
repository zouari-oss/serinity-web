<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 36)]
    private string $id;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: Types::STRING, length: 150, unique: true)]
    private string $email;

    #[ORM\Column(name: 'google_id', type: Types::STRING, length: 191, nullable: true, unique: true)]
    private ?string $googleId = null;

    /** Sensitive: password hash, never expose in API payloads. */
    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $password;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $role;

    #[ORM\Column(name: 'presence_status', type: Types::STRING, length: 255)]
    private string $presenceStatus;

    #[ORM\Column(name: 'account_status', type: Types::STRING, length: 255)]
    private string $accountStatus;

    #[ORM\Column(name: 'face_recognition_enabled', type: Types::BOOLEAN)]
    private bool $faceRecognitionEnabled;

    /** @var Collection<int, AuthSession> */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: AuthSession::class, orphanRemoval: true)]
    private Collection $authSessions;

    #[ORM\OneToOne(mappedBy: 'user', targetEntity: Profile::class, orphanRemoval: true)]
    private ?Profile $profile = null;

    /** @var Collection<int, UserFace> */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: UserFace::class, orphanRemoval: true)]
    private Collection $userFaces;

    /** @var Collection<int, MoodEntry> */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: MoodEntry::class, orphanRemoval: true)]
    private Collection $moodEntries;

    public function __construct()
    {
        $this->authSessions = new ArrayCollection();
        $this->userFaces = new ArrayCollection();
        $this->moodEntries = new ArrayCollection();
    }

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

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getGoogleId(): ?string
    {
        return $this->googleId;
    }

    public function setGoogleId(?string $googleId): self
    {
        $this->googleId = $googleId;

        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): self
    {
        $this->role = $role;

        return $this;
    }

    public function getPresenceStatus(): string
    {
        return $this->presenceStatus;
    }

    public function setPresenceStatus(string $presenceStatus): self
    {
        $this->presenceStatus = $presenceStatus;

        return $this;
    }

    public function getAccountStatus(): string
    {
        return $this->accountStatus;
    }

    public function setAccountStatus(string $accountStatus): self
    {
        $this->accountStatus = $accountStatus;

        return $this;
    }

    public function isFaceRecognitionEnabled(): bool
    {
        return $this->faceRecognitionEnabled;
    }

    public function setFaceRecognitionEnabled(bool $faceRecognitionEnabled): self
    {
        $this->faceRecognitionEnabled = $faceRecognitionEnabled;

        return $this;
    }

    /** @return Collection<int, AuthSession> */
    public function getAuthSessions(): Collection
    {
        return $this->authSessions;
    }

    public function addAuthSession(AuthSession $authSession): self
    {
        if (!$this->authSessions->contains($authSession)) {
            $this->authSessions->add($authSession);
            $authSession->setUser($this);
        }

        return $this;
    }

    public function removeAuthSession(AuthSession $authSession): self
    {
        $this->authSessions->removeElement($authSession);

        return $this;
    }

    public function getProfile(): ?Profile
    {
        return $this->profile;
    }

    public function setProfile(?Profile $profile): self
    {
        if ($profile !== null && $profile->getUser() !== $this) {
            $profile->setUser($this);
        }

        $this->profile = $profile;

        return $this;
    }

    /** @return Collection<int, UserFace> */
    public function getUserFaces(): Collection
    {
        return $this->userFaces;
    }

    public function addUserFace(UserFace $userFace): self
    {
        if (!$this->userFaces->contains($userFace)) {
            $this->userFaces->add($userFace);
            $userFace->setUser($this);
        }

        return $this;
    }

    public function removeUserFace(UserFace $userFace): self
    {
        $this->userFaces->removeElement($userFace);

        return $this;
    }

    /** @return Collection<int, MoodEntry> */
    public function getMoodEntries(): Collection
    {
        return $this->moodEntries;
    }

    public function addMoodEntry(MoodEntry $moodEntry): self
    {
        if (!$this->moodEntries->contains($moodEntry)) {
            $this->moodEntries->add($moodEntry);
            $moodEntry->setUser($this);
        }

        return $this;
    }

    public function removeMoodEntry(MoodEntry $moodEntry): self
    {
        $this->moodEntries->removeElement($moodEntry);

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return ['ROLE_' . strtoupper($this->role)];
    }

    public function eraseCredentials(): void {}
}
