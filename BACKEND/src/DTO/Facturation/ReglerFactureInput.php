<?php

namespace App\DTO\Facturation;

use App\Entity\FactureReglement;
use App\Util\CalendarDate;
use Symfony\Component\Validator\Constraints as Assert;

final class ReglerFactureInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'Le montant du règlement est obligatoire.')]
        public string $montant = '',

        #[Assert\NotBlank(message: 'Le mode de règlement est obligatoire.')]
        #[Assert\Choice(choices: FactureReglement::MODES, message: 'Mode de règlement invalide.')]
        public string $mode = FactureReglement::MODE_ESPECES,

        public string $dateReglement = '',

        #[Assert\Length(max: 255)]
        public ?string $notes = null,
    ) {
        $this->mode = strtoupper(trim($this->mode));
        $normalized = CalendarDate::toDateOnly($this->dateReglement);
        $this->dateReglement = $normalized ?? $this->dateReglement;
    }
}
