<?php

namespace App\Service\Patient;

use App\DTO\Common\PaginatedResult;
use App\DTO\Patient\AntecedentListQuery;
use App\DTO\Patient\CreateAntecedentInput;
use App\Entity\Antecedent;
use App\Entity\Dpi;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Repository\AntecedentRepository;
use App\Repository\MaladieRepository;
use App\Repository\TypeAntecedentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class AntecedentService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AntecedentRepository $antecedentRepository,
        private readonly TypeAntecedentRepository $typeAntecedentRepository,
        private readonly MaladieRepository $maladieRepository,
        private readonly PatientService $patientService,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function paginateByPatientId(string $patientId, AntecedentListQuery $query): PaginatedResult
    {
        $dpi = $this->patientService->getDpiByPatientId($patientId);

        return $this->paginateByDpi((int) $dpi->getId(), $query);
    }

    public function paginateByDpi(int $dpiId, AntecedentListQuery $query): PaginatedResult
    {
        $errors = $this->validator->validate($query);
        if (count($errors) > 0) {
            throw new ValidationFailedException($query, $errors);
        }

        $result = $this->antecedentRepository->paginateByDpi(
            $dpiId,
            $query->page,
            $query->limit,
            $query->typeId,
        );

        return new PaginatedResult(
            array_map([$this, 'serializeSummary'], $result['items']),
            $query->page,
            $query->limit,
            $result['total'],
        );
    }

    public function createForPatient(string $patientId, CreateAntecedentInput $input): array
    {
        $dpi = $this->patientService->getDpiByPatientId($patientId);

        return $this->serializeSummary($this->createForDpi($dpi, $input));
    }

    public function createForDpi(Dpi $dpi, CreateAntecedentInput $input): Antecedent
    {
        $this->assertDpiWritable($dpi);

        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new ValidationFailedException($input, $errors);
        }

        $type = $this->typeAntecedentRepository->find((int) $input->typeId);
        if (null === $type) {
            throw new NotFoundException('Type d\'antécédent non trouvé.');
        }

        $maladie = $this->maladieRepository->find((int) $input->maladieId);
        if (null === $maladie) {
            throw new NotFoundException('Maladie non trouvée.');
        }

        $dpiId = (int) $dpi->getId();
        if (null !== $this->antecedentRepository->findDuplicate($dpiId, (int) $type->getId(), (int) $maladie->getId())) {
            throw new ConflictException('Cet antécédent est déjà enregistré pour ce patient.');
        }

        $antecedent = (new Antecedent())
            ->setDpi($dpi)
            ->setType($type)
            ->setMaladie($maladie)
            ->setCreatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($antecedent);
        $this->entityManager->flush();

        return $antecedent;
    }

    public function deleteForPatient(string $patientId, int $antecedentId): void
    {
        $dpi = $this->patientService->getDpiByPatientId($patientId);
        $this->deleteForDpi($dpi, $antecedentId);
    }

    public function deleteForDpi(Dpi $dpi, int $antecedentId): void
    {
        $this->assertDpiWritable($dpi);

        $antecedent = $this->antecedentRepository->find($antecedentId);
        if (null === $antecedent || (int) $antecedent->getDpi()?->getId() !== (int) $dpi->getId()) {
            throw new NotFoundException('Antécédent non trouvé.');
        }

        $this->entityManager->remove($antecedent);
        $this->entityManager->flush();
    }

    /** @return array<string, mixed> */
    public function serializeSummary(Antecedent $antecedent): array
    {
        $type = $antecedent->getType();
        $maladie = $antecedent->getMaladie();

        return [
            'id' => $antecedent->getId(),
            'type' => null === $type ? null : [
                'id' => $type->getId(),
                'code' => $type->getCode(),
                'libelle' => $type->getLibelle(),
            ],
            'maladie' => null === $maladie ? null : [
                'id' => $maladie->getId(),
                'codeCim10' => $maladie->getCodeCim10(),
                'libelle' => $maladie->getLibelle(),
            ],
            'createdAt' => $antecedent->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    private function assertDpiWritable(Dpi $dpi): void
    {
        $patient = $dpi->getPatient();
        if (null !== $patient && !$patient->isClinicallyWritable()) {
            if ($patient->isDeceased()) {
                throw new ConflictException('Ce patient est décédé. Le dossier clinique est en lecture seule.');
            }

            throw new ConflictException('Ce patient est inactif. Réactivez-le pour modifier le dossier clinique.');
        }

        if (!$dpi->isWritable()) {
            throw new ConflictException('Ce dossier patient n\'est plus ouvert. Lecture seule.');
        }
    }
}
