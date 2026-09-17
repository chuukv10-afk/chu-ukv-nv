<?php

namespace App\Service\Referentiel;

use App\DTO\Referentiel\CreateFonctionInput;
use App\DTO\Referentiel\UpdateFonctionInput;
use App\Entity\Fonction;
use App\Entity\Service;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Referentiel\FonctionCatalog;
use App\Repository\FonctionRepository;
use App\Repository\ServiceRepository;
use App\Service\Support\AbstractCodeLibelleCrudService;
use App\Service\Support\ReferentielPaginateTrait;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/** @extends AbstractCodeLibelleCrudService<Fonction> */
final class FonctionService extends AbstractCodeLibelleCrudService
{
    use ReferentielPaginateTrait;

    public function __construct(
        EntityManagerInterface $eM,
        private readonly FonctionRepository $fonctionRepository,
        private readonly ServiceRepository $serviceRepository,
        ValidatorInterface $validator,
    ) {
        parent::__construct($eM, $validator);
    }

    public function delete(int $id): void
    {
        $fonction = $this->getById($id);
        if (!$fonction->getPersonnels()->isEmpty()) {
            throw new ConflictException('Cette fonction est encore affectée à du personnel et ne peut pas être supprimée.');
        }

        parent::delete($id);
    }

    public function serializeSummary(object $entity): array
    {
        /** @var Fonction $entity */
        $service = $entity->getService();

        return [
            ...parent::serializeSummary($entity),
            'personnelCount' => $entity->getPersonnels()->count(),
            'serviceId' => $service?->getId(),
            'service' => null !== $service ? [
                'id' => $service->getId(),
                'code' => $service->getCode(),
                'libelle' => $service->getLibelle(),
            ] : null,
        ];
    }

    public function create(object $input): object
    {
        /** @var Fonction $entity */
        $entity = parent::create($input);
        if ($input instanceof CreateFonctionInput) {
            $entity->setService($this->resolveService($input->serviceId));
            $this->eM->flush();
        }

        return $entity;
    }

    public function update(int $id, object $input): object
    {
        /** @var Fonction $entity */
        $entity = parent::update($id, $input);
        if ($input instanceof UpdateFonctionInput) {
            $entity->setService($this->resolveService($input->serviceId));
            $this->eM->flush();
        }

        return $entity;
    }

    /**
     * @return array{created: int, skipped: int, linked: int}
     */
    public function syncCatalog(): array
    {
        $created = 0;
        $skipped = 0;
        $linked = 0;
        $now = new \DateTimeImmutable();

        foreach (FonctionCatalog::definitions() as $definition) {
            $code = strtoupper(trim($definition['code']));
            $service = $this->resolveServiceByCode($definition['serviceCode'] ?? null);
            $existing = $this->fonctionRepository->findOneBy(['code' => $code]);
            if (null !== $existing) {
                if (null === $existing->getService() && null !== $service) {
                    $existing->setService($service);
                    ++$linked;
                } else {
                    ++$skipped;
                }
                continue;
            }

            $fonction = (new Fonction())
                ->setCode($code)
                ->setLibelle($definition['libelle'])
                ->setService($service)
                ->setCreatedAt($now);
            $this->eM->persist($fonction);
            ++$created;
        }

        $this->eM->flush();

        return ['created' => $created, 'skipped' => $skipped, 'linked' => $linked];
    }

    private function resolveService(?int $serviceId): ?Service
    {
        if (null === $serviceId) {
            return null;
        }

        $service = $this->serviceRepository->find($serviceId);
        if (null === $service) {
            throw new NotFoundException('Service non trouvé.');
        }

        return $service;
    }

    private function resolveServiceByCode(?string $code): ?Service
    {
        $normalized = strtoupper(trim((string) $code));
        if ('' === $normalized) {
            return null;
        }

        return $this->serviceRepository->findOneBy(['code' => $normalized]);
    }

    protected function paginateEntities(int $page, int $limit, ?string $search): array
    {
        return $this->fonctionRepository->paginate($page, $limit, $search);
    }

    protected function repository(): ObjectRepository
    {
        return $this->fonctionRepository;
    }

    protected function instantiate(): object
    {
        return new Fonction();
    }

    protected function duplicateCodeMessage(): string
    {
        return 'Ce code fonction existe déjà.';
    }

    protected function notFoundMessage(): string
    {
        return 'Fonction non trouvée.';
    }

    protected function setCode(object $entity, string $code): void
    {
        $entity->setCode($code);
    }

    protected function setLibelle(object $entity, string $libelle): void
    {
        $entity->setLibelle($libelle);
    }

    protected function setCreatedAt(object $entity, \DateTimeImmutable $createdAt): void
    {
        $entity->setCreatedAt($createdAt);
    }
}
