<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Enum\AccountStatus;
use App\Enum\UserRole;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<User> */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => mb_strtolower(trim($email))]);
    }

    public function findByGoogleId(string $googleId): ?User
    {
        return $this->findOneBy(['googleId' => trim($googleId)]);
    }

    /**
     * Find users with pagination and optional filters.
     * 
     * @param array{email?: string, role?: string, accountStatus?: string, riskLevel?: string} $filters
     * @return array{users: User[], total: int, page: int, limit: int, totalPages: int}
     */
    public function findPaginated(int $page = 1, int $limit = 20, array $filters = []): array
    {
        $qb = $this->createQueryBuilder('u')
            ->leftJoin('u.profile', 'p')
            ->addSelect('p');

        // Apply filters
        if (!empty($filters['email'])) {
            $qb->andWhere('u.email LIKE :email')
               ->setParameter('email', '%' . $filters['email'] . '%');
        }

        if (!empty($filters['role'])) {
            $qb->andWhere('u.role = :role')
               ->setParameter('role', $filters['role']);
        }

        if (!empty($filters['accountStatus'])) {
            $qb->andWhere('u.accountStatus = :accountStatus')
               ->setParameter('accountStatus', $filters['accountStatus']);
        }

        if (!empty($filters['riskLevel'])) {
            $qb->andWhere('u.riskLevel = :riskLevel')
                ->setParameter('riskLevel', $filters['riskLevel']);
        }

        // Count total before pagination
        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(u.id)')->getQuery()->getSingleScalarResult();

        // Apply pagination
        $offset = ($page - 1) * $limit;
        $qb->setFirstResult($offset)
           ->setMaxResults($limit)
           ->orderBy('u.createdAt', 'DESC');

        $users = $qb->getQuery()->getResult();
        $totalPages = (int) ceil($total / $limit);

        return [
            'users' => $users,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => $totalPages,
        ];
    }

    /**
     * Count total users in the system.
     */
    public function countUsers(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count users by account status.
     */
    public function countByAccountStatus(AccountStatus $status): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.accountStatus = :status')
            ->setParameter('status', $status->value)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countNonAdminByAccountStatus(AccountStatus $status): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.accountStatus = :status')
            ->andWhere('u.role != :adminRole')
            ->setParameter('status', $status->value)
            ->setParameter('adminRole', UserRole::ADMIN->value)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count users by role.
     */
    public function countByRole(UserRole $role): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.role = :role')
            ->setParameter('role', $role->value)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Calculate profile completion percentage.
     */
    public function getProfileCompletionPercentage(): int
    {
        $totalUsers = $this->countUsers();
        if ($totalUsers === 0) {
            return 0;
        }

        $usersWithProfiles = (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->innerJoin('u.profile', 'p')
            ->where('p.firstName IS NOT NULL')
            ->andWhere('p.lastName IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();

        return (int) round(($usersWithProfiles / $totalUsers) * 100);
    }

    public function countCreatedSince(\DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.createdAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countNonAdminCreatedSince(\DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.createdAt >= :since')
            ->andWhere('u.role != :adminRole')
            ->setParameter('since', $since)
            ->setParameter('adminRole', UserRole::ADMIN->value)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find non-admin users with pagination and optional filters.
     *
     * @param array{email?: string, role?: string, accountStatus?: string, riskLevel?: string} $filters
     * @return array{users: User[], total: int, page: int, limit: int, totalPages: int}
     */
    public function findPaginatedNonAdmin(int $page = 1, int $limit = 20, array $filters = []): array
    {
        $qb = $this->createQueryBuilder('u')
            ->leftJoin('u.profile', 'p')
            ->addSelect('p')
            ->andWhere('u.role != :adminRole')
            ->setParameter('adminRole', UserRole::ADMIN->value);

        if (!empty($filters['email'])) {
            $qb->andWhere('u.email LIKE :email')
                ->setParameter('email', '%' . $filters['email'] . '%');
        }

        if (!empty($filters['role']) && in_array($filters['role'], [UserRole::PATIENT->value, UserRole::THERAPIST->value], true)) {
            $qb->andWhere('u.role = :role')
                ->setParameter('role', $filters['role']);
        }

        if (!empty($filters['accountStatus'])) {
            $qb->andWhere('u.accountStatus = :accountStatus')
                ->setParameter('accountStatus', $filters['accountStatus']);
        }

        if (!empty($filters['riskLevel'])) {
            $qb->andWhere('u.riskLevel = :riskLevel')
                ->setParameter('riskLevel', $filters['riskLevel']);
        }

        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(u.id)')->getQuery()->getSingleScalarResult();

        $offset = ($page - 1) * $limit;
        $qb->setFirstResult($offset)
            ->setMaxResults($limit)
            ->orderBy('u.createdAt', 'DESC');

        $users = $qb->getQuery()->getResult();
        $totalPages = (int) ceil($total / $limit);

        return [
            'users' => $users,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => $totalPages,
        ];
    }

    /**
     * @return list<User>
     */
    public function findAllNonAdmin(): array
    {
        return $this->createQueryBuilder('u')
            ->leftJoin('u.profile', 'p')
            ->addSelect('p')
            ->where('u.role != :adminRole')
            ->setParameter('adminRole', UserRole::ADMIN->value)
            ->getQuery()
            ->getResult();
    }
}
