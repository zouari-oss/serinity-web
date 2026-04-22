<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260418154108 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE audit_logs (id VARCHAR(36) NOT NULL, action VARCHAR(100) NOT NULL, os_name VARCHAR(50) DEFAULT NULL, hostname VARCHAR(100) DEFAULT NULL, private_ip_address VARCHAR(45) NOT NULL, mac_address VARCHAR(17) DEFAULT NULL, location VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, auth_session_id VARCHAR(36) NOT NULL, INDEX idx_audit_created (created_at), INDEX fk_audit_logs_auth_session_id (auth_session_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE auth_sessions (id VARCHAR(36) NOT NULL, refresh_token VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, revoked TINYINT NOT NULL, user_id VARCHAR(36) NOT NULL, UNIQUE INDEX UNIQ_BE9A1A95C74F2195 (refresh_token), INDEX idx_session_token (refresh_token), INDEX idx_session_user (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE profiles (id VARCHAR(36) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, username VARCHAR(255) NOT NULL, firstName VARCHAR(255) DEFAULT NULL, lastName VARCHAR(255) DEFAULT NULL, phone VARCHAR(20) DEFAULT NULL, gender VARCHAR(255) DEFAULT NULL, profile_image_url VARCHAR(512) DEFAULT NULL, country VARCHAR(100) DEFAULT NULL, state VARCHAR(100) DEFAULT NULL, aboutMe VARCHAR(500) DEFAULT NULL, user_id VARCHAR(36) NOT NULL, UNIQUE INDEX UNIQ_8B308530A76ED395 (user_id), UNIQUE INDEX uk_profile_username (username), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE audit_logs ADD CONSTRAINT FK_D62F28587F3AE16B FOREIGN KEY (auth_session_id) REFERENCES auth_sessions (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE auth_sessions ADD CONSTRAINT FK_BE9A1A95A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE profiles ADD CONSTRAINT FK_8B308530A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('DROP INDEX uniq_bf661bde5e237e06 ON emotion');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_DEBC775E237E06 ON emotion (name)');
        $this->addSql('ALTER TABLE exercice CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE exercice_control DROP FOREIGN KEY `FK_21B7B4117D3C2A0A`');
        $this->addSql('ALTER TABLE exercice_control DROP FOREIGN KEY `FK_21B7B4118B2E1E52`');
        $this->addSql('ALTER TABLE exercice_control CHANGE started_at started_at DATETIME DEFAULT NULL, CHANGE completed_at completed_at DATETIME DEFAULT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL');
        $this->addSql('DROP INDEX idx_21b7b4117d3c2a0a ON exercice_control');
        $this->addSql('CREATE INDEX IDX_10605AE689D40298 ON exercice_control (exercice_id)');
        $this->addSql('DROP INDEX idx_21b7b4118b2e1e52 ON exercice_control');
        $this->addSql('CREATE INDEX IDX_10605AE661A2AF17 ON exercice_control (assigned_by)');
        $this->addSql('ALTER TABLE exercice_control ADD CONSTRAINT `FK_21B7B4117D3C2A0A` FOREIGN KEY (exercice_id) REFERENCES exercice (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE exercice_control ADD CONSTRAINT `FK_21B7B4118B2E1E52` FOREIGN KEY (assigned_by) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE exercice_favorite CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE exercice_resource CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('DROP INDEX uniq_2864f98e5e237e06 ON influence');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_487304315E237E06 ON influence (name)');
        $this->addSql('ALTER TABLE mood_entry CHANGE entry_date entry_date DATETIME NOT NULL, CHANGE moment_type moment_type VARCHAR(16) NOT NULL, CHANGE mood_level mood_level SMALLINT NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE mood_entry_emotion DROP FOREIGN KEY `FK_EB264C19A755B3DD`');
        $this->addSql('DROP INDEX idx_eb264c19a755b3dd ON mood_entry_emotion');
        $this->addSql('CREATE INDEX IDX_EB264C191EE4A582 ON mood_entry_emotion (emotion_id)');
        $this->addSql('ALTER TABLE mood_entry_emotion ADD CONSTRAINT `FK_EB264C19A755B3DD` FOREIGN KEY (emotion_id) REFERENCES emotion (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE mood_entry_influence DROP FOREIGN KEY `FK_EB05BFC277FE226`');
        $this->addSql('DROP INDEX idx_eb05bfc277fe226 ON mood_entry_influence');
        $this->addSql('CREATE INDEX IDX_EB05BFCEE88AF5D ON mood_entry_influence (influence_id)');
        $this->addSql('ALTER TABLE mood_entry_influence ADD CONSTRAINT `FK_EB05BFC277FE226` FOREIGN KEY (influence_id) REFERENCES influence (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reves DROP FOREIGN KEY `FK_REVES_SOMMEIL`');
        $this->addSql('ALTER TABLE reves CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL');
        $this->addSql('DROP INDEX idx_reves_sommeil ON reves');
        $this->addSql('CREATE INDEX IDX_C832E805F84A5F9B ON reves (sommeil_id)');
        $this->addSql('ALTER TABLE reves ADD CONSTRAINT `FK_REVES_SOMMEIL` FOREIGN KEY (sommeil_id) REFERENCES sommeil (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE sommeil CHANGE date_nuit date_nuit DATE NOT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL');
        $this->addSql('DROP INDEX idx_user_faces_user ON user_faces');
        $this->addSql('ALTER TABLE users CHANGE is_two_factor_enabled is_two_factor_enabled TINYINT NOT NULL');
        $this->addSql('DROP INDEX uniq_users_email ON users');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9E7927C74 ON users (email)');
        $this->addSql('DROP INDEX uniq_users_google_id ON users');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E976F5C865 ON users (google_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE audit_logs DROP FOREIGN KEY FK_D62F28587F3AE16B');
        $this->addSql('ALTER TABLE auth_sessions DROP FOREIGN KEY FK_BE9A1A95A76ED395');
        $this->addSql('ALTER TABLE profiles DROP FOREIGN KEY FK_8B308530A76ED395');
        $this->addSql('DROP TABLE audit_logs');
        $this->addSql('DROP TABLE auth_sessions');
        $this->addSql('DROP TABLE profiles');
        $this->addSql('DROP TABLE messenger_messages');
        $this->addSql('DROP INDEX uniq_debc775e237e06 ON emotion');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_BF661BDE5E237E06 ON emotion (name)');
        $this->addSql('ALTER TABLE exercice CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE exercice_control DROP FOREIGN KEY FK_10605AE689D40298');
        $this->addSql('ALTER TABLE exercice_control DROP FOREIGN KEY FK_10605AE661A2AF17');
        $this->addSql('ALTER TABLE exercice_control CHANGE started_at started_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE completed_at completed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('DROP INDEX idx_10605ae689d40298 ON exercice_control');
        $this->addSql('CREATE INDEX IDX_21B7B4117D3C2A0A ON exercice_control (exercice_id)');
        $this->addSql('DROP INDEX idx_10605ae661a2af17 ON exercice_control');
        $this->addSql('CREATE INDEX IDX_21B7B4118B2E1E52 ON exercice_control (assigned_by)');
        $this->addSql('ALTER TABLE exercice_control ADD CONSTRAINT FK_10605AE689D40298 FOREIGN KEY (exercice_id) REFERENCES exercice (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE exercice_control ADD CONSTRAINT FK_10605AE661A2AF17 FOREIGN KEY (assigned_by) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE exercice_favorite CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE exercice_resource CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('DROP INDEX uniq_487304315e237e06 ON influence');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2864F98E5E237E06 ON influence (name)');
        $this->addSql('ALTER TABLE mood_entry CHANGE entry_date entry_date DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE moment_type moment_type ENUM(\'MOMENT\', \'DAY\') NOT NULL, CHANGE mood_level mood_level TINYINT NOT NULL, CHANGE updated_at updated_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE mood_entry_emotion DROP FOREIGN KEY FK_EB264C191EE4A582');
        $this->addSql('DROP INDEX idx_eb264c191ee4a582 ON mood_entry_emotion');
        $this->addSql('CREATE INDEX IDX_EB264C19A755B3DD ON mood_entry_emotion (emotion_id)');
        $this->addSql('ALTER TABLE mood_entry_emotion ADD CONSTRAINT FK_EB264C191EE4A582 FOREIGN KEY (emotion_id) REFERENCES emotion (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE mood_entry_influence DROP FOREIGN KEY FK_EB05BFCEE88AF5D');
        $this->addSql('DROP INDEX idx_eb05bfcee88af5d ON mood_entry_influence');
        $this->addSql('CREATE INDEX IDX_EB05BFC277FE226 ON mood_entry_influence (influence_id)');
        $this->addSql('ALTER TABLE mood_entry_influence ADD CONSTRAINT FK_EB05BFCEE88AF5D FOREIGN KEY (influence_id) REFERENCES influence (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reves DROP FOREIGN KEY FK_C832E805F84A5F9B');
        $this->addSql('ALTER TABLE reves CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('DROP INDEX idx_c832e805f84a5f9b ON reves');
        $this->addSql('CREATE INDEX IDX_REVES_SOMMEIL ON reves (sommeil_id)');
        $this->addSql('ALTER TABLE reves ADD CONSTRAINT FK_C832E805F84A5F9B FOREIGN KEY (sommeil_id) REFERENCES sommeil (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE sommeil CHANGE date_nuit date_nuit DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE users CHANGE is_two_factor_enabled is_two_factor_enabled TINYINT DEFAULT 0 NOT NULL');
        $this->addSql('DROP INDEX uniq_1483a5e9e7927c74 ON users');
        $this->addSql('CREATE UNIQUE INDEX uniq_users_email ON users (email)');
        $this->addSql('DROP INDEX uniq_1483a5e976f5c865 ON users');
        $this->addSql('CREATE UNIQUE INDEX uniq_users_google_id ON users (google_id)');
        $this->addSql('CREATE INDEX idx_user_faces_user ON user_faces (user_id)');
    }
}
