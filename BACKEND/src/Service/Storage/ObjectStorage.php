<?php

namespace App\Service\Storage;

use Aws\Exception\AwsException;
use Aws\S3\S3Client;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/**
 * Stockage unifié : disque local (dev) ou AWS S3 (prod).
 * En mode S3, lecture locale de secours pour les fichiers déjà présents sur le serveur.
 */
final class ObjectStorage
{
    public const DRIVER_LOCAL = 'local';
    public const DRIVER_S3 = 's3';

    private ?S3Client $s3Client = null;

    public function __construct(
        private readonly string $driver,
        private readonly string $localRoot,
        private readonly string $bucket,
        private readonly string $region,
        private readonly string $prefix,
        private readonly string $accessKey,
        private readonly string $secretKey,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function isS3(): bool
    {
        return self::DRIVER_S3 === $this->normalizedDriver();
    }

    public function put(string $key, string $contents, string $mimeType): void
    {
        $relative = $this->normalizeKey($key);
        if ($this->isS3()) {
            $this->putS3($relative, $contents, $mimeType);

            return;
        }

        $this->putLocal($relative, $contents);
    }

    public function get(string $key, array $legacyKeys = []): ?StoredFile
    {
        $candidates = $this->candidateKeys($key, $legacyKeys);

        if ($this->isS3()) {
            foreach ($candidates as $candidate) {
                $fromS3 = $this->getS3($candidate);
                if (null !== $fromS3) {
                    return $fromS3;
                }
            }
        }

        foreach ($candidates as $candidate) {
            $fromLocal = $this->getLocal($candidate);
            if (null !== $fromLocal) {
                return $fromLocal;
            }
        }

        return null;
    }

    public function exists(string $key, array $legacyKeys = []): bool
    {
        return null !== $this->lastModified($key, $legacyKeys);
    }

    public function delete(string $key, array $legacyKeys = []): void
    {
        $candidates = $this->candidateKeys($key, $legacyKeys);

        foreach ($candidates as $candidate) {
            if ($this->isS3()) {
                $this->deleteS3($candidate);
            }
            $this->deleteLocal($candidate);
        }
    }

    public function presignPut(string $key, string $mimeType, int $expiresSeconds = 900): string
    {
        return $this->presign('PutObject', $key, $expiresSeconds, ['ContentType' => $mimeType]);
    }

    public function presignGet(string $key, int $expiresSeconds = 900): string
    {
        return $this->presign('GetObject', $key, $expiresSeconds);
    }

    public function lastModified(string $key, array $legacyKeys = []): ?int
    {
        $candidates = $this->candidateKeys($key, $legacyKeys);

        if ($this->isS3()) {
            foreach ($candidates as $candidate) {
                $fromS3 = $this->headS3($candidate);
                if (null !== $fromS3) {
                    return $fromS3;
                }
            }
        }

        foreach ($candidates as $candidate) {
            $fromLocal = $this->headLocal($candidate);
            if (null !== $fromLocal) {
                return $fromLocal;
            }
        }

        return null;
    }

    private function normalizedDriver(): string
    {
        $driver = strtolower(trim($this->driver));
        if (self::DRIVER_S3 === $driver && '' === trim($this->bucket)) {
            return self::DRIVER_LOCAL;
        }

        return in_array($driver, [self::DRIVER_LOCAL, self::DRIVER_S3], true) ? $driver : self::DRIVER_LOCAL;
    }

    /**
     * @param list<string> $legacyKeys
     * @return list<string>
     */
    private function candidateKeys(string $key, array $legacyKeys): array
    {
        return array_values(array_unique(array_filter(array_merge(
            [$this->normalizeKey($key)],
            array_map($this->normalizeKey(...), $legacyKeys),
        ))));
    }

    private function normalizeKey(string $key): string
    {
        return trim(str_replace('\\', '/', $key), '/');
    }

    private function s3Key(string $relative): string
    {
        $prefix = trim(str_replace('\\', '/', $this->prefix), '/');

        return '' === $prefix ? $relative : $prefix . '/' . $relative;
    }

    private function localPath(string $relative): string
    {
        return rtrim($this->localRoot, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }

    private function putLocal(string $relative, string $contents): void
    {
        $path = $this->localPath($relative);
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new ServiceUnavailableHttpException(null, 'Impossible de préparer le dossier de stockage local.');
        }
        if (false === file_put_contents($path, $contents)) {
            throw new ServiceUnavailableHttpException(null, 'Impossible d\'écrire le fichier en local.');
        }
    }

    private function headLocal(string $relative): ?int
    {
        $path = $this->localPath($relative);
        if (!is_file($path)) {
            return null;
        }

        return filemtime($path) ?: time();
    }

    private function getLocal(string $relative): ?StoredFile
    {
        $path = $this->localPath($relative);
        if (!is_file($path)) {
            return null;
        }
        $contents = file_get_contents($path);
        if (false === $contents) {
            return null;
        }

        return new StoredFile(
            $contents,
            mime_content_type($path) ?: 'application/octet-stream',
            filemtime($path) ?: null,
        );
    }

    private function deleteLocal(string $relative): void
    {
        $path = $this->localPath($relative);
        if (is_file($path)) {
            unlink($path);
        }
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function presign(string $commandName, string $key, int $expiresSeconds, array $extra = []): string
    {
        if (!$this->isS3()) {
            throw new ServiceUnavailableHttpException(null, 'Le stockage cloud n\'est pas actif.');
        }

        try {
            $command = $this->client()->getCommand($commandName, array_merge([
                'Bucket' => $this->bucket,
                'Key' => $this->s3Key($this->normalizeKey($key)),
            ], $extra));
            $request = $this->client()->createPresignedRequest($command, '+' . max(60, $expiresSeconds) . ' seconds');

            return (string) $request->getUri();
        } catch (AwsException $exception) {
            $this->logger?->error('Échec URL présignée S3.', [
                'key' => $key,
                'command' => $commandName,
                'message' => $exception->getAwsErrorMessage() ?: $exception->getMessage(),
            ]);
            throw new ServiceUnavailableHttpException(null, 'Impossible de préparer l\'accès au fichier cloud (S3).');
        }
    }

    private function client(): S3Client
    {
        if ($this->s3Client instanceof S3Client) {
            return $this->s3Client;
        }

        $config = [
            'version' => 'latest',
            'region' => '' !== trim($this->region) ? $this->region : 'eu-west-1',
        ];
        if ('' !== trim($this->accessKey) && '' !== trim($this->secretKey)) {
            $config['credentials'] = [
                'key' => $this->accessKey,
                'secret' => $this->secretKey,
            ];
        }

        $this->s3Client = new S3Client($config);

        return $this->s3Client;
    }

    private function putS3(string $relative, string $contents, string $mimeType): void
    {
        try {
            $this->client()->putObject([
                'Bucket' => $this->bucket,
                'Key' => $this->s3Key($relative),
                'Body' => $contents,
                'ContentType' => $mimeType,
                'ServerSideEncryption' => 'AES256',
            ]);
        } catch (AwsException $exception) {
            $this->logger?->error('Échec écriture S3.', [
                'key' => $relative,
                'message' => $exception->getAwsErrorMessage() ?: $exception->getMessage(),
            ]);
            throw new ServiceUnavailableHttpException(null, 'Impossible d\'enregistrer le fichier dans le cloud (S3).');
        }
    }

    private function headS3(string $relative): ?int
    {
        try {
            $result = $this->client()->headObject([
                'Bucket' => $this->bucket,
                'Key' => $this->s3Key($relative),
            ]);
            $modified = $result['LastModified'] ?? null;

            return $modified instanceof \DateTimeInterface ? $modified->getTimestamp() : time();
        } catch (AwsException $exception) {
            if ('NotFound' === $exception->getAwsErrorCode() || 404 === $exception->getStatusCode()) {
                return null;
            }
            $this->logger?->warning('Métadonnées S3 indisponibles, repli local.', [
                'key' => $relative,
                'message' => $exception->getAwsErrorMessage() ?: $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function getS3(string $relative): ?StoredFile
    {
        try {
            $result = $this->client()->getObject([
                'Bucket' => $this->bucket,
                'Key' => $this->s3Key($relative),
            ]);
            $body = $result['Body'] ?? null;
            $contents = null !== $body ? (string) $body : '';
            if ('' === $contents) {
                return null;
            }
            $modified = null;
            if (isset($result['LastModified']) && $result['LastModified'] instanceof \DateTimeInterface) {
                $modified = $result['LastModified']->getTimestamp();
            }

            return new StoredFile(
                $contents,
                (string) ($result['ContentType'] ?? 'application/octet-stream'),
                $modified,
            );
        } catch (AwsException $exception) {
            if ('NoSuchKey' === $exception->getAwsErrorCode() || 404 === $exception->getStatusCode()) {
                return null;
            }
            $this->logger?->warning('Lecture S3 impossible, repli local.', [
                'key' => $relative,
                'message' => $exception->getAwsErrorMessage() ?: $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function deleteS3(string $relative): void
    {
        try {
            $this->client()->deleteObject([
                'Bucket' => $this->bucket,
                'Key' => $this->s3Key($relative),
            ]);
        } catch (AwsException $exception) {
            $this->logger?->warning('Suppression S3 ignorée.', [
                'key' => $relative,
                'message' => $exception->getAwsErrorMessage() ?: $exception->getMessage(),
            ]);
        }
    }
}
