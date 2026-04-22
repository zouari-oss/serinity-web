<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260406120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create mood tracking tables';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Migration can only be executed safely on MySQL/MariaDB.'
        );

        $moodEmotions = $schema->createTable('mood_emotions');
        $moodEmotions->addColumn('id', 'guid');
        $moodEmotions->addColumn('emotion_key', 'string', ['length' => 64]);
        $moodEmotions->addColumn('label', 'string', ['length' => 128]);
        $moodEmotions->addColumn('created_at', 'datetime_immutable');
        $moodEmotions->addColumn('updated_at', 'datetime_immutable');
        $moodEmotions->setPrimaryKey(['id']);
        $moodEmotions->addUniqueIndex(['emotion_key'], 'uniq_mood_emotions_key');

        $moodInfluences = $schema->createTable('mood_influences');
        $moodInfluences->addColumn('id', 'guid');
        $moodInfluences->addColumn('influence_key', 'string', ['length' => 64]);
        $moodInfluences->addColumn('label', 'string', ['length' => 128]);
        $moodInfluences->addColumn('created_at', 'datetime_immutable');
        $moodInfluences->addColumn('updated_at', 'datetime_immutable');
        $moodInfluences->setPrimaryKey(['id']);
        $moodInfluences->addUniqueIndex(['influence_key'], 'uniq_mood_influences_key');

        $moodEntries = $schema->createTable('mood_entries');
        $moodEntries->addColumn('id', 'guid');
        $moodEntries->addColumn('user_id', 'string', ['length' => 36]);
        $moodEntries->addColumn('mood_score', 'smallint');
        $moodEntries->addColumn('note', 'text', ['notnull' => false]);
        $moodEntries->addColumn('recorded_at', 'datetime_immutable');
        $moodEntries->addColumn('created_at', 'datetime_immutable');
        $moodEntries->addColumn('updated_at', 'datetime_immutable');
        $moodEntries->setPrimaryKey(['id']);
        $moodEntries->addIndex(['user_id', 'recorded_at'], 'idx_mood_entries_user_recorded_at');
        $moodEntryEmotions = $schema->createTable('mood_entry_emotions');
        $moodEntryEmotions->addColumn('mood_entry_id', 'guid');
        $moodEntryEmotions->addColumn('emotion_id', 'guid');
        $moodEntryEmotions->setPrimaryKey(['mood_entry_id', 'emotion_id']);
        $moodEntryEmotions->addIndex(['emotion_id'], 'idx_mood_entry_emotions_emotion');
        $moodEntryEmotions->addForeignKeyConstraint(
            'mood_entries',
            ['mood_entry_id'],
            ['id'],
            ['onDelete' => 'CASCADE'],
            'fk_mood_entry_emotions_entry'
        );
        $moodEntryEmotions->addForeignKeyConstraint(
            'mood_emotions',
            ['emotion_id'],
            ['id'],
            ['onDelete' => 'CASCADE'],
            'fk_mood_entry_emotions_emotion'
        );

        $moodEntryInfluences = $schema->createTable('mood_entry_influences');
        $moodEntryInfluences->addColumn('mood_entry_id', 'guid');
        $moodEntryInfluences->addColumn('influence_id', 'guid');
        $moodEntryInfluences->setPrimaryKey(['mood_entry_id', 'influence_id']);
        $moodEntryInfluences->addIndex(['influence_id'], 'idx_mood_entry_influences_influence');
        $moodEntryInfluences->addForeignKeyConstraint(
            'mood_entries',
            ['mood_entry_id'],
            ['id'],
            ['onDelete' => 'CASCADE'],
            'fk_mood_entry_influences_entry'
        );
        $moodEntryInfluences->addForeignKeyConstraint(
            'mood_influences',
            ['influence_id'],
            ['id'],
            ['onDelete' => 'CASCADE'],
            'fk_mood_entry_influences_influence'
        );
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Migration can only be executed safely on MySQL/MariaDB.'
        );

        $schema->dropTable('mood_entry_influences');
        $schema->dropTable('mood_entry_emotions');
        $schema->dropTable('mood_entries');
        $schema->dropTable('mood_influences');
        $schema->dropTable('mood_emotions');
    }
}
