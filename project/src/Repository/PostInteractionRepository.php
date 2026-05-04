<?php

namespace App\Repository;

use App\Entity\ForumThread;
use App\Entity\PostInteraction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PostInteraction>
 */
class PostInteractionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PostInteraction::class);
    }

    public function findOneForUser(ForumThread $thread, string $userId): ?PostInteraction
    {
        return $this->findOneBy(['thread' => $thread, 'userId' => $userId]);
    }

    /**
     * @return ForumThread[]
     */
    public function findFollowedThreadsForUser(string $userId): array
    {
        $rows = $this->getEntityManager()->createQueryBuilder()
            ->select('t')
            ->from(ForumThread::class, 't')
            ->innerJoin(PostInteraction::class, 'pi', 'WITH', 'pi.thread = t')
            ->andWhere('pi.userId = :userId')
            ->andWhere('pi.follow = true')
            ->setParameter('userId', $userId)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $rows;
    }

    /**
     * @return list<array{categoryId: int, score: int}>
     */
    public function findCategoryScoresForUser(string $userId): array
    {
        $rows = $this->createQueryBuilder('pi')
            ->select('IDENTITY(t.category) AS categoryId')
            ->addSelect('SUM(CASE WHEN pi.follow = true THEN 4 ELSE 0 END) AS followScore')
            ->addSelect('SUM(CASE WHEN pi.vote = 1 THEN 3 WHEN pi.vote = -1 THEN -2 ELSE 0 END) AS voteScore')
            ->innerJoin('pi.thread', 't')
            ->andWhere('pi.userId = :userId')
            ->setParameter('userId', $userId)
            ->groupBy('t.category')
            ->getQuery()
            ->getArrayResult();

        $scores = [];

        foreach ($rows as $row) {
            $categoryId = (int) ($row['categoryId'] ?? 0);
            if ($categoryId <= 0) {
                continue;
            }

            $score = (int) ($row['followScore'] ?? 0) + (int) ($row['voteScore'] ?? 0);
            $scores[] = [
                'categoryId' => $categoryId,
                'score' => $score,
            ];
        }

        usort($scores, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return $scores;
    }
}
