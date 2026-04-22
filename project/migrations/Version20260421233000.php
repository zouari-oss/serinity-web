<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260421233000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add persisted anime avatar columns to profiles';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('profiles')) {
            return;
        }

        $table = $schema->getTable('profiles');
        $isPostgreSql = $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform;

        if (!$table->hasColumn('anime_avatar_image')) {
            $this->addSql('ALTER TABLE profiles ADD anime_avatar_image TEXT DEFAULT NULL');
        }

        if (!$table->hasColumn('anime_avatar_source_hash')) {
            if ($isPostgreSql) {
                $this->addSql('ALTER TABLE profiles ADD anime_avatar_source_hash VARCHAR(64) DEFAULT NULL');
            } else {
                $this->addSql('ALTER TABLE profiles ADD anime_avatar_source_hash VARCHAR(64) DEFAULT NULL');
            }
        }
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('profiles')) {
            return;
        }

        $table = $schema->getTable('profiles');

        if ($table->hasColumn('anime_avatar_source_hash')) {
            $this->addSql('ALTER TABLE profiles DROP COLUMN anime_avatar_source_hash');
        }

        if ($table->hasColumn('anime_avatar_image')) {
            $this->addSql('ALTER TABLE profiles DROP COLUMN anime_avatar_image');
        }
    }
}
