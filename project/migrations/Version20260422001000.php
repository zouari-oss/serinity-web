<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260422001000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Expand profiles.anime_avatar_image storage to LONGTEXT for large base64 payloads';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('profiles')) {
            return;
        }

        $table = $schema->getTable('profiles');
        if (!$table->hasColumn('anime_avatar_image')) {
            return;
        }

        if ($this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            $this->addSql('ALTER TABLE profiles MODIFY anime_avatar_image LONGTEXT DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('profiles')) {
            return;
        }

        $table = $schema->getTable('profiles');
        if (!$table->hasColumn('anime_avatar_image')) {
            return;
        }

        if ($this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            $this->addSql('ALTER TABLE profiles MODIFY anime_avatar_image TEXT DEFAULT NULL');
        }
    }
}
