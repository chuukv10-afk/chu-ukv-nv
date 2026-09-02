<?php

namespace App\Service\Organisation;

use App\DTO\Organisation\CreateLitInput;
use App\DTO\Organisation\UpdateLitInput;
use App\Entity\Lit;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
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
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function delete(int $id): void
    {
        $this->eM->remove($this->getById($id));
        $this->eM->flush();
    }

    public function update(int $id, UpdateLitInput $input): Lit
    {
        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new ValidationFailedException($input, $errors);
        }

        $lit = $this->getById($id);
        $lit->setNumeroLit($input->numeroLit);
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

    /**
     * @return list<Lit>
     */
    public function findAll(): array
    {
        return $this->litRepository->findAll();
    }

    public function create(CreateLitInput $input): Lit
    {
        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new ValidationFailedException($input, $errors);
        }

        if ($this->litRepository->findOneBy(['code' => $input->code])) {
            throw new ConflictException('Ce code lit existe déjà.');
        }

        $lit = (new Lit())
            ->setCode($input->code)
            ->setNumeroLit($input->numeroLit)
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
