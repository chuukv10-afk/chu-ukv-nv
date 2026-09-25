<?php

namespace App\Repository;

use App\Entity\EtudeImagerie;
use App\Repository\Trait\NumeroPrefixRepositoryTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<EtudeImagerie>
 */
class EtudeImagerieRepository extends ServiceEntityRepository
{
    use NumeroPrefixRepositoryTrait;

    private const TIMEZONE = 'Africa/Kinshasa';

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EtudeImagerie::class);
    }

    /**
     * @return array{items: list<EtudeImagerie>, total: int}
     */
    public function paginate(
        int $page,
        int $limit,
        ?string $search,
        ?string $statut,
        ?string $patientId = null,
        ?string $statuts = null,
        ?string $source = null,
        ?string $periode = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
    ): array {
        $qb = $this->createQueryBuilder('e')
            ->leftJoin('e.patient', 'p')->addSelect('p')
            ->leftJoin('e.examen', 'x')->addSelect('x')
            ->leftJoin('x.typeExamen', 't')->addSelect('t')
            ->orderBy('e.createdAt', 'DESC');

        $normalizedSearch = null !== $search ? trim($search) : '';
        if ('' !== $normalizedSearch) {
            $qb
                ->andWhere('LOWER(e.numero) LIKE :search OR LOWER(p.nom) LIKE :search OR LOWER(p.postNom) LIKE :search OR LOWER(p.prenom) LIKE :search OR LOWER(x.libelle) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($normalizedSearch) . '%');
        }
        if (null !== $statut && '' !== trim($statut)) {
            $qb->andWhere('e.statut = :statut')->setParameter('statut', strtoupper(trim($statut)));
        } elseif (null !== $statuts && '' !== trim($statuts)) {
            $values = array_values(array_filter(array_map(
                static fn (string $value): string => strtoupper(trim($value)),
                explode(',', $statuts),
            )));
            if ($values !== []) {
                $qb->andWhere('e.statut IN (:statuts)')->setParameter('statuts', $values);
            }
        }
        if (null !== $patientId && '' !== trim($patientId)) {
            try {
                $qb->andWhere('p.id = :patientId')->setParameter('patientId', Uuid::fromString($patientId), 'uuid');
            } catch (\InvalidArgumentException) {
                $qb->andWhere('1 = 0');
            }
        }
        if (null !== $source && '' !== trim($source)) {
            $qb->andWhere('e.source = :source')->setParameter('source', strtoupper(trim($source)));
        }

        [$from, $to] = $this->resolvePeriodBounds($periode, $dateFrom, $dateTo);
        $timezone = new \DateTimeZone(self::TIMEZONE);
        if (null !== $from) {
            $qb->andWhere('e.createdAt >= :dateFrom')->setParameter('dateFrom', new \DateTimeImmutable($from . ' 00:00:00', $timezone));
        }
        if (null !== $to) {
            $qb->andWhere('e.createdAt <= :dateTo')->setParameter('dateTo', new \DateTimeImmutable($to . ' 23:59:59', $timezone));
        }

        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(e.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();

        $items = $qb
            ->setFirstResult(max(0, ($page - 1) * $limit))
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function resolvePeriodBounds(?string $periode, ?string $dateFrom, ?string $dateTo): array
    {
        $timezone = new \DateTimeZone(self::TIMEZONE);
        $today = new \DateTimeImmutable('now', $timezone);
        $key = strtoupper(trim((string) $periode));

        return match ($key) {
            'AUJOURDHUI' => [$today->format('Y-m-d'), $today->format('Y-m-d')],
            'SEMAINE' => [
                $today->modify('monday this week')->format('Y-m-d'),
                $today->modify('sunday this week')->format('Y-m-d'),
            ],
            'MOIS' => [$today->format('Y-m-01'), $today->format('Y-m-t')],
            'ANNEE' => [$today->format('Y-01-01'), $today->format('Y-12-31')],
            default => [
                '' !== trim((string) $dateFrom) ? trim((string) $dateFrom) : null,
                '' !== trim((string) $dateTo) ? trim((string) $dateTo) : null,
            ],
        };
    }
}
