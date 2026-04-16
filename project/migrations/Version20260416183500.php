<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260416183500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ensure user_faces table enforces one face embedding per user';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('user_faces')) {
            $table = $schema->createTable('user_faces');
            $table->addColumn('id', 'string', ['length' => 36]);
            $table->addColumn('created_at', 'datetime_immutable');
            $table->addColumn('updated_at', 'datetime_immutable');
            $table->addColumn('embedding', 'blob');
            $table->addColumn('user_id', 'string', ['length' => 36]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['user_id'], 'idx_user_faces_user');
            $table->addUniqueIndex(['user_id'], 'uniq_user_faces_user');
            $table->addForeignKeyConstraint('users', ['user_id'], ['id'], ['onDelete' => 'CASCADE']);

            return;
        }

        $table = $schema->getTable('user_faces');
        if (!$table->hasIndex('idx_user_faces_user')) {
            $table->addIndex(['user_id'], 'idx_user_faces_user');
        }

        if (!$table->hasIndex('uniq_user_faces_user')) {
            $table->addUniqueIndex(['user_id'], 'uniq_user_faces_user');
        }
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('user_faces')) {
            return;
        }

        $table = $schema->getTable('user_faces');
        if ($table->hasIndex('uniq_user_faces_user')) {
            $table->dropIndex('uniq_user_faces_user');
        }
    }
}
