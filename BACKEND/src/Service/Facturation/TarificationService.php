<?php

namespace App\Service\Facturation;

use App\Entity\ActeFinancier;
use App\Entity\CategorieTarifaire;
use App\Entity\Patient;
use App\Entity\Service;
use App\Entity\Visite;
use App\Repository\ActeFinancierRepository;
use App\Util\CalendarDate;

/**
 * Copie la catégorie patient sur la visite et lit la colonne de tarif importée.
 */
final class TarificationService
{
    private const SERVICE_PREFIXES = [
        'CHIRURGIE' => 'CHI',
        'GYNECO-OBSTETRIQUE' => 'GYN',
        'MEDECINE INTERNE' => 'MED',
        'PEDIATRIE' => 'PED',
        'URGENCES & REANIMATION' => 'URG',
        'NURSING' => 'NUR',
        'LABORATOIRE' => 'LAB',
        'IMAGERIE' => 'IMG',
    ];

    private const SERVICE_ALIASES = [
        'CHIRURGIE' => ['chir'],
        'GYNECO-OBSTETRIQUE' => ['gyn', 'obstet'],
        'MEDECINE INTERNE' => ['interne', 'medecine', 'médecine'],
        'PEDIATRIE' => ['ped', 'pédiat', 'pedia'],
        'URGENCES & REANIMATION' => ['urg', 'rea', 'réa', 'reanim'],
        'NURSING' => ['nurs'],
        'LABORATOIRE' => ['labo', 'laborat'],
        'IMAGERIE' => ['imag', 'radio', 'echo', 'écho'],
    ];

    public function __construct(
        private readonly ActeFinancierRepository $acteFinancierRepository,
    ) {
    }

    public function snapshotPatientOntoVisite(Visite $visite, ?Patient $patient): void
    {
        if (null === $patient) {
            return;
        }

        $categorie = CategorieTarifaire::isValid($patient->getCategorieTarifaire())
            ? CategorieTarifaire::normalize((string) $patient->getCategorieTarifaire())
            : CategorieTarifaire::A;

        $structure = $patient->getStructure();
        $visite
            ->setCategorieTarifaire($categorie)
            ->setNumeroAffiliation($patient->getNumeroAffiliation())
            ->setStructure($structure)
            ->setStructureLibelle($structure?->getLibelle());
    }

    public function resolveConsultationActe(Visite $visite): ?ActeFinancier
    {
        $serviceGrille = $this->mapOrganisationService($visite->getService());
        if (null !== $serviceGrille) {
            $libelle = $this->isNight($visite->getEnterAt())
                ? ActeFinancier::LIBELLE_CONSULTATION_NUIT
                : ActeFinancier::LIBELLE_CONSULTATION_JOUR;
            $acte = $this->acteFinancierRepository->findByServiceAndLibelle($serviceGrille, $libelle);
            if (null !== $acte) {
                return $acte;
            }
        }

        return $this->acteFinancierRepository->findOneBy([
            'code' => ActeFinancier::CODE_CONSULTATION,
            'statut' => ActeFinancier::STATUT_ACTIF,
        ]);
    }

    public function prefixForService(string $serviceGrille): string
    {
        return self::SERVICE_PREFIXES[$serviceGrille] ?? 'ACT';
    }

    /**
     * @return list<string>
     */
    public static function serviceGrilles(): array
    {
        return array_keys(self::SERVICE_PREFIXES);
    }

    public function mapOrganisationService(?Service $service): ?string
    {
        if (null === $service) {
            return null;
        }

        $haystack = $this->fold((string) $service->getCode() . ' ' . (string) $service->getLibelle());
        foreach (self::SERVICE_ALIASES as $grille => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($haystack, $this->fold($needle))) {
                    return $grille;
                }
            }
        }

        return null;
    }

    private function isNight(?\DateTimeInterface $at): bool
    {
        $date = $at instanceof \DateTimeInterface
            ? \DateTimeImmutable::createFromInterface($at)
            : new \DateTimeImmutable('now', new \DateTimeZone(CalendarDate::TIMEZONE));
        $hour = (int) $date->setTimezone(new \DateTimeZone(CalendarDate::TIMEZONE))->format('G');

        return $hour < 7 || $hour >= 18;
    }

    private function fold(string $value): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (false === $ascii) {
            $ascii = $value;
        }

        return mb_strtolower($ascii);
    }
}
