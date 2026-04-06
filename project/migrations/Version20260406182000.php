<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260406182000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align mood table names with initial singular naming conventions';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('mood_entry_emotions') && !$schema->hasTable('mood_entry_emotion')) {
            $this->addSql('RENAME TABLE mood_entry_emotions TO mood_entry_emotion');
        }

        if ($schema->hasTable('mood_entry_influences') && !$schema->hasTable('mood_entry_influence')) {
            $this->addSql('RENAME TABLE mood_entry_influences TO mood_entry_influence');
        }

        if ($schema->hasTable('mood_entries') && !$schema->hasTable('mood_entry')) {
            $this->addSql('RENAME TABLE mood_entries TO mood_entry');
        }

        if ($schema->hasTable('mood_emotions') && !$schema->hasTable('emotion')) {
            $this->addSql('RENAME TABLE mood_emotions TO emotion');
        }

        if ($schema->hasTable('mood_influences') && !$schema->hasTable('influence')) {
            $this->addSql('RENAME TABLE mood_influences TO influence');
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('mood_entry_emotion') && !$schema->hasTable('mood_entry_emotions')) {
            $this->addSql('RENAME TABLE mood_entry_emotion TO mood_entry_emotions');
        }

        if ($schema->hasTable('mood_entry_influence') && !$schema->hasTable('mood_entry_influences')) {
            $this->addSql('RENAME TABLE mood_entry_influence TO mood_entry_influences');
        }

        if ($schema->hasTable('mood_entry') && !$schema->hasTable('mood_entries')) {
            $this->addSql('RENAME TABLE mood_entry TO mood_entries');
        }

        if ($schema->hasTable('emotion') && !$schema->hasTable('mood_emotions')) {
            $this->addSql('RENAME TABLE emotion TO mood_emotions');
        }

        if ($schema->hasTable('influence') && !$schema->hasTable('mood_influences')) {
            $this->addSql('RENAME TABLE influence TO mood_influences');
        }
    }
}
