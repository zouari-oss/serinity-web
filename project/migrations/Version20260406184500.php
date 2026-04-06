<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260406184500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create journal_entry table for user journal and self-awareness module';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('journal_entry')) {
            return;
        }

        $table = $schema->createTable('journal_entry');
        $table->addColumn('id', 'bigint', ['autoincrement' => true]);
        $table->addColumn('user_id', 'string', ['length' => 36]);
        $table->addColumn('title', 'string', ['length' => 255]);
        $table->addColumn('content', 'text');
        $table->addColumn('created_at', 'datetime_immutable');
        $table->addColumn('updated_at', 'datetime_immutable');
        $table->addColumn('ai_tags', 'text', ['notnull' => false]);
        $table->addColumn('ai_model_version', 'string', ['length' => 32, 'notnull' => false]);
        $table->addColumn('ai_generated_at', 'datetime_immutable', ['notnull' => false]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['user_id', 'created_at'], 'idx_journal_user_created');
        $table->addForeignKeyConstraint('users', ['user_id'], ['id'], ['onDelete' => 'CASCADE']);
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('journal_entry')) {
            $schema->dropTable('journal_entry');
        }
    }
}
