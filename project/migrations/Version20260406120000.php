<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260406120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create mood tracking tables and seed baseline emotions and influences';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('mood_emotions')) {
            $moodEmotions = $schema->createTable('mood_emotions');
            $moodEmotions->addColumn('id', 'string', ['length' => 36]);
            $moodEmotions->addColumn('emotion_key', 'string', ['length' => 64]);
            $moodEmotions->addColumn('label', 'string', ['length' => 100]);
            $moodEmotions->addColumn('created_at', 'datetime_immutable');
            $moodEmotions->addColumn('updated_at', 'datetime_immutable');
            $moodEmotions->setPrimaryKey(['id']);
            $moodEmotions->addUniqueIndex(['emotion_key'], 'uk_mood_emotion_key');
        }

        if (!$schema->hasTable('mood_influences')) {
            $moodInfluences = $schema->createTable('mood_influences');
            $moodInfluences->addColumn('id', 'string', ['length' => 36]);
            $moodInfluences->addColumn('influence_key', 'string', ['length' => 64]);
            $moodInfluences->addColumn('label', 'string', ['length' => 100]);
            $moodInfluences->addColumn('created_at', 'datetime_immutable');
            $moodInfluences->addColumn('updated_at', 'datetime_immutable');
            $moodInfluences->setPrimaryKey(['id']);
            $moodInfluences->addUniqueIndex(['influence_key'], 'uk_mood_influence_key');
        }

        if (!$schema->hasTable('mood_entries')) {
            $moodEntries = $schema->createTable('mood_entries');
            $moodEntries->addColumn('id', 'string', ['length' => 36]);
            $moodEntries->addColumn('user_id', 'string', ['length' => 36]);
            $moodEntries->addColumn('entry_date', 'date_immutable');
            $moodEntries->addColumn('note', 'text', ['notnull' => false]);
            $moodEntries->addColumn('created_at', 'datetime_immutable');
            $moodEntries->addColumn('updated_at', 'datetime_immutable');
            $moodEntries->setPrimaryKey(['id']);
            $moodEntries->addIndex(['user_id'], 'idx_mood_entries_user');
            $moodEntries->addIndex(['entry_date'], 'idx_mood_entries_entry_date');
            $moodEntries->addIndex(['user_id', 'entry_date'], 'idx_mood_entries_user_date');
            $moodEntries->addForeignKeyConstraint('users', ['user_id'], ['id'], ['onDelete' => 'CASCADE']);
        }

        if (!$schema->hasTable('mood_entry_emotions')) {
            $moodEntryEmotions = $schema->createTable('mood_entry_emotions');
            $moodEntryEmotions->addColumn('mood_entry_id', 'string', ['length' => 36]);
            $moodEntryEmotions->addColumn('mood_emotion_id', 'string', ['length' => 36]);
            $moodEntryEmotions->setPrimaryKey(['mood_entry_id', 'mood_emotion_id']);
            $moodEntryEmotions->addIndex(['mood_emotion_id'], 'idx_mood_entry_emotion_emotion');
            $moodEntryEmotions->addForeignKeyConstraint('mood_entries', ['mood_entry_id'], ['id'], ['onDelete' => 'CASCADE']);
            $moodEntryEmotions->addForeignKeyConstraint('mood_emotions', ['mood_emotion_id'], ['id'], ['onDelete' => 'CASCADE']);
        }

        if (!$schema->hasTable('mood_entry_influences')) {
            $moodEntryInfluences = $schema->createTable('mood_entry_influences');
            $moodEntryInfluences->addColumn('mood_entry_id', 'string', ['length' => 36]);
            $moodEntryInfluences->addColumn('mood_influence_id', 'string', ['length' => 36]);
            $moodEntryInfluences->setPrimaryKey(['mood_entry_id', 'mood_influence_id']);
            $moodEntryInfluences->addIndex(['mood_influence_id'], 'idx_mood_entry_influence_influence');
            $moodEntryInfluences->addForeignKeyConstraint('mood_entries', ['mood_entry_id'], ['id'], ['onDelete' => 'CASCADE']);
            $moodEntryInfluences->addForeignKeyConstraint('mood_influences', ['mood_influence_id'], ['id'], ['onDelete' => 'CASCADE']);
        }

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $emotionSeeds = [
            ['7ca11d14-1745-4b5e-8dd4-2b79de5dbf01', 'happy', 'Happy'],
            ['7ca11d14-1745-4b5e-8dd4-2b79de5dbf02', 'calm', 'Calm'],
            ['7ca11d14-1745-4b5e-8dd4-2b79de5dbf03', 'anxious', 'Anxious'],
            ['7ca11d14-1745-4b5e-8dd4-2b79de5dbf04', 'sad', 'Sad'],
            ['7ca11d14-1745-4b5e-8dd4-2b79de5dbf05', 'angry', 'Angry'],
        ];

        foreach ($emotionSeeds as [$id, $key, $label]) {
            $this->addSql(
                'INSERT INTO mood_emotions (id, emotion_key, label, created_at, updated_at)
                 SELECT :id, :emotionKey, :label, :createdAt, :updatedAt
                 WHERE NOT EXISTS (SELECT 1 FROM mood_emotions WHERE emotion_key = :emotionKey)',
                [
                    'id' => $id,
                    'emotionKey' => $key,
                    'label' => $label,
                    'createdAt' => $now,
                    'updatedAt' => $now,
                ]
            );
        }

        $influenceSeeds = [
            ['8db22e25-2856-4c6f-9ee5-3c80ef6ecf11', 'work', 'Work'],
            ['8db22e25-2856-4c6f-9ee5-3c80ef6ecf12', 'family', 'Family'],
            ['8db22e25-2856-4c6f-9ee5-3c80ef6ecf13', 'health', 'Health'],
            ['8db22e25-2856-4c6f-9ee5-3c80ef6ecf14', 'social', 'Social'],
            ['8db22e25-2856-4c6f-9ee5-3c80ef6ecf15', 'sleep', 'Sleep'],
        ];

        foreach ($influenceSeeds as [$id, $key, $label]) {
            $this->addSql(
                'INSERT INTO mood_influences (id, influence_key, label, created_at, updated_at)
                 SELECT :id, :influenceKey, :label, :createdAt, :updatedAt
                 WHERE NOT EXISTS (SELECT 1 FROM mood_influences WHERE influence_key = :influenceKey)',
                [
                    'id' => $id,
                    'influenceKey' => $key,
                    'label' => $label,
                    'createdAt' => $now,
                    'updatedAt' => $now,
                ]
            );
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('mood_entry_influences')) {
            $schema->dropTable('mood_entry_influences');
        }

        if ($schema->hasTable('mood_entry_emotions')) {
            $schema->dropTable('mood_entry_emotions');
        }

        if ($schema->hasTable('mood_entries')) {
            $schema->dropTable('mood_entries');
        }

        if ($schema->hasTable('mood_influences')) {
            $schema->dropTable('mood_influences');
        }

        if ($schema->hasTable('mood_emotions')) {
            $schema->dropTable('mood_emotions');
        }
    }
}
