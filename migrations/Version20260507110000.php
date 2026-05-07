<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260507110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la colonne lieu.offre_id si elle manque pour aligner le schema avec le mapping Doctrine.';
    }

    public function up(Schema $schema): void
    {
        if (!$this->tableExists('lieu') || $this->columnExists('lieu', 'offre_id')) {
            return;
        }

        $this->addSql('ALTER TABLE lieu ADD offre_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_2F1A9FE8594C45C ON lieu (offre_id)');
        $this->addSql('ALTER TABLE lieu ADD CONSTRAINT FK_2F1A9FE8594C45C FOREIGN KEY (offre_id) REFERENCES offre (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        if (!$this->tableExists('lieu') || !$this->columnExists('lieu', 'offre_id')) {
            return;
        }

        if ($this->foreignKeyExists('lieu', 'FK_2F1A9FE8594C45C')) {
            $this->addSql('ALTER TABLE lieu DROP FOREIGN KEY FK_2F1A9FE8594C45C');
        }

        if ($this->indexExists('lieu', 'IDX_2F1A9FE8594C45C')) {
            $this->addSql('DROP INDEX IDX_2F1A9FE8594C45C ON lieu');
        }

        $this->addSql('ALTER TABLE lieu DROP COLUMN offre_id');
    }

    private function tableExists(string $tableName): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$tableName]
        );
    }

    private function columnExists(string $tableName, string $columnName): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$tableName, $columnName]
        );
    }

    private function indexExists(string $tableName, string $indexName): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$tableName, $indexName]
        );
    }

    private function foreignKeyExists(string $tableName, string $constraintName): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ?',
            [$tableName, $constraintName, 'FOREIGN KEY']
        );
    }
}
