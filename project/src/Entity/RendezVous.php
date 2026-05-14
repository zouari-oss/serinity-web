<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
class RendezVous
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: "rendezVousPatient")]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $patient = null;

    #[ORM\ManyToOne(inversedBy: "rendezVousDoctor")]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $doctor = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\NotBlank(message: "Le motif est obligatoire")]
    #[Assert\Length(min: 3, minMessage: "Minimum 3 caractères")]
    private ?string $motif = null;

    #[ORM\Column(type: "text", nullable: true)]
    #[Assert\Length(max: 1000, maxMessage: "Max 1000 caractères")]
    #[Assert\NotBlank(message: "Le description est obligatoire")]

    private ?string $description = null;

    #[ORM\Column(type: "datetime", nullable: true)]
#[Assert\NotNull(message: "La date est obligatoire")]
#[Assert\GreaterThan("now", message: "La date doit être dans le futur")]
private ?\DateTimeInterface $dateTime = null;

#[ORM\OneToOne(mappedBy: 'rendezVous', targetEntity: Consultation::class)]
private ?Consultation $consultation = null;
 

    
    #[ORM\Column(length: 30)]
    private string $status = "EN_ATTENTE";

    
    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $createdAt;




    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    #[ORM\Column(type: "datetime", nullable: true)]
private ?\DateTimeInterface $proposedDateTime = null;

#[ORM\Column(type: "text", nullable: true)]
private ?string $doctorNote = null;


    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

 
 
public function getConsultation(): ?Consultation
{
    return $this->consultation;
}

 
public function setConsultation(?Consultation $consultation): self
{
    $this->consultation = $consultation;

    // ⚠️ Synchronisation inverse (très important)
    if ($consultation !== null && $consultation->getRendezVous() !== $this) {
        $consultation->setRendezVous($this);
    }

    return $this;
}
     public function getId(): ?int { return $this->id; }

    public function getPatient(): ?User { return $this->patient; }
    public function setPatient(?User $patient): self { $this->patient = $patient; return $this; }

    public function getDoctor(): ?User { return $this->doctor; }
    public function setDoctor(?User $doctor): self { $this->doctor = $doctor; return $this; }

    public function getMotif(): ?string { return $this->motif; }
    public function setMotif(?string $motif): self { $this->motif = $motif; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }

    public function getDateTime(): \DateTimeInterface { return $this->dateTime; }
    public function setDateTime(\DateTimeInterface $dateTime): self { $this->dateTime = $dateTime; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }


public function getProposedDateTime(): ?\DateTimeInterface
{
    return $this->proposedDateTime;
}

public function setProposedDateTime(?\DateTimeInterface $date): self
{
    $this->proposedDateTime = $date;
    return $this;
}

public function getDoctorNote(): ?string
{
    return $this->doctorNote;
}

public function setDoctorNote(?string $note): self
{
    $this->doctorNote = $note;
    return $this;
}
}
