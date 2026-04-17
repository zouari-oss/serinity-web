<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260417164318 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'No-op: discard unsafe auto-generated diff that conflicts with existing indexes';
    }

    public function up(Schema $schema): void
    {
        // Intentionally no-op.
        // The required google_id change is handled by Version20260417161800.
    }

    public function down(Schema $schema): void
    {
        // Intentionally no-op.
    }
}
