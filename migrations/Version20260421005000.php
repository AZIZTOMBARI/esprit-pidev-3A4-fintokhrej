<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260421005000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create offre_analysis table for storing offer analysis results in an idempotent way.';
    }

    public function up(Schema $schema): void
    {
        if ($this->tableExists('offre_analysis')) {
            return;
        }

        $this->addSql('CREATE TABLE offre_analysis (
            id INT AUTO_INCREMENT NOT NULL,
            offre_id INT,
            score INT NOT NULL,
            evaluation LONGTEXT NOT NULL,
            points_faibles JSON NOT NULL,
            ameliorations JSON NOT NULL,
            offre_optimisee JSON NOT NULL,
            diffusion JSON NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY(id),
            KEY idx_offre_id (offre_id),
            KEY idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    public function down(Schema $schema): void
    {
        if (!$this->tableExists('offre_analysis')) {
            return;
        }

        $this->addSql('DROP TABLE offre_analysis');
    }

    private function tableExists(string $tableName): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$tableName]
        );
    }
}
