<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260422104852 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE consultation DROP FOREIGN KEY `FK_964685A61DFBCC46`');
        $this->addSql('ALTER TABLE consultation DROP FOREIGN KEY `FK_964685A687F4FB17`');
        $this->addSql('ALTER TABLE consultation DROP FOREIGN KEY `FK_964685A691EF7EAA`');
        $this->addSql('ALTER TABLE rapport DROP FOREIGN KEY `FK_BE34A09C6B899279`');
        $this->addSql('ALTER TABLE rendez_vous DROP FOREIGN KEY `FK_65E8AA0A6B899279`');
        $this->addSql('ALTER TABLE rendez_vous DROP FOREIGN KEY `FK_65E8AA0A87F4FB17`');
        $this->addSql('DROP TABLE consultation');
        $this->addSql('DROP TABLE rapport');
        $this->addSql('DROP TABLE rendez_vous');
        $this->addSql('ALTER TABLE profiles ADD anime_avatar_image LONGTEXT DEFAULT NULL, ADD anime_avatar_source_hash VARCHAR(64) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE consultation (id INT AUTO_INCREMENT NOT NULL, date_consultation DATETIME NOT NULL, diagnostic LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_uca1400_ai_ci`, prescription LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_uca1400_ai_ci`, notes LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_uca1400_ai_ci`, rapport_id INT NOT NULL, rendez_vous_id INT DEFAULT NULL, doctor_id VARCHAR(36) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_uca1400_ai_ci`, INDEX IDX_964685A61DFBCC46 (rapport_id), INDEX IDX_964685A687F4FB17 (doctor_id), UNIQUE INDEX UNIQ_964685A691EF7EAA (rendez_vous_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_uca1400_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE rapport (id INT AUTO_INCREMENT NOT NULL, date_creation DATE NOT NULL, resume_general LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_uca1400_ai_ci`, patient_id VARCHAR(36) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_uca1400_ai_ci`, UNIQUE INDEX UNIQ_BE34A09C6B899279 (patient_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_uca1400_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE rendez_vous (id INT AUTO_INCREMENT NOT NULL, motif VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_uca1400_ai_ci`, description LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_uca1400_ai_ci`, date_time DATETIME DEFAULT NULL, status VARCHAR(30) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_uca1400_ai_ci`, created_at DATETIME NOT NULL, proposed_date_time DATETIME DEFAULT NULL, doctor_note LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_uca1400_ai_ci`, patient_id VARCHAR(36) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_uca1400_ai_ci`, doctor_id VARCHAR(36) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_uca1400_ai_ci`, INDEX IDX_65E8AA0A6B899279 (patient_id), INDEX IDX_65E8AA0A87F4FB17 (doctor_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_uca1400_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE consultation ADD CONSTRAINT `FK_964685A61DFBCC46` FOREIGN KEY (rapport_id) REFERENCES rapport (id)');
        $this->addSql('ALTER TABLE consultation ADD CONSTRAINT `FK_964685A687F4FB17` FOREIGN KEY (doctor_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE consultation ADD CONSTRAINT `FK_964685A691EF7EAA` FOREIGN KEY (rendez_vous_id) REFERENCES rendez_vous (id)');
        $this->addSql('ALTER TABLE rapport ADD CONSTRAINT `FK_BE34A09C6B899279` FOREIGN KEY (patient_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE rendez_vous ADD CONSTRAINT `FK_65E8AA0A6B899279` FOREIGN KEY (patient_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE rendez_vous ADD CONSTRAINT `FK_65E8AA0A87F4FB17` FOREIGN KEY (doctor_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE profiles DROP anime_avatar_image, DROP anime_avatar_source_hash');
    }
}
