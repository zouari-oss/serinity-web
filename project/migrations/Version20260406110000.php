<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260406110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create base users table required by downstream foreign keys';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Migration can only be executed safely on MySQL/MariaDB.'
        );

        if ($schema->hasTable('users')) {
            return;
        }

        $users = $schema->createTable('users');
        $users->addColumn('id', 'string', ['length' => 36]);
        $users->addColumn('created_at', 'datetime_immutable');
        $users->addColumn('updated_at', 'datetime_immutable');
        $users->addColumn('email', 'string', ['length' => 150]);
        $users->addColumn('password', 'string', ['length' => 255]);
        $users->addColumn('role', 'string', ['length' => 255]);
        $users->addColumn('presence_status', 'string', ['length' => 255]);
        $users->addColumn('account_status', 'string', ['length' => 255]);
        $users->addColumn('face_recognition_enabled', 'boolean');
        $users->setPrimaryKey(['id']);
        $users->addUniqueIndex(['email'], 'uniq_users_email');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Migration can only be executed safely on MySQL/MariaDB.'
        );

        if ($schema->hasTable('users')) {
            $schema->dropTable('users');
        }
    }
}
