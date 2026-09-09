<?php

namespace App\Service\Intendance;

use App\DTO\Common\PaginatedResult;
use App\DTO\Intendance\CreateFamilleBienInput;
use App\DTO\Intendance\UpdateFamilleBienInput;
use App\DTO\Referentiel\ReferentielListQuery;
use App\Entity\FamilleBien;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Repository\BienPatrimonialRepository;
use App\Repository\FamilleBienRepository;
use App\Repository\TypeBienRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class FamilleBienService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FamilleBienRepository $familleBienRepository,
        private readonly TypeBienRepository $typeBienRepository,
        private readonly BienPatrimonialRepository $bienPatrimonialRepository,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function paginate(ReferentielListQuery $query): PaginatedResult
    {
        $errors = $this->validator->validate($query);
        if (count($errors) > 0) {
            throw new ValidationFailedException($query, $errors);
        }

        $result = $this->familleBienRepository->paginate($query->page, $query->limit, $query->search);

        return new PaginatedResult(
            array_map([$this, 'serializeSummary'], $result['items']),
            $query->page,
            $query->limit,
            $result['total'],
        );
    }

    /** @return list<array<string, mixed>> */
    public function listActifs(): array
    {
        return array_map([$this, 'serializeSummary'], $this->familleBienRepository->findActifs());
    }

    public function create(CreateFamilleBienInput $input): FamilleBien
    {
        $this->assertValid($input);
        $code = strtoupper(trim($input->code));
        if ($this->familleBienRepository->findOneBy(['code' => $code])) {
            throw new ConflictException('Ce code famille existe déjà.');
        }

        $famille = (new FamilleBien())
            ->setCode($code)
            ->setLibelle(trim($input->libelle))
            ->setOrdre($input->ordre)
            ->setStatut($this->normalizeStatut($input->statut))
            ->setSeed(false)
            ->setCreatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($famille);

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            throw new ConflictException('Ce code famille existe déjà.');
        }

        return $famille;
    }

    public function update(int $id, UpdateFamilleBienInput $input): FamilleBien
    {
        $this->assertValid($input);
        $famille = $this->getById($id);
        $famille
            ->setLibelle(trim($input->libelle))
            ->setOrdre($input->ordre)
            ->setStatut($this->normalizeStatut($input->statut));

        $this->entityManager->flush();

        return $famille;
    }

    public function delete(int $id): void
    {
        $famille = $this->getById($id);
        if ($famille->isSeed() || in_array($famille->getCode(), FamilleBien::SEED_CODES, true)) {
            throw new ConflictException('Cette famille de référence ne peut pas être supprimée.');
        }
        if ($this->typeBienRepository->countByFamille($famille) > 0) {
            throw new ConflictException('Cette famille est utilisée par au moins un type.');
        }
        if ($this->bienPatrimonialRepository->countByFamille($famille) > 0) {
            throw new ConflictException('Cette famille est utilisée par au moins un bien.');
        }

        $this->entityManager->remove($famille);
        $this->entityManager->flush();
    }

    public function getById(int $id): FamilleBien
    {
        $famille = $this->familleBienRepository->find($id);
        if (null === $famille) {
            throw new NotFoundException('Famille de bien non trouvée.');
        }

        return $famille;
    }

    /** @return array<string, mixed> */
    public function serializeSummary(FamilleBien $famille): array
    {
        return [
            'id' => $famille->getId(),
            'code' => $famille->getCode(),
            'libelle' => $famille->getLibelle(),
            'ordre' => $famille->getOrdre(),
            'statut' => $famille->getStatut(),
            'seed' => $famille->isSeed(),
            'createdAt' => $famille->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    private function assertValid(object $input): void
    {
        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new ValidationFailedException($input, $errors);
        }
    }

    private function normalizeStatut(string $statut): string
    {
        $normalized = strtoupper(trim($statut));
        if (!in_array($normalized, FamilleBien::getStatuts(), true)) {
            throw new ConflictException('Statut invalide.');
        }

        return $normalized;
    }
}
