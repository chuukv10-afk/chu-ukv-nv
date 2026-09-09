<?php

namespace App\Service\Pharmacie;

use App\DTO\Pharmacie\ReceptionLigneInput;
use App\DTO\Pharmacie\UpsertReceptionInput;
use App\Entity\FamilleMedicament;
use App\Entity\Fournisseur;
use App\Entity\Lot;
use App\Entity\Medicament;
use App\Entity\UniteMedicament;
use App\Exception\ConflictException;
use App\Repository\FamilleMedicamentRepository;
use App\Repository\FournisseurRepository;
use App\Repository\LotRepository;
use App\Repository\MedicamentRepository;
use App\Repository\ReceptionRepository;
use App\Repository\UniteMedicamentRepository;
use Doctrine\ORM\EntityManagerInterface;

final class ImportLegacyPharmacyService
{
    public const FOURNISSEUR_CODE = 'REPRISE';
    public const FAMILLE_SANS_CODE = 'SANS';
    public const REF_VENDABLE = 'REPRISE-SIGAI-2026-09-09';
    public const REF_PERIME = 'REPRISE-SIGAI-PERIME';
    public const DEFAULT_PEREMPTION = '2028-12-31';
    public const DATE_RECEPTION = '2026-09-08';
    public const DATE_RECEPTION_PERIME = '2022-01-01';

    public function __construct(
        private readonly LegacySqlDumpParser $parser,
        private readonly EntityManagerInterface $entityManager,
        private readonly UniteMedicamentRepository $uniteRepository,
        private readonly FamilleMedicamentRepository $familleRepository,
        private readonly FournisseurRepository $fournisseurRepository,
        private readonly MedicamentRepository $medicamentRepository,
        private readonly LotRepository $lotRepository,
        private readonly ReceptionRepository $receptionRepository,
        private readonly ReceptionService $receptionService,
        private readonly StockService $stockService,
    ) {
    }

    /**
     * @return array{
     *     unites: int,
     *     familles: int,
     *     fournisseurs: int,
     *     medicaments: int,
     *     lotsVendables: int,
     *     lotsPerimes: int,
     *     sansStock: int,
     *     skippedLots: int,
     *     warnings: list<string>
     * }
     */
    public function preview(string $sql): array
    {
        return $this->run($sql, true);
    }

    /**
     * @return array{
     *     unites: int,
     *     familles: int,
     *     fournisseurs: int,
     *     medicaments: int,
     *     lotsVendables: int,
     *     lotsPerimes: int,
     *     sansStock: int,
     *     skippedLots: int,
     *     warnings: list<string>
     * }
     */
    public function import(string $sql): array
    {
        return $this->run($sql, false);
    }

