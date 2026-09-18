<?php

namespace App\Service\Clinique;

use App\Entity\CategorieTarifaire;
use App\Entity\CertificatAptitude;
use App\Entity\Dpi;
use App\Entity\Filiere;
use App\Entity\OrganisationPartenaire;
use App\Entity\Patient;
use App\Entity\Service;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Repository\CertificatAptitudeRepository;
use App\Repository\DpiRepository;
use App\Repository\FiliereRepository;
use App\Repository\OrganisationPartenaireRepository;
use App\Repository\PatientRepository;
use App\Repository\ServiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AptitudeImportService
{
    private const TIMEZONE = 'Africa/Kinshasa';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PatientRepository $patientRepository,
        private readonly DpiRepository $dpiRepository,
        private readonly FiliereRepository $filiereRepository,
        private readonly OrganisationPartenaireRepository $organisationPartenaireRepository,
        private readonly ServiceRepository $serviceRepository,
        private readonly CertificatAptitudeRepository $certificatRepository,
    ) {
    }

    public function createTemplateResponse(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Etudiants UKV');
        $headers = [
            'Code UKV',
            'Nom',
            'Postnom',
            'Prénom',
            'Sexe (M/F)',
            'Date de naissance',
            'Nationalité',
            'Promotion',
        ];

        $sheet->mergeCells([1, 1, count($headers), 1]);
        $sheet->setCellValue([1, 1], 'MODELE IMPORT ETUDIANTS UKV — UN FICHIER PAR FILIERE');
        $sheet->getStyle([1, 1])->getFont()->setBold(true);
        $sheet->getStyle([1, 1])->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        foreach ($headers as $index => $header) {
            $sheet->setCellValue([$index + 1, 2], $header);
        }
        $sheet->getStyle('A2:H2')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E5AA8'],
            ],
        ]);
        $sheet->setCellValue([1, 3], '1660');
        $sheet->setCellValue([2, 3], 'MAKAYA');
        $sheet->setCellValue([3, 3], 'KOSI');
        $sheet->setCellValue([4, 3], 'Exemple');
        $sheet->setCellValue([5, 3], 'M');
        $sheet->setCellValue([6, 3], '17/05/2005');
        $sheet->setCellValue([7, 3], 'Congolaise');
        $sheet->setCellValue([8, 3], 'BAC3');
        foreach (range(1, count($headers)) as $column) {
            $sheet->getColumnDimensionByColumn($column)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $response = new StreamedResponse(static function () use ($writer): void {
            $writer->save('php://output');
        });
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="modele-import-etudiants-ukv.xlsx"');
        $response->setStatusCode(Response::HTTP_OK);

        return $response;
    }

    /**
     * @return array{
     *     createdPatients: int,
     *     updatedPatients: int,
     *     createdDpis: int,
     *     createdAptitudes: int,
     *     skippedExisting: int,
     *     errors: list<array{row: int, message: string, identite: string}>,
     *     totalRows: int
     * }
     */
    public function importFromUpload(
        UploadedFile $file,
        int $organisationId,
        ?int $filiereId,
        int $serviceId,
        int $annee,
        string $categorieTarifaire,
    ): array {
        $extension = strtolower((string) $file->getClientOriginalExtension());
        if (!in_array($extension, ['xlsx', 'xls'], true)) {
            throw new ConflictException('Envoyez un fichier Excel (.xlsx).');
        }

        $categorie = CategorieTarifaire::normalize($categorieTarifaire);
        if (!in_array($categorie, [CategorieTarifaire::A0, CategorieTarifaire::A, CategorieTarifaire::B], true)) {
            throw new ConflictException('Pour l\'import, choisissez A0, A ou B (A1 et C exigent une structure).');
        }
        if ($annee < 2000 || $annee > 2100) {
            throw new ConflictException('Année académique invalide.');
        }

        $organisation = $this->organisationPartenaireRepository->find($organisationId);
        if (!$organisation instanceof OrganisationPartenaire) {
            throw new NotFoundException('Organisation partenaire non trouvée.');
        }
        if (OrganisationPartenaire::STATUT_ACTIF !== $organisation->getStatut()) {
            throw new ConflictException('Cette organisation partenaire est inactive.');
        }

        $filiere = null;
        if ($organisation->requiresFiliere()) {
            if (null === $filiereId || $filiereId < 1) {
                throw new ConflictException('Pour une université, choisissez la faculté (filière).');
            }
        }
        if (null !== $filiereId && $filiereId > 0) {
            $filiere = $this->filiereRepository->find($filiereId);
            if (!$filiere instanceof Filiere) {
                throw new NotFoundException('Filière non trouvée.');
            }
            $filiereOrg = $filiere->getOrganisation();
            if (null !== $filiereOrg && $filiereOrg->getId() !== $organisation->getId()) {
                throw new ConflictException('Cette filière n\'appartient pas à l\'organisation sélectionnée.');
            }
        }

        $service = $this->serviceRepository->find($serviceId);
        if (!$service instanceof Service) {
            throw new NotFoundException('Service non trouvé.');
        }

        $spreadsheet = IOFactory::load($file->getPathname());
        $sheet = $spreadsheet->getActiveSheet();
        $headerRow = $this->detectHeaderRow($sheet);
        $columns = $this->mapColumns($sheet, $headerRow);

        $createdPatients = 0;
        $updatedPatients = 0;
        $createdDpis = 0;
        $createdAptitudes = 0;
        $skippedExisting = 0;
        $errors = [];
        $totalRows = 0;
        $year = (int) (new \DateTimeImmutable('now', new \DateTimeZone(self::TIMEZONE)))->format('Y');
        $dpiSequence = $this->dpiRepository->getNextSequenceForYear($year);
        $seenCodes = [];
        /** @var array<string, Patient> $localByCode */
        $localByCode = [];
        /** @var array<string, Patient> $localByIdentity */
        $localByIdentity = [];
        $importedAptitudePatients = [];

        $this->entityManager->beginTransaction();
        try {
            for ($row = $headerRow + 1; $row <= $sheet->getHighestDataRow(); ++$row) {
                $nom = $this->cellString($sheet, $row, $columns['nom']);
                $postNom = $this->cellString($sheet, $row, $columns['postNom']);
                $prenom = isset($columns['prenom']) ? $this->cellString($sheet, $row, $columns['prenom']) : '';
                $codeUkv = isset($columns['codeUkv'])
                    ? $this->normalizeCodeUkv($this->cellString($sheet, $row, $columns['codeUkv']))
                    : null;
                if ('' === $nom && '' === $postNom && '' === $prenom && null === $codeUkv) {
                    continue;
                }
                ++$totalRows;

                if ('' === $nom || '' === $postNom) {
                    $errors[] = $this->errorRow($row, 'Nom et post-nom obligatoires.', $nom, $postNom, $prenom);
                    continue;
                }

                $sexe = $this->normalizeSexe($this->cellString($sheet, $row, $columns['sexe']));
                if (null === $sexe) {
                    $errors[] = $this->errorRow($row, 'Sexe invalide (M ou F).', $nom, $postNom, $prenom);
                    continue;
                }

                $dateNaissance = $this->cellDate($sheet, $row, $columns['dateNaissance']);
                if (null === $dateNaissance) {
                    $errors[] = $this->errorRow($row, 'Date de naissance manquante ou illisible.', $nom, $postNom, $prenom);
                    continue;
                }

                if (null !== $codeUkv) {
                    if (isset($seenCodes[$codeUkv])) {
                        $errors[] = $this->errorRow($row, 'Code UKV dupliqué dans le fichier.', $nom, $postNom, $prenom);
                        continue;
                    }
                    $seenCodes[$codeUkv] = $row;
                }

                $identityKey = $this->identityKey($nom, $postNom, $prenom, $dateNaissance);
                $patient = $this->resolvePatient(
                    $codeUkv,
                    $nom,
                    $postNom,
                    $prenom,
                    $dateNaissance,
                    $localByCode,
                    $localByIdentity,
                    $identityKey,
                );
                if (is_string($patient)) {
                    $errors[] = $this->errorRow($row, $patient, $nom, $postNom, $prenom);
                    continue;
                }

                $isNew = null === $patient;
                if ($isNew) {
                    $patient = (new Patient())
                        ->setNom(mb_strtoupper($nom))
                        ->setPostNom(mb_strtoupper($postNom))
                        ->setPrenom('' === $prenom ? null : mb_strtoupper($prenom))
                        ->setDateNaissance($dateNaissance)
                        ->setSexe($sexe)
                        ->setStatus(Patient::STATUS_ACTIF)
                        ->setCategorieTarifaire($categorie);
                    $this->entityManager->persist($patient);
                    ++$createdPatients;
                } else {
                    $patient
                        ->setNom(mb_strtoupper($nom))
                        ->setPostNom(mb_strtoupper($postNom))
                        ->setPrenom('' === $prenom ? null : mb_strtoupper($prenom))
                        ->setSexe($sexe)
                        ->setDateNaissance($dateNaissance);
                    if (null === $patient->getCategorieTarifaire()) {
                        $patient->setCategorieTarifaire($categorie);
                    }
                    ++$updatedPatients;
                }

                if (null !== $codeUkv) {
                    $patient->setCodeUkv($codeUkv);
                }
                $patient->setOrganisation($organisation);
                if ($filiere instanceof Filiere) {
                    $patient->setFiliere($filiere);
                }
                if (null !== $codeUkv) {
                    $localByCode[$codeUkv] = $patient;
                }
                $localByIdentity[$identityKey] = $patient;

                $dpi = $patient->getDpi();
                if (null === $dpi) {
                    $dpi = (new Dpi())
                        ->setNumDossier(sprintf('DPI-%d-%05d', $year, $dpiSequence))
                        ->setStatut(Dpi::STATUT_OUVERT)
                        ->setPatient($patient);
                    $patient->setDpi($dpi);
                    $this->entityManager->persist($dpi);
                    ++$dpiSequence;
                    ++$createdDpis;
                }

                $existingAptitude = $this->certificatRepository->findActiveAdmissionForPatient($patient, $annee);
                $patientKey = spl_object_id($patient);
                if (null !== $existingAptitude || isset($importedAptitudePatients[$patientKey])) {
                    if ($existingAptitude instanceof CertificatAptitude
                        && $filiere instanceof Filiere
                        && null === $existingAptitude->getFiliere()
                    ) {
                        $existingAptitude->setFiliere($filiere);
                    }
                    ++$skippedExisting;
                    continue;
                }

                $aptitude = (new CertificatAptitude())
                    ->setAnnee($annee)
                    ->setStatut(CertificatAptitude::STATUT_BROUILLON)
                    ->setService($service)
                    ->setPatient($patient)
                    ->setNom((string) $patient->getNom())
                    ->setPostNom((string) $patient->getPostNom())
                    ->setPrenom($patient->getPrenom())
                    ->setSexe((string) $patient->getSexe())
                    ->setDateNaissance(\DateTimeImmutable::createFromInterface($dateNaissance))
                    ->setMotif(CertificatAptitude::MOTIF_ADMISSION_UKV)
                    ->setFiliere($filiere);
                $this->entityManager->persist($aptitude);
                $importedAptitudePatients[$patientKey] = true;
                ++$createdAptitudes;
            }

            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (\Throwable $exception) {
            $this->entityManager->rollback();
            throw $exception;
        }

        $spreadsheet->disconnectWorksheets();

        return [
            'createdPatients' => $createdPatients,
            'updatedPatients' => $updatedPatients,
            'createdDpis' => $createdDpis,
            'createdAptitudes' => $createdAptitudes,
            'skippedExisting' => $skippedExisting,
            'errors' => $errors,
            'totalRows' => $totalRows,
            'organisation' => [
                'id' => (int) $organisation->getId(),
                'code' => (string) $organisation->getCode(),
                'libelle' => (string) $organisation->getLibelle(),
            ],
            'filiere' => $filiere instanceof Filiere ? [
                'id' => (int) $filiere->getId(),
                'code' => (string) $filiere->getCode(),
                'libelle' => (string) $filiere->getLibelle(),
            ] : null,
        ];
    }

    /**
     * @param array<string, Patient> $localByCode
     * @param array<string, Patient> $localByIdentity
     *
     * @return Patient|string|null
     */
    private function resolvePatient(
        ?string $codeUkv,
        string $nom,
        string $postNom,
        string $prenom,
        \DateTime $dateNaissance,
        array $localByCode,
        array $localByIdentity,
        string $identityKey,
    ): Patient|string|null {
        $byCode = null;
        if (null !== $codeUkv) {
            $byCode = $localByCode[$codeUkv] ?? $this->patientRepository->findOneByCodeUkv($codeUkv);
        }
        $byIdentity = $localByIdentity[$identityKey]
            ?? $this->patientRepository->findOneByIdentity($nom, $postNom, $prenom, $dateNaissance);

        if (null !== $byCode && null !== $byIdentity && $byCode !== $byIdentity) {
            return 'Le code UKV et l\'identité correspondent à deux patients différents.';
        }
        if (null !== $byIdentity && null !== $codeUkv) {
            $existingCode = $byIdentity->getCodeUkv();
            if (null !== $existingCode && $existingCode !== $codeUkv) {
                return sprintf('Cette identité a déjà le code UKV %s.', $existingCode);
            }
        }

        return $byCode ?? $byIdentity;
    }

    private function identityKey(string $nom, string $postNom, string $prenom, \DateTimeInterface $dateNaissance): string
    {
        return sprintf(
            '%s|%s|%s|%s',
            mb_strtoupper(trim($nom)),
            mb_strtoupper(trim($postNom)),
            mb_strtoupper(trim($prenom)),
            $dateNaissance->format('Y-m-d'),
        );
    }

    /**
     * @return array{codeUkv: int, nom: int, postNom: int, prenom: int, sexe: int, dateNaissance: int}
     */
    private function mapColumns(Worksheet $sheet, int $headerRow): array
    {
        $highest = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        $mapped = [];
        for ($col = 1; $col <= $highest; ++$col) {
            $key = $this->headerKey($this->cellString($sheet, $headerRow, $col));
            if (null === $key || isset($mapped[$key])) {
                continue;
            }
            $mapped[$key] = $col;
        }

        if (isset($mapped['sexeMf'])) {
            $mapped['sexe'] = $mapped['sexeMf'];
            unset($mapped['sexeMf']);
        }

        $required = ['nom', 'postNom', 'sexe', 'dateNaissance'];
        $missing = [];
        foreach ($required as $field) {
            if (!isset($mapped[$field])) {
                $missing[] = $field;
            }
        }
        if ($missing !== []) {
            throw new ConflictException(
                'Colonnes manquantes dans le fichier : Nom, Postnom, Sexe (M/F), Date de naissance. Code UKV est recommandé.',
            );
        }

        return $mapped;
    }

    private function detectHeaderRow(Worksheet $sheet): int
    {
        $highest = min(5, $sheet->getHighestDataRow());
        for ($row = 1; $row <= $highest; ++$row) {
            $joined = '';
            for ($col = 1; $col <= 12; ++$col) {
                $joined .= ' ' . ($this->headerKey($this->cellString($sheet, $row, $col)) ?? '');
            }
            if (str_contains($joined, 'nom') && (str_contains($joined, 'postnom') || str_contains($joined, 'post nom'))) {
                return $row;
            }
        }

        throw new ConflictException('Impossible de trouver la ligne d\'en-têtes (Nom, Postnom, …).');
    }

    private function headerKey(string $value): ?string
    {
        $normalized = $this->asciiLower($value);
        if ('' === $normalized) {
            return null;
        }
        if (str_contains($normalized, 'code ukv') || $normalized === 'code') {
            return 'codeUkv';
        }
        if (str_contains($normalized, 'postnom') || str_contains($normalized, 'post nom')) {
            return 'postNom';
        }
        if (str_contains($normalized, 'prenom')) {
            return 'prenom';
        }
        if ($normalized === 'nom' || str_starts_with($normalized, 'nom ')) {
            return 'nom';
        }
        if (str_contains($normalized, 'sexe') && (str_contains($normalized, 'm/f') || str_contains($normalized, 'm f'))) {
            return 'sexeMf';
        }
        if (str_contains($normalized, 'sexe')) {
            return 'sexe';
        }
        if (str_contains($normalized, 'naissance') || str_contains($normalized, 'date naiss')) {
            return 'dateNaissance';
        }

        return null;
    }

    private function asciiLower(string $value): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', trim($value));
        $ascii = is_string($ascii) ? $ascii : trim($value);

        return mb_strtolower(preg_replace('/\s+/', ' ', $ascii) ?? $ascii);
    }

    private function cellString(Worksheet $sheet, int $row, int $col): string
    {
        $value = $sheet->getCell([$col, $row])->getValue();
        if ($value instanceof \DateTimeInterface) {
            return $value->format('d/m/Y');
        }
        if (null === $value) {
            return '';
        }

        return trim((string) $value);
    }

    private function cellDate(Worksheet $sheet, int $row, int $col): ?\DateTime
    {
        $cell = $sheet->getCell([$col, $row]);
        $value = $cell->getValue();
        if ($value instanceof \DateTimeInterface) {
            return \DateTime::createFromInterface($value)->setTime(0, 0);
        }
        if (is_numeric($value) && ExcelDate::isDateTime($cell)) {
            return ExcelDate::excelToDateTimeObject((float) $value)->setTime(0, 0);
        }

        $text = trim((string) ($cell->getFormattedValue() ?: $value ?? ''));
        if ('' === $text || '-' === $text || '—' === $text) {
            return null;
        }

        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'd.m.Y'] as $format) {
            $date = \DateTime::createFromFormat('!' . $format, $text);
            if (false !== $date) {
                return $date;
            }
        }

        return null;
    }

    private function normalizeSexe(string $value): ?string
    {
        $normalized = $this->asciiLower($value);
        if (in_array($normalized, ['m', 'masculin', 'homme', 'h', 'male'], true)) {
            return Patient::SEXE_MASCULIN;
        }
        if (in_array($normalized, ['f', 'feminin', 'femme', 'female'], true)) {
            return Patient::SEXE_FEMININ;
        }

        return null;
    }

    private function normalizeCodeUkv(string $value): ?string
    {
        $value = trim($value);
        if ('' === $value || !preg_match('/^[0-9A-Za-z\-]+$/', $value)) {
            return null;
        }

        return mb_strtoupper($value);
    }

    /**
     * @return array{row: int, message: string, identite: string}
     */
    private function errorRow(int $row, string $message, string $nom, string $postNom, string $prenom): array
    {
        return [
            'row' => $row,
            'message' => $message,
            'identite' => trim(sprintf('%s %s %s', $nom, $postNom, $prenom)),
        ];
    }
}
