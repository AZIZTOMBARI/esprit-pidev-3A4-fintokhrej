<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin')]
class AdminController extends AbstractController
{
    #[Route('', name: 'app_admin_dashboard')]
    public function dashboard(Connection $connection): Response
    {
        return $this->render('admin/dashboard/index.html.twig', [
            'active' => 'dashboard',
            'stats' => [
                'users' => $this->fetchValue($connection, 'SELECT COUNT(*) FROM user'),
                'admins' => $this->fetchValue($connection, "SELECT COUNT(*) FROM user WHERE role = 'admin'"),
                'abonnes' => $this->fetchValue($connection, "SELECT COUNT(*) FROM user WHERE role = 'abonne'"),
                'visiteurs' => $this->fetchValue($connection, "SELECT COUNT(*) FROM user WHERE role = 'visiteur'"),
            ],
        ]);
    }

    #[Route('/users', name: 'app_admin_users')]
    public function users(Request $request, Connection $connection): Response
    {
        $query = trim((string) $request->query->get('q', ''));

        if ($query !== '') {
            $likeQuery = '%'.$query.'%';
            $users = $this->fetchAll(
                $connection,
                'SELECT id, prenom, nom, email, telephone, role, imageUrl FROM user WHERE LOWER(prenom) LIKE LOWER(?) OR LOWER(nom) LIKE LOWER(?) OR LOWER(email) LIKE LOWER(?) OR LOWER(telephone) LIKE LOWER(?) OR LOWER(role) LIKE LOWER(?) ORDER BY id ASC',
                [$likeQuery, $likeQuery, $likeQuery, $likeQuery, $likeQuery]
            );
        } else {
            $users = $this->fetchAll($connection, 'SELECT id, prenom, nom, email, telephone, role, imageUrl FROM user ORDER BY id ASC');
        }

        return $this->render('admin/user/index.html.twig', [
            'active' => 'users',
            'searchQuery' => $query,
            'stats' => [
                'users' => $this->fetchValue($connection, 'SELECT COUNT(*) FROM user'),
                'admins' => $this->fetchValue($connection, "SELECT COUNT(*) FROM user WHERE role = 'admin'"),
                'abonnes' => $this->fetchValue($connection, "SELECT COUNT(*) FROM user WHERE role = 'abonne'"),
                'visiteurs' => $this->fetchValue($connection, "SELECT COUNT(*) FROM user WHERE role = 'visiteur'"),
            ],
            'users' => $users,
        ]);
    }

