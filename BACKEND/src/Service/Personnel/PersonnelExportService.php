<?php



namespace App\Service\Personnel;



use App\Entity\Personnel;
use App\Service\Export\PdfExportService;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;

final class PersonnelExportService
{

    /**

     * @var list<string>

     */

    private const HEADERS = [
        'N°',
        'Matricule',

        'Nom complet',

        'Téléphone',

        'Sexe',

        'Type',

        'Statut',

        'Grade',

        'Service',

        'Rôles',

        'CNOM',

    ];

    private const REPORT_TITLE = 'LISTE DU PERSONNEL';



    private const STATUS_LABELS = [

        Personnel::STATUS_ACTIF => 'Actif',

        Personnel::STATUS_INACTIF => 'Inactif',

        Personnel::STATUS_SUSPENDU => 'Suspendu',

        Personnel::STATUS_RETRAITE => 'Retraité',

        Personnel::STATUS_DEMISSION => 'Démission',

        Personnel::STATUS_DECESE => 'Décédé',

    ];



    private const TYPE_LABELS = [

        Personnel::TYPE_MEDICAL => 'Médical',

        Personnel::TYPE_PARAMEDICAL => 'Paramédical',

        Personnel::TYPE_ADMINISTRATIF => 'Administratif',

        Personnel::TYPE_TECHNIQUE => 'Technique',

    ];

    private const ROLES_PDF_COLUMN_INDEX = 9;

    private const ROLES_ROW_VALUE_INDEX = 8;



    public function __construct(
        private readonly PdfExportService $pdfExportService,
        private readonly Security $security,
    ) {
    }



    /**

     * @param list<array<string, string|null>> $rows

     */

    public function createExcelResponse(array $rows): Response

    {

        $spreadsheet = new Spreadsheet();

        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setTitle('Personnel');

        $columnCount = count(self::HEADERS);
        $sheet->mergeCells([1, 1, $columnCount, 1]);
        $sheet->setCellValue([1, 1], self::REPORT_TITLE);
        $sheet->getStyle([1, 1])->getFont()->setBold(true);
        $sheet->getStyle([1, 1])->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        foreach (self::HEADERS as $columnIndex => $header) {

            $cell = $sheet->getCell([$columnIndex + 1, 2]);

            $cell->setValue($header);

        }



        $headerRange = 'A2:' . $sheet->getCell([$columnCount, 2])->getCoordinate();

        $sheet->getStyle($headerRange)->applyFromArray([

            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],

            'fill' => [

                'fillType' => Fill::FILL_SOLID,

                'startColor' => ['rgb' => '1E5AA8'],

            ],

        ]);



        $rowIndex = 3;
        foreach ($rows as $index => $row) {
            $sheet->setCellValue([1, $rowIndex], $index + 1);
            foreach (array_values($row) as $columnIndex => $value) {
                $cell = [$columnIndex + 2, $rowIndex];
                if (self::ROLES_ROW_VALUE_INDEX === $columnIndex && \is_string($value) && '' !== $value) {
                    $sheet->setCellValue($cell, $this->formatRolesAsRichText($value));
                    continue;
                }

                $sheet->setCellValue($cell, $value ?? '');
            }
            ++$rowIndex;
        }



        foreach (range(1, count(self::HEADERS)) as $columnIndex) {

            $sheet->getColumnDimensionByColumn($columnIndex)->setAutoSize(true);

        }



        $writer = new Xlsx($spreadsheet);

        $tempFile = tempnam(sys_get_temp_dir(), 'personnel_export_');

        if (false === $tempFile) {

            throw new \RuntimeException('Impossible de préparer le fichier Excel.');

        }



        $writer->save($tempFile);

        $content = file_get_contents($tempFile);

        unlink($tempFile);



        if (false === $content) {

            throw new \RuntimeException('Impossible de lire le fichier Excel généré.');

        }



