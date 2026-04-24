<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260423002000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create and seed gamification tables in an idempotent way';
    }

    public function up(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist(['user_points'])) {
            $this->addSql(<<<'SQL'
                CREATE TABLE user_points (
                    user_id INT NOT NULL,
                    total_points INT NOT NULL DEFAULT 0,
                    nb_lieux_visites INT NOT NULL DEFAULT 0,
                    nb_avis_laisses INT NOT NULL DEFAULT 0,
                    nb_favoris INT NOT NULL DEFAULT 0,
                    nb_sorties_jointes INT NOT NULL DEFAULT 0,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY(user_id),
                    CONSTRAINT fk_user_points_user FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
        }

        if (!$schemaManager->tablesExist(['badge'])) {
            $this->addSql(<<<'SQL'
                CREATE TABLE badge (
                    id INT AUTO_INCREMENT NOT NULL,
                    code VARCHAR(50) NOT NULL,
                    nom VARCHAR(100) NOT NULL,
                    description TEXT DEFAULT NULL,
                    emoji VARCHAR(20) DEFAULT '🏅',
                    categorie VARCHAR(30) DEFAULT 'GENERAL',
                    seuil_requis INT NOT NULL DEFAULT 1,
                    points_bonus INT NOT NULL DEFAULT 0,
                    PRIMARY KEY(id),
                    UNIQUE KEY uq_badge_code (code)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
        }

        if (!$schemaManager->tablesExist(['user_badge'])) {
            $this->addSql(<<<'SQL'
                CREATE TABLE user_badge (
                    user_id INT NOT NULL,
                    badge_id INT NOT NULL,
                    date_obtenu TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY(user_id, badge_id),
                    KEY idx_user_badge_badge (badge_id),
                    CONSTRAINT fk_user_badge_user FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT fk_user_badge_badge FOREIGN KEY (badge_id) REFERENCES badge (id) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
        }

        if (!$schemaManager->tablesExist(['avis_utile'])) {
            $this->addSql(<<<'SQL'
                CREATE TABLE avis_utile (
                    evaluation_id INT NOT NULL,
                    votant_user_id INT NOT NULL,
                    date_vote DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY(evaluation_id, votant_user_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
        }

        if (!$schemaManager->tablesExist(['badge_recompense'])) {
            $this->addSql(<<<'SQL'
                CREATE TABLE badge_recompense (
                    id INT AUTO_INCREMENT NOT NULL,
                    badge_code VARCHAR(50) NOT NULL,
                    titre VARCHAR(140) NOT NULL,
                    description TEXT DEFAULT NULL,
                    pourcentage FLOAT NOT NULL DEFAULT 10,
                    duree_jours INT NOT NULL DEFAULT 7,
                    PRIMARY KEY(id),
                    UNIQUE KEY uq_badge_recompense (badge_code),
                    KEY idx_badge_recompense_badge_code (badge_code),
                    CONSTRAINT fk_badge_recompense_badge FOREIGN KEY (badge_code) REFERENCES badge (code) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
        }

        if (!$schemaManager->tablesExist(['gamification_lieu_visite'])) {
            $this->addSql(<<<'SQL'
                CREATE TABLE gamification_lieu_visite (
                    user_id INT NOT NULL,
                    lieu_id INT NOT NULL,
                    visited_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY(user_id, lieu_id),
                    KEY IDX_GAMIF_VISIT_LIEU (lieu_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
        }

        if ($schemaManager->tablesExist(['offre_badge_user'])) {
            $indexes = array_map(
                static fn ($index): string => $index->getName(),
                $schemaManager->listTableIndexes('offre_badge_user')
            );

            if (in_array('UNIQ_A79E419B6B3CA4B', $indexes, true)) {
                $this->addSql('ALTER TABLE offre_badge_user DROP INDEX UNIQ_A79E419B6B3CA4B');
            }
            if (in_array('UNIQ_A79E419B7A5CB15F', $indexes, true)) {
                $this->addSql('ALTER TABLE offre_badge_user DROP INDEX UNIQ_A79E419B7A5CB15F');
            }
            if (!in_array('uq_offre_badge_user', $indexes, true) && !in_array('UNIQ_OFFRE_BADGE_USER_CODE', $indexes, true)) {
                $this->addSql('CREATE UNIQUE INDEX uq_offre_badge_user ON offre_badge_user (user_id, badge_code)');
            }
        }

        $this->addSql("INSERT IGNORE INTO badge (code, nom, description, emoji, categorie, seuil_requis, points_bonus) VALUES
            ('PREMIER_PAS', 'Premier Pas', 'Vous avez visite votre premier lieu', '👣', 'EXPLORATEUR', 1, 10),
            ('EXPLORATEUR_5', 'Explorateur', 'Vous avez visite 5 lieux differents', '🧭', 'EXPLORATEUR', 5, 25),
            ('EXPLORATEUR_20', 'Grand Explorateur', 'Vous avez visite 20 lieux differents', '🗺', 'EXPLORATEUR', 20, 75),
            ('EXPLORATEUR_50', 'Legende Locale', '50 lieux visites ! Vous connaissez tout !', '🏆', 'EXPLORATEUR', 50, 200),
            ('PREMIER_AVIS', 'Critique Debutant', 'Vous avez laisse votre premier avis', '✍', 'AVIS', 1, 15),
            ('AVIS_10', 'Critique Confirme', '10 avis laisses, votre opinion compte !', '📝', 'AVIS', 10, 50),
            ('AVIS_FIABLE', 'Avis de Confiance', '3 de vos avis ont ete juges utiles', '⭐', 'AVIS', 3, 60),
            ('SUPER_CRITIQUE', 'Super Critique', '5 avis fiables reconnus par la communaute', '🌟', 'AVIS', 5, 100),
            ('PREMIER_FAVORI', 'Coup de Coeur', 'Vous avez ajoute votre premier lieu favori', '❤', 'FAVORI', 1, 5),
            ('COLLECTION_10', 'Collectionneur', '10 lieux dans vos favoris', '💝', 'FAVORI', 10, 30),
            ('SORTIE_JOINTE', 'Esprit Equipe', 'Vous avez rejoint votre premiere sortie', '🤝', 'SOCIAL', 1, 10),
            ('SORTIE_5', 'Ame Sociale', '5 sorties rejointes, vous adorez sortir !', '🎉', 'SOCIAL', 5, 40),
            ('NIVEAU_ARGENT', 'Niveau Argent', 'Vous avez atteint le niveau Argent', '🥈', 'SPECIAL', 100, 20),
            ('NIVEAU_OR', 'Niveau Or', 'Vous avez atteint le niveau Or', '🥇', 'SPECIAL', 500, 50),
            ('NIVEAU_PLATINE', 'Niveau Platine', 'Elite ! Vous avez atteint le niveau Platine', '💎', 'SPECIAL', 1500, 150)
        ");

        $this->addSql("INSERT IGNORE INTO badge_recompense (badge_code, titre, description, pourcentage, duree_jours) VALUES
            ('PREMIER_PAS', 'Bienvenue Explorateur !', '10% sur tous les lieux partenaires pendant 7 jours', 10, 7),
            ('EXPLORATEUR_5', 'Explorateur Confirme', '15% sur les cafes et restos partenaires pendant 7 jours', 15, 7),
            ('EXPLORATEUR_20', 'Grand Voyageur', '20% sur tous les lieux partenaires pendant 7 jours', 20, 7),
            ('EXPLORATEUR_50', 'Legende - Offre Platine', '30% sur tous les lieux partenaires pendant 14 jours', 30, 14),
            ('PREMIER_AVIS', 'Merci pour votre avis !', '5% de reduction sur votre prochain lieu visite', 5, 7),
            ('AVIS_FIABLE', 'Critique de Confiance', '20% sur les lieux partenaires, votre avis est precieux !', 20, 7),
            ('SUPER_CRITIQUE', 'Super Critique - Offre VIP', '25% exclusif sur tous les lieux partenaires', 25, 10),
            ('NIVEAU_OR', 'Membre Or - Acces Premium', '20% sur tous les lieux partenaires pendant 7 jours', 20, 7),
            ('NIVEAU_PLATINE', 'Membre Platine - Offre Elite', '35% exclusif sur tous les lieux partenaires pendant 30 jours', 35, 30)
        ");

        $this->addSql('INSERT IGNORE INTO user_points (user_id, total_points, nb_lieux_visites, nb_avis_laisses, nb_favoris, nb_sorties_jointes, updated_at)
                       SELECT id, 0, 0, 0, 0, 0, NOW() FROM user');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS gamification_lieu_visite');
        $this->addSql('DROP TABLE IF EXISTS avis_utile');
        $this->addSql('DROP TABLE IF EXISTS user_badge');
        $this->addSql('DROP TABLE IF EXISTS badge_recompense');
        $this->addSql('DROP TABLE IF EXISTS badge');
        $this->addSql('DROP TABLE IF EXISTS user_points');
    }
}
