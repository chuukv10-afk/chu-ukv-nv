<?php



namespace App\Service\Export;



use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\Response;



/**

 * Service générique de génération PDF avec la charte CHU UKV.

 */

final class PdfExportService

{

    public function __construct(

        private readonly ChuPdfLayoutProvider $layoutProvider,

    ) {

    }



    public function createTableDocumentResponse(

        string $reportTitle,

        string $tableHtml,

        string $filename,

        string $generatedBy,

        ?\DateTimeInterface $generatedAt = null,

        string $orientation = 'landscape',

    ): Response {

        $generatedAt ??= new \DateTimeImmutable();



        $html = $this->layoutProvider->buildDocument(

            $reportTitle,

            $tableHtml,

            $generatedBy,

            $generatedAt,

            $orientation,

            $this->layoutProvider->formatOfficialDateLine($generatedAt),

            $generatedBy,

        );



        return $this->createDownloadResponse(

            $html,

            $filename,

            $orientation,

            inline: true,

        );

    }



    public function createDownloadResponse(

        string $html,

        string $filename,

        string $orientation = 'landscape',

        bool $inline = false,

    ): Response {

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $this->layoutProvider->configureDompdfOptions($options);

        $dompdf = new Dompdf($options);
        $this->layoutProvider->registerFonts($dompdf);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', $orientation);

        $dompdf->render();



        return new Response(

            $dompdf->output(),

            Response::HTTP_OK,

            [

                'Content-Type' => 'application/pdf',

                'Content-Disposition' => ($inline ? 'inline' : 'attachment') . '; filename="' . $filename . '"',

            ],

        );

    }



    /**

     * @param list<string> $headers

     * @param list<list<string|null>> $rows

     */

    public function buildTableHtml(
        array $headers,
        array $rows,
        string $emptyMessage,
        array $htmlColumnIndexes = [],
    ): string {
        $headerCells = '';

        foreach ($headers as $header) {
            $headerCells .= '<th>' . htmlspecialchars($header, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</th>';
        }

        $bodyRows = '';

        foreach ($rows as $row) {
            $bodyRows .= '<tr>';

            foreach ($row as $columnIndex => $value) {
                $cellValue = (string) ($value ?? '—');
                if (\in_array($columnIndex, $htmlColumnIndexes, true)) {
                    $bodyRows .= '<td>' . $cellValue . '</td>';
                } else {
                    $bodyRows .= '<td>' . htmlspecialchars($cellValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
                }
            }

            $bodyRows .= '</tr>';
        }



        if ('' === $bodyRows) {

            $colspan = max(1, count($headers));

            $safeEmpty = htmlspecialchars($emptyMessage, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            $bodyRows = '<tr class="chu-empty-row"><td colspan="' . $colspan . '">' . $safeEmpty . '</td></tr>';

        }



        return <<<HTML

<table class="chu-table">

    <thead><tr>{$headerCells}</tr></thead>

    <tbody>{$bodyRows}</tbody>

</table>

HTML;

    }

}


