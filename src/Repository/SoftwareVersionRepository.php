<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SoftwareVersion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository for \App\Entity\SoftwareVersion.
 *
 * The primary query method `findAllOrdered()` returns all records sorted
 * consistently. Filtering is intentionally delegated to the service layer
 * to maintain exact parity with the legacy PHP iteration logic.
 *
 * @extends \Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository<\App\Entity\SoftwareVersion>
 */
class SoftwareVersionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SoftwareVersion::class);
    }

    /**
     * Returns all software version records ordered for consistent API iteration.
     *
     * Sort order mirrors the original JSON file order (by sort_order ASC),
     * which is critical because the legacy loop breaks on the first match.
     *
     * @return \App\Entity\SoftwareVersion[]
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('sv')
            ->orderBy('sv.sortOrder', 'ASC')
            ->addOrderBy('sv.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Finds an existing entry in the same product line already marked as latest.
     *
     * Used by UniqueLatestInProductNameValidator to prevent duplicate "latest"
     * entries within the same product line (grouped by the `name` field).
     *
     * @param  string  $productName  The product line name to search within.
     * @param  int|null  $excludeId  The ID of the current entity being validated (excluded from results).
     * @return \App\Entity\SoftwareVersion|null The conflicting entry, or null if none exists.
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findConflictingLatest(string $productName, ?int $excludeId): ?SoftwareVersion
    {
        $qb = $this->createQueryBuilder('sv')
            ->where('sv.name = :name')
            ->andWhere('sv.isLatest = :isLatest')
            ->setParameter('name', $productName)
            ->setParameter('isLatest', true)
            ->setMaxResults(1);

        if ($excludeId !== null) {
            $qb->andWhere('sv.id != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * Returns summary statistics for the admin dashboard.
     *
     * @return array{total: int, lci: int, standard: int, latest: int}
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getStats(): array
    {
        $result = $this->createQueryBuilder('sv')
            ->select(
                'COUNT(sv.id) AS total',
                'SUM(CASE WHEN sv.isLci = true THEN 1 ELSE 0 END) AS lci',
                'SUM(CASE WHEN sv.isLci = false THEN 1 ELSE 0 END) AS standard',
                'SUM(CASE WHEN sv.isLatest = true THEN 1 ELSE 0 END) AS latest',
            )
            ->getQuery()
            ->getSingleResult();

        return [
            'total' => (int) $result['total'],
            'lci' => (int) $result['lci'],
            'standard' => (int) $result['standard'],
            'latest' => (int) $result['latest'],
        ];
    }
}
