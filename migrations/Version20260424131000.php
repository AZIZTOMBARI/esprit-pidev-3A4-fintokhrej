<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260424131000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Aligne la colonne inscription.statut avec le code Symfony et repare les statuts vides.';
    }

    public function up(Schema $schema): void
    {
        if (!$this->tableExists('inscription') || !$this->columnExists('inscription', 'statut')) {
            return;
        }

        $this->addSql("ALTER TABLE inscription MODIFY statut VARCHAR(20) NOT NULL DEFAULT 'CONFIRMEE'");

        $this->addSql(<<<'SQL'
            UPDATE inscription i
            LEFT JOIN (
                SELECT inscription_id, MAX(CASE WHEN statut = 'PAYE' THEN 1 ELSE 0 END) AS has_paid
                FROM paiement
                GROUP BY inscription_id
            ) p ON p.inscription_id = i.id
            SET i.statut = CASE
                WHEN COALESCE(NULLIF(i.statut, ''), '') <> '' THEN i.statut
                WHEN COALESCE(p.has_paid, 0) = 1 THEN 'PAYEE'
                ELSE 'CONFIRMEE'
            END
            WHERE i.statut = '' OR i.statut IS NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        if (!$this->tableExists('inscription') || !$this->columnExists('inscription', 'statut')) {
            return;
        }

        $this->addSql("UPDATE inscription SET statut = 'CONFIRMEE' WHERE statut NOT IN ('EN_ATTENTE', 'CONFIRMEE', 'ANNULEE')");
        $this->addSql("ALTER TABLE inscription MODIFY statut ENUM('EN_ATTENTE','CONFIRMEE','ANNULEE') NOT NULL DEFAULT 'EN_ATTENTE'");
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
