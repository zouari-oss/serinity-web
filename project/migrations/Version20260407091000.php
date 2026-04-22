<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260407091000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align sleep schema with source Sommeil/Reves entities (field names/count)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('SET FOREIGN_KEY_CHECKS = 0');

        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS sommeil (
    id BIGINT AUTO_INCREMENT NOT NULL,
    user_id VARCHAR(36) NOT NULL,
    date_nuit DATE NOT NULL COMMENT '(DC2Type:date_immutable)',
    heure_coucher VARCHAR(10) NOT NULL,
    heure_reveil VARCHAR(10) NOT NULL,
    qualite VARCHAR(50) NOT NULL,
    commentaire LONGTEXT DEFAULT NULL,
    duree_sommeil DOUBLE PRECISION DEFAULT NULL,
    interruptions INT DEFAULT NULL,
    humeur_reveil VARCHAR(50) DEFAULT NULL,
    environnement VARCHAR(100) DEFAULT NULL,
    temperature DOUBLE PRECISION DEFAULT NULL,
    bruit_niveau VARCHAR(50) DEFAULT NULL,
    created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    INDEX idx_sommeil_user_date (user_id, date_nuit),
    INDEX idx_sommeil_qualite (qualite),
    PRIMARY KEY(id),
    CONSTRAINT FK_SOMMEIL_USER FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS reves (
    id BIGINT AUTO_INCREMENT NOT NULL,
    sommeil_id BIGINT DEFAULT NULL,
    titre VARCHAR(200) NOT NULL,
    description LONGTEXT NOT NULL,
    humeur VARCHAR(50) DEFAULT NULL,
    type_reve VARCHAR(50) DEFAULT NULL,
    intensite INT DEFAULT NULL,
    couleur TINYINT(1) NOT NULL,
    emotions VARCHAR(200) DEFAULT NULL,
    symboles LONGTEXT DEFAULT NULL,
    recurrent TINYINT(1) NOT NULL,
    created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    INDEX idx_reves_created (created_at),
    INDEX idx_reves_type (type_reve),
    INDEX IDX_REVES_SOMMEIL (sommeil_id),
    PRIMARY KEY(id),
    CONSTRAINT FK_REVES_SOMMEIL FOREIGN KEY (sommeil_id) REFERENCES sommeil (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB
SQL);

        $this->addSql('DROP TABLE IF EXISTS sleep_dream');
        $this->addSql('DROP TABLE IF EXISTS sleep_session');
        $this->addSql('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('SET FOREIGN_KEY_CHECKS = 0');
        $this->addSql('DROP TABLE IF EXISTS reves');
        $this->addSql('DROP TABLE IF EXISTS sommeil');
        $this->addSql('SET FOREIGN_KEY_CHECKS = 1');
    }
}
