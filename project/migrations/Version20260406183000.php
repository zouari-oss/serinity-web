<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260406183000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Normalize mood join-table index names after singular table rename (disabled for MariaDB compatibility)';
    }

    public function up(Schema $schema): void
    {
        // ❌ Disabled because MariaDB 10.4 does not support the RENAME INDEX syntax used here
        // These index renames are cosmetic and not required for the application to work

        // Original code removed to prevent SQL syntax errors
    }

    public function down(Schema $schema): void
    {
        // No-op
    }
}