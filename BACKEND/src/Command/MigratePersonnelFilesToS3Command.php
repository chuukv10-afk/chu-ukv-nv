<?php

namespace App\Command;

use App\Repository\PersonnelRepository;
use App\Service\Storage\ObjectStorage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:storage:migrate-personnel-files',
    description: 'Envoie les avatars et signatures déjà présents en local vers AWS S3',
)]
final class MigratePersonnelFilesToS3Command extends Command
{
    public function __construct(
        private readonly PersonnelRepository $personnelRepository,
        private readonly ObjectStorage $objectStorage,
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (!$this->objectStorage->isS3()) {
            $io->error('FILE_STORAGE doit valoir « s3 » et AWS_S3_BUCKET doit être renseigné.');

            return Command::FAILURE;
        }

        $uploads = $this->projectDir . '/var/uploads';
        $copied = 0;

        foreach ($this->personnelRepository->findAll() as $personnel) {
            $avatar = $personnel->getAvatarFilename();
            if (is_string($avatar) && '' !== trim($avatar)) {
                $copied += $this->pushIfLocal('personnel/avatars/' . $avatar, [
                    $uploads . '/personnel/' . $avatar,
                    $uploads . '/personnel/avatars/' . $avatar,
                ]) ? 1 : 0;
            }

            $signature = $personnel->getSignatureFilename();
            if (is_string($signature) && '' !== trim($signature)) {
                $copied += $this->pushIfLocal('personnel/signatures/' . $signature, [
                    $uploads . '/personnel/signatures/' . $signature,
                    $uploads . '/personnel/' . $signature,
                ]) ? 1 : 0;
            }
        }

        $io->success(sprintf('%d fichier(s) envoyé(s) vers S3.', $copied));

        return Command::SUCCESS;
    }

    /**
     * @param list<string> $localCandidates
     */
    private function pushIfLocal(string $key, array $localCandidates): bool
    {
        foreach ($localCandidates as $path) {
            if (!is_file($path)) {
                continue;
            }
            $contents = file_get_contents($path);
            if (false === $contents || '' === $contents) {
                continue;
            }
            $mime = mime_content_type($path) ?: 'application/octet-stream';
            $this->objectStorage->put($key, $contents, $mime);

            return true;
        }

        return false;
    }
}
