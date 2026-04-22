<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260422120500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename profiles.anime_avatar_image to anime_avatar_image_url and drop anime_avatar_source_hash';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('profiles')) {
            return;
        }
        if (!$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            return;
        }

        $table = $schema->getTable('profiles');

        if ($table->hasColumn('anime_avatar_source_hash')) {
            $this->addSql('ALTER TABLE profiles DROP COLUMN anime_avatar_source_hash');
        }

        if ($table->hasColumn('anime_avatar_image') && !$table->hasColumn('anime_avatar_image_url')) {
            $this->addSql('ALTER TABLE profiles CHANGE anime_avatar_image anime_avatar_image_url VARCHAR(512) DEFAULT NULL');
            return;
        }

        if ($table->hasColumn('anime_avatar_image_url')) {
            $this->addSql('ALTER TABLE profiles MODIFY anime_avatar_image_url VARCHAR(512) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('profiles')) {
            return;
        }
        if (!$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            return;
        }

        $table = $schema->getTable('profiles');

        if ($table->hasColumn('anime_avatar_image_url') && !$table->hasColumn('anime_avatar_image')) {
            $this->addSql('ALTER TABLE profiles CHANGE anime_avatar_image_url anime_avatar_image LONGTEXT DEFAULT NULL');
        } elseif ($table->hasColumn('anime_avatar_image')) {
            $this->addSql('ALTER TABLE profiles MODIFY anime_avatar_image LONGTEXT DEFAULT NULL');
        }

        if (!$table->hasColumn('anime_avatar_source_hash')) {
            $this->addSql('ALTER TABLE profiles ADD anime_avatar_source_hash VARCHAR(64) DEFAULT NULL');
        }
    }
}
