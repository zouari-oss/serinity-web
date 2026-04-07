<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260407030000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add sleep_session and sleep_dream tables using source-compatible column names';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('SET FOREIGN_KEY_CHECKS = 0');
        $this->addSql('DROP TABLE IF EXISTS sleep_dream');
        $this->addSql('DROP TABLE IF EXISTS sleep_session');

        $this->addSql(<<<'SQL'
CREATE TABLE sleep_session (
    id BIGINT AUTO_INCREMENT NOT NULL,
    user_id VARCHAR(36) NOT NULL,
    sleep_date DATE NOT NULL COMMENT '(DC2Type:date_immutable)',
    bed_time DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    wake_time DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    duration_minutes SMALLINT NOT NULL,
    sleep_quality SMALLINT NOT NULL,
    notes LONGTEXT DEFAULT NULL,
    created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    INDEX idx_sleep_session_user_date (user_id, sleep_date),
    INDEX idx_sleep_session_quality (sleep_quality),
    PRIMARY KEY(id),
    CONSTRAINT FK_SLEEP_SESSION_USER FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_uca1400_ai_ci` ENGINE = InnoDB
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE sleep_dream (
    id BIGINT AUTO_INCREMENT NOT NULL,
    user_id VARCHAR(36) NOT NULL,
    sleep_session_id BIGINT DEFAULT NULL,
    title VARCHAR(255) NOT NULL,
    content LONGTEXT NOT NULL,
    dream_type VARCHAR(50) NOT NULL,
    created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    INDEX idx_sleep_dream_user_created (user_id, created_at),
    INDEX idx_sleep_dream_type (dream_type),
    INDEX IDX_SLEEP_DREAM_SESSION (sleep_session_id),
    PRIMARY KEY(id),
    CONSTRAINT FK_SLEEP_DREAM_USER FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT FK_SLEEP_DREAM_SESSION FOREIGN KEY (sleep_session_id) REFERENCES sleep_session (id) ON DELETE SET NULL
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_uca1400_ai_ci` ENGINE = InnoDB
SQL);
        $this->addSql('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS sleep_dream');
        $this->addSql('DROP TABLE IF EXISTS sleep_session');
    }
}
