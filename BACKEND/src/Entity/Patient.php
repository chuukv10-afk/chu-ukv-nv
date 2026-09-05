<?php

namespace App\Entity;

use App\Entity\Contract\BlameableInterface;
use App\Entity\Trait\BlameableTrait;
use App\Entity\Trait\UuidV7PrimaryKeyTrait;
use App\Repository\PatientRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PatientRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Patient implements BlameableInterface
{
    use BlameableTrait;
    use UuidV7PrimaryKeyTrait;

    public const STATUS_ACTIF = 'ACTIF';
    public const STATUS_INACTIF = 'INACTIF';
    public const STATUS_DECEDE = 'DECEDE';

    public const SEXE_MASCULIN = 'M';
    public const SEXE_FEMININ = 'F';

    #[ORM\Column(length: 50)]
    private ?string $nom = null;

    #[ORM\Column(length: 50)]
    private ?string $postNom = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $prenom = null;

    #[ORM\Column(length: 15, nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $adresse = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $lieuNaissance = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $dateNaissance = null;

    #[ORM\Column(length: 1)]
    private ?string $sexe = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $password = null;

    #[ORM\Column(length: 20)]
    private ?string $status = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $groupeSanguin = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $personneAprevenir = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $contactAPrevenir = null;

    #[ORM\OneToOne(mappedBy: 'patient', cascade: ['persist', 'remove'])]
    private ?Dpi $dpi = null;

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getPostNom(): ?string
    {
        return $this->postNom;
    }

    public function setPostNom(string $postNom): static
    {
        $this->postNom = $postNom;

        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(?string $prenom): static
    {
        $this->prenom = $prenom;

        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): static
    {
        $this->telephone = $telephone;

        return $this;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(?string $adresse): static
    {
        $this->adresse = $adresse;

        return $this;
    }

    public function getLieuNaissance(): ?string
    {
        return $this->lieuNaissance;
    }

    public function setLieuNaissance(?string $lieuNaissance): static
    {
        $this->lieuNaissance = $lieuNaissance;

        return $this;
    }

    public function getDateNaissance(): ?\DateTime
    {
        return $this->dateNaissance;
    }

    public function setDateNaissance(\DateTime $dateNaissance): static
    {
        $this->dateNaissance = $dateNaissance;

        return $this;
    }

    public function getSexe(): ?string
    {
        return $this->sexe;
    }

    public function setSexe(string $sexe): static
    {
        $this->sexe = $sexe;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getGroupeSanguin(): ?string
    {
        return $this->groupeSanguin;
    }

    public function setGroupeSanguin(?string $groupeSanguin): static
    {
        $this->groupeSanguin = $groupeSanguin;

        return $this;
    }

    public function getPersonneAprevenir(): ?string
    {
        return $this->personneAprevenir;
    }

    public function setPersonneAprevenir(?string $personneAprevenir): static
    {
        $this->personneAprevenir = $personneAprevenir;

        return $this;
    }

    public function getContactAPrevenir(): ?string
    {
        return $this->contactAPrevenir;
    }

    public function setContactAPrevenir(?string $contactAPrevenir): static
    {
        $this->contactAPrevenir = $contactAPrevenir;

        return $this;
    }

    public function getDpi(): ?Dpi
    {
        return $this->dpi;
    }

    public function setDpi(Dpi $dpi): static
    {
        if ($this->dpi !== $dpi) {
            $this->dpi = $dpi;
            $dpi->setPatient($this);
        }

        return $this;
    }

    /**
     * @return list<string>
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_ACTIF,
            self::STATUS_INACTIF,
            self::STATUS_DECEDE,
        ];
    }

    /**
     * @return list<string>
     */
    public static function getSexes(): array
    {
        return [
            self::SEXE_MASCULIN,
            self::SEXE_FEMININ,
        ];
    }

    public static function normalizeStatus(string $status): string
    {
        return strtoupper(trim($status));
    }

    public static function isValidStatus(?string $status): bool
    {
        if (null === $status || '' === trim($status)) {
            return false;
        }

        return in_array(self::normalizeStatus($status), self::getStatuses(), true);
    }

    public static function isValidSexe(?string $sexe): bool
    {
        if (null === $sexe || '' === trim($sexe)) {
            return false;
        }

        return in_array(strtoupper(trim($sexe)), self::getSexes(), true);
    }

    public function isDeceased(): bool
    {
        return self::STATUS_DECEDE === self::normalizeStatus((string) $this->status);
    }

    public function isClinicallyWritable(): bool
    {
        return self::STATUS_ACTIF === self::normalizeStatus((string) $this->status);
    }

    public function getFullName(): string
    {
        return trim(sprintf('%s %s %s', $this->nom ?? '', $this->postNom ?? '', $this->prenom ?? ''));
    }
}
