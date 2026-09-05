<?php

namespace App\Entity;

use App\Repository\DiagnosticRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DiagnosticRepository::class)]
class Diagnostic
{
    public const TYPE_PROVISOIRE = 'PROVISOIRE';
    public const TYPE_DEFINITIF = 'DEFINITIF';
    public const TYPE_DIFFERENTIEL = 'DIFFERENTIEL';

    public const CERTITUDE_SUSPECTE = 'SUSPECTE';
    public const CERTITUDE_PROBABLE = 'PROBABLE';
    public const CERTITUDE_CONFIRMEE = 'CONFIRMEE';

    public const STADE_NON_RENSEIGNE = 'NON_RENSEIGNE';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $type = null;

    #[ORM\Column(length: 20)]
    private ?string $certitude = null;

    #[ORM\Column(length: 20)]
    private ?string $stadeEvolution = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $remarque = null;

    #[ORM\ManyToOne(inversedBy: 'diagnostics')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Consultation $consultation = null;

    #[ORM\ManyToOne(inversedBy: 'diagnostics')]
    private ?DemandeExamen $demandeExamen = null;

    #[ORM\ManyToOne(inversedBy: 'diagnostics')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Maladie $maladie = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getCertitude(): ?string
    {
        return $this->certitude;
    }

    public function setCertitude(string $certitude): static
    {
        $this->certitude = $certitude;

        return $this;
    }

    public function getStadeEvolution(): ?string
    {
        return $this->stadeEvolution;
    }

    public function setStadeEvolution(string $stadeEvolution): static
    {
        $this->stadeEvolution = $stadeEvolution;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getRemarque(): ?string
    {
        return $this->remarque;
    }

    public function setRemarque(?string $remarque): static
    {
        $this->remarque = $remarque;

        return $this;
    }

    public function getConsultation(): ?Consultation
    {
        return $this->consultation;
    }

    public function setConsultation(?Consultation $consultation): static
    {
        $this->consultation = $consultation;

        return $this;
    }

    public function getDemandeExamen(): ?DemandeExamen
    {
        return $this->demandeExamen;
    }

    public function setDemandeExamen(?DemandeExamen $demandeExamen): static
    {
        $this->demandeExamen = $demandeExamen;

        return $this;
    }

    public function getMaladie(): ?Maladie
    {
        return $this->maladie;
    }

    public function setMaladie(?Maladie $maladie): static
    {
        $this->maladie = $maladie;

        return $this;
    }

    /** @return list<string> */
    public static function getTypes(): array
    {
        return [
            self::TYPE_PROVISOIRE,
            self::TYPE_DEFINITIF,
            self::TYPE_DIFFERENTIEL,
        ];
    }

    /** @return list<string> */
    public static function getCertitudes(): array
    {
        return [
            self::CERTITUDE_SUSPECTE,
            self::CERTITUDE_PROBABLE,
            self::CERTITUDE_CONFIRMEE,
        ];
    }

    public static function isValidType(?string $type): bool
    {
        return null === $type || in_array($type, self::getTypes(), true);
    }

    public static function isValidCertitude(?string $certitude): bool
    {
        return null !== $certitude && in_array($certitude, self::getCertitudes(), true);
    }

    public static function normalizeType(?string $type): ?string
    {
        if (null === $type || '' === trim($type)) {
            return self::TYPE_PROVISOIRE;
        }

        return strtoupper(trim($type));
    }

    public static function normalizeCertitude(?string $certitude): string
    {
        if (null === $certitude || '' === trim($certitude)) {
            return self::CERTITUDE_SUSPECTE;
        }

        return strtoupper(trim($certitude));
    }

    public static function normalizeStadeEvolution(?string $stadeEvolution): string
    {
        if (null === $stadeEvolution || '' === trim($stadeEvolution)) {
            return self::STADE_NON_RENSEIGNE;
        }

        return strtoupper(trim($stadeEvolution));
    }
}
