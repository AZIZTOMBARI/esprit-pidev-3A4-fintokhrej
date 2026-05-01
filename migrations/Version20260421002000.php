<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260421002000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the nb_tickets column to the inscription table in an idempotent way.';
    }

    public function up(Schema $schema): void
    {
        if ($this->columnExists('inscription', 'nb_tickets')) {
            return;
        }

        $this->addSql('ALTER TABLE inscription ADD nb_tickets INT NOT NULL DEFAULT 1');
    }

    public function down(Schema $schema): void
    {
        if (!$this->columnExists('inscription', 'nb_tickets')) {
            return;
        }

        $this->addSql('ALTER TABLE inscription DROP nb_tickets');
    }

    private function columnExists(string $tableName, string $columnName): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$tableName, $columnName]
        );
    }
}
