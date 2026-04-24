<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260423110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Migration historique restauree pour realigner le code source avec doctrine_migration_versions.';
    }

    public function up(Schema $schema): void
    {
        // Cette migration a deja ete executee dans certaines bases.
        // Le fichier est restaure pour eviter l incoherence entre le depot et la table doctrine_migration_versions.
    }

    public function down(Schema $schema): void
    {
        // Intentionnellement vide.
    }
}
