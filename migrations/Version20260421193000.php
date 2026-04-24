<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260421193000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le module chat des sorties de facon compatible avec les tables deja existantes.';
    }

    public function up(Schema $schema): void
    {
        $chatGroupeExists = $this->tableExists('chat_groupe');
        $chatGroupeMembreExists = $this->tableExists('chat_groupe_membre');
        $pollVoteExists = $this->tableExists('poll_vote');
        $chatMessageExists = $this->tableExists('chat_message');

        if (!$this->tableExists('chat_groupe')) {
            $this->addSql('CREATE TABLE chat_groupe (
                id INT AUTO_INCREMENT NOT NULL,
                annonce_id INT NOT NULL,
                created_at DATETIME NOT NULL,
                UNIQUE INDEX UNIQ_CHAT_GROUPE_ANNONCE (annonce_id),
                INDEX IDX_CHAT_GROUPE_ANNONCE (annonce_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }

        if (!$this->tableExists('chat_groupe_membre')) {
            $this->addSql('CREATE TABLE chat_groupe_membre (
                id INT AUTO_INCREMENT NOT NULL,
                chat_groupe_id INT NOT NULL,
                user_id INT NOT NULL,
                joined_at DATETIME NOT NULL,
                UNIQUE INDEX UNIQ_CHAT_GROUPE_MEMBRE_PAIR (chat_groupe_id, user_id),
                INDEX IDX_CHAT_GROUPE_MEMBRE_GROUPE (chat_groupe_id),
                INDEX IDX_CHAT_GROUPE_MEMBRE_USER (user_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }

        if (!$this->tableExists('poll_vote')) {
            $this->addSql('CREATE TABLE poll_vote (
                id INT AUTO_INCREMENT NOT NULL,
                poll_id INT NOT NULL,
                option_id INT NOT NULL,
                user_id INT NOT NULL,
                voted_at DATETIME NOT NULL,
                UNIQUE INDEX UNIQ_POLL_VOTE_USER (poll_id, user_id),
                INDEX IDX_POLL_VOTE_OPTION (option_id),
                INDEX IDX_POLL_VOTE_USER (user_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }

        if (!$this->tableExists('chat_message')) {
            $this->addSql('CREATE TABLE chat_message (
                id INT AUTO_INCREMENT NOT NULL,
                annonce_id INT NOT NULL,
                chat_groupe_id INT DEFAULT NULL,
                sender_id INT DEFAULT NULL,
                content LONGTEXT NOT NULL,
                message_type VARCHAR(50) NOT NULL,
                poll_id INT DEFAULT NULL,
                meta_json LONGTEXT DEFAULT NULL,
                sent_at DATETIME NOT NULL,
                INDEX IDX_CHAT_MESSAGE_ANNONCE (annonce_id),
                INDEX IDX_CHAT_MESSAGE_GROUPE (chat_groupe_id),
                INDEX IDX_CHAT_MESSAGE_SENDER (sender_id),
                INDEX IDX_CHAT_MESSAGE_POLL (poll_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }

        if ($chatMessageExists && !$this->columnExists('chat_message', 'chat_groupe_id')) {
            $this->addSql('ALTER TABLE chat_message ADD chat_groupe_id INT DEFAULT NULL');
        }

        if ($chatMessageExists && $this->columnExists('chat_message', 'sender_id')) {
            $this->addSql('ALTER TABLE chat_message MODIFY sender_id INT DEFAULT NULL');
        }

        if ($chatMessageExists && $this->columnExists('chat_message', 'message_type')) {
            $this->addSql('ALTER TABLE chat_message MODIFY message_type VARCHAR(50) NOT NULL');
        }

        if ($chatMessageExists && $this->columnExists('chat_message', 'chat_groupe_id') && !$this->indexExists('chat_message', 'IDX_CHAT_MESSAGE_GROUPE')) {
            $this->addSql('CREATE INDEX IDX_CHAT_MESSAGE_GROUPE ON chat_message (chat_groupe_id)');
        }

        if ($chatMessageExists && !$this->indexExists('chat_message', 'IDX_CHAT_MESSAGE_ANNONCE')) {
            $this->addSql('CREATE INDEX IDX_CHAT_MESSAGE_ANNONCE ON chat_message (annonce_id)');
        }

        if ($chatMessageExists && !$this->indexExists('chat_message', 'IDX_CHAT_MESSAGE_SENDER')) {
            $this->addSql('CREATE INDEX IDX_CHAT_MESSAGE_SENDER ON chat_message (sender_id)');
        }

        if ($chatMessageExists && !$this->indexExists('chat_message', 'IDX_CHAT_MESSAGE_POLL')) {
            $this->addSql('CREATE INDEX IDX_CHAT_MESSAGE_POLL ON chat_message (poll_id)');
        }

        if ($chatGroupeExists && !$this->foreignKeyExists('chat_groupe', 'FK_CHAT_GROUPE_ANNONCE')) {
            $this->addSql('ALTER TABLE chat_groupe ADD CONSTRAINT FK_CHAT_GROUPE_ANNONCE FOREIGN KEY (annonce_id) REFERENCES annonce_sortie (id) ON DELETE CASCADE');
        }

        if ($chatGroupeMembreExists && !$this->foreignKeyExists('chat_groupe_membre', 'FK_CHAT_GROUPE_MEMBRE_GROUPE')) {
            $this->addSql('ALTER TABLE chat_groupe_membre ADD CONSTRAINT FK_CHAT_GROUPE_MEMBRE_GROUPE FOREIGN KEY (chat_groupe_id) REFERENCES chat_groupe (id) ON DELETE CASCADE');
        }

        if ($chatGroupeMembreExists && !$this->foreignKeyExists('chat_groupe_membre', 'FK_CHAT_GROUPE_MEMBRE_USER')) {
            $this->addSql('ALTER TABLE chat_groupe_membre ADD CONSTRAINT FK_CHAT_GROUPE_MEMBRE_USER FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        }

        if ($pollVoteExists && $this->tableExists('poll') && !$this->foreignKeyExists('poll_vote', 'FK_POLL_VOTE_POLL')) {
            $this->addSql('ALTER TABLE poll_vote ADD CONSTRAINT FK_POLL_VOTE_POLL FOREIGN KEY (poll_id) REFERENCES poll (id) ON DELETE CASCADE');
        }

        if ($pollVoteExists && $this->tableExists('poll_option') && !$this->foreignKeyExists('poll_vote', 'FK_POLL_VOTE_OPTION')) {
            $this->addSql('ALTER TABLE poll_vote ADD CONSTRAINT FK_POLL_VOTE_OPTION FOREIGN KEY (option_id) REFERENCES poll_option (id) ON DELETE CASCADE');
        }

        if ($pollVoteExists && !$this->foreignKeyExists('poll_vote', 'FK_POLL_VOTE_USER')) {
            $this->addSql('ALTER TABLE poll_vote ADD CONSTRAINT FK_POLL_VOTE_USER FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        }

        if (!$chatMessageExists) {
            if (!$this->foreignKeyExists('chat_message', 'FK_CHAT_MESSAGE_ANNONCE')) {
                $this->addSql('ALTER TABLE chat_message ADD CONSTRAINT FK_CHAT_MESSAGE_ANNONCE FOREIGN KEY (annonce_id) REFERENCES annonce_sortie (id) ON DELETE CASCADE');
            }

            if ($this->tableExists('chat_groupe') && !$this->foreignKeyExists('chat_message', 'FK_CHAT_MESSAGE_GROUPE')) {
                $this->addSql('ALTER TABLE chat_message ADD CONSTRAINT FK_CHAT_MESSAGE_GROUPE FOREIGN KEY (chat_groupe_id) REFERENCES chat_groupe (id) ON DELETE SET NULL');
            }

            if (!$this->foreignKeyExists('chat_message', 'FK_CHAT_MESSAGE_SENDER')) {
                $this->addSql('ALTER TABLE chat_message ADD CONSTRAINT FK_CHAT_MESSAGE_SENDER FOREIGN KEY (sender_id) REFERENCES user (id) ON DELETE SET NULL');
            }

            if ($this->tableExists('poll') && !$this->foreignKeyExists('chat_message', 'FK_CHAT_MESSAGE_POLL')) {
                $this->addSql('ALTER TABLE chat_message ADD CONSTRAINT FK_CHAT_MESSAGE_POLL FOREIGN KEY (poll_id) REFERENCES poll (id) ON DELETE SET NULL');
            }
        }
    }

    public function down(Schema $schema): void
    {
        if ($this->tableExists('chat_message') && $this->foreignKeyExists('chat_message', 'FK_CHAT_MESSAGE_POLL')) {
            $this->addSql('ALTER TABLE chat_message DROP FOREIGN KEY FK_CHAT_MESSAGE_POLL');
        }
        if ($this->tableExists('chat_message') && $this->foreignKeyExists('chat_message', 'FK_CHAT_MESSAGE_SENDER')) {
            $this->addSql('ALTER TABLE chat_message DROP FOREIGN KEY FK_CHAT_MESSAGE_SENDER');
        }
        if ($this->tableExists('chat_message') && $this->foreignKeyExists('chat_message', 'FK_CHAT_MESSAGE_GROUPE')) {
            $this->addSql('ALTER TABLE chat_message DROP FOREIGN KEY FK_CHAT_MESSAGE_GROUPE');
        }
        if ($this->tableExists('chat_message') && $this->foreignKeyExists('chat_message', 'FK_CHAT_MESSAGE_ANNONCE')) {
            $this->addSql('ALTER TABLE chat_message DROP FOREIGN KEY FK_CHAT_MESSAGE_ANNONCE');
        }

        if ($this->tableExists('poll_vote') && $this->foreignKeyExists('poll_vote', 'FK_POLL_VOTE_USER')) {
            $this->addSql('ALTER TABLE poll_vote DROP FOREIGN KEY FK_POLL_VOTE_USER');
        }
        if ($this->tableExists('poll_vote') && $this->foreignKeyExists('poll_vote', 'FK_POLL_VOTE_OPTION')) {
            $this->addSql('ALTER TABLE poll_vote DROP FOREIGN KEY FK_POLL_VOTE_OPTION');
        }
        if ($this->tableExists('poll_vote') && $this->foreignKeyExists('poll_vote', 'FK_POLL_VOTE_POLL')) {
            $this->addSql('ALTER TABLE poll_vote DROP FOREIGN KEY FK_POLL_VOTE_POLL');
        }

        if ($this->tableExists('chat_groupe_membre') && $this->foreignKeyExists('chat_groupe_membre', 'FK_CHAT_GROUPE_MEMBRE_USER')) {
            $this->addSql('ALTER TABLE chat_groupe_membre DROP FOREIGN KEY FK_CHAT_GROUPE_MEMBRE_USER');
        }
        if ($this->tableExists('chat_groupe_membre') && $this->foreignKeyExists('chat_groupe_membre', 'FK_CHAT_GROUPE_MEMBRE_GROUPE')) {
            $this->addSql('ALTER TABLE chat_groupe_membre DROP FOREIGN KEY FK_CHAT_GROUPE_MEMBRE_GROUPE');
        }
        if ($this->tableExists('chat_groupe') && $this->foreignKeyExists('chat_groupe', 'FK_CHAT_GROUPE_ANNONCE')) {
            $this->addSql('ALTER TABLE chat_groupe DROP FOREIGN KEY FK_CHAT_GROUPE_ANNONCE');
        }

        $this->addSql('DROP TABLE IF EXISTS chat_message');
        $this->addSql('DROP TABLE IF EXISTS poll_vote');
        $this->addSql('DROP TABLE IF EXISTS chat_groupe_membre');
        $this->addSql('DROP TABLE IF EXISTS chat_groupe');
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
