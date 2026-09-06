<?php

namespace App\Service\Pharmacie;

use App\DTO\Common\PaginatedResult;
use App\DTO\Pharmacie\CreateFournisseurInput;
use App\DTO\Pharmacie\PharmacieListQuery;
use App\DTO\Pharmacie\UpdateFournisseurInput;
use App\Entity\Fournisseur;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Repository\FournisseurRepository;
use App\Repository\ReceptionRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class FournisseurService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FournisseurRepository $fournisseurRepository,
        private readonly ReceptionRepository $receptionRepository,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function paginate(PharmacieListQuery $query): PaginatedResult
    {
        $this->assertValid($query);
        $result = $this->fournisseurRepository->paginate($query->page, $query->limit, $query->search);

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
        return array_map([$this, 'serializeSummary'], $this->fournisseurRepository->findActifs());
    }

    public function create(CreateFournisseurInput $input): Fournisseur
    {
        $this->assertValid($input);
        $code = strtoupper(trim($input->code));
        if ($this->fournisseurRepository->findOneBy(['code' => $code])) {
            throw new ConflictException('Ce code fournisseur existe déjà.');
        }

        $fournisseur = (new Fournisseur())
            ->setCode($code)
            ->setCreatedAt(new \DateTimeImmutable());
        $this->apply($fournisseur, $input->libelle, $input->telephone, $input->adresse, $input->statut);

        $this->entityManager->persist($fournisseur);
        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            throw new ConflictException('Ce code fournisseur existe déjà.');
        }

        return $fournisseur;
    }

    public function update(int $id, UpdateFournisseurInput $input): Fournisseur
    {
        $this->assertValid($input);
        $fournisseur = $this->getById($id);
        $this->apply($fournisseur, $input->libelle, $input->telephone, $input->adresse, $input->statut);
        $this->entityManager->flush();

        return $fournisseur;
    }

    public function delete(int $id): void
    {
        $fournisseur = $this->getById($id);
        if ($this->receptionRepository->countByFournisseur($fournisseur) > 0) {
            throw new ConflictException('Ce fournisseur a déjà des réceptions.');
        }
        $this->entityManager->remove($fournisseur);
        $this->entityManager->flush();
    }

    public function getById(int $id): Fournisseur
    {
        $fournisseur = $this->fournisseurRepository->find($id);
        if (null === $fournisseur) {
            throw new NotFoundException('Fournisseur non trouvé.');
        }

        return $fournisseur;
    }

    /** @return array<string, mixed> */
    public function serializeSummary(Fournisseur $fournisseur): array
    {
        return [
            'id' => $fournisseur->getId(),
            'code' => $fournisseur->getCode(),
            'libelle' => $fournisseur->getLibelle(),
            'telephone' => $fournisseur->getTelephone(),
            'adresse' => $fournisseur->getAdresse(),
            'statut' => $fournisseur->getStatut(),
            'createdAt' => $fournisseur->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    private function apply(
        Fournisseur $fournisseur,
        string $libelle,
        ?string $telephone,
        ?string $adresse,
        string $statut,
    ): void {
        $normalized = strtoupper(trim($statut));
        if (!in_array($normalized, Fournisseur::getStatuts(), true)) {
            throw new ConflictException('Statut invalide.');
        }

        $fournisseur
            ->setLibelle(trim($libelle))
            ->setTelephone($this->nullable($telephone))
            ->setAdresse($this->nullable($adresse))
            ->setStatut($normalized);
    }

    private function nullable(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return '' === $trimmed ? null : $trimmed;
    }

    private function assertValid(object $value): void
    {
        $errors = $this->validator->validate($value);
        if (count($errors) > 0) {
            throw new ValidationFailedException($value, $errors);
        }
    }
}
