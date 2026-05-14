<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity]
class Rapport
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $patient = null;

    #[ORM\Column(type: "date")]
    private \DateTimeInterface $dateCreation;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $resumeGeneral = null;

 

    public function __construct()
    {
         $this->dateCreation = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getPatient(): ?User { return $this->patient; }
    public function setPatient(?User $patient): self { $this->patient = $patient; return $this; }

    public function getDateCreation(): \DateTimeInterface { return $this->dateCreation; }
    public function setDateCreation(\DateTimeInterface $date): self { $this->dateCreation = $date; return $this; }

    public function getResumeGeneral(): ?string { return $this->resumeGeneral; }
    public function setResumeGeneral(?string $resume): self { $this->resumeGeneral = $resume; return $this; }

 
}
