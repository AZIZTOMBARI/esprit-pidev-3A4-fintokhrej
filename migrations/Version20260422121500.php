<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260422121500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Etend le chat des sorties avec edition, suppression, pieces jointes et etats de lecture.';
    }

    public function up(Schema $schema): void
    {
        if ($this->tableExists('chat_message')) {
            if (!$this->columnExists('chat_message', 'edited_at')) {
                $this->addSql('ALTER TABLE chat_message ADD edited_at DATETIME DEFAULT NULL');
            }

            if (!$this->columnExists('chat_message', 'deleted_at')) {
                $this->addSql('ALTER TABLE chat_message ADD deleted_at DATETIME DEFAULT NULL');
            }

            if (!$this->columnExists('chat_message', 'attachment_path')) {
                $this->addSql('ALTER TABLE chat_message ADD attachment_path VARCHAR(255) DEFAULT NULL');
            }

            if (!$this->columnExists('chat_message', 'attachment_type')) {
                $this->addSql('ALTER TABLE chat_message ADD attachment_type VARCHAR(60) DEFAULT NULL');
            }

            if (!$this->indexExists('chat_message', 'IDX_CHAT_MESSAGE_DELETED')) {
                $this->addSql('CREATE INDEX IDX_CHAT_MESSAGE_DELETED ON chat_message (deleted_at)');
            }
        }

        if (!$this->tableExists('chat_message_read')) {
            $this->addSql('CREATE TABLE chat_message_read (
                id INT AUTO_INCREMENT NOT NULL,
                chat_message_id INT NOT NULL,
                user_id INT NOT NULL,
                read_at DATETIME NOT NULL,
                UNIQUE INDEX UNIQ_CHAT_MESSAGE_READ_PAIR (chat_message_id, user_id),
                INDEX IDX_CHAT_MESSAGE_READ_MESSAGE (chat_message_id),
                INDEX IDX_CHAT_MESSAGE_READ_USER (user_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }

        if ($this->tableExists('chat_message_read') && !$this->foreignKeyExists('chat_message_read', 'FK_CHAT_MESSAGE_READ_MESSAGE')) {
            $this->addSql('ALTER TABLE chat_message_read ADD CONSTRAINT FK_CHAT_MESSAGE_READ_MESSAGE FOREIGN KEY (chat_message_id) REFERENCES chat_message (id) ON DELETE CASCADE');
        }

        if ($this->tableExists('chat_message_read') && !$this->foreignKeyExists('chat_message_read', 'FK_CHAT_MESSAGE_READ_USER')) {
            $this->addSql('ALTER TABLE chat_message_read ADD CONSTRAINT FK_CHAT_MESSAGE_READ_USER FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        }
    }

    public function down(Schema $schema): void
    {
        if ($this->tableExists('chat_message_read') && $this->foreignKeyExists('chat_message_read', 'FK_CHAT_MESSAGE_READ_USER')) {
            $this->addSql('ALTER TABLE chat_message_read DROP FOREIGN KEY FK_CHAT_MESSAGE_READ_USER');
        }

        if ($this->tableExists('chat_message_read') && $this->foreignKeyExists('chat_message_read', 'FK_CHAT_MESSAGE_READ_MESSAGE')) {
            $this->addSql('ALTER TABLE chat_message_read DROP FOREIGN KEY FK_CHAT_MESSAGE_READ_MESSAGE');
        }

        if ($this->tableExists('chat_message_read')) {
            $this->addSql('DROP TABLE chat_message_read');
        }

        if ($this->tableExists('chat_message')) {
            if ($this->indexExists('chat_message', 'IDX_CHAT_MESSAGE_DELETED')) {
                $this->addSql('DROP INDEX IDX_CHAT_MESSAGE_DELETED ON chat_message');
            }

            if ($this->columnExists('chat_message', 'attachment_type')) {
                $this->addSql('ALTER TABLE chat_message DROP attachment_type');
            }

            if ($this->columnExists('chat_message', 'attachment_path')) {
                $this->addSql('ALTER TABLE chat_message DROP attachment_path');
            }

            if ($this->columnExists('chat_message', 'deleted_at')) {
                $this->addSql('ALTER TABLE chat_message DROP deleted_at');
            }

            if ($this->columnExists('chat_message', 'edited_at')) {
                $this->addSql('ALTER TABLE chat_message DROP edited_at');
            }
        }
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
        return (bool) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ?',
            [$tableName, $constraintName, 'FOREIGN KEY']
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
