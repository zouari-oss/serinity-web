<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use Symfony\Component\Validator\Constraints as Assert;


#[ORM\Entity]
class Consultation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: "consultations")]
    #[ORM\JoinColumn(nullable: false)]
    private ?Rapport $rapport = null;

     



    #[ORM\OneToOne(inversedBy: "consultation")]
        #[Assert\NotBlank(message: "Le rendezVous est obligatoire")]

private ?RendezVous $rendezVous = null;







    #[ORM\ManyToOne(inversedBy: "consultations")]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $doctor = null;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $dateConsultation;

    #[ORM\Column(type: "text", nullable: true)]
    #[Assert\Length(max: 255)]
    #[Assert\NotBlank(message: "Le diagnostic est obligatoire")]
        #[Assert\Length(min: 3, minMessage: "Min 3 caractères")]


    private ?string $diagnostic = null;

    #[ORM\Column(type: "text", nullable: true)]
    #[Assert\NotBlank(message: "La prescription est obligatoire")]
        #[Assert\Length(min: 3, minMessage: "Min 3 caractères")]

    private ?string $prescription = null;

    #[ORM\Column(type: "text", nullable: true)]
    #[Assert\NotBlank(message: "Les notes sont obligatoires")]
    #[Assert\Length(min: 3, minMessage: "Min 3 caractères")]
    private ?string $notes = null;

    public function __construct()
    {
        $this->dateConsultation = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getRapport(): ?Rapport { return $this->rapport; }
    public function setRapport(?Rapport $rapport): self { $this->rapport = $rapport; return $this; }

    public function getRendezVous(): ?RendezVous { return $this->rendezVous; }
    public function setRendezVous(?RendezVous $rendezVous): self { $this->rendezVous = $rendezVous; return $this; }

    public function getDoctor(): ?User { return $this->doctor; }
    public function setDoctor(?User $doctor): self { $this->doctor = $doctor; return $this; }

    public function getDateConsultation(): \DateTimeInterface { return $this->dateConsultation; }
    public function setDateConsultation(\DateTimeInterface $date): self { $this->dateConsultation = $date; return $this; }

    public function getDiagnostic(): ?string { return $this->diagnostic; }
    public function setDiagnostic(?string $diagnostic): self { $this->diagnostic = $diagnostic; return $this; }

    public function getPrescription(): ?string { return $this->prescription; }
    public function setPrescription(?string $prescription): self { $this->prescription = $prescription; return $this; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): self { $this->notes = $notes; return $this; }
}
