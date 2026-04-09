<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class FrontController extends AbstractController
{
    #[Route('/home', name: 'app_home')]
    public function home(Connection $connection): Response
    {
        return $this->render('front/home/index.html.twig', [
            'active' => 'home',
            'stats' => $this->getFrontStats($connection),
            'cities' => $this->fetchAll($connection, "
                SELECT ville, COUNT(*) AS total, MIN(categorie) AS categorie
                FROM lieu
                GROUP BY ville
                ORDER BY total DESC, ville ASC
                LIMIT 6
            "),
            'places' => $this->fetchAll($connection, "
                SELECT id, nom, ville, categorie, type, budget_min, budget_max
                FROM lieu
                ORDER BY id DESC
                LIMIT 6
            "),
            'sorties' => $this->fetchAll($connection, "
                SELECT id, titre, ville, type_activite, date_sortie, budget_max, statut
                FROM annonce_sortie
                ORDER BY date_sortie ASC
                LIMIT 6
            "),
            'offres' => $this->fetchAll($connection, "
                SELECT o.id, o.titre, o.type, o.pourcentage, o.date_fin, l.nom AS lieu_nom, l.ville
                FROM offre o
                LEFT JOIN lieu l ON l.id = o.lieu_id
                ORDER BY o.date_fin ASC
                LIMIT 6
            "),
            'events' => $this->fetchAll($connection, "
                SELECT e.id, e.titre, e.type, e.date_debut, e.date_fin, e.prix, e.image_url as imageUrl, e.capacite_max, l.nom AS lieu_nom, l.ville
                FROM evenement e
                LEFT JOIN lieu l ON l.id = e.lieu_id
                ORDER BY e.date_debut ASC
                LIMIT 6
            "),
            'userChip' => $this->fetchOne($connection, "
                SELECT prenom, nom, role, email, imageUrl
                FROM user
                ORDER BY id ASC
                LIMIT 1
            "),
        ]);
    }

    #[Route('/lieux', name: 'app_lieux')]
    public function lieux(Connection $connection): Response
    {
        return $this->render('front/lieu/index.html.twig', [
            'active' => 'lieux',
            'places' => $this->fetchAll($connection, "
                SELECT id, nom, ville, adresse, categorie, type, budget_min, budget_max, site_web, instagram
                FROM lieu
                ORDER BY ville ASC, nom ASC
            "),
        ]);
    }

    #[Route('/sorties', name: 'app_sorties')]
    public function sorties(Connection $connection): Response
    {
        return $this->render('front/sortie/index.html.twig', [
            'active' => 'sorties',
            'sorties' => $this->fetchAll($connection, "
                SELECT s.id, s.titre, s.description, s.ville, s.type_activite, s.date_sortie, s.budget_max, s.nb_places, s.statut,
                       u.prenom, u.nom
                FROM annonce_sortie s
                LEFT JOIN user u ON u.id = s.user_id
                ORDER BY s.date_sortie ASC
            "),
        ]);
    }

    #[Route('/offres', name: 'app_offres')]
    public function offres(Connection $connection): Response
    {
        return $this->render('front/offre/index.html.twig', [
            'active' => 'offres',
            'offres' => $this->fetchAll($connection, "
                SELECT o.id, o.titre, o.description, o.type, o.pourcentage, o.date_debut, o.date_fin, o.statut,
                       l.nom AS lieu_nom, l.ville
                FROM offre o
                LEFT JOIN lieu l ON l.id = o.lieu_id
                ORDER BY o.date_fin ASC
            "),
        ]);
    }

    private function getFrontStats(Connection $connection): array
    {
        return [
            'lieux' => $this->fetchValue($connection, 'SELECT COUNT(*) FROM lieu'),
            'sorties' => $this->fetchValue($connection, 'SELECT COUNT(*) FROM annonce_sortie'),
            'offres' => $this->fetchValue($connection, 'SELECT COUNT(*) FROM offre'),
            'events' => $this->fetchValue($connection, 'SELECT COUNT(*) FROM evenement'),
        ];
    }

    private function fetchAll(Connection $connection, string $sql): array
    {
        try {
            return $connection->fetchAllAssociative($sql);
        } catch (Exception) {
            return [];
        }
    }

    private function fetchOne(Connection $connection, string $sql): ?array
    {
        try {
            return $connection->fetchAssociative($sql) ?: null;
        } catch (Exception) {
            return null;
        }
    }

    private function fetchValue(Connection $connection, string $sql): int
    {
        try {
            return (int) $connection->fetchOne($sql);
        } catch (Exception) {
            return 0;
        }
    }
}