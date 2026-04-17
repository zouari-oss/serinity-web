<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260417161800 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add nullable unique google_id to users for Google account linking';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('users')) {
            return;
        }

        $table = $schema->getTable('users');

        if (!$table->hasColumn('google_id')) {
            $table->addColumn('google_id', 'string', [
                'length' => 191,
                'notnull' => false,
            ]);
        }

        if (!$table->hasIndex('uniq_users_google_id')) {
            $table->addUniqueIndex(['google_id'], 'uniq_users_google_id');
        }
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('users')) {
            return;
        }

        $table = $schema->getTable('users');

        if ($table->hasIndex('uniq_users_google_id')) {
            $table->dropIndex('uniq_users_google_id');
        }

        if ($table->hasColumn('google_id')) {
            $table->dropColumn('google_id');
        }
    }
}
