<?php

namespace App\Service\Clinique;

use App\DTO\Clinique\CreateExamenInput;
use App\DTO\Clinique\ExamenListQuery;
use App\DTO\Clinique\UpdateExamenInput;
use App\DTO\Common\PaginatedResult;
use App\Entity\Examen;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Repository\ExamenRepository;
use App\Repository\TypeExamenRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ExamenService
{
    public function __construct(
        private readonly EntityManagerInterface $eM,
        private readonly ExamenRepository $examenRepository,
        private readonly TypeExamenRepository $typeExamenRepository,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function delete(int $id): void
    {
        $examen = $this->getById($id);
        if (!$examen->getDemandeExamens()->isEmpty()) {
            throw new ConflictException('Cet examen est encore utilisé dans des demandes et ne peut pas être supprimé.');
        }

        $this->eM->remove($examen);
        $this->eM->flush();
    }

    public function update(int $id, UpdateExamenInput $input): Examen
    {
        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new ValidationFailedException($input, $errors);
        }

        $examen = $this->getById($id);
        $typeExamen = $this->typeExamenRepository->find($input->typeExamenId);
        if (null === $typeExamen) {
            throw new NotFoundException('Catégorie d\'examen non trouvée.');
        }

        $examen
            ->setLibelle(trim($input->libelle))
            ->setTypeExamen($typeExamen);
        $this->eM->flush();

        return $examen;
    }

    public function getById(int $id): Examen
    {
        $examen = $this->examenRepository->find($id);
        if (null === $examen) {
            throw new NotFoundException('Examen non trouvé.');
        }

        return $examen;
    }

    public function paginate(ExamenListQuery $query): PaginatedResult
    {
        $errors = $this->validator->validate($query);
        if (count($errors) > 0) {
            throw new ValidationFailedException($query, $errors);
        }

        $result = $this->examenRepository->paginate(
            $query->page,
            $query->limit,
            $query->search,
            $query->typeExamenId,
        );

        return new PaginatedResult(
            array_map([$this, 'serializeSummary'], $result['items']),
            $query->page,
            $query->limit,
            $result['total'],
        );
    }

    /**
     * @return list<list<string|null>>
     */
    public function buildExportRows(ExamenListQuery $query): array
    {
        $errors = $this->validator->validate($query);
        if (count($errors) > 0) {
            throw new ValidationFailedException($query, $errors);
        }

        $items = $this->examenRepository->findForExport($query->search, $query->typeExamenId);

        return array_map(
            fn (Examen $examen): array => $this->buildExportRow($examen),
            $items,
        );
    }

    /**
     * @return list<string|null>
     */
    public function buildExportRow(Examen $examen): array
    {
        return [
            $examen->getCode(),
            $examen->getLibelle(),
            $examen->getTypeExamen()?->getLibelle(),
            (string) $examen->getDemandeExamens()->count(),
        ];
    }

    /** @return array<string, mixed> */
    public function serializeSummary(Examen $examen): array
    {
        return [
            'id' => $examen->getId(),
            'code' => $examen->getCode(),
            'libelle' => $examen->getLibelle(),
            'typeExamenId' => $examen->getTypeExamen()?->getId(),
            'typeExamen' => null !== $examen->getTypeExamen() ? [
                'id' => $examen->getTypeExamen()->getId(),
                'code' => $examen->getTypeExamen()->getCode(),
                'libelle' => $examen->getTypeExamen()->getLibelle(),
            ] : null,
            'demandeExamenCount' => $examen->getDemandeExamens()->count(),
            'createdAt' => $examen->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    public function create(CreateExamenInput $input): Examen
    {
        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new ValidationFailedException($input, $errors);
        }

        $typeExamen = $this->typeExamenRepository->find($input->typeExamenId);
        if (null === $typeExamen) {
            throw new NotFoundException('Catégorie d\'examen non trouvée.');
        }

        $normalizedCode = strtoupper(trim($input->code));
        if ($this->examenRepository->findOneBy(['code' => $normalizedCode])) {
            throw new ConflictException('Ce code examen existe déjà.');
        }

        $examen = (new Examen())
            ->setCode($normalizedCode)
            ->setLibelle(trim($input->libelle))
            ->setTypeExamen($typeExamen)
            ->setCreatedAt(new \DateTimeImmutable());

        $this->eM->persist($examen);

        try {
            $this->eM->flush();
        } catch (UniqueConstraintViolationException) {
            throw new ConflictException('Ce code examen existe déjà.');
        }

        return $examen;
    }
}
