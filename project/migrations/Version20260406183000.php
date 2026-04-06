<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260406183000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Normalize mood join-table index names after singular table rename';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('mood_entry_emotion')) {
            $table = $schema->getTable('mood_entry_emotion');
            if ($table->hasIndex('idx_7f8e418775b2d1a3') && !$table->hasIndex('IDX_EB264C1975B2D1A3')) {
                $this->addSql('ALTER TABLE mood_entry_emotion RENAME INDEX idx_7f8e418775b2d1a3 TO IDX_EB264C1975B2D1A3');
            }
            if ($table->hasIndex('idx_7f8e4187a755b3dd') && !$table->hasIndex('IDX_EB264C19A755B3DD')) {
                $this->addSql('ALTER TABLE mood_entry_emotion RENAME INDEX idx_7f8e4187a755b3dd TO IDX_EB264C19A755B3DD');
            }
        }

        if ($schema->hasTable('mood_entry_influence')) {
            $table = $schema->getTable('mood_entry_influence');
            if ($table->hasIndex('idx_af0bc16775b2d1a3') && !$table->hasIndex('IDX_EB05BFC75B2D1A3')) {
                $this->addSql('ALTER TABLE mood_entry_influence RENAME INDEX idx_af0bc16775b2d1a3 TO IDX_EB05BFC75B2D1A3');
            }
            if ($table->hasIndex('idx_af0bc167277fe226') && !$table->hasIndex('IDX_EB05BFC277FE226')) {
                $this->addSql('ALTER TABLE mood_entry_influence RENAME INDEX idx_af0bc167277fe226 TO IDX_EB05BFC277FE226');
            }
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('mood_entry_emotion')) {
            $table = $schema->getTable('mood_entry_emotion');
            if ($table->hasIndex('IDX_EB264C1975B2D1A3') && !$table->hasIndex('idx_7f8e418775b2d1a3')) {
                $this->addSql('ALTER TABLE mood_entry_emotion RENAME INDEX IDX_EB264C1975B2D1A3 TO idx_7f8e418775b2d1a3');
            }
            if ($table->hasIndex('IDX_EB264C19A755B3DD') && !$table->hasIndex('idx_7f8e4187a755b3dd')) {
                $this->addSql('ALTER TABLE mood_entry_emotion RENAME INDEX IDX_EB264C19A755B3DD TO idx_7f8e4187a755b3dd');
            }
        }

        if ($schema->hasTable('mood_entry_influence')) {
            $table = $schema->getTable('mood_entry_influence');
            if ($table->hasIndex('IDX_EB05BFC75B2D1A3') && !$table->hasIndex('idx_af0bc16775b2d1a3')) {
                $this->addSql('ALTER TABLE mood_entry_influence RENAME INDEX IDX_EB05BFC75B2D1A3 TO idx_af0bc16775b2d1a3');
            }
            if ($table->hasIndex('IDX_EB05BFC277FE226') && !$table->hasIndex('idx_af0bc167277fe226')) {
                $this->addSql('ALTER TABLE mood_entry_influence RENAME INDEX IDX_EB05BFC277FE226 TO idx_af0bc167277fe226');
            }
        }
    }
}
