<?php

namespace App\Service\Intendance;

use App\DTO\Common\PaginatedResult;
use App\DTO\Intendance\CreateTypeBienInput;
use App\DTO\Intendance\TypeBienListQuery;
use App\DTO\Intendance\UpdateTypeBienInput;
use App\Entity\TypeBien;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Repository\BienPatrimonialRepository;
use App\Repository\FamilleBienRepository;
use App\Repository\TypeBienRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class TypeBienService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TypeBienRepository $typeBienRepository,
        private readonly FamilleBienRepository $familleBienRepository,
        private readonly BienPatrimonialRepository $bienPatrimonialRepository,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function paginate(TypeBienListQuery $query): PaginatedResult
    {
        $this->assertValid($query);
        $result = $this->typeBienRepository->paginate($query->page, $query->limit, $query->search, $query->familleId);

        return new PaginatedResult(
            array_map([$this, 'serializeSummary'], $result['items']),
            $query->page,
            $query->limit,
            $result['total'],
        );
    }

    /** @return list<array<string, mixed>> */
    public function listActifs(?int $familleId = null): array
    {
        return array_map([$this, 'serializeSummary'], $this->typeBienRepository->findActifs($familleId));
    }

    public function create(CreateTypeBienInput $input): TypeBien
    {
        $this->assertValid($input);
        $code = strtoupper(trim($input->code));
        if ($this->typeBienRepository->findOneBy(['code' => $code])) {
            throw new ConflictException('Ce code type existe déjà.');
        }

        $type = (new TypeBien())
            ->setCode($code)
            ->setLibelle(trim($input->libelle))
            ->setFamille($this->requireFamille($input->familleId))
            ->setOrdre($input->ordre)
            ->setStatut($this->normalizeStatut($input->statut))
            ->setSeed(false)
            ->setCreatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($type);

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            throw new ConflictException('Ce code type existe déjà.');
        }

        return $type;
    }

    public function update(int $id, UpdateTypeBienInput $input): TypeBien
    {
        $this->assertValid($input);
        $type = $this->getById($id);
        $type
            ->setLibelle(trim($input->libelle))
            ->setFamille($this->requireFamille($input->familleId))
            ->setOrdre($input->ordre)
            ->setStatut($this->normalizeStatut($input->statut));

        $this->entityManager->flush();

        return $type;
    }

    public function delete(int $id): void
    {
        $type = $this->getById($id);
        if ($type->isSeed()) {
            throw new ConflictException('Ce type de référence ne peut pas être supprimé.');
        }
        if ($this->bienPatrimonialRepository->countByType($type) > 0) {
            throw new ConflictException('Ce type est utilisé par au moins un bien.');
        }

        $this->entityManager->remove($type);
        $this->entityManager->flush();
    }

    public function getById(int $id): TypeBien
    {
        $type = $this->typeBienRepository->find($id);
        if (null === $type) {
            throw new NotFoundException('Type de bien non trouvé.');
        }

        return $type;
    }

    /** @return array<string, mixed> */
    public function serializeSummary(TypeBien $type): array
    {
        $famille = $type->getFamille();

        return [
            'id' => $type->getId(),
            'code' => $type->getCode(),
            'libelle' => $type->getLibelle(),
            'ordre' => $type->getOrdre(),
            'statut' => $type->getStatut(),
            'seed' => $type->isSeed(),
            'famille' => $famille ? [
                'id' => $famille->getId(),
                'code' => $famille->getCode(),
                'libelle' => $famille->getLibelle(),
            ] : null,
            'createdAt' => $type->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    private function requireFamille(int $id): \App\Entity\FamilleBien
    {
        $famille = $this->familleBienRepository->find($id);
        if (null === $famille) {
            throw new NotFoundException('Famille de bien non trouvée.');
        }

        return $famille;
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
        if (!in_array($normalized, TypeBien::getStatuts(), true)) {
            throw new ConflictException('Statut invalide.');
        }

        return $normalized;
    }
}
