<?php

namespace App\Service\Personnel;

use App\Entity\Personnel;
use App\Service\Storage\ObjectStorage;
use App\Service\Storage\StoredFile;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Uid\Uuid;

final class PersonnelAvatarService
{
    public const MAX_SIZE_BYTES = 8_388_608;
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(
        private readonly ObjectStorage $objectStorage,
    ) {
    }

    public function upload(Personnel $personnel, UploadedFile $file): void
    {
        $this->assertValidUpload($file);

        $personnelId = $personnel->getId();
        if (!$personnelId instanceof Uuid) {
            throw new BadRequestHttpException('Impossible d\'enregistrer l\'avatar avant la création du personnel.');
        }

        $mimeType = $file->getMimeType() ?? '';
        $extension = self::ALLOWED_MIME_TYPES[$mimeType] ?? null;
        if (null === $extension) {
            throw new BadRequestHttpException('Format d\'image non supporté. Utilisez JPG, PNG ou WebP.');
        }

        $contents = file_get_contents($file->getPathname());
        if (false === $contents || '' === $contents) {
            throw new BadRequestHttpException('Le fichier avatar est invalide ou corrompu.');
        }

        $this->deleteStoredFile($personnel);

        $filename = sprintf('%s.%s', $personnelId->toRfc4122(), $extension);
        $this->objectStorage->put($this->storageKey($filename), $contents, $mimeType);
        $personnel->setAvatarFilename($filename);
    }

    public function delete(Personnel $personnel): void
    {
        $this->deleteStoredFile($personnel);
        $personnel->setAvatarFilename(null);
    }

    public function read(Personnel $personnel): ?StoredFile
    {
        $filename = $personnel->getAvatarFilename();
        if (null === $filename || '' === trim($filename)) {
            return null;
        }

        return $this->objectStorage->get($this->storageKey($filename), $this->legacyKeys($filename));
    }

    public function buildAvatarUrl(Personnel $personnel): ?string
    {
        $filename = $personnel->getAvatarFilename();
        $personnelId = $personnel->getId();
        if (null === $filename || '' === trim($filename) || !$personnelId instanceof Uuid) {
            return null;
        }

        $modified = $this->objectStorage->lastModified($this->storageKey($filename), $this->legacyKeys($filename));
        if (null === $modified) {
            return null;
        }

        return sprintf(
            '/api/v1/admin/personnels/%s/avatar?v=%d',
            $personnelId->toRfc4122(),
            $modified,
        );
    }

    public function resolveMimeType(Personnel $personnel): ?string
    {
        return $this->read($personnel)?->mimeType ?? $this->mimeFromFilename($personnel->getAvatarFilename());
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
            throw new BadRequestHttpException('L\'avatar ne doit pas dépasser 8 Mo.');
        }
    }

    private function deleteStoredFile(Personnel $personnel): void
    {
        $filename = $personnel->getAvatarFilename();
        if (null === $filename || '' === trim($filename)) {
            return;
        }

        $this->objectStorage->delete($this->storageKey($filename), $this->legacyKeys($filename));
    }

    private function storageKey(string $filename): string
    {
        return 'personnel/avatars/' . $filename;
    }

    /** @return list<string> */
    private function legacyKeys(string $filename): array
    {
        return ['personnel/' . $filename];
    }

    private function mimeFromFilename(?string $filename): ?string
    {
        if (null === $filename) {
            return null;
        }

        return match (strtolower(pathinfo($filename, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => null,
        };
    }
}
