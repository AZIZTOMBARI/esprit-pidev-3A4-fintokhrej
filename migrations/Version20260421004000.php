<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260421004000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add missing user_id column to code_promo for offer detail filtering in an idempotent way.';
    }

    public function up(Schema $schema): void
    {
        if (!$this->columnExists('code_promo', 'user_id')) {
            $this->addSql('ALTER TABLE code_promo ADD user_id INT DEFAULT NULL');
        }

        if (!$this->indexExists('code_promo', 'IDX_F3A20D6A76ED395')) {
            $this->addSql('CREATE INDEX IDX_F3A20D6A76ED395 ON code_promo (user_id)');
        }
    }

    public function down(Schema $schema): void
    {
        if ($this->indexExists('code_promo', 'IDX_F3A20D6A76ED395')) {
            $this->addSql('DROP INDEX IDX_F3A20D6A76ED395 ON code_promo');
        }

        if ($this->columnExists('code_promo', 'user_id')) {
            $this->addSql('ALTER TABLE code_promo DROP user_id');
        }
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
}
