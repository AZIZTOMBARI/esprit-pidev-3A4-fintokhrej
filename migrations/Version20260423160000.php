<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260423160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Realigne la base chat avec le code actuel, cree les tables manquantes et migre les anciennes donnees group_chat.';
    }

    public function up(Schema $schema): void
    {
        $this->createChatTables();
        $this->createPollTables();
        $this->createTaskTable();
        $this->migrateLegacyChatData();
    }

    public function down(Schema $schema): void
    {
        // Migration de rattrapage orientee donnees: pas de rollback automatique.
    }

    private function createChatTables(): void
    {
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
                edited_at DATETIME DEFAULT NULL,
                deleted_at DATETIME DEFAULT NULL,
                attachment_path VARCHAR(255) DEFAULT NULL,
                attachment_type VARCHAR(60) DEFAULT NULL,
                INDEX IDX_CHAT_MESSAGE_ANNONCE (annonce_id),
                INDEX IDX_CHAT_MESSAGE_GROUPE (chat_groupe_id),
                INDEX IDX_CHAT_MESSAGE_SENDER (sender_id),
                INDEX IDX_CHAT_MESSAGE_POLL (poll_id),
                INDEX IDX_CHAT_MESSAGE_DELETED (deleted_at),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        } else {
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

        if (!$this->foreignKeyExists('chat_groupe', 'FK_CHAT_GROUPE_ANNONCE')) {
            $this->addSql('ALTER TABLE chat_groupe ADD CONSTRAINT FK_CHAT_GROUPE_ANNONCE FOREIGN KEY (annonce_id) REFERENCES annonce_sortie (id) ON DELETE CASCADE');
        }
        if (!$this->foreignKeyExists('chat_groupe_membre', 'FK_CHAT_GROUPE_MEMBRE_GROUPE')) {
            $this->addSql('ALTER TABLE chat_groupe_membre ADD CONSTRAINT FK_CHAT_GROUPE_MEMBRE_GROUPE FOREIGN KEY (chat_groupe_id) REFERENCES chat_groupe (id) ON DELETE CASCADE');
        }
        if (!$this->foreignKeyExists('chat_groupe_membre', 'FK_CHAT_GROUPE_MEMBRE_USER')) {
            $this->addSql('ALTER TABLE chat_groupe_membre ADD CONSTRAINT FK_CHAT_GROUPE_MEMBRE_USER FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        }
        if (!$this->foreignKeyExists('chat_message', 'FK_CHAT_MESSAGE_ANNONCE')) {
            $this->addSql('ALTER TABLE chat_message ADD CONSTRAINT FK_CHAT_MESSAGE_ANNONCE FOREIGN KEY (annonce_id) REFERENCES annonce_sortie (id) ON DELETE CASCADE');
        }
        if (!$this->foreignKeyExists('chat_message', 'FK_CHAT_MESSAGE_GROUPE')) {
            $this->addSql('ALTER TABLE chat_message ADD CONSTRAINT FK_CHAT_MESSAGE_GROUPE FOREIGN KEY (chat_groupe_id) REFERENCES chat_groupe (id) ON DELETE SET NULL');
        }
        if (!$this->foreignKeyExists('chat_message', 'FK_CHAT_MESSAGE_SENDER')) {
            $this->addSql('ALTER TABLE chat_message ADD CONSTRAINT FK_CHAT_MESSAGE_SENDER FOREIGN KEY (sender_id) REFERENCES user (id) ON DELETE SET NULL');
        }
        if ($this->tableExists('poll') && !$this->foreignKeyExists('chat_message', 'FK_CHAT_MESSAGE_POLL')) {
            $this->addSql('ALTER TABLE chat_message ADD CONSTRAINT FK_CHAT_MESSAGE_POLL FOREIGN KEY (poll_id) REFERENCES poll (id) ON DELETE SET NULL');
        }
        if (!$this->foreignKeyExists('chat_message_read', 'FK_CHAT_MESSAGE_READ_MESSAGE')) {
            $this->addSql('ALTER TABLE chat_message_read ADD CONSTRAINT FK_CHAT_MESSAGE_READ_MESSAGE FOREIGN KEY (chat_message_id) REFERENCES chat_message (id) ON DELETE CASCADE');
        }
        if (!$this->foreignKeyExists('chat_message_read', 'FK_CHAT_MESSAGE_READ_USER')) {
            $this->addSql('ALTER TABLE chat_message_read ADD CONSTRAINT FK_CHAT_MESSAGE_READ_USER FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        }
    }

    private function createPollTables(): void
    {
        if (!$this->tableExists('poll')) {
            $this->addSql('CREATE TABLE poll (
                id INT AUTO_INCREMENT NOT NULL,
                annonce_id INT NOT NULL,
                question LONGTEXT NOT NULL,
                created_by INT DEFAULT NULL,
                created_at DATETIME NOT NULL,
                is_open TINYINT(1) NOT NULL,
                allow_multi TINYINT(1) NOT NULL,
                allow_add_options TINYINT(1) NOT NULL,
                is_pinned TINYINT(1) NOT NULL,
                closed_at DATETIME DEFAULT NULL,
                INDEX IDX_POLL_ANNONCE (annonce_id),
                INDEX IDX_POLL_CREATED_BY (created_by),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }

        if (!$this->tableExists('poll_option')) {
            $this->addSql('CREATE TABLE poll_option (
                id INT AUTO_INCREMENT NOT NULL,
                poll_id INT NOT NULL,
                text VARCHAR(255) NOT NULL,
                created_by INT DEFAULT NULL,
                created_at DATETIME NOT NULL,
                INDEX IDX_POLL_OPTION_POLL (poll_id),
                INDEX IDX_POLL_OPTION_CREATED_BY (created_by),
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

        if (!$this->foreignKeyExists('poll', 'FK_POLL_ANNONCE')) {
            $this->addSql('ALTER TABLE poll ADD CONSTRAINT FK_POLL_ANNONCE FOREIGN KEY (annonce_id) REFERENCES annonce_sortie (id) ON DELETE CASCADE');
        }
        if (!$this->foreignKeyExists('poll', 'FK_POLL_CREATED_BY')) {
            $this->addSql('ALTER TABLE poll ADD CONSTRAINT FK_POLL_CREATED_BY FOREIGN KEY (created_by) REFERENCES user (id) ON DELETE SET NULL');
        }
        if (!$this->foreignKeyExists('poll_option', 'FK_POLL_OPTION_POLL')) {
            $this->addSql('ALTER TABLE poll_option ADD CONSTRAINT FK_POLL_OPTION_POLL FOREIGN KEY (poll_id) REFERENCES poll (id) ON DELETE CASCADE');
        }
        if (!$this->foreignKeyExists('poll_option', 'FK_POLL_OPTION_CREATED_BY')) {
            $this->addSql('ALTER TABLE poll_option ADD CONSTRAINT FK_POLL_OPTION_CREATED_BY FOREIGN KEY (created_by) REFERENCES user (id) ON DELETE SET NULL');
        }
        if (!$this->foreignKeyExists('poll_vote', 'FK_POLL_VOTE_POLL')) {
            $this->addSql('ALTER TABLE poll_vote ADD CONSTRAINT FK_POLL_VOTE_POLL FOREIGN KEY (poll_id) REFERENCES poll (id) ON DELETE CASCADE');
        }
        if (!$this->foreignKeyExists('poll_vote', 'FK_POLL_VOTE_OPTION')) {
            $this->addSql('ALTER TABLE poll_vote ADD CONSTRAINT FK_POLL_VOTE_OPTION FOREIGN KEY (option_id) REFERENCES poll_option (id) ON DELETE CASCADE');
        }
        if (!$this->foreignKeyExists('poll_vote', 'FK_POLL_VOTE_USER')) {
            $this->addSql('ALTER TABLE poll_vote ADD CONSTRAINT FK_POLL_VOTE_USER FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        }
    }

    private function createTaskTable(): void
    {
        if (!$this->tableExists('sortie_task')) {
            $this->addSql('CREATE TABLE sortie_task (
                id INT AUTO_INCREMENT NOT NULL,
                annonce_id INT NOT NULL,
                created_by INT DEFAULT NULL,
                title VARCHAR(255) NOT NULL,
                description LONGTEXT DEFAULT NULL,
                status VARCHAR(30) NOT NULL,
                assigned_to INT DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                done_at DATETIME DEFAULT NULL,
                INDEX IDX_SORTIE_TASK_ANNONCE (annonce_id),
                INDEX IDX_SORTIE_TASK_CREATED_BY (created_by),
                INDEX IDX_SORTIE_TASK_ASSIGNED_TO (assigned_to),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }

        if (!$this->foreignKeyExists('sortie_task', 'FK_SORTIE_TASK_ANNONCE')) {
            $this->addSql('ALTER TABLE sortie_task ADD CONSTRAINT FK_SORTIE_TASK_ANNONCE FOREIGN KEY (annonce_id) REFERENCES annonce_sortie (id) ON DELETE CASCADE');
        }
        if (!$this->foreignKeyExists('sortie_task', 'FK_SORTIE_TASK_CREATED_BY')) {
            $this->addSql('ALTER TABLE sortie_task ADD CONSTRAINT FK_SORTIE_TASK_CREATED_BY FOREIGN KEY (created_by) REFERENCES user (id) ON DELETE SET NULL');
        }
        if (!$this->foreignKeyExists('sortie_task', 'FK_SORTIE_TASK_ASSIGNED_TO')) {
            $this->addSql('ALTER TABLE sortie_task ADD CONSTRAINT FK_SORTIE_TASK_ASSIGNED_TO FOREIGN KEY (assigned_to) REFERENCES user (id) ON DELETE SET NULL');
        }
    }

    private function migrateLegacyChatData(): void
    {
        if (!$this->tableExists('group_chat')) {
            return;
        }

        $this->addSql('INSERT INTO chat_groupe (annonce_id, created_at)
            SELECT gc.sortie_id, gc.created_at
            FROM group_chat gc
            LEFT JOIN chat_groupe cg ON cg.annonce_id = gc.sortie_id
            WHERE gc.sortie_id IS NOT NULL AND cg.id IS NULL');

        if ($this->tableExists('group_chat_member')) {
            $this->addSql('INSERT INTO chat_groupe_membre (chat_groupe_id, user_id, joined_at)
                SELECT cg.id, gcm.user_id, gcm.joined_at
                FROM group_chat_member gcm
                INNER JOIN group_chat gc ON gc.id = gcm.group_chat_id
                INNER JOIN chat_groupe cg ON cg.annonce_id = gc.sortie_id
                LEFT JOIN chat_groupe_membre cgm
                    ON cgm.chat_groupe_id = cg.id
                    AND cgm.user_id = gcm.user_id
                WHERE cgm.id IS NULL');
        }

        if ($this->tableExists('group_chat_message')) {
            $this->addSql('INSERT INTO chat_message (
                    annonce_id,
                    chat_groupe_id,
                    sender_id,
                    content,
                    message_type,
                    poll_id,
                    meta_json,
                    sent_at,
                    edited_at,
                    deleted_at,
                    attachment_path,
                    attachment_type
                )
                SELECT
                    gc.sortie_id,
                    cg.id,
                    gcm.sender_id,
                    gcm.content,
                    CASE
                        WHEN gcm.message_type IS NULL OR gcm.message_type = "" THEN "TEXT"
                        ELSE UPPER(gcm.message_type)
                    END,
                    NULL,
                    gcm.meta_json,
                    gcm.created_at,
                    NULL,
                    NULL,
                    NULL,
                    NULL
                FROM group_chat_message gcm
                INNER JOIN group_chat gc ON gc.id = gcm.group_chat_id
                INNER JOIN chat_groupe cg ON cg.annonce_id = gc.sortie_id
                LEFT JOIN chat_message cm
                    ON cm.annonce_id = gc.sortie_id
                    AND ((cm.chat_groupe_id = cg.id) OR (cm.chat_groupe_id IS NULL AND cg.id IS NULL))
                    AND ((cm.sender_id = gcm.sender_id) OR (cm.sender_id IS NULL AND gcm.sender_id IS NULL))
                    AND cm.content = gcm.content
                    AND cm.message_type = CASE
                        WHEN gcm.message_type IS NULL OR gcm.message_type = "" THEN "TEXT"
                        ELSE UPPER(gcm.message_type)
                    END
                    AND cm.sent_at = gcm.created_at
                WHERE cm.id IS NULL');
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