    /**
     * @return array<string, mixed>
     */
    private function run(string $sql, bool $dryRun): array
    {
        $units = $this->parser->parseTable($sql, 'pharmacy_unit');
        $categories = $this->parser->parseTable($sql, 'pharmacy_category');
        $suppliers = $this->parser->parseTable($sql, 'pharmacy_supplier');
        $medicaments = $this->parser->parseTable($sql, 'pharmacy_medicament');

        if ([] === $medicaments) {
            throw new ConflictException('Aucun médicament trouvé dans le dump SIGAI.');
        }

        $existingVendable = $this->receptionRepository->findOneBy(['referenceExterne' => self::REF_VENDABLE]);
        $existingPerime = $this->receptionRepository->findOneBy(['referenceExterne' => self::REF_PERIME]);
        if (null !== $existingVendable && null !== $existingPerime && !$dryRun) {
            throw new ConflictException('Une reprise SIGAI existe déjà (réceptions vendable et périmée). Import déjà fait.');
        }

        $warnings = [];
        $counts = [
            'unites' => 0,
            'familles' => 0,
            'fournisseurs' => 0,
            'medicaments' => 0,
            'lotsVendables' => 0,
            'lotsPerimes' => 0,
            'sansStock' => 0,
            'skippedLots' => 0,
            'warnings' => [],
        ];

        $uniteByCode = [];
        foreach ($units as $row) {
            $code = $this->normalizeCode((string) ($row['code'] ?? ''), 15);
            if ('' === $code) {
                continue;
            }
            $unite = $this->uniteRepository->findOneBy(['code' => $code]);
            if (null === $unite) {
                ++$counts['unites'];
                if (!$dryRun) {
                    $unite = (new UniteMedicament())
                        ->setCode($code)
                        ->setLibelle($this->clip((string) ($row['label'] ?: $code), 100))
                        ->setOrdre((int) ($row['id'] ?? 0))
                        ->setStatut(!empty($row['is_active']) ? UniteMedicament::STATUT_ACTIF : UniteMedicament::STATUT_INACTIF)
                        ->setCreatedAt(new \DateTimeImmutable());
                    $this->entityManager->persist($unite);
                }
            }
            $uniteByCode[$code] = $unite;
        }

        $familleByOldId = [];
        foreach ($categories as $row) {
            $code = $this->normalizeCode((string) ($row['code'] ?? ''), 15);
            if ('' === $code) {
                continue;
            }
            $famille = $this->familleRepository->findOneBy(['code' => $code]);
            if (null === $famille) {
                ++$counts['familles'];
                if (!$dryRun) {
                    $famille = (new FamilleMedicament())
                        ->setCode($code)
                        ->setLibelle($this->clip((string) ($row['name'] ?: $code), 100))
                        ->setOrdre((int) ($row['id'] ?? 0))
                        ->setStatut(!empty($row['is_active']) ? FamilleMedicament::STATUT_ACTIF : FamilleMedicament::STATUT_INACTIF)
                        ->setCreatedAt(new \DateTimeImmutable());
                    $this->entityManager->persist($famille);
                }
            }
            $familleByOldId[(int) $row['id']] = $famille;
        }

        $familleSans = $this->familleRepository->findOneBy(['code' => self::FAMILLE_SANS_CODE]);
        if (null === $familleSans) {
            ++$counts['familles'];
            if (!$dryRun) {
                $familleSans = (new FamilleMedicament())
                    ->setCode(self::FAMILLE_SANS_CODE)
                    ->setLibelle('Sans famille')
                    ->setOrdre(999)
                    ->setStatut(FamilleMedicament::STATUT_ACTIF)
                    ->setCreatedAt(new \DateTimeImmutable());
                $this->entityManager->persist($familleSans);
            }
        }

        $reprise = $this->fournisseurRepository->findOneBy(['code' => self::FOURNISSEUR_CODE]);
        if (null === $reprise) {
            ++$counts['fournisseurs'];
            if (!$dryRun) {
                $reprise = (new Fournisseur())
                    ->setCode(self::FOURNISSEUR_CODE)
                    ->setLibelle('Reprise inventaire SIGAI')
                    ->setStatut(Fournisseur::STATUT_ACTIF)
                    ->setCreatedAt(new \DateTimeImmutable());
                $this->entityManager->persist($reprise);
            }
        }

        foreach ($suppliers as $row) {
            $code = $this->normalizeCode('FRN' . (int) $row['id'], 15);
            if (null !== $this->fournisseurRepository->findOneBy(['code' => $code])) {
                continue;
            }
            ++$counts['fournisseurs'];
            if (!$dryRun) {
                $this->entityManager->persist(
                    (new Fournisseur())
                        ->setCode($code)
                        ->setLibelle($this->clip((string) ($row['name'] ?: $code), 150))
                        ->setTelephone($this->clip((string) ($row['phone'] ?? ''), 20) ?: null)
                        ->setAdresse($this->clip((string) ($row['address'] ?? ''), 200) ?: null)
                        ->setStatut(!empty($row['is_active']) ? Fournisseur::STATUT_ACTIF : Fournisseur::STATUT_INACTIF)
                        ->setCreatedAt(new \DateTimeImmutable()),
                );
            }
        }

        if (!$dryRun) {
            $this->entityManager->flush();
            $familleSans = $this->familleRepository->findOneBy(['code' => self::FAMILLE_SANS_CODE]);
            $reprise = $this->fournisseurRepository->findOneBy(['code' => self::FOURNISSEUR_CODE]);
            foreach ($units as $row) {
                $code = $this->normalizeCode((string) ($row['code'] ?? ''), 15);
                if ('' !== $code) {
                    $uniteByCode[$code] = $this->uniteRepository->findOneBy(['code' => $code]);
                }
            }
            foreach ($categories as $row) {
                $code = $this->normalizeCode((string) ($row['code'] ?? ''), 15);
                if ('' !== $code) {
                    $familleByOldId[(int) $row['id']] = $this->familleRepository->findOneBy(['code' => $code]);
                }
            }
        }

        $lignesVendables = [];
        $lignesPerimes = [];
        $today = $this->stockService->today();

        foreach ($medicaments as $row) {
            $oldId = (int) $row['id'];
            $code = $this->medicamentCode($oldId);
            $libelle = $this->clip(trim((string) ($row['name'] ?? '')), 150);
            if ('' === $libelle) {
                $warnings[] = sprintf('Ligne SIGAI #%d ignorée (libellé vide).', $oldId);
                continue;
            }

            $unitCode = $this->normalizeCode((string) ($row['unit'] ?? 'PCS'), 15) ?: 'PCS';
            $unite = $uniteByCode[$unitCode] ?? $this->uniteRepository->findOneBy(['code' => $unitCode]);
            if (null === $unite && !$dryRun) {
                $unite = (new UniteMedicament())
                    ->setCode($unitCode)
                    ->setLibelle($unitCode)
                    ->setOrdre(500)
                    ->setStatut(UniteMedicament::STATUT_ACTIF)
                    ->setCreatedAt(new \DateTimeImmutable());
                $this->entityManager->persist($unite);
                $this->entityManager->flush();
                $uniteByCode[$unitCode] = $unite;
                ++$counts['unites'];
            }

            $familleId = $row['category_entity_id'] ?? null;
            $famille = is_numeric($familleId) ? ($familleByOldId[(int) $familleId] ?? $familleSans) : $familleSans;
            if (null === $famille) {
                $warnings[] = sprintf('%s : famille absente, classé « Sans famille ».', $libelle);
            }

            $medicament = $this->medicamentRepository->findOneBy(['code' => $code]);
            if (null === $medicament) {
                ++$counts['medicaments'];
                if (!$dryRun) {
                    $prixVente = $this->prix((string) ($row['selling_price'] ?? '0'));
                    $medicament = (new Medicament())
                        ->setCode($code)
                        ->setLibelle($libelle)
                        ->setDci($this->nullableString($row['generic_name'] ?? null, 150))
                        ->setForme($this->nullableString($row['form'] ?? null, 80))
                        ->setDosage($this->nullableString($row['dosage'] ?? null, 50))
                        ->setUnite($unite)
                        ->setFamille($famille ?? $familleSans)
                        ->setPrixVente($prixVente)
                        ->setSeuilAlerte(max(0, (int) ($row['stock_min'] ?? 0)))
                        ->setStatut(!empty($row['is_active']) ? Medicament::STATUT_ACTIF : Medicament::STATUT_INACTIF)
                        ->setCreatedAt(new \DateTimeImmutable());
                    $this->entityManager->persist($medicament);
                }
            }

            $qty = (int) ($row['current_stock'] ?? 0);
            if ($qty <= 0) {
                ++$counts['sansStock'];
                continue;
            }

            $numeroLot = $this->lotNumero($oldId);
            $existingLot = $medicament instanceof Medicament
                ? $this->lotRepository->findOneByMedicamentAndNumero($medicament, $numeroLot)
                : null;
            if ($existingLot instanceof Lot) {
                ++$counts['skippedLots'];
                continue;
            }

            $expiryRaw = $row['expiration_date'] ?? null;
            $expiry = $this->normalizeExpiry($expiryRaw);
            if (null === $expiry) {
                $expiry = self::DEFAULT_PEREMPTION;
                $warnings[] = sprintf('%s : péremption absente, lot au %s.', $libelle, self::DEFAULT_PEREMPTION);
            }
            $prixAchat = $this->prix((string) ($row['reference_price'] ?? '0'));
            if ('0.0000' === $prixAchat) {
                $warnings[] = sprintf('%s : prix d\'achat à 0.', $libelle);
            }

            $ligne = [
                'oldId' => $oldId,
                'libelle' => $libelle,
                'numeroLot' => $numeroLot,
                'datePeremption' => $expiry,
                'quantite' => $qty,
                'prixAchatUnitaire' => $prixAchat,
                'prixVente' => $this->prix((string) ($row['selling_price'] ?? '0')),
            ];

            $expiryDate = \DateTimeImmutable::createFromFormat('Y-m-d', $expiry) ?: $today;
            if ($expiryDate < $today) {
                ++$counts['lotsPerimes'];
                $lignesPerimes[] = $ligne;
            } else {
                ++$counts['lotsVendables'];
                $lignesVendables[] = $ligne;
            }
        }

        if (!$dryRun) {
            $this->entityManager->flush();
            if (null === $reprise) {
                throw new ConflictException('Fournisseur de reprise introuvable après création.');
            }
            $this->creerReception(
                self::REF_VENDABLE,
                $this->dateReceptionSafe(self::DATE_RECEPTION, $lignesVendables),
                $reprise,
                $lignesVendables,
            );
            $this->creerReception(
                self::REF_PERIME,
                $this->dateReceptionSafe(self::DATE_RECEPTION_PERIME, $lignesPerimes),
                $reprise,
                $lignesPerimes,
            );
        }

        $counts['warnings'] = $warnings;

        return $counts;
    }

