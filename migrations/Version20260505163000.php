<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260505163000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Renomme les colonnes user.imageUrl, poll.created_by, poll_option.created_by, sortie_task.created_by et sortie_task.assigned_to.';
    }

    public function up(Schema $schema): void
    {
        if ($this->tableExists('user') && $this->columnExists('user', 'imageUrl') && !$this->columnExists('user', 'image_url')) {
            $this->addSql('ALTER TABLE user CHANGE imageUrl image_url VARCHAR(255) NOT NULL');
        }

        if ($this->tableExists('poll') && $this->columnExists('poll', 'created_by') && !$this->columnExists('poll', 'created_by_id')) {
            $this->addSql('ALTER TABLE poll CHANGE created_by created_by_id INT DEFAULT NULL');
        }

        if ($this->tableExists('poll_option') && $this->columnExists('poll_option', 'created_by') && !$this->columnExists('poll_option', 'created_by_id')) {
            $this->addSql('ALTER TABLE poll_option CHANGE created_by created_by_id INT DEFAULT NULL');
        }

        if ($this->tableExists('sortie_task') && $this->columnExists('sortie_task', 'created_by') && !$this->columnExists('sortie_task', 'created_by_id')) {
            $this->addSql('ALTER TABLE sortie_task CHANGE created_by created_by_id INT DEFAULT NULL');
        }

        if ($this->tableExists('sortie_task') && $this->columnExists('sortie_task', 'assigned_to') && !$this->columnExists('sortie_task', 'assigned_to_id')) {
            $this->addSql('ALTER TABLE sortie_task CHANGE assigned_to assigned_to_id INT DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        if ($this->tableExists('user') && $this->columnExists('user', 'image_url') && !$this->columnExists('user', 'imageUrl')) {
            $this->addSql('ALTER TABLE user CHANGE image_url imageUrl VARCHAR(255) NOT NULL');
        }

        if ($this->tableExists('poll') && $this->columnExists('poll', 'created_by_id') && !$this->columnExists('poll', 'created_by')) {
            $this->addSql('ALTER TABLE poll CHANGE created_by_id created_by INT DEFAULT NULL');
        }

        if ($this->tableExists('poll_option') && $this->columnExists('poll_option', 'created_by_id') && !$this->columnExists('poll_option', 'created_by')) {
            $this->addSql('ALTER TABLE poll_option CHANGE created_by_id created_by INT DEFAULT NULL');
        }

        if ($this->tableExists('sortie_task') && $this->columnExists('sortie_task', 'created_by_id') && !$this->columnExists('sortie_task', 'created_by')) {
            $this->addSql('ALTER TABLE sortie_task CHANGE created_by_id created_by INT DEFAULT NULL');
        }

        if ($this->tableExists('sortie_task') && $this->columnExists('sortie_task', 'assigned_to_id') && !$this->columnExists('sortie_task', 'assigned_to')) {
            $this->addSql('ALTER TABLE sortie_task CHANGE assigned_to_id assigned_to INT DEFAULT NULL');
        }
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
}
