<?php

namespace App\Service\Organisation;

use App\DTO\Organisation\CreateChambreInput;
use App\DTO\Organisation\UpdateChambreInput;
use App\Entity\Chambre;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
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
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function delete(int $id): void
    {
        $this->eM->remove($this->getById($id));
        $this->eM->flush();
    }

    public function update(int $id, UpdateChambreInput $input): Chambre
    {
        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new ValidationFailedException($input, $errors);
        }

        $chambre = $this->getById($id);
        $chambre
            ->setLibelle($input->libelle)
            ->setType($input->type);
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

    /**
     * @return list<Chambre>
     */
    public function findAll(): array
    {
        return $this->chambreRepository->findAll();
    }

    public function create(CreateChambreInput $input): Chambre
    {
        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new ValidationFailedException($input, $errors);
        }

        if ($this->chambreRepository->findOneBy(['code' => $input->code])) {
            throw new ConflictException('Ce code chambre existe déjà.');
        }

        $chambre = (new Chambre())
            ->setCode($input->code)
            ->setLibelle($input->libelle)
            ->setType($input->type)
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
