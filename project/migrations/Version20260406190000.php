<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260406190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align mood tables with mood-control.sql structure and seed emotion/influence data';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('SET FOREIGN_KEY_CHECKS = 0');

        $this->addSql('DROP TABLE IF EXISTS mood_entry_emotion');
        $this->addSql('DROP TABLE IF EXISTS mood_entry_influence');
        $this->addSql('DROP TABLE IF EXISTS mood_entry');
        $this->addSql('DROP TABLE IF EXISTS emotion');
        $this->addSql('DROP TABLE IF EXISTS influence');

        $this->addSql(<<<'SQL'
CREATE TABLE emotion (
  id INT AUTO_INCREMENT NOT NULL,
  name VARCHAR(40) NOT NULL,
  UNIQUE INDEX UNIQ_BF661BDE5E237E06 (name),
  PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE influence (
  id INT AUTO_INCREMENT NOT NULL,
  name VARCHAR(60) NOT NULL,
  UNIQUE INDEX UNIQ_2864F98E5E237E06 (name),
  PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE mood_entry (
  id BIGINT AUTO_INCREMENT NOT NULL,
  user_id VARCHAR(36) NOT NULL,
  entry_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  moment_type ENUM('MOMENT', 'DAY') NOT NULL,
  mood_level TINYINT NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_mood_entry_user_date (user_id, entry_date),
  PRIMARY KEY(id),
  CONSTRAINT fk_mood_entry_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_uca1400_ai_ci` ENGINE = InnoDB
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE mood_entry_emotion (
  mood_entry_id BIGINT NOT NULL,
  emotion_id INT NOT NULL,
  INDEX IDX_EB264C19A755B3DD (emotion_id),
  PRIMARY KEY(mood_entry_id, emotion_id),
  CONSTRAINT FK_EB264C1975B2D1A3 FOREIGN KEY (mood_entry_id) REFERENCES mood_entry (id) ON DELETE CASCADE,
  CONSTRAINT FK_EB264C19A755B3DD FOREIGN KEY (emotion_id) REFERENCES emotion (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE mood_entry_influence (
  mood_entry_id BIGINT NOT NULL,
  influence_id INT NOT NULL,
  INDEX IDX_EB05BFC277FE226 (influence_id),
  PRIMARY KEY(mood_entry_id, influence_id),
  CONSTRAINT FK_EB05BFC75B2D1A3 FOREIGN KEY (mood_entry_id) REFERENCES mood_entry (id) ON DELETE CASCADE,
  CONSTRAINT FK_EB05BFC277FE226 FOREIGN KEY (influence_id) REFERENCES influence (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        $this->addSql(<<<'SQL'
INSERT INTO emotion (id, name) VALUES
(19, 'Afraid'), (25, 'Angry'), (16, 'Anxious'), (27, 'Ashamed'), (13, 'Bored'),
(1, 'Calm'), (7, 'Confident'), (2, 'Content'), (23, 'Disappointed'), (4, 'Excited'),
(24, 'Frustrated'), (5, 'Grateful'), (26, 'Guilty'), (3, 'Happy'), (6, 'Hopeful'),
(30, 'Hurt'), (20, 'Insecure'), (9, 'Inspired'), (29, 'Irritated'), (28, 'Jealous'),
(22, 'Lonely'), (10, 'Motivated'), (11, 'Neutral'), (14, 'Numb'), (15, 'Overwhelmed'),
(8, 'Proud'), (21, 'Sad'), (17, 'Stressed'), (12, 'Tired'), (18, 'Worried')
SQL);

        $this->addSql(<<<'SQL'
INSERT INTO influence (id, name) VALUES
(19, 'Achievement'), (27, 'Caffeine'), (18, 'Conflict'), (3, 'Deadlines'), (11, 'Exercise'),
(20, 'Failure'), (6, 'Family'), (12, 'Food'), (5, 'Friends'), (23, 'Gaming'),
(9, 'Health'), (17, 'Loneliness'), (26, 'Medication'), (15, 'Money'), (22, 'Music'),
(14, 'News'), (10, 'Pain'), (7, 'Relationship'), (21, 'Relaxation'), (2, 'School/Work'),
(1, 'Sleep'), (8, 'Social media'), (4, 'Stress'), (24, 'Study'), (25, 'Therapy'),
(16, 'Travel/Commute'), (13, 'Weather')
SQL);

        $this->addSql('ALTER TABLE emotion AUTO_INCREMENT = 32');
        $this->addSql('ALTER TABLE influence AUTO_INCREMENT = 28');
        $this->addSql('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('This migration rebuilds mood tables from mood-control.sql and cannot be safely reversed.');
    }
}
