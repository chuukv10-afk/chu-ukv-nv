<?php

namespace App\Service\Personnel;

use App\Entity\Personnel;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Uid\Uuid;

final class PersonnelAvatarService
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
            throw new BadRequestHttpException('Impossible d\'enregistrer l\'avatar avant la création du personnel.');
        }

        $extension = self::ALLOWED_MIME_TYPES[$file->getMimeType() ?? ''] ?? null;
        if (null === $extension) {
            throw new BadRequestHttpException('Format d\'image non supporté. Utilisez JPG, PNG ou WebP.');
        }

        $this->ensureUploadDirectoryExists();
        $this->deleteStoredFile($personnel);

        $filename = sprintf('%s.%s', $personnelId->toRfc4122(), $extension);
        $file->move($this->uploadDir, $filename);

        $personnel->setAvatarFilename($filename);
    }

    public function delete(Personnel $personnel): void
    {
        $this->deleteStoredFile($personnel);
        $personnel->setAvatarFilename(null);
    }

    public function resolvePath(Personnel $personnel): ?string
    {
        $filename = $personnel->getAvatarFilename();
        if (null === $filename || '' === trim($filename)) {
            return null;
        }

        $path = $this->uploadDir . DIRECTORY_SEPARATOR . $filename;
        if (!is_file($path)) {
            return null;
        }

        return $path;
    }

    public function buildAvatarUrl(Personnel $personnel): ?string
    {
        $path = $this->resolvePath($personnel);
        $personnelId = $personnel->getId();

        if (null === $path || !$personnelId instanceof Uuid) {
            return null;
        }

        $version = filemtime($path) ?: time();

        return sprintf(
            '/api/v1/admin/personnels/%s/avatar?v=%d',
            $personnelId->toRfc4122(),
            $version,
        );
    }

    public function resolveMimeType(Personnel $personnel): ?string
    {
        $filename = $personnel->getAvatarFilename();
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

    private function assertValidUpload(UploadedFile $file): void
    {
        if (!$file->isValid()) {
            throw new BadRequestHttpException('Le fichier avatar est invalide ou corrompu.');
        }

        $mimeType = $file->getMimeType();
        if (null === $mimeType || !isset(self::ALLOWED_MIME_TYPES[$mimeType])) {
            throw new BadRequestHttpException('Format d\'image non supporté. Utilisez JPG, PNG ou WebP.');
        }

        if ($file->getSize() > self::MAX_SIZE_BYTES) {
            throw new BadRequestHttpException('L\'avatar ne doit pas dépasser 2 Mo.');
        }
    }

    private function ensureUploadDirectoryExists(): void
    {
        if (is_dir($this->uploadDir)) {
            return;
        }

        if (!mkdir($this->uploadDir, 0775, true) && !is_dir($this->uploadDir)) {
            throw new BadRequestHttpException('Impossible de préparer le dossier de stockage des avatars.');
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