    /**
     * @param list<array<string, mixed>> $lignes
     */
    private function creerReception(string $reference, string $dateReception, Fournisseur $fournisseur, array $lignes): void
    {
        if ([] === $lignes) {
            return;
        }
        if (null !== $this->receptionRepository->findOneBy(['referenceExterne' => $reference])) {
            return;
        }

        $inputs = [];
        foreach ($lignes as $ligne) {
            $medicament = $this->medicamentRepository->findOneBy(['code' => $this->medicamentCode((int) $ligne['oldId'])]);
            if (null === $medicament) {
                continue;
            }
            $inputs[] = new ReceptionLigneInput(
                (int) $medicament->getId(),
                (string) $ligne['numeroLot'],
                (string) $ligne['datePeremption'],
                (int) $ligne['quantite'],
                (string) $ligne['prixAchatUnitaire'],
                (string) $ligne['prixVente'],
            );
        }
        if ([] === $inputs) {
            return;
        }

        $reception = $this->receptionService->create(new UpsertReceptionInput(
            (int) $fournisseur->getId(),
            $dateReception,
            $reference,
            $inputs,
        ));
        $this->receptionService->valider((int) $reception->getId());
    }

    /**
     * @param list<array<string, mixed>> $lignes
     */
    private function dateReceptionSafe(string $fallback, array $lignes): string
    {
        $fallbackDate = \DateTimeImmutable::createFromFormat('Y-m-d', $fallback) ?: $this->stockService->today();
        $fallbackDate = $fallbackDate->setTime(0, 0);
        $minExpiry = null;
        foreach ($lignes as $ligne) {
            $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', substr((string) $ligne['datePeremption'], 0, 10));
            if (false === $parsed) {
                continue;
            }
            $parsed = $parsed->setTime(0, 0);
            if (null === $minExpiry || $parsed < $minExpiry) {
                $minExpiry = $parsed;
            }
        }
        if (null !== $minExpiry && $minExpiry <= $fallbackDate) {
            return $minExpiry->modify('-1 day')->format('Y-m-d');
        }

        return $fallbackDate->format('Y-m-d');
    }

    private function normalizeExpiry(mixed $value): ?string
    {
        if (!is_string($value) && !is_numeric($value)) {
            return null;
        }
        $raw = substr(trim((string) $value), 0, 10);
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $raw);
        if (false === $date) {
            return null;
        }

        return $date->format('Y-m-d');
    }

    private function medicamentCode(int $oldId): string
    {
        return 'LEG-' . $oldId;
    }

    private function lotNumero(int $oldId): string
    {
        return 'INIT-' . $oldId;
    }

    private function normalizeCode(string $value, int $max): string
    {
        $code = strtoupper(trim($value));
        $code = preg_replace('/[^A-Z0-9_-]/', '', $code) ?? '';

        return substr($code, 0, $max);
    }

    private function clip(string $value, int $max): string
    {
        $trimmed = trim($value);

        return mb_substr($trimmed, 0, $max);
    }

    private function nullableString(mixed $value, int $max): ?string
    {
        $text = trim((string) $value);

        return '' === $text ? null : $this->clip($text, $max);
    }

    private function prix(string $value): string
    {
        $raw = trim($value);
        if ('' === $raw || !is_numeric($raw)) {
            return '0.0000';
        }

        return $this->stockService->normalizePrix($raw);
    }
}
