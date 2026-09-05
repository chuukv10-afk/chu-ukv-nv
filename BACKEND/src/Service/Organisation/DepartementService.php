<?php



namespace App\Service\Organisation;



use App\DTO\Organisation\CreateDepartementInput;
use App\DTO\Organisation\DepartementListQuery;
use App\DTO\Organisation\UpdateDepartementInput;
use App\DTO\Common\PaginatedResult;

use App\Entity\Departement;

use App\Exception\ConflictException;

use App\Exception\NotFoundException;

use App\Repository\DepartementRepository;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

use Doctrine\ORM\EntityManagerInterface;

use Symfony\Component\Validator\Exception\ValidationFailedException;

use Symfony\Component\Validator\Validator\ValidatorInterface;



final class DepartementService

{

    public function __construct(

        private readonly EntityManagerInterface $eM,

        private readonly DepartementRepository $departementRepository,

        private readonly ValidatorInterface $validator,

    ) {}



    public function delete(int $id): void
    {
        $departement = $this->getById($id);

        if (!$departement->getServices()->isEmpty()) {
            throw new ConflictException('Ce département contient encore des services et ne peut pas être supprimé.');
        }

        $this->eM->remove($departement);
        $this->eM->flush();
    }



    public function update(int $id, UpdateDepartementInput $input): Departement

    {

        $errors = $this->validator->validate($input);

        if (count($errors) > 0) {

            throw new ValidationFailedException($input, $errors);

        }



        $departement = $this->getById($id);
        $normalizedCode = strtoupper(trim($input->code));

        $existing = $this->departementRepository->findOneBy(['code' => $normalizedCode]);
        if (null !== $existing && $existing->getId() !== $departement->getId()) {
            throw new ConflictException('Ce code département existe déjà.');
        }

        $departement
            ->setCode($normalizedCode)
            ->setLibelle(trim($input->libelle))
            ->setType(strtoupper(trim($input->type)));



        $this->eM->flush();



        return $departement;

    }



    public function getById(int $id): Departement

    {

        $departement = $this->departementRepository->find($id);

        if (null === $departement) {

            throw new NotFoundException('Département non trouvé.');

        }



        return $departement;

    }



    public function findByCode(string $code): ?Departement

    {

        return $this->departementRepository->findOneBy(['code' => $code]);

    }



    public function findById(int $id): ?Departement

    {

        return $this->departementRepository->find($id);

    }



    /**

     * @return list<Departement>

     */

    public function findAll(): array
    {
        return $this->departementRepository->findBy([], ['libelle' => 'ASC']);
    }

    public function paginate(DepartementListQuery $query): PaginatedResult
    {
        $errors = $this->validator->validate($query);
        if (count($errors) > 0) {
            throw new ValidationFailedException($query, $errors);
        }

        $result = $this->departementRepository->paginate(
            $query->page,
            $query->limit,
            $query->search,
            $query->type,
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
    public function buildExportRows(DepartementListQuery $query): array
    {
        $errors = $this->validator->validate($query);
        if (count($errors) > 0) {
            throw new ValidationFailedException($query, $errors);
        }

        $items = $this->departementRepository->findForExport($query->search, $query->type);

        return array_map(
            fn (Departement $departement): array => $this->buildExportRow($departement),
            $items,
        );
    }

    /**
     * @return list<string|null>
     */
    public function buildExportRow(Departement $departement): array
    {
        return [
            $departement->getCode(),
            $departement->getLibelle(),
            $departement->getType(),
            (string) $departement->getServices()->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeSummary(Departement $departement): array
    {
        return [
            'id' => $departement->getId(),
            'code' => $departement->getCode(),
            'libelle' => $departement->getLibelle(),
            'type' => $departement->getType(),
            'servicesCount' => $departement->getServices()->count(),
            'createdAt' => $departement->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }



    public function findOneBy(array $criteria): ?Departement

    {

        return $this->departementRepository->findOneBy($criteria);

    }



    public function findOneByOrFail(array $criteria): Departement

    {

        $departement = $this->departementRepository->findOneBy($criteria);

        if (null === $departement) {

            throw new NotFoundException('Département non trouvé.');

        }



        return $departement;

    }



    public function create(CreateDepartementInput $input): Departement

    {

        $errors = $this->validator->validate($input);

        if (count($errors) > 0) {

            throw new ValidationFailedException($input, $errors);

        }



        if ($this->departementRepository->findOneBy(['code' => $input->code])) {

            throw new ConflictException('Ce code département existe déjà.');

        }



        $departement = (new Departement())
            ->setCode(strtoupper(trim($input->code)))
            ->setLibelle(trim($input->libelle))
            ->setType(strtoupper(trim($input->type)))
            ->setCreatedAt(new \DateTimeImmutable());



        $this->eM->persist($departement);



        try {

            $this->eM->flush();

        } catch (UniqueConstraintViolationException) {

            throw new ConflictException('Ce code département existe déjà.');

        }



        return $departement;

    }

}


