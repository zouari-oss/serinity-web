<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AuthSessionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AuthSessionRepository::class)]
#[ORM\Table(name: 'auth_sessions')]
#[ORM\Index(name: 'idx_session_token', columns: ['refresh_token'])]
#[ORM\Index(name: 'idx_session_user', columns: ['user_id'])]
class AuthSession
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 36)]
    private string $id;

    /** Sensitive: refresh token, never expose in API payloads. */
    #[ORM\Column(name: 'refresh_token', type: Types::STRING, length: 255, unique: true)]
    private string $refreshToken;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'expires_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $revoked;

    #[ORM\ManyToOne(inversedBy: 'authSessions')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    /** @var Collection<int, AuditLog> */
    #[ORM\OneToMany(mappedBy: 'authSession', targetEntity: AuditLog::class, orphanRemoval: true)]
    private Collection $auditLogs;

    public function __construct()
    {
        $this->auditLogs = new ArrayCollection();
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

    public function getRefreshToken(): string
    {
        return $this->refreshToken;
    }

    public function setRefreshToken(string $refreshToken): self
    {
        $this->refreshToken = $refreshToken;

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

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeImmutable $expiresAt): self
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function isRevoked(): bool
    {
        return $this->revoked;
    }

    public function setRevoked(bool $revoked): self
    {
        $this->revoked = $revoked;

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

    /** @return Collection<int, AuditLog> */
    public function getAuditLogs(): Collection
    {
        return $this->auditLogs;
    }

    public function addAuditLog(AuditLog $auditLog): self
    {
        if (!$this->auditLogs->contains($auditLog)) {
            $this->auditLogs->add($auditLog);
            $auditLog->setAuthSession($this);
        }

        return $this;
    }

    public function removeAuditLog(AuditLog $auditLog): self
    {
        $this->auditLogs->removeElement($auditLog);

        return $this;
    }
}
