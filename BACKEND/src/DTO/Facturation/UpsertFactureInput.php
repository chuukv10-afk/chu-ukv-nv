<?php

namespace App\DTO\Facturation;

use App\Entity\CategorieTarifaire;
use App\Util\CalendarDate;
use Symfony\Component\Validator\Constraints as Assert;

final class UpsertFactureInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'Le patient est obligatoire.')]
        #[Assert\Uuid(message: 'Identifiant patient invalide.')]
        public string $patientId = '',

        #[Assert\NotBlank(message: 'La date de facture est obligatoire.')]
        public string $dateFacture = '',

        #[Assert\NotBlank(message: 'La catégorie tarifaire est obligatoire.')]
        #[Assert\Length(max: 8)]
        public string $categorieTarifaire = CategorieTarifaire::A,

        public ?int $structureId = null,

        #[Assert\Length(max: 80)]
        public ?string $numeroAffiliation = null,

        #[Assert\Length(max: 2000)]
        public ?string $notes = null,

        #[Assert\Length(max: 16)]
        public string $remiseType = 'NONE',

        public string $remiseValeur = '0',

        /** @var list<FactureLigneInput> */
        #[Assert\Valid]
        public array $lignes = [],
    ) {
        $this->patientId = trim($this->patientId);
        $normalized = CalendarDate::toDateOnly($this->dateFacture);
        $this->dateFacture = $normalized ?? $this->dateFacture;
        $this->categorieTarifaire = CategorieTarifaire::normalize($this->categorieTarifaire);
        if (0 === $this->structureId) {
            $this->structureId = null;
        }
    }
}
