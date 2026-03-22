<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AdminUser;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Repository for \App\Entity\AdminUser.
 *
 * Implements \Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface so
 * Symfony Security can load users by username via Doctrine.
 *
 * @extends \Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository<\App\Entity\AdminUser>
 */
class AdminUserRepository extends ServiceEntityRepository implements UserLoaderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AdminUser::class);
    }

    /**
     * Loads a user by their username for Symfony Security authentication.
     *
     * @param  string  $identifier  The username submitted in the login form.
     * @return \Symfony\Component\Security\Core\User\UserInterface|null
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function loadUserByIdentifier(string $identifier): ?UserInterface
    {
        return $this->createQueryBuilder('u')
            ->where('LOWER(u.username) = LOWER(:identifier)')
            ->setParameter('identifier', $identifier)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
