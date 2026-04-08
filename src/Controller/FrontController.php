<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\OffreManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
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
                SELECT e.id, e.titre, e.type, e.date_debut, e.prix, l.nom AS lieu_nom, l.ville
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
    public function offres(Request $request, Connection $connection, OffreManager $offreManager): Response
    {
        $lieuId = (int) $request->query->get('lieu', 0);

        return $this->render('front/offre/index.html.twig', [
            'active' => 'offres',
            'offres' => $offreManager->findActiveByLieu($lieuId > 0 ? $lieuId : null),
            'lieux' => $this->fetchAll($connection, 'SELECT id, nom FROM lieu ORDER BY nom ASC'),
            'selectedLieu' => $lieuId,
        ]);
    }

    #[Route('/offres/{id}', name: 'app_offres_show', methods: ['GET'])]
    public function offreShow(int $id, Connection $connection): Response
    {
        $offre = $connection->fetchAssociative(
            "SELECT o.id, o.titre, o.description, o.type, o.pourcentage, o.date_debut, o.date_fin, o.statut, o.lieu_id,
                    l.nom AS lieu_nom, l.ville
             FROM offre o
             LEFT JOIN lieu l ON l.id = o.lieu_id
             WHERE o.id = ?",
            [$id]
        );

        if (!$offre) {
            throw $this->createNotFoundException('Offre introuvable.');
        }

        $currentUser = $this->getUser();
        $userPromoCodes = [];
        if ($currentUser instanceof User) {
            $userPromoCodes = $connection->fetchAllAssociative(
                'SELECT id, qr_image_url, date_generation, date_expiration, statut
                 FROM code_promo
                 WHERE offre_id = ? AND user_id = ?
                 ORDER BY id DESC',
                [$id, $currentUser->getId()]
            );
        }

        $userReservations = [];
        if ($currentUser instanceof User) {
            $userReservations = $connection->fetchAllAssociative(
                "SELECT id, date_reservation, nombre_personnes, statut, note, created_at
                 FROM reservation_offre
                 WHERE offre_id = ? AND user_id = ?
                 ORDER BY id DESC",
                [$id, $currentUser->getId()]
            );
        }

        return $this->render('front/offre/show.html.twig', [
            'active' => 'offres',
            'offre' => $offre,
            'userPromoCodes' => $userPromoCodes,
            'userReservations' => $userReservations,
        ]);
    }

    #[Route('/offres/{id}/reserve', name: 'app_offres_reserve', methods: ['POST'])]
    public function reserveOffre(int $id, Request $request, Connection $connection): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            $this->addFlash('error', 'Veuillez vous connecter pour réserver une offre.');
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('reserve_offre_'.$id, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_offres_show', ['id' => $id]);
        }

        $offre = $connection->fetchAssociative('SELECT id, statut, date_fin, lieu_id FROM offre WHERE id = ?', [$id]);
        if (!$offre) {
            throw $this->createNotFoundException('Offre introuvable.');
        }

        $offreStatus = strtolower((string) ($offre['statut'] ?? ''));
        if (!in_array($offreStatus, ['active', 'actif'], true)) {
            $this->addFlash('error', 'Cette offre n\'est pas disponible à la réservation.');
            return $this->redirectToRoute('app_offres_show', ['id' => $id]);
        }

        if (!empty($offre['date_fin']) && new \DateTimeImmutable((string) $offre['date_fin']) < new \DateTimeImmutable('today')) {
            $this->addFlash('error', 'Cette offre est expirée.');
            return $this->redirectToRoute('app_offres_show', ['id' => $id]);
        }

        $nombrePersonnes = max(1, (int) $request->request->get('nombre_personnes', 1));
        $note = trim((string) $request->request->get('note', ''));

        $existingReservation = (int) $connection->fetchOne(
            "SELECT COUNT(*) FROM reservation_offre
             WHERE user_id = ? AND offre_id = ? AND statut IN ('EN_ATTENTE', 'CONFIRMÉE')",
            [(int) $user->getId(), $id]
        );

        if ($existingReservation > 0) {
            $this->addFlash('error', 'Vous avez déjà une réservation en cours ou confirmée pour cette offre.');
            return $this->redirectToRoute('app_offres_show', ['id' => $id]);
        }

        try {
            $connection->insert('reservation_offre', [
                'user_id' => (int) $user->getId(),
                'offre_id' => $id,
                'lieu_id' => (int) ($offre['lieu_id'] ?? 0),
                'date_reservation' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
                'nombre_personnes' => $nombrePersonnes,
                'statut' => 'EN_ATTENTE',
                'note' => $note !== '' ? $note : null,
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);

            $this->addFlash('success', 'Réservation envoyée. Elle sera confirmée par l\'administrateur.');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Erreur réservation: '.$e->getMessage());
        }

        return $this->redirectToRoute('app_offres_show', ['id' => $id]);
    }

    #[Route('/offres/{id}/generate-code', name: 'app_offres_generate_code', methods: ['POST'])]
    public function generateCodePromo(int $id, Request $request, Connection $connection): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            $this->addFlash('error', 'Veuillez vous connecter pour générer un code promo.');
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('generate_promo_'.$id, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_offres_show', ['id' => $id]);
        }

        $offre = $connection->fetchAssociative('SELECT id, date_fin FROM offre WHERE id = ?', [$id]);
        if (!$offre) {
            throw $this->createNotFoundException('Offre introuvable.');
        }

        $today = new \DateTimeImmutable('today');
        $expiration = isset($offre['date_fin']) ? new \DateTimeImmutable((string) $offre['date_fin']) : $today->modify('+7 days');
        if ($expiration < $today) {
            $expiration = $today->modify('+7 days');
        }

        $code = sprintf('OFF-%d-U%d-%s', $id, (int) $user->getId(), strtoupper(substr(bin2hex(random_bytes(4)), 0, 8)));
        $qrImageUrl = $this->buildQrImageUrl($code, $id, (int) $user->getId(), $expiration->format('Y-m-d'));

        try {
            $connection->insert('code_promo', [
                'offre_id' => $id,
                'user_id' => (int) $user->getId(),
                'qr_image_url' => $qrImageUrl,
                'date_generation' => $today->format('Y-m-d'),
                'date_expiration' => $expiration->format('Y-m-d'),
                'statut' => 'ACTIF',
            ]);

            $this->addFlash('success', 'Code promo généré avec succès.');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Erreur génération code promo: '.$e->getMessage());
        }

        return $this->redirectToRoute('app_offres_show', ['id' => $id]);
    }

    #[Route('/offres/{offreId}/promo/{promoId}/use', name: 'app_offres_use_code', methods: ['POST'])]
    public function useCodePromo(int $offreId, int $promoId, Request $request, Connection $connection): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            $this->addFlash('error', 'Veuillez vous connecter pour utiliser un code promo.');
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('use_promo_'.$promoId, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_offres_show', ['id' => $offreId]);
        }

        $promo = $connection->fetchAssociative(
            'SELECT id, date_expiration, statut
             FROM code_promo
             WHERE id = ? AND offre_id = ? AND user_id = ?',
            [$promoId, $offreId, (int) $user->getId()]
        );

        if (!$promo) {
            $this->addFlash('error', 'Code promo introuvable.');
            return $this->redirectToRoute('app_offres_show', ['id' => $offreId]);
        }

        if ((string) $promo['statut'] !== 'ACTIF') {
            $this->addFlash('error', 'Ce code promo n\'est pas actif.');
            return $this->redirectToRoute('app_offres_show', ['id' => $offreId]);
        }

        $expiration = new \DateTimeImmutable((string) $promo['date_expiration']);
        if ($expiration < new \DateTimeImmutable('today')) {
            $connection->update('code_promo', ['statut' => 'EXPIRE'], ['id' => $promoId]);
            $this->addFlash('error', 'Ce code promo est expiré.');
            return $this->redirectToRoute('app_offres_show', ['id' => $offreId]);
        }

        $connection->update('code_promo', ['statut' => 'UTILISE'], ['id' => $promoId]);
        $this->addFlash('success', 'Code promo utilisé avec succès.');

        return $this->redirectToRoute('app_offres_show', ['id' => $offreId]);
    }

    #[Route('/evenements', name: 'app_evenements')]
    public function evenements(Connection $connection): Response
    {
        return $this->render('front/evenement/index.html.twig', [
            'active' => 'evenements',
            'events' => $this->fetchAll($connection, "
                SELECT e.id, e.titre, e.description, e.date_debut, e.date_fin, e.prix, e.type, e.statut,
                       l.nom AS lieu_nom, l.ville
                FROM evenement e
                LEFT JOIN lieu l ON l.id = e.lieu_id
                ORDER BY e.date_debut ASC
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

    private function buildQrImageUrl(string $code, int $offreId, int $userId, string $expirationDate): string
    {
        $payload = sprintf(
            'PROMO:%s|OFFRE:%d|USER:%d|EXP:%s',
            $code,
            $offreId,
            $userId,
            $expirationDate
        );

        return 'https://api.qrserver.com/v1/create-qr-code/?size=280x280&data='.urlencode($payload);
    }
}