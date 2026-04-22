<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260406120100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed baseline mood emotions and influences';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Migration can only be executed safely on MySQL/MariaDB.'
        );

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $emotionSeeds = [
            ['7ca11d14-1745-4b5e-8dd4-2b79de5dbf01', 'happy', 'Happy'],
            ['7ca11d14-1745-4b5e-8dd4-2b79de5dbf02', 'calm', 'Calm'],
            ['7ca11d14-1745-4b5e-8dd4-2b79de5dbf03', 'anxious', 'Anxious'],
            ['7ca11d14-1745-4b5e-8dd4-2b79de5dbf04', 'sad', 'Sad'],
            ['7ca11d14-1745-4b5e-8dd4-2b79de5dbf05', 'angry', 'Angry'],
        ];

        foreach ($emotionSeeds as [$id, $key, $label]) {
            $this->addSql(sprintf(
                "INSERT INTO mood_emotions (id, emotion_key, label, created_at, updated_at)
                 SELECT '%s', '%s', '%s', '%s', '%s'
                 WHERE NOT EXISTS (
                    SELECT 1 FROM mood_emotions WHERE emotion_key = '%s'
                 )",
                $id,
                $key,
                addslashes($label),
                $now,
                $now,
                $key
            ));
        }

        $influenceSeeds = [
            ['8db22e25-2856-4c6f-9ee5-3c80ef6ecf11', 'work', 'Work'],
            ['8db22e25-2856-4c6f-9ee5-3c80ef6ecf12', 'family', 'Family'],
            ['8db22e25-2856-4c6f-9ee5-3c80ef6ecf13', 'health', 'Health'],
            ['8db22e25-2856-4c6f-9ee5-3c80ef6ecf14', 'social', 'Social'],
            ['8db22e25-2856-4c6f-9ee5-3c80ef6ecf15', 'sleep', 'Sleep'],
        ];

        foreach ($influenceSeeds as [$id, $key, $label]) {
            $this->addSql(sprintf(
                "INSERT INTO mood_influences (id, influence_key, label, created_at, updated_at)
                 SELECT '%s', '%s', '%s', '%s', '%s'
                 WHERE NOT EXISTS (
                    SELECT 1 FROM mood_influences WHERE influence_key = '%s'
                 )",
                $id,
                $key,
                addslashes($label),
                $now,
                $now,
                $key
            ));
        }
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Migration can only be executed safely on MySQL/MariaDB.'
        );

        $this->addSql("DELETE FROM mood_influences WHERE influence_key IN ('work', 'family', 'health', 'social', 'sleep')");
        $this->addSql("DELETE FROM mood_emotions WHERE emotion_key IN ('happy', 'calm', 'anxious', 'sad', 'angry')");
    }
}