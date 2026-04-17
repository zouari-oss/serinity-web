<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260417175000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add TOTP 2FA columns to users';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('users')) {
            return;
        }

        $table = $schema->getTable('users');
        $isPostgreSql = $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform;

        if (!$table->hasColumn('totp_secret_encrypted')) {
            $this->addSql('ALTER TABLE users ADD totp_secret_encrypted VARCHAR(255) DEFAULT NULL');
        }

        if (!$table->hasColumn('is_two_factor_enabled')) {
            if ($isPostgreSql) {
                $this->addSql('ALTER TABLE users ADD is_two_factor_enabled BOOLEAN NOT NULL DEFAULT FALSE');
            } else {
                $this->addSql('ALTER TABLE users ADD is_two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0');
            }
        }

        if (!$table->hasColumn('totp_enabled_at')) {
            if ($isPostgreSql) {
                $this->addSql('ALTER TABLE users ADD totp_enabled_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
            } else {
                $this->addSql('ALTER TABLE users ADD totp_enabled_at DATETIME DEFAULT NULL');
            }
        }
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('users')) {
            return;
        }

        $table = $schema->getTable('users');

        if ($table->hasColumn('totp_enabled_at')) {
            $this->addSql('ALTER TABLE users DROP COLUMN totp_enabled_at');
        }

        if ($table->hasColumn('is_two_factor_enabled')) {
            $this->addSql('ALTER TABLE users DROP COLUMN is_two_factor_enabled');
        }

        if ($table->hasColumn('totp_secret_encrypted')) {
            $this->addSql('ALTER TABLE users DROP COLUMN totp_secret_encrypted');
        }
    }
}
