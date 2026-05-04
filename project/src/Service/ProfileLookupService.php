<?php

namespace App\Service;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

class ProfileLookupService
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @return array{user_id: string, username: string, roles: string}|null
     */
    public function findById(string $userId): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT user_id, username, roles FROM profiles WHERE user_id = :id LIMIT 1',
            ['id' => $userId]
        );

        if (!is_array($row)) {
            return null;
        }

        return [
            'user_id' => (string) ($row['user_id'] ?? ''),
            'username' => (string) ($row['username'] ?? ''),
            'roles' => (string) ($row['roles'] ?? 'client'),
        ];
    }

    /**
     * @param list<string> $userIds
     *
     * @return array<string, string>
     */
    public function usernamesByIds(array $userIds): array
    {
        $cleanIds = array_values(array_unique(array_filter($userIds, static fn (string $id): bool => $id !== '')));
        if ($cleanIds === []) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT user_id, username FROM profiles WHERE user_id IN (?)',
            [$cleanIds],
            [ArrayParameterType::STRING]
        );

        $map = [];
        foreach ($rows as $row) {
            $id = (string) ($row['user_id'] ?? '');
            if ($id === '') {
                continue;
            }

            $map[$id] = (string) ($row['username'] ?? 'Unknown User');
        }

        return $map;
    }

    public function countProfiles(): int
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM profiles');
    }
}