    #[Route('/users/create', name: 'app_admin_users_create', methods: ['POST'])]
    public function usersCreate(
        Request $request,
        Connection $connection,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        if (!$this->isCsrfTokenValid('admin_user_create', (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Jeton CSRF invalide pour la création.');
            return $this->redirectToRoute('app_admin_users');
        }

        $nom = trim((string) $request->request->get('nom', ''));
        $prenom = trim((string) $request->request->get('prenom', ''));
        $email = strtolower(trim((string) $request->request->get('email', '')));
        $telephone = trim((string) $request->request->get('telephone', ''));
        $role = strtolower(trim((string) $request->request->get('role', 'abonne')));
        $password = (string) $request->request->get('password', '');

        if ($nom === '' || $prenom === '' || $email === '' || $password === '') {
            $this->addFlash('error', 'Nom, prénom, email et mot de passe sont obligatoires.');
            return $this->redirectToRoute('app_admin_users');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('error', 'Email invalide.');
            return $this->redirectToRoute('app_admin_users');
        }

        if (strlen($password) < 8) {
            $this->addFlash('error', 'Le mot de passe doit contenir au moins 8 caractères.');
            return $this->redirectToRoute('app_admin_users');
        }

        if (!in_array($role, ['admin', 'abonne', 'visiteur'], true)) {
            $role = 'abonne';
        }

        try {
            $exists = (int) $connection->fetchOne('SELECT COUNT(*) FROM user WHERE email = ?', [$email]);
            if ($exists > 0) {
                $this->addFlash('error', 'Un utilisateur avec cet email existe déjà.');
                return $this->redirectToRoute('app_admin_users');
            }

            $user = new User();
            $passwordHash = $passwordHasher->hashPassword($user, $password);

            $connection->insert('user', [
                'nom' => $nom,
                'prenom' => $prenom,
                'email' => $email,
                'password_hash' => $passwordHash,
                'role' => $role,
                'telephone' => $telephone !== '' ? $telephone : null,
                'imageUrl' => 'theme/images/logo.png',
            ]);

            $this->addFlash('success', 'Utilisateur ajouté avec succès.');
        } catch (Exception $e) {
            $this->addFlash('error', 'Erreur création utilisateur: '.$e->getMessage());
        }

        return $this->redirectToRoute('app_admin_users');
    }

    #[Route('/users/{id}/update', name: 'app_admin_users_update', methods: ['POST'])]
    public function usersUpdate(
        int $id,
        Request $request,
        Connection $connection,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        if (!$this->isCsrfTokenValid('admin_user_edit_'.$id, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Jeton CSRF invalide pour la modification.');
            return $this->redirectToRoute('app_admin_users');
        }

        $nom = trim((string) $request->request->get('nom', ''));
        $prenom = trim((string) $request->request->get('prenom', ''));
        $email = strtolower(trim((string) $request->request->get('email', '')));
        $telephone = trim((string) $request->request->get('telephone', ''));
        $role = strtolower(trim((string) $request->request->get('role', 'abonne')));
        $password = (string) $request->request->get('password', '');

        if ($nom === '' || $prenom === '' || $email === '') {
            $this->addFlash('error', 'Nom, prénom et email sont obligatoires pour la modification.');
            return $this->redirectToRoute('app_admin_users');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('error', 'Email invalide.');
            return $this->redirectToRoute('app_admin_users');
        }

        if (!in_array($role, ['admin', 'abonne', 'visiteur'], true)) {
            $role = 'abonne';
        }

        try {
            $exists = (int) $connection->fetchOne('SELECT COUNT(*) FROM user WHERE email = ? AND id <> ?', [$email, $id]);
            if ($exists > 0) {
                $this->addFlash('error', 'Cet email est déjà utilisé par un autre compte.');
                return $this->redirectToRoute('app_admin_users');
            }

            $data = [
                'nom' => $nom,
                'prenom' => $prenom,
                'email' => $email,
                'telephone' => $telephone !== '' ? $telephone : null,
                'role' => $role,
            ];

            if ($password !== '') {
                if (strlen($password) < 8) {
                    $this->addFlash('error', 'Le nouveau mot de passe doit contenir au moins 8 caractères.');
                    return $this->redirectToRoute('app_admin_users');
                }

                $user = new User();
                $data['password_hash'] = $passwordHasher->hashPassword($user, $password);
            }

            $connection->update('user', $data, ['id' => $id]);
            $this->addFlash('success', 'Utilisateur modifié avec succès.');
        } catch (Exception $e) {
            $this->addFlash('error', 'Erreur modification utilisateur: '.$e->getMessage());
        }

        return $this->redirectToRoute('app_admin_users');
    }

    #[Route('/users/{id}/delete', name: 'app_admin_users_delete', methods: ['POST'])]
    public function usersDelete(int $id, Request $request, Connection $connection): Response
    {
        if (!$this->isCsrfTokenValid('admin_user_delete_'.$id, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Jeton CSRF invalide pour la suppression.');
            return $this->redirectToRoute('app_admin_users');
        }

        try {
            $connection->delete('user', ['id' => $id]);
            $this->addFlash('success', 'Utilisateur supprimé avec succès.');
        } catch (Exception $e) {
            $this->addFlash('error', 'Erreur suppression utilisateur: '.$e->getMessage());
        }

        return $this->redirectToRoute('app_admin_users');
    }

    #[Route('/lieux', name: 'app_admin_lieux')]
    public function lieux(Connection $connection): Response
    {
        return $this->render('admin/lieu/index.html.twig', [
            'active' => 'lieux',
            'places' => $this->fetchAll($connection, 'SELECT id, nom, ville, categorie, type, budget_min, budget_max FROM lieu ORDER BY id DESC'),
        ]);
    }

    #[Route('/sorties', name: 'app_admin_sorties')]
    public function sorties(Connection $connection): Response
    {
        return $this->render('admin/sortie/index.html.twig', [
            'active' => 'sorties',
            'sorties' => $this->fetchAll($connection, 'SELECT id, titre, ville, type_activite, date_sortie, nb_places, statut FROM annonce_sortie ORDER BY date_sortie ASC'),
        ]);
    }

    #[Route('/offres', name: 'app_admin_offres')]
    public function offres(Request $request, Connection $connection): Response
    {
        $query = trim((string) $request->query->get('q', ''));
        $status = trim((string) $request->query->get('status', ''));
        $page = max(1, (int) $request->query->get('page', 1));
        $pageSize = 10;
        $offset = ($page - 1) * $pageSize;

        $whereParts = [];
        $params = [];

        if ($query !== '') {
            $whereParts[] = '(LOWER(o.titre) LIKE LOWER(?) OR LOWER(o.type) LIKE LOWER(?) OR LOWER(COALESCE(o.description, \'\')) LIKE LOWER(?))';
            $like = '%'.$query.'%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ($status !== '') {
            $aliases = $this->statusAliases($status);
            $placeholders = implode(', ', array_fill(0, count($aliases), '?'));
            $whereParts[] = 'LOWER(o.statut) IN ('.$placeholders.')';
            foreach ($aliases as $alias) {
                $params[] = $alias;
            }
        }

        $whereSql = $whereParts !== [] ? ' WHERE '.implode(' AND ', $whereParts) : '';

        $total = (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM offre o'.$whereSql,
            $params
        );

        $offres = $this->fetchAll(
            $connection,
            'SELECT o.id, o.titre, o.type, o.pourcentage, o.date_debut, o.date_fin, o.statut, o.description, o.lieu_id, l.nom AS lieu_nom
             FROM offre o
             LEFT JOIN lieu l ON l.id = o.lieu_id'
            .$whereSql.
            ' ORDER BY o.date_fin ASC, o.id DESC LIMIT '.$pageSize.' OFFSET '.$offset,
            $params
        );

        $stats = [
            'total' => $this->fetchValue($connection, 'SELECT COUNT(*) FROM offre'),
            'actives' => $this->fetchValue($connection, "SELECT COUNT(*) FROM offre WHERE LOWER(statut) IN ('actif', 'active')"),
            'expirees' => $this->fetchValue($connection, "SELECT COUNT(*) FROM offre WHERE date_fin < CURDATE() OR LOWER(statut) IN ('expiree', 'expire', 'expired')"),
        ];

        return $this->render('admin/offre/index.html.twig', [
            'active' => 'offres',
            'offres' => $offres,
            'lieux' => $this->fetchAll($connection, 'SELECT id, nom FROM lieu ORDER BY nom ASC'),
            'promoCodes' => $this->fetchAll($connection, "
                SELECT cp.id, cp.offre_id, cp.user_id, cp.qr_image_url, cp.date_generation, cp.date_expiration, cp.statut,
                       o.titre AS offre_titre, u.prenom, u.nom
                FROM code_promo cp
                LEFT JOIN offre o ON o.id = cp.offre_id
                LEFT JOIN user u ON u.id = cp.user_id
                ORDER BY cp.id DESC
                LIMIT 120
            "),
            'reservations' => $this->fetchAll($connection, "
                SELECT r.id, r.date_reservation, r.nombre_personnes, r.statut, r.note, r.created_at,
                       o.titre AS offre_titre, l.nom AS lieu_nom,
                       u.prenom, u.nom
                FROM reservation_offre r
                LEFT JOIN offre o ON o.id = r.offre_id
                LEFT JOIN lieu l ON l.id = r.lieu_id
                LEFT JOIN user u ON u.id = r.user_id
                ORDER BY r.id DESC
                LIMIT 150
            "),
            'stats' => $stats,
            'filters' => [
                'q' => $query,
                'status' => $status,
            ],
            'pagination' => [
                'page' => $page,
                'pageSize' => $pageSize,
                'total' => $total,
                'totalPages' => max(1, (int) ceil($total / $pageSize)),
            ],
        ]);
    }

    #[Route('/offres/create', name: 'app_admin_offres_create', methods: ['POST'])]
    public function offresCreate(Request $request, Connection $connection): Response
    {
        if (!$this->isCsrfTokenValid('admin_offre_create', (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Jeton CSRF invalide pour la création d\'offre.');
            return $this->redirectToRoute('app_admin_offres');
        }

        $payload = $this->normalizeOffrePayload([
            'titre' => (string) $request->request->get('titre', ''),
            'type' => (string) $request->request->get('type', ''),
            'pourcentage' => (string) $request->request->get('pourcentage', ''),
            'date_debut' => (string) $request->request->get('date_debut', ''),
            'date_fin' => (string) $request->request->get('date_fin', ''),
            'statut' => (string) $request->request->get('statut', ''),
            'description' => (string) $request->request->get('description', ''),
            'lieu_id' => (string) $request->request->get('lieu_id', ''),
        ]);

        $errors = $this->validateOffrePayload($payload, true);
        if ($errors !== []) {
            $this->addFlash('error', implode(' ', $errors));
            return $this->redirectToRoute('app_admin_offres');
        }

        $userId = $this->getUser() instanceof User ? $this->getUser()->getId() : null;

        try {
            $connection->insert('offre', [
                'user_id' => $userId,
                'titre' => $payload['titre'],
                'type' => $payload['type'],
                'pourcentage' => $payload['pourcentage'],
                'date_debut' => $payload['date_debut'],
                'date_fin' => $payload['date_fin'],
                'statut' => $payload['statut'],
                'description' => $payload['description'] !== '' ? $payload['description'] : null,
                'lieu_id' => $payload['lieu_id'],
            ]);

            $this->addFlash('success', 'Offre créée avec succès.');
        } catch (Exception $e) {
            $this->addFlash('error', 'Erreur création offre: '.$e->getMessage());
        }

        return $this->redirectToRoute('app_admin_offres');
    }

    #[Route('/offres/{id}/update', name: 'app_admin_offres_update', methods: ['POST'])]
    public function offresUpdate(int $id, Request $request, Connection $connection): Response
    {
        if (!$this->isCsrfTokenValid('admin_offre_edit_'.$id, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Jeton CSRF invalide pour la modification d\'offre.');
            return $this->redirectToRoute('app_admin_offres');
        }

        $payload = $this->normalizeOffrePayload([
            'titre' => (string) $request->request->get('titre', ''),
            'type' => (string) $request->request->get('type', ''),
            'pourcentage' => (string) $request->request->get('pourcentage', ''),
            'date_debut' => (string) $request->request->get('date_debut', ''),
            'date_fin' => (string) $request->request->get('date_fin', ''),
            'statut' => (string) $request->request->get('statut', ''),
            'description' => (string) $request->request->get('description', ''),
            'lieu_id' => (string) $request->request->get('lieu_id', ''),
        ]);

        $errors = $this->validateOffrePayload($payload, true);
        if ($errors !== []) {
            $this->addFlash('error', implode(' ', $errors));
            return $this->redirectToRoute('app_admin_offres');
        }

        try {
            $connection->update('offre', [
                'titre' => $payload['titre'],
                'type' => $payload['type'],
                'pourcentage' => $payload['pourcentage'],
                'date_debut' => $payload['date_debut'],
                'date_fin' => $payload['date_fin'],
                'statut' => $payload['statut'],
                'description' => $payload['description'] !== '' ? $payload['description'] : null,
                'lieu_id' => $payload['lieu_id'],
            ], ['id' => $id]);

            $this->addFlash('success', 'Offre modifiée avec succès.');
        } catch (Exception $e) {
            $this->addFlash('error', 'Erreur modification offre: '.$e->getMessage());
        }

        return $this->redirectToRoute('app_admin_offres');
    }

    #[Route('/offres/{id}/delete', name: 'app_admin_offres_delete', methods: ['POST'])]
    public function offresDelete(int $id, Request $request, Connection $connection): Response
    {
        if (!$this->isCsrfTokenValid('admin_offre_delete_'.$id, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Jeton CSRF invalide pour la suppression d\'offre.');
            return $this->redirectToRoute('app_admin_offres');
        }

        try {
            $connection->delete('offre', ['id' => $id]);
            $this->addFlash('success', 'Offre supprimée avec succès.');
        } catch (Exception $e) {
            $this->addFlash('error', 'Erreur suppression offre: '.$e->getMessage());
        }

        return $this->redirectToRoute('app_admin_offres');
    }

    #[Route('/reservations/{id}/confirm', name: 'app_admin_reservation_confirm', methods: ['POST'])]
    public function reservationConfirm(int $id, Request $request, Connection $connection): Response
    {
        if (!$this->isCsrfTokenValid('admin_reservation_confirm_'.$id, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Jeton CSRF invalide pour la confirmation.');
            return $this->redirectToRoute('app_admin_offres');
        }

        try {
            $connection->update('reservation_offre', ['statut' => 'CONFIRMÉE'], ['id' => $id]);
            $this->addFlash('success', 'Réservation confirmée.');
        } catch (Exception $e) {
            $this->addFlash('error', 'Erreur confirmation réservation: '.$e->getMessage());
        }

        return $this->redirectToRoute('app_admin_offres');
    }

    #[Route('/reservations/{id}/refuse', name: 'app_admin_reservation_refuse', methods: ['POST'])]
    public function reservationRefuse(int $id, Request $request, Connection $connection): Response
    {
        if (!$this->isCsrfTokenValid('admin_reservation_refuse_'.$id, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Jeton CSRF invalide pour le refus.');
            return $this->redirectToRoute('app_admin_offres');
        }

        try {
            $connection->update('reservation_offre', ['statut' => 'REFUSÉE'], ['id' => $id]);
            $this->addFlash('success', 'Réservation refusée.');
        } catch (Exception $e) {
            $this->addFlash('error', 'Erreur refus réservation: '.$e->getMessage());
        }

        return $this->redirectToRoute('app_admin_offres');
    }

    #[Route('/api/offres', name: 'app_admin_api_offres_list', methods: ['GET'])]
    public function apiOffresList(Request $request, Connection $connection): JsonResponse
    {
        $query = trim((string) $request->query->get('q', ''));
        $status = trim((string) $request->query->get('status', ''));
        $page = max(1, (int) $request->query->get('page', 1));
        $pageSize = min(100, max(1, (int) $request->query->get('pageSize', 10)));
        $offset = ($page - 1) * $pageSize;

        $whereParts = [];
        $params = [];

        if ($query !== '') {
            $whereParts[] = '(LOWER(o.titre) LIKE LOWER(?) OR LOWER(o.type) LIKE LOWER(?) OR LOWER(COALESCE(o.description, \'\')) LIKE LOWER(?))';
            $like = '%'.$query.'%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ($status !== '') {
            $aliases = $this->statusAliases($status);
            $placeholders = implode(', ', array_fill(0, count($aliases), '?'));
            $whereParts[] = 'LOWER(o.statut) IN ('.$placeholders.')';
            foreach ($aliases as $alias) {
                $params[] = $alias;
            }
        }

        $whereSql = $whereParts !== [] ? ' WHERE '.implode(' AND ', $whereParts) : '';
        $total = (int) $connection->fetchOne('SELECT COUNT(*) FROM offre o'.$whereSql, $params);

        $rows = $this->fetchAll(
            $connection,
            'SELECT o.id, o.titre, o.description, o.type, o.pourcentage, o.date_debut, o.date_fin, o.statut, o.lieu_id, l.nom AS lieu_nom
             FROM offre o
             LEFT JOIN lieu l ON l.id = o.lieu_id'
            .$whereSql.
            ' ORDER BY o.id DESC LIMIT '.$pageSize.' OFFSET '.$offset,
            $params
        );

        return $this->json([
            'data' => array_map(fn (array $row) => $this->toOffreDto($row), $rows),
            'meta' => [
                'page' => $page,
                'pageSize' => $pageSize,
                'total' => $total,
                'totalPages' => max(1, (int) ceil($total / $pageSize)),
            ],
        ]);
    }

    #[Route('/api/offres/{id}', name: 'app_admin_api_offres_detail', methods: ['GET'])]
    public function apiOffresDetail(int $id, Connection $connection): JsonResponse
    {
        $row = $connection->fetchAssociative(
            'SELECT o.id, o.titre, o.description, o.type, o.pourcentage, o.date_debut, o.date_fin, o.statut, o.lieu_id, l.nom AS lieu_nom
             FROM offre o
             LEFT JOIN lieu l ON l.id = o.lieu_id
             WHERE o.id = ?',
            [$id]
        );

        if (!$row) {
            return $this->json(['error' => 'Offre introuvable.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json(['data' => $this->toOffreDto($row)]);
    }

    #[Route('/api/offres', name: 'app_admin_api_offres_create', methods: ['POST'])]
    public function apiOffresCreate(Request $request, Connection $connection): JsonResponse
    {
        $payload = json_decode((string) $request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json(['error' => 'Payload JSON invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $payload = $this->normalizeOffrePayload($payload);
        $errors = $this->validateOffrePayload($payload, true);
        if ($errors !== []) {
            return $this->json(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $userId = $this->getUser() instanceof User ? $this->getUser()->getId() : null;

        try {
            $connection->insert('offre', [
                'user_id' => $userId,
                'titre' => $payload['titre'],
                'type' => $payload['type'],
                'pourcentage' => $payload['pourcentage'],
                'date_debut' => $payload['date_debut'],
                'date_fin' => $payload['date_fin'],
                'statut' => $payload['statut'],
                'description' => $payload['description'] !== '' ? $payload['description'] : null,
                'lieu_id' => $payload['lieu_id'],
            ]);

            $newId = (int) $connection->lastInsertId();
        } catch (Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json(['id' => $newId], Response::HTTP_CREATED);
    }

    #[Route('/api/offres/{id}', name: 'app_admin_api_offres_update', methods: ['PUT', 'PATCH'])]
    public function apiOffresUpdate(int $id, Request $request, Connection $connection): JsonResponse
    {
        $payload = json_decode((string) $request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json(['error' => 'Payload JSON invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $payload = $this->normalizeOffrePayload($payload);
        $errors = $this->validateOffrePayload($payload, true);
        if ($errors !== []) {
            return $this->json(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $affected = $connection->update('offre', [
                'titre' => $payload['titre'],
                'type' => $payload['type'],
                'pourcentage' => $payload['pourcentage'],
                'date_debut' => $payload['date_debut'],
                'date_fin' => $payload['date_fin'],
                'statut' => $payload['statut'],
                'description' => $payload['description'] !== '' ? $payload['description'] : null,
                'lieu_id' => $payload['lieu_id'],
            ], ['id' => $id]);

            if ($affected === 0) {
                return $this->json(['error' => 'Offre introuvable ou inchangée.'], Response::HTTP_NOT_FOUND);
            }
        } catch (Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json(['status' => 'updated']);
    }

    #[Route('/api/offres/{id}', name: 'app_admin_api_offres_delete', methods: ['DELETE'])]
    public function apiOffresDelete(int $id, Connection $connection): JsonResponse
    {
        try {
            $affected = $connection->delete('offre', ['id' => $id]);
            if ($affected === 0) {
                return $this->json(['error' => 'Offre introuvable.'], Response::HTTP_NOT_FOUND);
            }
        } catch (Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json(['status' => 'deleted']);
    }

    #[Route('/evenements', name: 'app_admin_evenements')]
    public function evenements(Connection $connection): Response
    {
        return $this->render('admin/evenement/index.html.twig', [
            'active' => 'evenements',
            'events' => $this->fetchAll($connection, 'SELECT id, titre, date_debut, date_fin, type, prix, statut FROM evenement ORDER BY date_debut ASC'),
        ]);
    }

    private function fetchAll(Connection $connection, string $sql, array $params = []): array
    {
        try {
            return $connection->fetchAllAssociative($sql, $params);
        } catch (Exception) {
            return [];
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

    private function normalizeOffrePayload(array $payload): array
    {
        return [
            'titre' => trim((string) ($payload['titre'] ?? '')),
            'type' => trim((string) ($payload['type'] ?? '')),
            'pourcentage' => isset($payload['pourcentage']) ? (float) $payload['pourcentage'] : -1,
            'date_debut' => trim((string) ($payload['date_debut'] ?? '')),
            'date_fin' => trim((string) ($payload['date_fin'] ?? '')),
            'statut' => $this->normalizeStatus(trim((string) ($payload['statut'] ?? ''))),
            'description' => trim((string) ($payload['description'] ?? '')),
            'lieu_id' => isset($payload['lieu_id']) ? (int) $payload['lieu_id'] : 0,
        ];
    }

    /**
     * @return string[]
     */
    private function validateOffrePayload(array $payload, bool $requireLieu): array
    {
        $errors = [];

        if ($payload['titre'] === '') {
            $errors[] = 'Le titre est obligatoire.';
        }

        if ($payload['type'] === '') {
            $errors[] = 'Le type est obligatoire.';
        }

        if ($payload['statut'] === '') {
            $errors[] = 'Le statut est obligatoire.';
        } elseif (!in_array($payload['statut'], ['ACTIVE', 'EXPIREE', 'DESACTIVEE'], true)) {
            $errors[] = 'Le statut est invalide.';
        }

        if ($payload['pourcentage'] < 0 || $payload['pourcentage'] > 100) {
            $errors[] = 'Le pourcentage doit être entre 0 et 100.';
        }

        if ($requireLieu && $payload['lieu_id'] <= 0) {
            $errors[] = 'Le lieu est obligatoire.';
        }

        $dateDebut = \DateTimeImmutable::createFromFormat('Y-m-d', $payload['date_debut']);
        $dateFin = \DateTimeImmutable::createFromFormat('Y-m-d', $payload['date_fin']);

        if (!$dateDebut || !$dateFin) {
            $errors[] = 'Les dates début/fin sont obligatoires et doivent être au format YYYY-MM-DD.';
        } elseif ($dateDebut > $dateFin) {
            $errors[] = 'La date de début doit être inférieure ou égale à la date de fin.';
        }

        return $errors;
    }

    private function normalizeStatus(string $status): string
    {
        return match (strtolower(trim($status))) {
            'actif', 'active' => 'ACTIVE',
            'expiree', 'expire', 'expired' => 'EXPIREE',
            'desactivee', 'desactive', 'inactif', 'inactive', 'brouillon', 'draft' => 'DESACTIVEE',
            default => strtoupper(trim($status)),
        };
    }

    /**
     * @return string[]
     */
    private function statusAliases(string $status): array
    {
        return match ($this->normalizeStatus($status)) {
            'ACTIVE' => ['active', 'actif'],
            'EXPIREE' => ['expiree', 'expire', 'expired'],
            'DESACTIVEE' => ['desactivee', 'desactive', 'inactive', 'inactif', 'draft', 'brouillon'],
            default => [strtolower(trim($status))],
        };
    }

    private function toOffreDto(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'titre' => (string) ($row['titre'] ?? ''),
            'type' => (string) ($row['type'] ?? ''),
            'pourcentage' => isset($row['pourcentage']) ? (float) $row['pourcentage'] : 0.0,
            'dateDebut' => isset($row['date_debut']) ? (string) $row['date_debut'] : null,
            'dateFin' => isset($row['date_fin']) ? (string) $row['date_fin'] : null,
            'statut' => (string) ($row['statut'] ?? ''),
            'description' => isset($row['description']) ? (string) $row['description'] : null,
            'lieu' => [
                'id' => isset($row['lieu_id']) ? (int) $row['lieu_id'] : null,
                'nom' => $row['lieu_nom'] ?? null,
            ],
            'createdAt' => null,
        ];
    }
}