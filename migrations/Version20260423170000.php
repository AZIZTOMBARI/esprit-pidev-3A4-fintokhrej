<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20260423170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Supprime les anciennes tables group_chat devenues legacy apres migration vers chat_groupe.';
    }

    public function up(Schema $schema): void
    {
        if ($this->tableExists('group_chat_message') && $this->foreignKeyExists('group_chat_message', 'fk_gcm_msg_sender')) {
            $this->addSql('ALTER TABLE group_chat_message DROP FOREIGN KEY fk_gcm_msg_sender');
        }

        if ($this->tableExists('group_chat_message') && $this->foreignKeyExists('group_chat_message', 'fk_gcm_msg_groupchat')) {
            $this->addSql('ALTER TABLE group_chat_message DROP FOREIGN KEY fk_gcm_msg_groupchat');
        }

        if ($this->tableExists('group_chat_member') && $this->foreignKeyExists('group_chat_member', 'fk_gcm_user')) {
            $this->addSql('ALTER TABLE group_chat_member DROP FOREIGN KEY fk_gcm_user');
        }

        if ($this->tableExists('group_chat_member') && $this->foreignKeyExists('group_chat_member', 'fk_gcm_groupchat')) {
            $this->addSql('ALTER TABLE group_chat_member DROP FOREIGN KEY fk_gcm_groupchat');
        }

        if ($this->tableExists('group_chat') && $this->foreignKeyExists('group_chat', 'fk_groupchat_sortie')) {
            $this->addSql('ALTER TABLE group_chat DROP FOREIGN KEY fk_groupchat_sortie');
        }

        if ($this->tableExists('group_chat_message')) {
            $this->addSql('DROP TABLE group_chat_message');
        }

        if ($this->tableExists('group_chat_member')) {
            $this->addSql('DROP TABLE group_chat_member');
        }

        if ($this->tableExists('group_chat')) {
            $this->addSql('DROP TABLE group_chat');
        }
    }

    public function down(Schema $schema): void
    {
        // Suppression legacy non reversible automatiquement.
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