        return new Response(

            $content,

            Response::HTTP_OK,

            [

                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',

                'Content-Disposition' => 'attachment; filename="' . $this->buildFilename('xlsx') . '"',

            ],

        );

    }



    /**

     * @param list<array<string, string|null>> $rows

     */

    public function createPdfResponse(array $rows): Response
    {
        $tableRows = [];
        foreach ($rows as $index => $row) {
            $values = array_values($row);
            if (isset($values[self::ROLES_ROW_VALUE_INDEX])) {
                $values[self::ROLES_ROW_VALUE_INDEX] = $this->formatRolesAsHtml($values[self::ROLES_ROW_VALUE_INDEX]);
            }

            $tableRows[] = array_merge(
                [(string) ($index + 1)],
                $values,
            );
        }

        return $this->pdfExportService->createTableDocumentResponse(
            reportTitle: self::REPORT_TITLE,
            tableHtml: $this->pdfExportService->buildTableHtml(
                self::HEADERS,
                $tableRows,
                'Aucun personnel trouvé pour les filtres sélectionnés.',
                [self::ROLES_PDF_COLUMN_INDEX],
            ),
            filename: $this->buildFilename('pdf'),
            generatedBy: $this->resolveCurrentUserDisplayName(),
            generatedAt: new \DateTimeImmutable(),
            orientation: 'landscape',
        );
    }



    /**

     * @return array<string, string|null>

     */

    public function buildRow(Personnel $personnel): array

    {

        $roles = [];

        foreach ($personnel->getRoleAssignments() as $assignment) {
            $role = $assignment->getRole();
            $roleLibelle = $role?->getLibelle() ?? $role?->getCode();

            if (null === $roleLibelle || '' === trim($roleLibelle)) {
                continue;
            }

            $scope = $assignment->getService()?->getLibelle()
                ?? $assignment->getDepartement()?->getLibelle()
                ?? 'Global';

            $roles[] = sprintf('%s : %s', $roleLibelle, $scope);
        }



        return [

            'matricule' => $personnel->getMatricule(),

            'nomComplet' => $this->formatFullName($personnel),

            'telephone' => $personnel->getTelephone(),

            'sexe' => 'M' === $personnel->getSexe() ? 'Masculin' : 'Féminin',

            'type' => self::TYPE_LABELS[$personnel->getType() ?? ''] ?? $personnel->getType(),

            'statut' => self::STATUS_LABELS[$personnel->getStatus() ?? ''] ?? $personnel->getStatus(),

            'grade' => $personnel->getGrade()?->getLibelle(),

            'service' => $personnel->getService()?->getLibelle(),

            'roles' => [] !== $roles ? implode('; ', $roles) : null,

            'cnome' => $personnel->getCnome(),

        ];

    }



    private function formatFullName(Personnel $personnel): string

    {

        $parts = array_filter([

            $personnel->getPrenom(),

            $personnel->getNom(),

            $personnel->getPostNom(),

        ], static fn (?string $part): bool => null !== $part && '' !== trim($part));



        $fullName = trim(implode(' ', $parts));



        return '' !== $fullName ? $fullName : (string) $personnel->getMatricule();

    }



    private function buildFilename(string $extension): string
    {
        return sprintf('personnel_%s.%s', (new \DateTimeImmutable())->format('Ymd_His'), $extension);
    }

    private function formatRolesAsHtml(?string $roles): ?string
    {
        if (null === $roles || '' === trim($roles)) {
            return null;
        }

        $parts = [];
        foreach (explode('; ', $roles) as $part) {
            if (preg_match('/^(.+?) : (.+)$/', $part, $matches)) {
                $parts[] = sprintf(
                    '<strong>%s</strong> : %s',
                    htmlspecialchars($matches[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                    htmlspecialchars($matches[2], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                );
                continue;
            }

            $parts[] = htmlspecialchars($part, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        return implode('; ', $parts);
    }

    private function formatRolesAsRichText(string $roles): RichText
    {
        $richText = new RichText();
        $segments = explode('; ', $roles);

        foreach ($segments as $index => $part) {
            if ($index > 0) {
                $richText->createTextRun('; ');
            }

            if (preg_match('/^(.+?) : (.+)$/', $part, $matches)) {
                $bold = $richText->createTextRun($matches[1]);
                $bold->getFont()->setBold(true);
                $richText->createTextRun(' : ' . $matches[2]);
                continue;
            }

            $richText->createTextRun($part);
        }

        return $richText;
    }

    private function resolveCurrentUserDisplayName(): string
    {
        $user = $this->security->getUser();
        if (!$user instanceof Personnel) {
            return '';
        }

        $parts = array_filter([
            $user->getPrenom(),
            $user->getNom(),
            $user->getPostNom(),
        ], static fn (?string $part): bool => null !== $part && '' !== trim($part));

        $fullName = trim(implode(' ', $parts));

        return '' !== $fullName ? $fullName : (string) ($user->getMatricule() ?? '');
    }
}


