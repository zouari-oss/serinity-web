<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260406123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add moment type and mood level to mood entries';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('mood_entries')) {
            return;
        }

        $table = $schema->getTable('mood_entries');

        if (!$table->hasColumn('moment_type')) {
            $table->addColumn('moment_type', 'string', ['length' => 16, 'default' => 'DAY']);
            $table->addIndex(['moment_type'], 'idx_mood_entries_moment_type');
        }

        if (!$table->hasColumn('mood_level')) {
            $table->addColumn('mood_level', 'smallint', ['default' => 3]);
        }
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('mood_entries')) {
            return;
        }

        $table = $schema->getTable('mood_entries');

        if ($table->hasIndex('idx_mood_entries_moment_type')) {
            $table->dropIndex('idx_mood_entries_moment_type');
        }

        if ($table->hasColumn('moment_type')) {
            $table->dropColumn('moment_type');
        }

        if ($table->hasColumn('mood_level')) {
            $table->dropColumn('mood_level');
        }
    }
}
