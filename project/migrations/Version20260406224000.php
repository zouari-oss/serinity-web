<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260406224000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add exercice control module tables (exercice, control, resources, favorites) with indexes and starter seed data';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('SET FOREIGN_KEY_CHECKS = 0');
        $this->addSql('DROP TABLE IF EXISTS exercice_favorite');
        $this->addSql('DROP TABLE IF EXISTS exercice_resource');
        $this->addSql('DROP TABLE IF EXISTS exercice_control');
        $this->addSql('DROP TABLE IF EXISTS exercice');

        $this->addSql(<<<'SQL'
CREATE TABLE exercice (
    id INT AUTO_INCREMENT NOT NULL,
    title VARCHAR(255) NOT NULL,
    type VARCHAR(100) NOT NULL,
    level SMALLINT NOT NULL,
    duration_minutes SMALLINT NOT NULL,
    description LONGTEXT DEFAULT NULL,
    is_active TINYINT(1) NOT NULL,
    created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    INDEX idx_exercice_active (is_active),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_uca1400_ai_ci` ENGINE = InnoDB
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE exercice_control (
    id BIGINT AUTO_INCREMENT NOT NULL,
    user_id VARCHAR(36) NOT NULL,
    exercice_id INT NOT NULL,
    assigned_by VARCHAR(36) DEFAULT NULL,
    status VARCHAR(20) NOT NULL,
    started_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    completed_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    active_seconds INT NOT NULL,
    feedback LONGTEXT DEFAULT NULL,
    created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    INDEX idx_exercice_control_user (user_id),
    INDEX idx_exercice_control_status (status),
    INDEX idx_exercice_control_started_at (started_at),
    INDEX idx_exercice_control_completed_at (completed_at),
    INDEX IDX_21B7B4117D3C2A0A (exercice_id),
    INDEX IDX_21B7B4118B2E1E52 (assigned_by),
    PRIMARY KEY(id),
    CONSTRAINT FK_21B7B411A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT FK_21B7B4117D3C2A0A FOREIGN KEY (exercice_id) REFERENCES exercice (id) ON DELETE CASCADE,
    CONSTRAINT FK_21B7B4118B2E1E52 FOREIGN KEY (assigned_by) REFERENCES users (id) ON DELETE SET NULL
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_uca1400_ai_ci` ENGINE = InnoDB
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE exercice_resource (
    id INT AUTO_INCREMENT NOT NULL,
    exercice_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    resource_type VARCHAR(40) NOT NULL,
    resource_url VARCHAR(512) NOT NULL,
    created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    INDEX idx_exercice_resource_exercice (exercice_id),
    PRIMARY KEY(id),
    CONSTRAINT FK_C75F218B7D3C2A0A FOREIGN KEY (exercice_id) REFERENCES exercice (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_uca1400_ai_ci` ENGINE = InnoDB
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE exercice_favorite (
    id INT AUTO_INCREMENT NOT NULL,
    user_id VARCHAR(36) NOT NULL,
    favorite_type VARCHAR(20) NOT NULL,
    item_id INT NOT NULL,
    created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    INDEX idx_exercice_favorite_user (user_id),
    UNIQUE INDEX uniq_exercice_favorite (user_id, favorite_type, item_id),
    PRIMARY KEY(id),
    CONSTRAINT FK_4BE85D7CA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_uca1400_ai_ci` ENGINE = InnoDB
SQL);

        $this->addSql(<<<'SQL'
INSERT INTO exercice (title, type, level, duration_minutes, description, is_active, created_at, updated_at) VALUES
('Breathing Reset', 'RESPIRATION', 1, 8, 'Guided breathing for stress down-regulation.', 1, NOW(), NOW()),
('Body Scan Focus', 'MINDFULNESS', 2, 12, 'Attention exercise to reduce anxious rumination.', 1, NOW(), NOW()),
('Cognitive Reframe', 'CBT', 3, 15, 'Structured reframing sequence for negative thoughts.', 1, NOW(), NOW())
SQL);

        $this->addSql(<<<'SQL'
INSERT INTO exercice_resource (exercice_id, title, resource_type, resource_url, created_at) VALUES
(1, 'Breathing audio guide', 'AUDIO', 'https://example.org/resources/breathing-guide', NOW()),
(2, 'Body scan script', 'DOCUMENT', 'https://example.org/resources/body-scan', NOW()),
(3, 'Reframe worksheet', 'DOCUMENT', 'https://example.org/resources/cbt-reframe', NOW())
SQL);
        $this->addSql('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS exercice_favorite');
        $this->addSql('DROP TABLE IF EXISTS exercice_resource');
        $this->addSql('DROP TABLE IF EXISTS exercice_control');
        $this->addSql('DROP TABLE IF EXISTS exercice');
    }
}
