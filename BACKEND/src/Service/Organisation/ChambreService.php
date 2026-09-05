<?php

namespace App\Service\Organisation;

use App\DTO\Common\PaginatedResult;
use App\DTO\Organisation\CreateChambreInput;
use App\DTO\Organisation\OrganisationListQuery;
use App\DTO\Organisation\UpdateChambreInput;
use App\Entity\Chambre;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Repository\BlocRepository;
use App\Repository\ChambreRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ChambreService
{
    public function __construct(
        private readonly EntityManagerInterface $eM,
        private readonly ChambreRepository $chambreRepository,
        private readonly BlocRepository $blocRepository,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function delete(int $id): void
    {
        $chambre = $this->getById($id);
        if (!$chambre->getLits()->isEmpty()) {
            throw new ConflictException('Cette chambre contient encore des lits et ne peut pas être supprimée.');
        }

        $this->eM->remove($chambre);
        $this->eM->flush();
    }

    public function update(int $id, UpdateChambreInput $input): Chambre
    {
        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new ValidationFailedException($input, $errors);
        }

        $chambre = $this->getById($id);
        $bloc = $this->blocRepository->find($input->blocId);
        if (null === $bloc) {
            throw new NotFoundException('Bloc non trouvé.');
        }

        $chambre
            ->setLibelle(trim($input->libelle))
            ->setType(strtoupper(trim($input->type)))
            ->setBloc($bloc);
        $this->eM->flush();

        return $chambre;
    }

    public function getById(int $id): Chambre
    {
        $chambre = $this->chambreRepository->find($id);
        if (null === $chambre) {
            throw new NotFoundException('Chambre non trouvée.');
        }

        return $chambre;
    }

    /** @return list<Chambre> */
    public function findAll(): array
    {
        return $this->chambreRepository->findBy([], ['libelle' => 'ASC']);
    }

    public function paginate(OrganisationListQuery $query): PaginatedResult
    {
        $errors = $this->validator->validate($query);
        if (count($errors) > 0) {
            throw new ValidationFailedException($query, $errors);
        }

        $result = $this->chambreRepository->paginate(
            $query->page,
            $query->limit,
            $query->search,
            $query->type,
            $query->blocId,
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
    public function buildExportRows(OrganisationListQuery $query): array
    {
        $errors = $this->validator->validate($query);
        if (count($errors) > 0) {
            throw new ValidationFailedException($query, $errors);
        }

        $items = $this->chambreRepository->findForExport(
            $query->search,
            $query->type,
            $query->blocId,
        );

        return array_map(
            fn (Chambre $chambre): array => $this->buildExportRow($chambre),
            $items,
        );
    }

    /**
     * @return list<string|null>
     */
    public function buildExportRow(Chambre $chambre): array
    {
        return [
            $chambre->getCode(),
            $chambre->getLibelle(),
            $chambre->getType(),
            $chambre->getBloc()?->getLibelle(),
            (string) $chambre->getLits()->count(),
        ];
    }

    /** @return array<string, mixed> */
    public function serializeSummary(Chambre $chambre): array
    {
        return [
            'id' => $chambre->getId(),
            'code' => $chambre->getCode(),
            'libelle' => $chambre->getLibelle(),
            'type' => $chambre->getType(),
            'blocId' => $chambre->getBloc()?->getId(),
            'bloc' => null !== $chambre->getBloc() ? [
                'id' => $chambre->getBloc()->getId(),
                'code' => $chambre->getBloc()->getCode(),
                'libelle' => $chambre->getBloc()->getLibelle(),
            ] : null,
            'litsCount' => $chambre->getLits()->count(),
            'createdAt' => $chambre->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    public function create(CreateChambreInput $input): Chambre
    {
        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new ValidationFailedException($input, $errors);
        }

        $bloc = $this->blocRepository->find($input->blocId);
        if (null === $bloc) {
            throw new NotFoundException('Bloc non trouvé.');
        }

        $normalizedCode = strtoupper(trim($input->code));
        if ($this->chambreRepository->findOneBy(['code' => $normalizedCode])) {
            throw new ConflictException('Ce code chambre existe déjà.');
        }

        $chambre = (new Chambre())
            ->setCode($normalizedCode)
            ->setLibelle(trim($input->libelle))
            ->setType(strtoupper(trim($input->type)))
            ->setBloc($bloc)
            ->setCreatedAt(new \DateTimeImmutable());

        $this->eM->persist($chambre);

        try {
            $this->eM->flush();
        } catch (UniqueConstraintViolationException) {
            throw new ConflictException('Ce code chambre existe déjà.');
        }

        return $chambre;
    }
}
