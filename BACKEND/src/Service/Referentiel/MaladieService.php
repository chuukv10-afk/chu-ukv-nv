<?php

namespace App\Service\Referentiel;

use App\DTO\Clinique\BulkDeleteMaladieInput;
use App\DTO\Clinique\CreateMaladieInput;
use App\DTO\Clinique\MaladieListQuery;
use App\DTO\Clinique\UpdateMaladieInput;
use App\DTO\Common\PaginatedResult;
use App\Entity\Maladie;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Repository\MaladieRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class MaladieService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MaladieRepository $maladieRepository,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function paginate(MaladieListQuery $query): PaginatedResult
    {
        $errors = $this->validator->validate($query);
        if (count($errors) > 0) {
            throw new ValidationFailedException($query, $errors);
        }

        $result = $this->maladieRepository->paginate(
            $query->page,
            $query->limit,
            $query->search,
            $query->chapitre,
        );

        return new PaginatedResult(
            array_map([$this, 'serializeSummary'], $result['items']),
            $query->page,
            $query->limit,
            $result['total'],
        );
    }

    /** @return list<string> */
    public function listChapitres(): array
    {
        return $this->maladieRepository->findDistinctChapitres();
    }

    /**
     * @return list<list<string|null>>
     */
    public function buildExportRows(MaladieListQuery $query): array
    {
        $errors = $this->validator->validate($query);
        if (count($errors) > 0) {
            throw new ValidationFailedException($query, $errors);
        }

        $items = $this->maladieRepository->findForExport($query->search, $query->chapitre);

        return array_map(
            fn (Maladie $maladie): array => $this->buildExportRow($maladie),
            $items,
        );
    }

    /** @return list<string|null> */
    public function buildExportRow(Maladie $maladie): array
    {
        return [
            $maladie->getCodeCim10(),
            $maladie->getLibelle(),
            $maladie->getChapitre(),
        ];
    }

    public function create(CreateMaladieInput $input): Maladie
    {
        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new ValidationFailedException($input, $errors);
        }

        $normalizedCode = strtoupper(trim($input->codeCim10));
        if ($this->maladieRepository->findOneBy(['code_cim10' => $normalizedCode])) {
            throw new ConflictException('Ce code CIM-10 existe déjà.');
        }

        $maladie = (new Maladie())
            ->setCodeCim10($normalizedCode)
            ->setLibelle(trim($input->libelle))
            ->setChapitre($this->normalizeChapitre($input->chapitre))
            ->setCreatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($maladie);

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            throw new ConflictException('Ce code CIM-10 existe déjà.');
        }

        return $maladie;
    }

    public function update(int $id, UpdateMaladieInput $input): Maladie
    {
        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new ValidationFailedException($input, $errors);
        }

        $maladie = $this->getById($id);
        $maladie
            ->setLibelle(trim($input->libelle))
            ->setChapitre($this->normalizeChapitre($input->chapitre));
        $this->entityManager->flush();

        return $maladie;
    }

    public function delete(int $id): void
    {
        $maladie = $this->getById($id);
        $this->assertDeletable($maladie);
        $this->entityManager->remove($maladie);
        $this->entityManager->flush();
    }

    /**
     * @return array{deleted: int, blocked: int, notFound: int}
     */
    public function deleteMany(BulkDeleteMaladieInput $input): array
    {
        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new ValidationFailedException($input, $errors);
        }

        $deleted = 0;
        $blocked = 0;
        $notFound = 0;

        foreach ($input->ids as $id) {
            $maladie = $this->maladieRepository->find($id);
            if (null === $maladie) {
                ++$notFound;
                continue;
            }

            try {
                $this->assertDeletable($maladie);
            } catch (ConflictException) {
                ++$blocked;
                continue;
            }

            $this->entityManager->remove($maladie);
            ++$deleted;
        }

        if ($deleted > 0) {
            $this->entityManager->flush();
        }

        return [
            'deleted' => $deleted,
            'blocked' => $blocked,
            'notFound' => $notFound,
        ];
    }

    public function getById(int $id): Maladie
    {
        $maladie = $this->maladieRepository->find($id);
        if (null === $maladie) {
            throw new NotFoundException('Maladie non trouvée.');
        }

        return $maladie;
    }

    /** @return array<string, mixed> */
    public function serializeSummary(Maladie $maladie): array
    {
        return [
            'id' => $maladie->getId(),
            'codeCim10' => $maladie->getCodeCim10(),
            'libelle' => $maladie->getLibelle(),
            'chapitre' => $maladie->getChapitre(),
            'antecedentCount' => $maladie->getDpi()->count(),
            'diagnosticCount' => $maladie->getDiagnostics()->count(),
            'createdAt' => $maladie->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    private function assertDeletable(Maladie $maladie): void
    {
        if (!$maladie->getDpi()->isEmpty() || !$maladie->getDiagnostics()->isEmpty()) {
            throw new ConflictException('Cette maladie est utilisée dans des antécédents ou diagnostics et ne peut pas être supprimée.');
        }
    }

    private function normalizeChapitre(?string $chapitre): ?string
    {
        if (null === $chapitre) {
            return null;
        }

        $normalized = trim(str_replace(['[', ']'], '', $chapitre));

        return '' === $normalized ? null : mb_substr($normalized, 0, 20);
    }
}
