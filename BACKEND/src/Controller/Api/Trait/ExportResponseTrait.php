<?php

namespace App\Controller\Api\Trait;

use App\Service\Export\TableExportService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

trait ExportResponseTrait
{
    /**
     * @param list<string>            $headers
     * @param list<list<string|null>> $dataRows
     * @param list<int>               $htmlColumnIndexes
     * @param list<int>               $richTextColumnIndexes
     */
    protected function createTableExportResponse(
        Request $request,
        TableExportService $tableExportService,
        array $headers,
        array $dataRows,
        string $reportTitle,
        string $filenamePrefix,
        string $emptyMessage,
        array $htmlColumnIndexes = [],
        array $richTextColumnIndexes = [],
        string $pdfOrientation = 'portrait',
    ): Response {
        $format = strtolower(trim((string) $request->query->get('format', 'xlsx')));

        if (!\in_array($format, ['pdf', 'xlsx'], true)) {
            throw new BadRequestHttpException('Format d\'export invalide. Utilisez pdf ou xlsx.');
        }

        return $tableExportService->createResponse(
            $format,
            $headers,
            $dataRows,
            $reportTitle,
            $filenamePrefix,
            $emptyMessage,
            $htmlColumnIndexes,
            $richTextColumnIndexes,
            $pdfOrientation,
        );
    }
}
