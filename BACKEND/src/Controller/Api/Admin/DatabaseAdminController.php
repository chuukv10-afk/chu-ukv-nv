<?php

namespace App\Controller\Api\Admin;

use App\Controller\Api\Trait\JsonResponseTrait;
use App\Security\Permission\AdminPermissions;
use App\Service\Admin\DatabaseAdminService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/admin/database')]
#[IsGranted('ROLE_PERSONNEL')]
final class DatabaseAdminController extends AbstractController
{
    use JsonResponseTrait;

    public function __construct(
        private readonly DatabaseAdminService $databaseAdminService,
    ) {
    }

    #[Route('', name: 'api_admin_database_overview', methods: ['GET'])]
    #[IsGranted(AdminPermissions::DATABASE_MANAGE)]
    public function overview(): JsonResponse
    {
        return $this->apiSuccess(
            $this->databaseAdminService->overview(),
            'État de la base de données.',
        );
    }

    #[Route('/truncate', name: 'api_admin_database_truncate', methods: ['POST'])]
    #[IsGranted(AdminPermissions::DATABASE_TRUNCATE)]
    public function truncate(Request $request): JsonResponse
    {
        $tables = $this->tablesFromRequest($request);
        $result = $this->databaseAdminService->truncate($tables);

        return $this->apiSuccess(
            $result,
            sprintf('%d table(s) vidée(s).', count($result['truncated'])),
        );
    }

    #[Route('/export', name: 'api_admin_database_export', methods: ['POST'])]
    #[IsGranted(AdminPermissions::DATABASE_EXPORT)]
    public function export(Request $request): Response
    {
        $payload = $this->jsonPayload($request);
        $format = strtolower(trim((string) ($payload['format'] ?? 'sql')));
        $tables = $payload['tables'] ?? [];
        if (!is_array($tables)) {
            throw new BadRequestHttpException('La liste des tables est invalide.');
        }

        return $this->databaseAdminService->export(array_values($tables), $format);
    }

    #[Route('/import', name: 'api_admin_database_import', methods: ['POST'])]
    #[IsGranted(AdminPermissions::DATABASE_IMPORT)]
    public function import(Request $request): JsonResponse
    {
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            throw new BadRequestHttpException('Envoyez un fichier .sql (champ file).');
        }

        $result = $this->databaseAdminService->importSql($file);

        return $this->apiSuccess(
            $result,
            sprintf('Import terminé : %d instruction(s) exécutée(s).', $result['executed']),
        );
    }

    /**
     * @return list<string>
     */
    private function tablesFromRequest(Request $request): array
    {
        $payload = $this->jsonPayload($request);
        $tables = $payload['tables'] ?? [];
        if (!is_array($tables)) {
            throw new BadRequestHttpException('La liste des tables est invalide.');
        }

        return array_values($tables);
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonPayload(Request $request): array
    {
        $payload = json_decode($request->getContent() ?: '{}', true);

        return is_array($payload) ? $payload : [];
    }
}
