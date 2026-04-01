<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AuditLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AuditLogRepository::class)]
#[ORM\Table(name: 'audit_logs')]
#[ORM\Index(name: 'idx_audit_created', columns: ['created_at'])]
#[ORM\Index(name: 'fk_audit_logs_auth_session_id', columns: ['auth_session_id'])]
class AuditLog
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 36)]
    private string $id;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $action;

    #[ORM\Column(name: 'os_name', type: Types::STRING, length: 50, nullable: true)]
    private ?string $osName = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $hostname = null;

    #[ORM\Column(name: 'private_ip_address', type: Types::STRING, length: 45)]
    private string $privateIpAddress;

    #[ORM\Column(name: 'mac_address', type: Types::STRING, length: 17, nullable: true)]
    private ?string $macAddress = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $location = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(inversedBy: 'auditLogs')]
    #[ORM\JoinColumn(name: 'auth_session_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private AuthSession $authSession;

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function setAction(string $action): self
    {
        $this->action = $action;

        return $this;
    }

    public function getOsName(): ?string
    {
        return $this->osName;
    }

    public function setOsName(?string $osName): self
    {
        $this->osName = $osName;

        return $this;
    }

    public function getHostname(): ?string
    {
        return $this->hostname;
    }

    public function setHostname(?string $hostname): self
    {
        $this->hostname = $hostname;

        return $this;
    }

    public function getPrivateIpAddress(): string
    {
        return $this->privateIpAddress;
    }

    public function setPrivateIpAddress(string $privateIpAddress): self
    {
        $this->privateIpAddress = $privateIpAddress;

        return $this;
    }

    public function getMacAddress(): ?string
    {
        return $this->macAddress;
    }

    public function setMacAddress(?string $macAddress): self
    {
        $this->macAddress = $macAddress;

        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): self
    {
        $this->location = $location;

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

    public function getAuthSession(): AuthSession
    {
        return $this->authSession;
    }

    public function setAuthSession(AuthSession $authSession): self
    {
        $this->authSession = $authSession;

        return $this;
    }
}
