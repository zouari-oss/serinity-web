<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260511173700 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE exercice');
        $this->addSql('DROP TABLE rapport');
        $this->addSql('DROP TABLE rendez_vous');
        $this->addSql('ALTER TABLE exercice_control DROP FOREIGN KEY `FK_10605AE689D40298`');
        $this->addSql('ALTER TABLE exercice_control DROP FOREIGN KEY `FK_10605AE661A2AF17`');
        $this->addSql('DROP INDEX IDX_10605AE661A2AF17 ON exercice_control');
        $this->addSql('ALTER TABLE exercice_control CHANGE assigned_by assigned_by_id VARCHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE exercice_control ADD CONSTRAINT FK_10605AE689D40298 FOREIGN KEY (exercice_id) REFERENCES exercise (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE exercice_control ADD CONSTRAINT FK_10605AE66E6F1246 FOREIGN KEY (assigned_by_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_10605AE66E6F1246 ON exercice_control (assigned_by_id)');
        $this->addSql('ALTER TABLE exercice_resource DROP FOREIGN KEY `FK_140B903589D40298`');
        $this->addSql('ALTER TABLE exercice_resource ADD CONSTRAINT FK_140B903589D40298 FOREIGN KEY (exercice_id) REFERENCES exercise (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE exercice (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_uca1400_ai_ci`, type VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_uca1400_ai_ci`, level SMALLINT NOT NULL, duration_minutes SMALLINT NOT NULL, description LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_uca1400_ai_ci`, is_active TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, benefits LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_uca1400_ai_ci`, tips LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_uca1400_ai_ci`, theme VARCHAR(50) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_uca1400_ai_ci`, guided_instructions JSON DEFAULT NULL, INDEX idx_exercice_active (is_active), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_uca1400_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE rapport (id INT AUTO_INCREMENT NOT NULL, date_creation DATE NOT NULL, resume_general LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_uca1400_ai_ci`, patient_id VARCHAR(36) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_uca1400_ai_ci`, UNIQUE INDEX UNIQ_BE34A09C6B899279 (patient_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_uca1400_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE rendez_vous (id INT AUTO_INCREMENT NOT NULL, motif VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_uca1400_ai_ci`, description LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_uca1400_ai_ci`, date_time DATETIME DEFAULT NULL, status VARCHAR(30) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_uca1400_ai_ci`, created_at DATETIME NOT NULL, proposed_date_time DATETIME DEFAULT NULL, doctor_note LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_uca1400_ai_ci`, patient_id VARCHAR(36) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_uca1400_ai_ci`, doctor_id VARCHAR(36) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_uca1400_ai_ci`, INDEX IDX_65E8AA0A6B899279 (patient_id), INDEX IDX_65E8AA0A87F4FB17 (doctor_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_uca1400_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE exercice_control DROP FOREIGN KEY FK_10605AE689D40298');
        $this->addSql('ALTER TABLE exercice_control DROP FOREIGN KEY FK_10605AE66E6F1246');
        $this->addSql('DROP INDEX IDX_10605AE66E6F1246 ON exercice_control');
        $this->addSql('ALTER TABLE exercice_control CHANGE assigned_by_id assigned_by VARCHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE exercice_control ADD CONSTRAINT `FK_10605AE689D40298` FOREIGN KEY (exercice_id) REFERENCES exercice (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE exercice_control ADD CONSTRAINT `FK_10605AE661A2AF17` FOREIGN KEY (assigned_by) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_10605AE661A2AF17 ON exercice_control (assigned_by)');
        $this->addSql('ALTER TABLE exercice_resource DROP FOREIGN KEY FK_140B903589D40298');
        $this->addSql('ALTER TABLE exercice_resource ADD CONSTRAINT `FK_140B903589D40298` FOREIGN KEY (exercice_id) REFERENCES exercice (id) ON DELETE CASCADE');
    }
}
