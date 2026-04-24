<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260423001000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user ban columns and create review moderation log table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE user ADD banned_until DATETIME DEFAULT NULL, ADD ban_reason VARCHAR(255) DEFAULT NULL");

        $this->addSql("CREATE TABLE review_moderation_log (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT DEFAULT NULL,
            lieu_id INT DEFAULT NULL,
            severity VARCHAR(16) NOT NULL,
            score INT NOT NULL DEFAULT 0,
            terms_text VARCHAR(255) DEFAULT NULL,
            comment_preview VARCHAR(500) DEFAULT NULL,
            action VARCHAR(16) NOT NULL DEFAULT 'create',
            created_at DATETIME NOT NULL,
            INDEX IDX_REVIEW_MOD_LOG_USER (user_id),
            INDEX IDX_REVIEW_MOD_LOG_LIEU (lieu_id),
            INDEX IDX_REVIEW_MOD_LOG_CREATED (created_at),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql('ALTER TABLE review_moderation_log ADD CONSTRAINT FK_REVIEW_MOD_LOG_USER FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE review_moderation_log ADD CONSTRAINT FK_REVIEW_MOD_LOG_LIEU FOREIGN KEY (lieu_id) REFERENCES lieu (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE review_moderation_log DROP FOREIGN KEY FK_REVIEW_MOD_LOG_USER');
        $this->addSql('ALTER TABLE review_moderation_log DROP FOREIGN KEY FK_REVIEW_MOD_LOG_LIEU');
        $this->addSql('DROP TABLE review_moderation_log');

        $this->addSql('ALTER TABLE user DROP banned_until, DROP ban_reason');
    }
}
