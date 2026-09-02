<?php

namespace App\Service\Organisation;

use App\DTO\Organisation\CreateBlocInput;
use App\DTO\Organisation\UpdateBlocInput;
use App\Entity\Bloc;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Repository\BlocRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class BlocService
{
    public function __construct(
        private readonly EntityManagerInterface $eM,
        private readonly BlocRepository $blocRepository,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function delete(int $id): void
    {
        $this->eM->remove($this->getById($id));
        $this->eM->flush();
    }

    public function update(int $id, UpdateBlocInput $input): Bloc
    {
        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new ValidationFailedException($input, $errors);
        }

        $bloc = $this->getById($id);
        $bloc
            ->setLibelle($input->libelle)
            ->setChambre($input->chambre);
        $this->eM->flush();

        return $bloc;
    }

    public function getById(int $id): Bloc
    {
        $bloc = $this->blocRepository->find($id);
        if (null === $bloc) {
            throw new NotFoundException('Bloc non trouvé.');
        }

        return $bloc;
    }

    /**
     * @return list<Bloc>
     */
    public function findAll(): array
    {
        return $this->blocRepository->findAll();
    }

    public function create(CreateBlocInput $input): Bloc
    {
        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new ValidationFailedException($input, $errors);
        }

        if ($this->blocRepository->findOneBy(['code' => $input->code])) {
            throw new ConflictException('Ce code bloc existe déjà.');
        }

        $bloc = (new Bloc())
            ->setCode($input->code)
            ->setLibelle($input->libelle)
            ->setChambre($input->chambre)
            ->setCreatedAt(new \DateTimeImmutable());

        $this->eM->persist($bloc);

        try {
            $this->eM->flush();
        } catch (UniqueConstraintViolationException) {
            throw new ConflictException('Ce code bloc existe déjà.');
        }

        return $bloc;
    }
}
