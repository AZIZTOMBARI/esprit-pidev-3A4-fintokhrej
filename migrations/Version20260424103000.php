<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260424103000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les donnees persistantes de billetterie intelligente pour les QR codes et les PDF.';
    }

    public function up(Schema $schema): void
    {
        if (!$this->tableExists('ticket')) {
            return;
        }

        if (!$this->columnExists('ticket', 'numero_ticket')) {
            $this->addSql("ALTER TABLE ticket ADD numero_ticket VARCHAR(80) DEFAULT NULL, ADD statut VARCHAR(20) NOT NULL DEFAULT 'VALIDE', ADD code_validation VARCHAR(32) DEFAULT NULL");
        }

        $this->addSql("UPDATE ticket SET
            numero_ticket = COALESCE(numero_ticket, CONCAT('EVT-', inscription_id, '-', DATE_FORMAT(`date`, '%Y%m%d'), '-', LPAD(id, 4, '0'))),
            code_validation = COALESCE(code_validation, UPPER(SUBSTRING(MD5(CONCAT(id, '-', inscription_id, '-', `date`)), 1, 16))),
            statut = COALESCE(NULLIF(statut, ''), 'VALIDE')
        ");

        $this->addSql('ALTER TABLE ticket MODIFY numero_ticket VARCHAR(80) NOT NULL, MODIFY code_validation VARCHAR(32) NOT NULL');

        if (!$this->indexExists('ticket', 'UNIQ_TICKET_NUMERO')) {
            $this->addSql('CREATE UNIQUE INDEX UNIQ_TICKET_NUMERO ON ticket (numero_ticket)');
        }

        if (!$this->indexExists('ticket', 'UNIQ_TICKET_CODE_VALIDATION')) {
            $this->addSql('CREATE UNIQUE INDEX UNIQ_TICKET_CODE_VALIDATION ON ticket (code_validation)');
        }
    }

    public function down(Schema $schema): void
    {
        if (!$this->tableExists('ticket')) {
            return;
        }

        if ($this->indexExists('ticket', 'UNIQ_TICKET_NUMERO')) {
            $this->addSql('DROP INDEX UNIQ_TICKET_NUMERO ON ticket');
        }

        if ($this->indexExists('ticket', 'UNIQ_TICKET_CODE_VALIDATION')) {
            $this->addSql('DROP INDEX UNIQ_TICKET_CODE_VALIDATION ON ticket');
        }

        if ($this->columnExists('ticket', 'numero_ticket')) {
            $this->addSql('ALTER TABLE ticket DROP numero_ticket, DROP statut, DROP code_validation');
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

    private function indexExists(string $tableName, string $indexName): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$tableName, $indexName]
        );
    }
}
