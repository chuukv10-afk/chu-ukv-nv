<?php

namespace App\Service\Organisation;

use App\DTO\Common\PaginatedResult;
use App\DTO\Organisation\CreateLitInput;
use App\DTO\Organisation\OrganisationListQuery;
use App\DTO\Organisation\UpdateLitInput;
use App\Entity\Lit;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Repository\ChambreRepository;
use App\Repository\LitRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class LitService
{
    public function __construct(
        private readonly EntityManagerInterface $eM,
        private readonly LitRepository $litRepository,
        private readonly ChambreRepository $chambreRepository,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function delete(int $id): void
    {
        $lit = $this->getById($id);
        if (!$lit->getVisites()->isEmpty()) {
            throw new ConflictException('Ce lit est encore affecté à des visites et ne peut pas être supprimé.');
        }

        $this->eM->remove($lit);
        $this->eM->flush();
    }

    public function update(int $id, UpdateLitInput $input): Lit
    {
        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new ValidationFailedException($input, $errors);
        }

        $lit = $this->getById($id);
        $chambre = $this->chambreRepository->find($input->chambreId);
        if (null === $chambre) {
            throw new NotFoundException('Chambre non trouvée.');
        }

        $lit
            ->setNumeroLit(trim($input->numeroLit))
            ->setChambre($chambre);
        $this->eM->flush();

        return $lit;
    }

    public function getById(int $id): Lit
    {
        $lit = $this->litRepository->find($id);
        if (null === $lit) {
            throw new NotFoundException('Lit non trouvé.');
        }

        return $lit;
    }

    /** @return list<Lit> */
    public function findAll(): array
    {
        return $this->litRepository->findBy([], ['numeroLit' => 'ASC']);
    }

    public function paginate(OrganisationListQuery $query): PaginatedResult
    {
        $errors = $this->validator->validate($query);
        if (count($errors) > 0) {
            throw new ValidationFailedException($query, $errors);
        }

        $result = $this->litRepository->paginate($query->page, $query->limit, $query->search, $query->chambreId);

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
    public function buildExportRows(OrganisationListQuery $query): array
    {
        $errors = $this->validator->validate($query);
        if (count($errors) > 0) {
            throw new ValidationFailedException($query, $errors);
        }

        $items = $this->litRepository->findForExport($query->search, $query->chambreId);

        return array_map(
            fn (Lit $lit): array => $this->buildExportRow($lit),
            $items,
        );
    }

    /**
     * @return list<string|null>
     */
    public function buildExportRow(Lit $lit): array
    {
        return [
            $lit->getCode(),
            $lit->getNumeroLit(),
            $lit->getChambre()?->getBloc()?->getLibelle(),
            $lit->getChambre()?->getLibelle(),
            (string) $lit->getVisites()->count(),
        ];
    }

    /** @return array<string, mixed> */
    public function serializeSummary(Lit $lit): array
    {
        return [
            'id' => $lit->getId(),
            'code' => $lit->getCode(),
            'numeroLit' => $lit->getNumeroLit(),
            'chambreId' => $lit->getChambre()?->getId(),
            'chambre' => null !== $lit->getChambre() ? [
                'id' => $lit->getChambre()->getId(),
                'code' => $lit->getChambre()->getCode(),
                'libelle' => $lit->getChambre()->getLibelle(),
            ] : null,
            'blocId' => $lit->getChambre()?->getBloc()?->getId(),
            'bloc' => null !== $lit->getChambre()?->getBloc() ? [
                'id' => $lit->getChambre()->getBloc()->getId(),
                'code' => $lit->getChambre()->getBloc()->getCode(),
                'libelle' => $lit->getChambre()->getBloc()->getLibelle(),
            ] : null,
            'visiteCount' => $lit->getVisites()->count(),
            'createdAt' => $lit->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    public function create(CreateLitInput $input): Lit
    {
        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new ValidationFailedException($input, $errors);
        }

        $chambre = $this->chambreRepository->find($input->chambreId);
        if (null === $chambre) {
            throw new NotFoundException('Chambre non trouvée.');
        }

        $normalizedCode = strtoupper(trim($input->code));
        if ($this->litRepository->findOneBy(['code' => $normalizedCode])) {
            throw new ConflictException('Ce code lit existe déjà.');
        }

        $lit = (new Lit())
            ->setCode($normalizedCode)
            ->setNumeroLit(trim($input->numeroLit))
            ->setChambre($chambre)
            ->setCreatedAt(new \DateTimeImmutable());

        $this->eM->persist($lit);

        try {
            $this->eM->flush();
        } catch (UniqueConstraintViolationException) {
            throw new ConflictException('Ce code lit existe déjà.');
        }

        return $lit;
    }
}
