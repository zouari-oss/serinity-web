<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260504164500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add persisted user risk fields for risk detection system';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD risk_level VARCHAR(20) DEFAULT NULL, ADD risk_confidence DOUBLE PRECISION DEFAULT NULL, ADD risk_prediction INT DEFAULT NULL, ADD risk_evaluated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP risk_level, DROP risk_confidence, DROP risk_prediction, DROP risk_evaluated_at');
    }
}

