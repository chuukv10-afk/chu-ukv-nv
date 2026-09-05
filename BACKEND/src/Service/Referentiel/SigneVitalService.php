<?php

namespace App\Service\Referentiel;

use App\DTO\Common\PaginatedResult;
use App\DTO\Referentiel\CreateSigneVitalInput;
use App\DTO\Referentiel\ReferentielListQuery;
use App\DTO\Referentiel\UpdateSigneVitalInput;
use App\Entity\SigneVital;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Repository\SigneVitalRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class SigneVitalService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SigneVitalRepository $signeVitalRepository,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function paginate(ReferentielListQuery $query): PaginatedResult
    {
        $errors = $this->validator->validate($query);
        if (count($errors) > 0) {
            throw new ValidationFailedException($query, $errors);
        }

        $result = $this->signeVitalRepository->paginate($query->page, $query->limit, $query->search);

        return new PaginatedResult(
            array_map([$this, 'serializeSummary'], $result['items']),
            $query->page,
            $query->limit,
            $result['total'],
        );
    }

    /** @return list<array<string, mixed>> */
    public function listForTriage(): array
    {
        return array_map(
            [$this, 'serializeSummary'],
            $this->signeVitalRepository->findForTriage(),
        );
    }

    public function create(CreateSigneVitalInput $input): SigneVital
    {
        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new ValidationFailedException($input, $errors);
        }

        $this->assertTriageRules($input->demandeAuTriage, $input->obligatoireAuTriage);

        $normalizedCode = strtoupper(trim($input->code));
        if ($this->signeVitalRepository->findOneBy(['code' => $normalizedCode])) {
            throw new ConflictException('Ce code signe vital existe déjà.');
        }

        $signeVital = (new SigneVital())
            ->setCode($normalizedCode)
            ->setLibelle(trim($input->libelle))
            ->setUnite($this->normalizeOptionalText($input->unite))
            ->setDemandeAuTriage($input->demandeAuTriage)
            ->setObligatoireAuTriage($input->obligatoireAuTriage)
            ->setOrdre($input->ordre)
            ->setStatut($this->normalizeStatut($input->statut))
            ->setCreatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($signeVital);

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            throw new ConflictException('Ce code signe vital existe déjà.');
        }

        return $signeVital;
    }

    public function update(int $id, UpdateSigneVitalInput $input): SigneVital
    {
        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new ValidationFailedException($input, $errors);
        }

        $this->assertTriageRules($input->demandeAuTriage, $input->obligatoireAuTriage);

        $signeVital = $this->getById($id);
        $signeVital
            ->setLibelle(trim($input->libelle))
            ->setUnite($this->normalizeOptionalText($input->unite))
            ->setDemandeAuTriage($input->demandeAuTriage)
            ->setObligatoireAuTriage($input->obligatoireAuTriage)
            ->setOrdre($input->ordre)
            ->setStatut($this->normalizeStatut($input->statut));

        $this->entityManager->flush();

        return $signeVital;
    }

    public function delete(int $id): void
    {
        $this->entityManager->remove($this->getById($id));
        $this->entityManager->flush();
    }

    public function getById(int $id): SigneVital
    {
        $signeVital = $this->signeVitalRepository->find($id);
        if (null === $signeVital) {
            throw new NotFoundException('Signe vital non trouvé.');
        }

        return $signeVital;
    }

    /** @return array<string, mixed> */
    public function serializeSummary(SigneVital $signeVital): array
    {
        return [
            'id' => $signeVital->getId(),
            'code' => $signeVital->getCode(),
            'libelle' => $signeVital->getLibelle(),
            'unite' => $signeVital->getUnite(),
            'demandeAuTriage' => $signeVital->isDemandeAuTriage(),
            'obligatoireAuTriage' => $signeVital->isObligatoireAuTriage(),
            'ordre' => $signeVital->getOrdre(),
            'statut' => $signeVital->getStatut(),
            'createdAt' => $signeVital->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    private function assertTriageRules(bool $demandeAuTriage, bool $obligatoireAuTriage): void
    {
        if ($obligatoireAuTriage && !$demandeAuTriage) {
            throw new ConflictException('Un signe vital obligatoire au triage doit d\'abord être demandé au triage.');
        }
    }

    private function normalizeStatut(string $statut): string
    {
        $normalized = strtoupper(trim($statut));
        if (!in_array($normalized, SigneVital::getStatuts(), true)) {
            throw new ConflictException('Statut invalide.');
        }

        return $normalized;
    }

    private function normalizeOptionalText(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $normalized = trim($value);

        return '' === $normalized ? null : $normalized;
    }
}
