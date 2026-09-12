<?php

namespace App\Service\Personnel;

use App\Entity\Personnel;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Uid\Uuid;

final class PersonnelSignatureService
{
    private const MAX_SIZE_BYTES = 2_097_152;
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(
        private readonly string $uploadDir,
    ) {
    }

    public function upload(Personnel $personnel, UploadedFile $file): void
    {
        $this->assertValidUpload($file);

        $personnelId = $personnel->getId();
        if (!$personnelId instanceof Uuid) {
            throw new BadRequestHttpException('Impossible d\'enregistrer la signature avant la création du personnel.');
        }

        $extension = self::ALLOWED_MIME_TYPES[$file->getMimeType() ?? ''] ?? null;
        if (null === $extension) {
            throw new BadRequestHttpException('Format d\'image non supporté. Utilisez JPG, PNG ou WebP.');
        }

        $this->ensureUploadDirectoryExists();
        $this->deleteStoredFile($personnel);

        $filename = sprintf('%s-sig.%s', $personnelId->toRfc4122(), $extension);
        $file->move($this->uploadDir, $filename);

        $personnel->setSignatureFilename($filename);
    }

    public function delete(Personnel $personnel): void
    {
        $this->deleteStoredFile($personnel);
        $personnel->setSignatureFilename(null);
    }

    public function resolvePath(Personnel $personnel): ?string
    {
        $filename = $personnel->getSignatureFilename();
        if (null === $filename || '' === trim($filename)) {
            return null;
        }

        $path = $this->uploadDir . DIRECTORY_SEPARATOR . $filename;
        if (!is_file($path)) {
            return null;
        }

        return $path;
    }

    public function buildSignatureUrl(Personnel $personnel): ?string
    {
        $path = $this->resolvePath($personnel);
        $personnelId = $personnel->getId();

        if (null === $path || !$personnelId instanceof Uuid) {
            return null;
        }

        $version = filemtime($path) ?: time();

        return sprintf(
            '/api/v1/admin/personnels/%s/signature?v=%d',
            $personnelId->toRfc4122(),
            $version,
        );
    }

    public function resolveMimeType(Personnel $personnel): ?string
    {
        $filename = $personnel->getSignatureFilename();
        if (null === $filename) {
            return null;
        }

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => null,
        };
    }

    public function toDataUri(Personnel $personnel): ?string
    {
        $path = $this->resolvePath($personnel);
        $mime = $this->resolveMimeType($personnel);
        if (null === $path || null === $mime) {
            return null;
        }

        $contents = file_get_contents($path);
        if (false === $contents || '' === $contents) {
            return null;
        }

        return 'data:' . $mime . ';base64,' . base64_encode($contents);
    }

    private function assertValidUpload(UploadedFile $file): void
    {
        if (!$file->isValid()) {
            throw new BadRequestHttpException('Le fichier signature est invalide ou corrompu.');
        }

        $mimeType = $file->getMimeType();
        if (null === $mimeType || !isset(self::ALLOWED_MIME_TYPES[$mimeType])) {
            throw new BadRequestHttpException('Format d\'image non supporté. Utilisez JPG, PNG ou WebP.');
        }

        if ($file->getSize() > self::MAX_SIZE_BYTES) {
            throw new BadRequestHttpException('La signature ne doit pas dépasser 2 Mo.');
        }
    }

    private function ensureUploadDirectoryExists(): void
    {
        if (is_dir($this->uploadDir)) {
            return;
        }

        if (!mkdir($this->uploadDir, 0775, true) && !is_dir($this->uploadDir)) {
            throw new BadRequestHttpException('Impossible de préparer le dossier de stockage des signatures.');
        }
    }

    private function deleteStoredFile(Personnel $personnel): void
    {
        $path = $this->resolvePath($personnel);
        if (null !== $path && is_file($path)) {
            unlink($path);
        }
    }
}
