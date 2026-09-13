<?php

declare(strict_types=1);

namespace App\Tests\Storage;

use App\Service\Storage\ObjectStorage;

final class ObjectStorageTest
{
    public static function run(): array
    {
        $failures = [];
        $root = sys_get_temp_dir() . '/chu-ukv-storage-' . bin2hex(random_bytes(4));
        $storage = new ObjectStorage('local', $root, '', 'eu-west-1', 'chu-ukv', '', '');

        $storage->put('personnel/avatars/demo.png', 'png-bytes', 'image/png');
        $file = $storage->get('personnel/avatars/demo.png');
        if (null === $file || 'png-bytes' !== $file->contents) {
            $failures[] = 'put/get local doit retrouver le contenu';
        }

        $legacy = $storage->get('personnel/avatars/missing.png', ['personnel/avatars/demo.png']);
        if (null === $legacy || 'png-bytes' !== $legacy->contents) {
            $failures[] = 'get doit accepter une clé héritée';
        }

        if (!$storage->exists('personnel/avatars/demo.png')) {
            $failures[] = 'exists doit être vrai après put';
        }

        if (null === $storage->lastModified('personnel/avatars/demo.png')) {
            $failures[] = 'lastModified ne doit pas lire tout le fichier, mais doit renvoyer un timestamp';
        }

        $storage->delete('personnel/avatars/demo.png');
        if ($storage->exists('personnel/avatars/demo.png')) {
            $failures[] = 'delete local doit retirer le fichier';
        }

        $s3WithoutBucket = new ObjectStorage('s3', $root, '', 'eu-west-1', 'chu-ukv', '', '');
        if ($s3WithoutBucket->isS3()) {
            $failures[] = 'FILE_STORAGE=s3 sans bucket doit rester en local';
        }

        self::removeDirectory($root);

        return $failures;
    }

    private static function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($directory);
    }
}
