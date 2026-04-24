<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260423183000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le module experience_sharing pour les souvenirs post-sortie.';
    }

    public function up(Schema $schema): void
    {
        if ($this->tableExists('experience_sharing')) {
            return;
        }

        $this->addSql('CREATE TABLE experience_sharing (
            id INT AUTO_INCREMENT NOT NULL,
            sortie_id INT NOT NULL,
            activated_at DATETIME NOT NULL,
            is_open TINYINT(1) NOT NULL DEFAULT 1,
            media_count INT NOT NULL DEFAULT 0,
            last_media_added_at DATETIME DEFAULT NULL,
            last_generated_at DATETIME DEFAULT NULL,
            recap_mode VARCHAR(20) NOT NULL DEFAULT \'immersive_html\',
            UNIQUE INDEX UNIQ_EXPERIENCE_SORTIE (sortie_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE experience_sharing ADD CONSTRAINT FK_EXPERIENCE_SORTIE FOREIGN KEY (sortie_id) REFERENCES annonce_sortie (id) ON DELETE CASCADE ON UPDATE CASCADE');
    }

    public function down(Schema $schema): void
    {
        if (!$this->tableExists('experience_sharing')) {
            return;
        }

        if ($this->foreignKeyExists('experience_sharing', 'FK_EXPERIENCE_SORTIE')) {
            $this->addSql('ALTER TABLE experience_sharing DROP FOREIGN KEY FK_EXPERIENCE_SORTIE');
        }

        $this->addSql('DROP TABLE experience_sharing');
    }

    private function tableExists(string $tableName): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$tableName]
        );
    }

    private function foreignKeyExists(string $tableName, string $constraintName): bool
    {
        if (!$this->tableExists($tableName)) {
            return false;
        }

        return (bool) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ?',
            [$tableName, $constraintName, 'FOREIGN KEY']
        );
    }
}
