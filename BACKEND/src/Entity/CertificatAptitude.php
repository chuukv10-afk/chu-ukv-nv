<?php

namespace App\Entity;

use App\Entity\Contract\BlameableInterface;
use App\Entity\Trait\BlameableTrait;
use App\Repository\CertificatAptitudeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CertificatAptitudeRepository::class)]
#[ORM\Table(name: 'certificat_aptitude')]
#[ORM\UniqueConstraint(name: 'UNIQ_CAP_NUMERO', columns: ['numero'])]
class CertificatAptitude implements BlameableInterface
{
    use BlameableTrait;

    public const STATUT_BROUILLON = 'BROUILLON';
    public const STATUT_SIGNE = 'SIGNE';
    public const STATUT_ANNULE = 'ANNULE';

    public const MOTIF_ADMISSION_UKV = 'ADMISSION_UKV';
    public const MOTIF_EMPLOI = 'EMPLOI';
    public const MOTIF_AUTRE = 'AUTRE';

    public const VERDICT_APTE = 'APTE';
    public const VERDICT_INAPTE = 'INAPTE';

    public const IMC_MAIGREUR = 'MAIGREUR';
    public const IMC_NORMAL = 'NORMAL';
    public const IMC_SURPOIDS = 'SURPOIDS';
    public const IMC_OBESITE_I = 'OBESITE_I';
    public const IMC_OBESITE_II = 'OBESITE_II';
    public const IMC_OBESITE_III = 'OBESITE_III';

    public const PIGNET_TRES_FORTE = 'TRES_FORTE';
    public const PIGNET_FORTE = 'FORTE';
    public const PIGNET_BONNE = 'BONNE';
    public const PIGNET_MOYENNE = 'MOYENNE';
    public const PIGNET_FAIBLE = 'FAIBLE';
    public const PIGNET_TRES_FAIBLE = 'TRES_FAIBLE';
    public const PIGNET_EXTREME = 'EXTREME';

    public const RUFFIER_EXCELLENTE = 'EXCELLENTE';
    public const RUFFIER_BONNE = 'BONNE';
    public const RUFFIER_MOYENNE = 'MOYENNE';
    public const RUFFIER_INSUFFISANTE = 'INSUFFISANTE';
    public const RUFFIER_MAUVAISE = 'MAUVAISE';

    public const DICKSON_EXCELLENT = 'EXCELLENT';
    public const DICKSON_TRES_BON = 'TRES_BON';
    public const DICKSON_BON = 'BON';
    public const DICKSON_MOYEN = 'MOYEN';
    public const DICKSON_FAIBLE = 'FAIBLE';
    public const DICKSON_MAUVAIS = 'MAUVAIS';

    public const SEXE_MASCULIN = 'M';
    public const SEXE_FEMININ = 'F';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $numero = null;

    #[ORM\Column]
    private int $annee = 0;

    #[ORM\Column(length: 20)]
    private string $statut = self::STATUT_BROUILLON;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Service $service = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Patient $patient = null;

    #[ORM\Column(length: 50)]
    private string $nom = '';

    #[ORM\Column(length: 50)]
    private string $postNom = '';

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $prenom = null;

    #[ORM\Column(length: 1)]
    private string $sexe = self::SEXE_MASCULIN;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $etatCivil = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dateNaissance = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $lieuNaissance = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $adresse = null;

