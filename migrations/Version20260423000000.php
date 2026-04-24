<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260423000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align lieu.categorie SQL enum with App\\Enum\\LieuCategorie and normalize empty values to AUTRE';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE lieu MODIFY categorie ENUM('CAFE','RESTO','RESTO_BAR','LIEU_PUBLIC','PARC_ATTRACTION','MUSEE','PLAGE','CENTRE_COMMERCIAL','HOTEL','SALLE','PARC','AUTRE') NOT NULL DEFAULT 'AUTRE'");
        $this->addSql("UPDATE lieu SET categorie = 'AUTRE' WHERE categorie IS NULL OR TRIM(categorie) = ''");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE lieu MODIFY categorie ENUM('CAFE','RESTO','RESTO_BAR','LIEU_PUBLIC','PARC_ATTRACTION','MUSEE','PLAGE','CENTRE_COMMERCIAL') NOT NULL");
    }
}
