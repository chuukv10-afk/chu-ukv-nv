<?php

namespace App\DTO\Imagerie;

use App\Entity\EtudeImagerie;
use Symfony\Component\Validator\Constraints as Assert;

final class CreateEtudeImagerieInput
{
    public function __construct(
        public ?string $patientId = null,

        #[Assert\Positive]
        public ?int $examenId = null,

        public ?int $demandeExamenId = null,

        #[Assert\Length(max: 4000)]
        public ?string $indication = null,

        #[Assert\Length(max: 255)]
        public ?string $but = null,

        #[Assert\Choice(choices: [EtudeImagerie::SOURCE_INTERNE, EtudeImagerie::SOURCE_EXTERNE])]
        public string $source = EtudeImagerie::SOURCE_INTERNE,

        #[Assert\Length(max: 255)]
        public ?string $etablissement = null,

        public ?string $demandeParId = null,

        #[Assert\Length(max: 255)]
        public ?string $demandeParNom = null,
    ) {
        if ('' === $this->patientId) {
            $this->patientId = null;
        }
        if ('' === $this->indication) {
            $this->indication = null;
        }
        if ('' === $this->but) {
            $this->but = null;
        }
        if ('' === $this->etablissement) {
            $this->etablissement = null;
        }
        if ('' === $this->demandeParId) {
            $this->demandeParId = null;
        }
        if ('' === $this->demandeParNom) {
            $this->demandeParNom = null;
        }
        $this->source = strtoupper(trim($this->source));
    }
}