    #[ORM\Column(length: 20)]
    private string $motif = self::MOTIF_ADMISSION_UKV;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $motifAutre = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 6, scale: 2, nullable: true)]
    private ?string $poidsKg = null;

    #[ORM\Column(name: 'taille_m', type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $tailleM = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 6, scale: 2, nullable: true)]
    private ?string $perimetreThoraciqueCm = null;

    #[ORM\Column(nullable: true)]
    private ?int $p1 = null;

    #[ORM\Column(nullable: true)]
    private ?int $p2 = null;

    #[ORM\Column(nullable: true)]
    private ?int $p3 = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 6, scale: 2, nullable: true)]
    private ?string $imc = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $imcClasse = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2, nullable: true)]
    private ?string $pignet = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $pignetRobustesse = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2, nullable: true)]
    private ?string $ruffier = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2, nullable: true)]
    private ?string $dickson = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $ruffierClasse = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $dicksonClasse = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $verdictPropose = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $verdict = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $signeAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $valideJusqua = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Personnel $signePar = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $annuleAt = null;

    /**
     * @return list<string>
     */
    public static function getStatuts(): array
    {
        return [self::STATUT_BROUILLON, self::STATUT_SIGNE, self::STATUT_ANNULE];
    }

    /**
     * @return list<string>
     */
    public static function getMotifs(): array
    {
        return [self::MOTIF_ADMISSION_UKV, self::MOTIF_EMPLOI, self::MOTIF_AUTRE];
    }

    /**
     * @return list<string>
     */
    public static function getVerdicts(): array
    {
        return [self::VERDICT_APTE, self::VERDICT_INAPTE];
    }

    /**
     * @return list<string>
     */
    public static function getImcClasses(): array
    {
        return [
            self::IMC_MAIGREUR,
            self::IMC_NORMAL,
            self::IMC_SURPOIDS,
            self::IMC_OBESITE_I,
            self::IMC_OBESITE_II,
            self::IMC_OBESITE_III,
        ];
    }

    /**
     * @return list<string>
     */
    public static function getPignetClasses(): array
    {
        return [
            self::PIGNET_TRES_FORTE,
            self::PIGNET_FORTE,
            self::PIGNET_BONNE,
            self::PIGNET_MOYENNE,
            self::PIGNET_FAIBLE,
            self::PIGNET_TRES_FAIBLE,
            self::PIGNET_EXTREME,
        ];
    }

    /**
     * @return list<string>
     */
    public static function getRuffierClasses(): array
    {
        return [
            self::RUFFIER_EXCELLENTE,
            self::RUFFIER_BONNE,
            self::RUFFIER_MOYENNE,
            self::RUFFIER_INSUFFISANTE,
            self::RUFFIER_MAUVAISE,
        ];
    }

    /**
     * @return list<string>
     */
    public static function getDicksonClasses(): array
    {
        return [
            self::DICKSON_EXCELLENT,
            self::DICKSON_TRES_BON,
            self::DICKSON_BON,
            self::DICKSON_MOYEN,
            self::DICKSON_FAIBLE,
            self::DICKSON_MAUVAIS,
        ];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumero(): ?string
    {
        return $this->numero;
    }

    public function setNumero(?string $numero): static
    {
        $this->numero = $numero;

        return $this;
    }

    public function getAnnee(): int
    {
        return $this->annee;
    }

    public function setAnnee(int $annee): static
    {
        $this->annee = $annee;

        return $this;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function isBrouillon(): bool
    {
        return self::STATUT_BROUILLON === $this->statut;
    }

    public function isSigne(): bool
    {
        return self::STATUT_SIGNE === $this->statut;
    }

    public function getService(): ?Service
    {
        return $this->service;
    }

    public function setService(?Service $service): static
    {
        $this->service = $service;

        return $this;
    }

    public function getPatient(): ?Patient
    {
        return $this->patient;
    }

    public function setPatient(?Patient $patient): static
    {
        $this->patient = $patient;

        return $this;
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getPostNom(): string
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

    public function getFullName(): string
    {
        return trim(sprintf('%s %s %s', $this->nom, $this->postNom, $this->prenom ?? ''));
    }

    public function getSexe(): string
    {
        return $this->sexe;
    }

    public function setSexe(string $sexe): static
    {
        $this->sexe = $sexe;

        return $this;
    }

    public function getEtatCivil(): ?string
    {
        return $this->etatCivil;
    }

    public function setEtatCivil(?string $etatCivil): static
    {
        $this->etatCivil = $etatCivil;

        return $this;
    }

    public function getDateNaissance(): ?\DateTimeImmutable
    {
        return $this->dateNaissance;
    }

    public function setDateNaissance(?\DateTimeImmutable $dateNaissance): static
    {
        $this->dateNaissance = $dateNaissance;

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

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(?string $adresse): static
    {
        $this->adresse = $adresse;

        return $this;
    }

    public function getMotif(): string
    {
        return $this->motif;
    }

    public function setMotif(string $motif): static
    {
        $this->motif = $motif;

        return $this;
    }

    public function getMotifAutre(): ?string
    {
        return $this->motifAutre;
    }

    public function setMotifAutre(?string $motifAutre): static
    {
        $this->motifAutre = $motifAutre;

        return $this;
    }

    public function getPoidsKg(): ?string
    {
        return $this->poidsKg;
    }

    public function setPoidsKg(?string $poidsKg): static
    {
        $this->poidsKg = $poidsKg;

        return $this;
    }

    public function getTailleM(): ?string
    {
        return $this->tailleM;
    }

    public function setTailleM(?string $tailleM): static
    {
        $this->tailleM = $tailleM;

        return $this;
    }

    public function getPerimetreThoraciqueCm(): ?string
    {
        return $this->perimetreThoraciqueCm;
    }

    public function setPerimetreThoraciqueCm(?string $perimetreThoraciqueCm): static
    {
        $this->perimetreThoraciqueCm = $perimetreThoraciqueCm;

        return $this;
    }

    public function getP1(): ?int
    {
        return $this->p1;
    }

    public function setP1(?int $p1): static
    {
        $this->p1 = $p1;

        return $this;
    }

    public function getP2(): ?int
    {
        return $this->p2;
    }

    public function setP2(?int $p2): static
    {
        $this->p2 = $p2;

        return $this;
    }

    public function getP3(): ?int
    {
        return $this->p3;
    }

    public function setP3(?int $p3): static
    {
        $this->p3 = $p3;

        return $this;
    }

    public function getImc(): ?string
    {
        return $this->imc;
    }

    public function setImc(?string $imc): static
    {
        $this->imc = $imc;

        return $this;
    }

    public function getImcClasse(): ?string
    {
        return $this->imcClasse;
    }

    public function setImcClasse(?string $imcClasse): static
    {
        $this->imcClasse = $imcClasse;

        return $this;
    }

    public function getPignet(): ?string
    {
        return $this->pignet;
    }

    public function setPignet(?string $pignet): static
    {
        $this->pignet = $pignet;

        return $this;
    }

    public function getPignetRobustesse(): ?string
    {
        return $this->pignetRobustesse;
    }

    public function setPignetRobustesse(?string $pignetRobustesse): static
    {
        $this->pignetRobustesse = $pignetRobustesse;

        return $this;
    }

    public function getRuffier(): ?string
    {
        return $this->ruffier;
    }

    public function setRuffier(?string $ruffier): static
    {
        $this->ruffier = $ruffier;

        return $this;
    }

    public function getDickson(): ?string
    {
        return $this->dickson;
    }

    public function setDickson(?string $dickson): static
    {
        $this->dickson = $dickson;

        return $this;
    }

    public function getRuffierClasse(): ?string
    {
        return $this->ruffierClasse;
    }

    public function setRuffierClasse(?string $ruffierClasse): static
    {
        $this->ruffierClasse = $ruffierClasse;

        return $this;
    }

    public function getDicksonClasse(): ?string
    {
        return $this->dicksonClasse;
    }

    public function setDicksonClasse(?string $dicksonClasse): static
    {
        $this->dicksonClasse = $dicksonClasse;

        return $this;
    }

    public function getVerdictPropose(): ?string
    {
        return $this->verdictPropose;
    }

    public function setVerdictPropose(?string $verdictPropose): static
    {
        $this->verdictPropose = $verdictPropose;

        return $this;
    }

    public function getVerdict(): ?string
    {
        return $this->verdict;
    }

    public function setVerdict(?string $verdict): static
    {
        $this->verdict = $verdict;

        return $this;
    }

    public function getSigneAt(): ?\DateTimeImmutable
    {
        return $this->signeAt;
    }

    public function setSigneAt(?\DateTimeImmutable $signeAt): static
    {
        $this->signeAt = $signeAt;

        return $this;
    }

    public function getValideJusqua(): ?\DateTimeImmutable
    {
        return $this->valideJusqua;
    }

    public function setValideJusqua(?\DateTimeImmutable $valideJusqua): static
    {
        $this->valideJusqua = $valideJusqua;

        return $this;
    }

    public function getSignePar(): ?Personnel
    {
        return $this->signePar;
    }

    public function setSignePar(?Personnel $signePar): static
    {
        $this->signePar = $signePar;

        return $this;
    }

    public function getAnnuleAt(): ?\DateTimeImmutable
    {
        return $this->annuleAt;
    }

    public function setAnnuleAt(?\DateTimeImmutable $annuleAt): static
    {
        $this->annuleAt = $annuleAt;

        return $this;
    }
}
