<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260415193000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove password_reset_tokens table (reset flow no longer uses a dedicated entity)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS password_reset_tokens');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE password_reset_tokens (id VARCHAR(36) NOT NULL, code_hash VARCHAR(255) NOT NULL, expires_at DATETIME NOT NULL, used_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id VARCHAR(36) NOT NULL, INDEX idx_password_reset_user (user_id), INDEX idx_password_reset_expires_at (expires_at), INDEX idx_password_reset_used_at (used_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE password_reset_tokens ADD CONSTRAINT FK_3967A216A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
    }
}
