<?php

namespace App\DTO\Clinique;

use App\Entity\CertificatAptitude;
use Symfony\Component\Validator\Constraints as Assert;

final class UpsertAptitudeInput
{
    public function __construct(
        #[Assert\NotNull(message: 'Le service est obligatoire.')]
        #[Assert\Positive(message: 'Le service est obligatoire.')]
        public ?int $serviceId = null,

        #[Assert\Length(max: 36)]
        public ?string $patientId = null,

        #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
        #[Assert\Length(max: 50)]
        public string $nom = '',

        #[Assert\NotBlank(message: 'Le postnom est obligatoire.')]
        #[Assert\Length(max: 50)]
        public string $postNom = '',

        #[Assert\Length(max: 50)]
        public ?string $prenom = null,

        #[Assert\NotBlank(message: 'Le sexe est obligatoire.')]
        #[Assert\Choice(choices: [CertificatAptitude::SEXE_MASCULIN, CertificatAptitude::SEXE_FEMININ], message: 'Sexe invalide.')]
        public string $sexe = CertificatAptitude::SEXE_MASCULIN,

        #[Assert\Length(max: 50)]
        public ?string $etatCivil = null,

        public ?string $dateNaissance = null,

        #[Assert\Length(max: 80)]
        public ?string $lieuNaissance = null,

        #[Assert\Length(max: 150)]
        public ?string $adresse = null,

        #[Assert\NotBlank(message: 'Le motif est obligatoire.')]
        #[Assert\Choice(choices: [
            CertificatAptitude::MOTIF_ADMISSION_UKV,
            CertificatAptitude::MOTIF_EMPLOI,
            CertificatAptitude::MOTIF_AUTRE,
        ], message: 'Motif invalide.')]
        public string $motif = CertificatAptitude::MOTIF_ADMISSION_UKV,

        #[Assert\Length(max: 150)]
        public ?string $motifAutre = null,

        public mixed $poidsKg = null,

        public mixed $tailleM = null,

        public mixed $perimetreThoraciqueCm = null,

        public mixed $p1 = null,

        public mixed $p2 = null,

        public mixed $p3 = null,

        public ?string $imcClasse = null,

        public ?string $verdict = null,
    ) {
        if ('' === $this->patientId) {
            $this->patientId = null;
        }
        if ('' === $this->prenom) {
            $this->prenom = null;
        }
        if ('' === $this->etatCivil) {
            $this->etatCivil = null;
        }
        if ('' === $this->dateNaissance) {
            $this->dateNaissance = null;
        }
        if ('' === $this->lieuNaissance) {
            $this->lieuNaissance = null;
        }
        if ('' === $this->adresse) {
            $this->adresse = null;
        }
        if ('' === $this->motifAutre) {
            $this->motifAutre = null;
        }
        if ('' === $this->imcClasse) {
            $this->imcClasse = null;
        }
        if ('' === $this->verdict) {
            $this->verdict = null;
        }
    }
}
