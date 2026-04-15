<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Contracts\HttpClient\HttpClientInterface;
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

    #[Route('/dashboard-offres', name: 'app_admin_dashboard_offres')]
    public function dashboardOffres(Connection $connection): Response
    {
        $offerStats = [
            'total' => $this->fetchValue($connection, 'SELECT COUNT(*) FROM offre'),
            'active' => $this->fetchValue($connection, "SELECT COUNT(*) FROM offre WHERE LOWER(statut) IN ('actif', 'active')"),
            'expiringSoon' => $this->fetchValue($connection, "SELECT COUNT(*) FROM offre WHERE TIMESTAMPDIFF(HOUR, NOW(), CONCAT(date_fin, ' 23:59:59')) BETWEEN 0 AND 24"),
            'expired' => $this->fetchValue($connection, "SELECT COUNT(*) FROM offre WHERE date_fin < CURDATE() OR LOWER(statut) IN ('expiree', 'expire', 'expired')"),
        ];

        $promoStats = [
            'total' => $this->fetchValue($connection, 'SELECT COUNT(*) FROM code_promo'),
            'active' => $this->fetchValue($connection, "SELECT COUNT(*) FROM code_promo WHERE LOWER(statut) = 'actif'"),
            'used' => $this->fetchValue($connection, "SELECT COUNT(*) FROM code_promo WHERE LOWER(statut) = 'utilise'"),
            'blocked' => $this->fetchValue($connection, "SELECT COUNT(*) FROM code_promo WHERE LOWER(statut) = 'bloque_abus'"),
        ];

        $reservationStats = [
            'total' => $this->fetchValue($connection, 'SELECT COUNT(*) FROM reservation_offre'),
            'pending' => $this->fetchValue($connection, "SELECT COUNT(*) FROM reservation_offre WHERE LOWER(statut) = 'en_attente'"),
            'confirmed' => $this->fetchValue($connection, "SELECT COUNT(*) FROM reservation_offre WHERE LOWER(statut) = 'confirmée' OR LOWER(statut) = 'confirmee'"),
            'refused' => $this->fetchValue($connection, "SELECT COUNT(*) FROM reservation_offre WHERE LOWER(statut) = 'refusée' OR LOWER(statut) = 'refusee'"),
        ];

        return $this->render('admin/dashboard/offres.html.twig', [
            'active' => 'dashboard_offres',
            'offerStats' => $offerStats,
            'promoStats' => $promoStats,
            'reservationStats' => $reservationStats,
            'recentOffers' => $this->fetchAll($connection, "
                SELECT o.id, o.titre, o.type, o.pourcentage, o.date_fin, o.statut, l.nom AS lieu_nom,
                       CASE
                           WHEN TIMESTAMPDIFF(HOUR, NOW(), CONCAT(o.date_fin, ' 23:59:59')) BETWEEN 0 AND 24 THEN 1
                           ELSE 0
                       END AS expiring_soon
                FROM offre o
                LEFT JOIN lieu l ON l.id = o.lieu_id
                ORDER BY o.date_fin ASC, o.id DESC
                LIMIT 8
            "),
            'recentPromos' => $this->fetchAll($connection, "
                SELECT cp.id, cp.statut, cp.date_generation, cp.date_expiration, o.titre AS offre_titre
                FROM code_promo cp
                LEFT JOIN offre o ON o.id = cp.offre_id
                ORDER BY cp.id DESC
                LIMIT 8
            "),
            'recentReservations' => $this->fetchAll($connection, "
                SELECT r.id, r.statut, r.date_reservation, r.nombre_personnes, o.titre AS offre_titre
                FROM reservation_offre r
                LEFT JOIN offre o ON o.id = r.offre_id
                ORDER BY r.id DESC
                LIMIT 8
            "),
        ]);
    }

    #[Route('/users', name: 'app_admin_users')]
    public function users(Request $request, Connection $connection): Response
    {
        $query = trim((string) $request->query->get('q', ''));
        $perPage = 3;
        $page = max(1, (int) $request->query->get('page', 1));

        if ($query !== '') {
            $likeQuery = '%'.$query.'%';
            $filters = [$likeQuery, $likeQuery, $likeQuery, $likeQuery, $likeQuery];
            $countRows = $this->fetchAll(
                $connection,
                'SELECT COUNT(*) AS total FROM user WHERE LOWER(prenom) LIKE LOWER(?) OR LOWER(nom) LIKE LOWER(?) OR LOWER(email) LIKE LOWER(?) OR LOWER(telephone) LIKE LOWER(?) OR LOWER(role) LIKE LOWER(?)',
                $filters
            );
            $totalFiltered = (int) ($countRows[0]['total'] ?? 0);
            $pageCount = max(1, (int) ceil($totalFiltered / $perPage));
            $page = min($page, $pageCount);
            $offset = ($page - 1) * $perPage;

            $users = $this->fetchAll(
                $connection,
                'SELECT id, prenom, nom, email, telephone, role, imageUrl FROM user WHERE LOWER(prenom) LIKE LOWER(?) OR LOWER(nom) LIKE LOWER(?) OR LOWER(email) LIKE LOWER(?) OR LOWER(telephone) LIKE LOWER(?) OR LOWER(role) LIKE LOWER(?) ORDER BY id ASC LIMIT '.$perPage.' OFFSET '.$offset,
                $filters
            );
        } else {
            $totalFiltered = $this->fetchValue($connection, 'SELECT COUNT(*) FROM user');
            $pageCount = max(1, (int) ceil($totalFiltered / $perPage));
            $page = min($page, $pageCount);
            $offset = ($page - 1) * $perPage;

            $users = $this->fetchAll(
                $connection,
                'SELECT id, prenom, nom, email, telephone, role, imageUrl FROM user ORDER BY id ASC LIMIT '.$perPage.' OFFSET '.$offset
            );
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
            'page' => $page,
            'pageCount' => $pageCount,
            'perPage' => $perPage,
            'totalFiltered' => $totalFiltered,
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

        if (!$this->isValidHumanName($nom) || !$this->isValidHumanName($prenom)) {
            $this->addFlash('error', 'Nom et prénom doivent contenir 2 à 50 lettres (espaces, tirets et apostrophes autorisés).');
            return $this->redirectToRoute('app_admin_users');
        }

        if ($telephone !== '' && !$this->isValidPhoneNumber($telephone)) {
            $this->addFlash('error', 'Téléphone invalide. Format attendu: +216 12 345 678');
            return $this->redirectToRoute('app_admin_users');
        }

        if (strlen($password) < 8) {
            $this->addFlash('error', 'Le mot de passe doit contenir au moins 8 caractères.');
            return $this->redirectToRoute('app_admin_users');
        }

        if (!$this->isStrongPassword($password)) {
            $this->addFlash('error', 'Mot de passe trop faible (majuscule, minuscule, chiffre et caractère spécial requis).');
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

        if (!$this->isValidHumanName($nom) || !$this->isValidHumanName($prenom)) {
            $this->addFlash('error', 'Nom et prénom doivent contenir 2 à 50 lettres (espaces, tirets et apostrophes autorisés).');
            return $this->redirectToRoute('app_admin_users');
        }

        if ($telephone !== '' && !$this->isValidPhoneNumber($telephone)) {
            $this->addFlash('error', 'Téléphone invalide. Format attendu: +216 12 345 678');
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

                if (!$this->isStrongPassword($password)) {
                    $this->addFlash('error', 'Nouveau mot de passe trop faible (majuscule, minuscule, chiffre et caractère spécial requis).');
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
    public function sorties(Request $request, Connection $connection): Response
    {
        $query = trim((string) $request->query->get('q', ''));
        $status = strtoupper(trim((string) $request->query->get('status', '')));
        $sort = trim((string) $request->query->get('sort', 'recent'));

        $whereParts = [];
        $params = [];

        if ($query !== '') {
            $whereParts[] = "(LOWER(s.titre) LIKE LOWER(?) OR LOWER(COALESCE(s.description, '')) LIKE LOWER(?) OR LOWER(s.ville) LIKE LOWER(?) OR LOWER(COALESCE(s.type_activite, '')) LIKE LOWER(?) OR LOWER(COALESCE(s.lieu_texte, '')) LIKE LOWER(?) OR LOWER(CONCAT(COALESCE(u.prenom, ''), ' ', COALESCE(u.nom, ''))) LIKE LOWER(?))";
            $like = '%'.$query.'%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $allowedStatuses = ['OUVERTE', 'CLOTUREE', 'ANNULEE', 'TERMINEE'];
        if (in_array($status, $allowedStatuses, true)) {
            $whereParts[] = 's.statut = ?';
            $params[] = $status;
        } else {
            $status = '';
        }

        $sortSql = match ($sort) {
            'date_asc' => 's.date_sortie ASC',
            'date_desc', 'recent' => 's.date_sortie DESC',
            'title_asc' => 's.titre ASC',
            'title_desc' => 's.titre DESC',
            'city_asc' => 's.ville ASC',
            'places_desc' => 's.nb_places DESC',
            'status_asc' => 's.statut ASC',
            'creator_asc' => "COALESCE(u.prenom, '') ASC, COALESCE(u.nom, '') ASC",
            default => 's.date_sortie DESC, s.id DESC',
        };
        if (!in_array($sort, ['date_asc', 'date_desc', 'recent', 'title_asc', 'title_desc', 'city_asc', 'places_desc', 'status_asc', 'creator_asc'], true)) {
            $sort = 'recent';
        }

        $whereSql = $whereParts === [] ? '' : ' WHERE '.implode(' AND ', $whereParts);

        $page = max(1, (int) $request->query->get('page', 1));
        $pageSize = 6;
        $total = (int) $connection->fetchOne(
            'SELECT COUNT(*)
             FROM annonce_sortie s
             LEFT JOIN user u ON u.id = s.user_id'
             .$whereSql,
            $params
        );
        $totalPages = max(1, (int) ceil($total / $pageSize));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $pageSize;

        return $this->render('admin/sortie/index.html.twig', [
            'active' => 'sorties',
            'sorties' => $this->fetchAll(
                $connection,
                'SELECT s.id, s.user_id, s.titre, s.description, s.ville, s.lieu_texte, s.point_rencontre, s.type_activite, s.date_sortie, s.budget_max, s.nb_places, s.statut, s.image_url, s.questions_json, u.prenom, u.nom, u.imageUrl AS user_image_url
                 FROM annonce_sortie s
                 LEFT JOIN user u ON u.id = s.user_id
                 '.$whereSql.'
                 ORDER BY '.$sortSql.'
                 LIMIT '.$pageSize.' OFFSET '.$offset
            ,
                $params
            ),
            'page' => $page,
            'pageSize' => $pageSize,
            'total' => $total,
            'totalPages' => $totalPages,
            'filters' => [
                'q' => $query,
                'status' => $status,
                'sort' => $sort,
            ],
        ]);
    }

    #[Route('/offres', name: 'app_admin_offres')]
    public function offres(Request $request, Connection $connection): Response
    {
        $query = trim((string) $request->query->get('q', ''));
        $status = trim((string) $request->query->get('status', ''));
        $sort = trim((string) $request->query->get('sort', 'date_fin'));
        $direction = $this->normalizeSortDirection((string) $request->query->get('direction', 'asc'));
        $promoQuery = trim((string) $request->query->get('promo_q', ''));
        $promoStatus = trim((string) $request->query->get('promo_status', ''));
        $promoSort = trim((string) $request->query->get('promo_sort', 'id'));
        $promoDirection = $this->normalizeSortDirection((string) $request->query->get('promo_direction', 'desc'));
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
        $offerSortSql = $this->offerSortSql($sort, $direction);

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
            ' ORDER BY '.$offerSortSql.', o.id DESC LIMIT '.$pageSize.' OFFSET '.$offset,
            $params
        );

        $promoWhereParts = [];
        $promoParams = [];

        if ($promoQuery !== '') {
            $promoWhereParts[] = '(LOWER(COALESCE(o.titre, \'\')) LIKE LOWER(?) OR LOWER(COALESCE(u.prenom, \'\')) LIKE LOWER(?) OR LOWER(COALESCE(u.nom, \'\')) LIKE LOWER(?) OR LOWER(CAST(cp.id AS CHAR)) LIKE LOWER(?) OR LOWER(cp.statut) LIKE LOWER(?))';
            $like = '%'.$promoQuery.'%';
            $promoParams[] = $like;
            $promoParams[] = $like;
            $promoParams[] = $like;
            $promoParams[] = $like;
            $promoParams[] = $like;
        }

        if ($promoStatus !== '') {
            $promoWhereParts[] = 'LOWER(cp.statut) = LOWER(?)';
            $promoParams[] = $this->normalizePromoStatus($promoStatus);
        }

        $promoWhereSql = $promoWhereParts !== [] ? ' WHERE '.implode(' AND ', $promoWhereParts) : '';
        $promoSortSql = $this->promoSortSql($promoSort, $promoDirection);
        $promoSql = "
                SELECT cp.id, cp.offre_id, cp.user_id, cp.qr_image_url, cp.date_generation, cp.date_expiration, cp.statut,
                       o.titre AS offre_titre, u.prenom, u.nom
                FROM code_promo cp
                LEFT JOIN offre o ON o.id = cp.offre_id
                LEFT JOIN user u ON u.id = cp.user_id
            ".$promoWhereSql.' ORDER BY '.$promoSortSql.' LIMIT 120';

        $stats = [
            'total' => $this->fetchValue($connection, 'SELECT COUNT(*) FROM offre'),
            'actives' => $this->fetchValue($connection, "SELECT COUNT(*) FROM offre WHERE LOWER(statut) IN ('actif', 'active')"),
            'expirees' => $this->fetchValue($connection, "SELECT COUNT(*) FROM offre WHERE date_fin < CURDATE() OR LOWER(statut) IN ('expiree', 'expire', 'expired')"),
        ];

        return $this->render('admin/offre/index.html.twig', [
            'active' => 'offres',
            'offres' => $offres,
            'lieux' => $this->fetchAll($connection, 'SELECT id, nom FROM lieu ORDER BY nom ASC'),
            'promoCodes' => $this->fetchAll($connection, $promoSql, $promoParams),
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
                'sort' => $sort,
                'direction' => $direction,
                'promo_q' => $promoQuery,
                'promo_status' => $promoStatus,
                'promo_sort' => $promoSort,
                'promo_direction' => $promoDirection,
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

        $currentUser = $this->getUser();
        $userId = $currentUser instanceof User ? $currentUser->getId() : null;

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
        $adminUser = $this->getUser();
        $adminUserId = $adminUser instanceof User ? (int) $adminUser->getId() : null;

        if (!$this->isCsrfTokenValid('admin_reservation_confirm_'.$id, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Jeton CSRF invalide pour la confirmation.');
            return $this->redirectToRoute('app_admin_offres');
        }

        $reservation = $connection->fetchAssociative(
            "SELECT r.id, r.statut, r.user_id, r.offre_id, o.titre AS offre_titre
             FROM reservation_offre r
             LEFT JOIN offre o ON o.id = r.offre_id
             WHERE r.id = ?",
            [$id]
        );
        if (!$reservation) {
            $this->addFlash('error', 'Réservation introuvable.');
            return $this->redirectToRoute('app_admin_offres');
        }

        if ((string) $reservation['statut'] !== 'EN_ATTENTE') {
            $this->addFlash('error', 'Cette réservation ne peut plus être confirmée.');
            return $this->redirectToRoute('app_admin_offres');
        }

        try {
            $connection->update('reservation_offre', ['statut' => 'CONFIRMÉE'], ['id' => $id]);
            $this->createInAppNotification(
                $connection,
                (int) $reservation['user_id'],
                $adminUserId,
                'RESERVATION_CONFIRMEE',
                'Réservation confirmée',
                'Votre réservation pour l\'offre "'.(string) ($reservation['offre_titre'] ?? 'Offre').'" a été confirmée.',
                'reservation_offre',
                (int) $reservation['id'],
                ['offre_id' => (int) $reservation['offre_id']],
                168
            );
            $this->addFlash('success', 'Réservation confirmée.');
        } catch (Exception $e) {
            $this->addFlash('error', 'Erreur confirmation réservation: '.$e->getMessage());
        }

        return $this->redirectToRoute('app_admin_offres');
    }

    #[Route('/reservations/{id}/refuse', name: 'app_admin_reservation_refuse', methods: ['POST'])]
    public function reservationRefuse(int $id, Request $request, Connection $connection): Response
    {
        $adminUser = $this->getUser();
        $adminUserId = $adminUser instanceof User ? (int) $adminUser->getId() : null;

        if (!$this->isCsrfTokenValid('admin_reservation_refuse_'.$id, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Jeton CSRF invalide pour le refus.');
            return $this->redirectToRoute('app_admin_offres');
        }

        $reservation = $connection->fetchAssociative(
            "SELECT r.id, r.statut, r.user_id, r.offre_id, o.titre AS offre_titre
             FROM reservation_offre r
             LEFT JOIN offre o ON o.id = r.offre_id
             WHERE r.id = ?",
            [$id]
        );
        if (!$reservation) {
            $this->addFlash('error', 'Réservation introuvable.');
            return $this->redirectToRoute('app_admin_offres');
        }

        if ((string) $reservation['statut'] !== 'EN_ATTENTE') {
            $this->addFlash('error', 'Cette réservation ne peut plus être refusée.');
            return $this->redirectToRoute('app_admin_offres');
        }

        try {
            $connection->update('reservation_offre', ['statut' => 'REFUSÉE'], ['id' => $id]);
            $this->createInAppNotification(
                $connection,
                (int) $reservation['user_id'],
                $adminUserId,
                'RESERVATION_REFUSEE',
                'Réservation refusée',
                'Votre réservation pour l\'offre "'.(string) ($reservation['offre_titre'] ?? 'Offre').'" a été refusée.',
                'reservation_offre',
                (int) $reservation['id'],
                ['offre_id' => (int) $reservation['offre_id']],
                168
            );
            $this->addFlash('success', 'Réservation refusée.');
        } catch (Exception $e) {
            $this->addFlash('error', 'Erreur refus réservation: '.$e->getMessage());
        }

        return $this->redirectToRoute('app_admin_offres');
    }

    #[Route('/promo-codes/{id}/update', name: 'app_admin_promo_codes_update', methods: ['POST'])]
    public function promoCodeUpdate(int $id, Request $request, Connection $connection): Response
    {
        if (!$this->isCsrfTokenValid('admin_promo_edit_'.$id, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Jeton CSRF invalide pour la modification du code promo.');
            return $this->redirectToRoute('app_admin_offres');
        }

        $dateExpiration = trim((string) $request->request->get('date_expiration', ''));
        $statut = $this->normalizePromoStatus(trim((string) $request->request->get('statut', '')));

        if ($dateExpiration === '') {
            $this->addFlash('error', 'La date d’expiration est obligatoire.');
            return $this->redirectToRoute('app_admin_offres');
        }

        $dateExpirationObject = \DateTimeImmutable::createFromFormat('Y-m-d', $dateExpiration);
        if (!$dateExpirationObject) {
            $this->addFlash('error', 'Format de date invalide pour le code promo.');
            return $this->redirectToRoute('app_admin_offres');
        }

        if (!in_array($statut, ['ACTIF', 'EXPIRE', 'DESACTIVE', 'UTILISE', 'BLOQUE_ABUS'], true)) {
            $this->addFlash('error', 'Statut de code promo invalide.');
            return $this->redirectToRoute('app_admin_offres');
        }

        try {
            $connection->update('code_promo', [
                'date_expiration' => $dateExpirationObject->format('Y-m-d'),
                'statut' => $statut,
            ], ['id' => $id]);

            $this->addFlash('success', 'Code promo modifié avec succès.');
        } catch (Exception $e) {
            $this->addFlash('error', 'Erreur modification code promo: '.$e->getMessage());
        }

        return $this->redirectToRoute('app_admin_offres');
    }

    #[Route('/promo-codes/{id}/delete', name: 'app_admin_promo_codes_delete', methods: ['POST'])]
    public function promoCodeDelete(int $id, Request $request, Connection $connection): Response
    {
        if (!$this->isCsrfTokenValid('admin_promo_delete_'.$id, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Jeton CSRF invalide pour la suppression du code promo.');
            return $this->redirectToRoute('app_admin_offres');
        }

        try {
            $connection->delete('code_promo', ['id' => $id]);
            $this->addFlash('success', 'Code promo supprimé avec succès.');
        } catch (Exception $e) {
            $this->addFlash('error', 'Erreur suppression code promo: '.$e->getMessage());
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

        $currentUser = $this->getUser();
        $userId = $currentUser instanceof User ? $currentUser->getId() : null;

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

    #[Route('/moderation', name: 'app_admin_moderation')]
    public function moderation(Request $request, Connection $connection): Response
    {
        $this->ensureModerationLogTable($connection);
        $this->ensureUserBanTable($connection);

        $dateFrom = trim((string) $request->query->get('date_from', ''));
        $dateTo = trim((string) $request->query->get('date_to', ''));
        $severity = trim((string) $request->query->get('severity', ''));
        $userId = (int) $request->query->get('user_id', 0);
        $lieuId = (int) $request->query->get('lieu_id', 0);
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = 20;

        $where = [];
        $params = [];

        if ($dateFrom !== '') {
            $where[] = 'm.created_at >= ?';
            $params[] = $dateFrom.' 00:00:00';
        }

        if ($dateTo !== '') {
            $where[] = 'm.created_at <= ?';
            $params[] = $dateTo.' 23:59:59';
        }

        if (in_array($severity, ['moderate', 'severe'], true)) {
            $where[] = 'm.severity = ?';
            $params[] = $severity;
        } else {
            $severity = '';
        }

        if ($userId > 0) {
            $where[] = 'm.user_id = ?';
            $params[] = $userId;
        }

        if ($lieuId > 0) {
            $where[] = 'm.lieu_id = ?';
            $params[] = $lieuId;
        }

        $whereSql = $where !== [] ? ' WHERE '.implode(' AND ', $where) : '';

        $total = (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM moderation_attempt_log m'.$whereSql,
            $params
        );
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $rows = $this->fetchAll(
            $connection,
            "SELECT
                m.id,
                m.user_id,
                m.lieu_id,
                m.action,
                m.severity,
                m.score,
                m.terms_json,
                m.comment_preview,
                m.comment_length,
                m.created_at,
                u.prenom,
                u.nom,
                u.email,
                l.nom AS lieu_nom,
                                l.ville AS lieu_ville,
                                (
                                        SELECT ub.end_at
                                        FROM user_ban ub
                                        WHERE ub.user_id = m.user_id
                                            AND ub.status = 'active'
                                            AND ub.end_at > NOW()
                                        ORDER BY ub.end_at DESC
                                        LIMIT 1
                                ) AS active_ban_until,
                                (
                                        SELECT ub.reason
                                        FROM user_ban ub
                                        WHERE ub.user_id = m.user_id
                                            AND ub.status = 'active'
                                            AND ub.end_at > NOW()
                                        ORDER BY ub.end_at DESC
                                        LIMIT 1
                                ) AS active_ban_reason
             FROM moderation_attempt_log m
             LEFT JOIN user u ON u.id = m.user_id
                         LEFT JOIN lieu l ON l.id = m.lieu_id"
             .$whereSql.
             ' ORDER BY m.created_at DESC, m.id DESC
               LIMIT '.$perPage.' OFFSET '.$offset,
            $params
        );

        foreach ($rows as &$row) {
            $termsRaw = (string) ($row['terms_json'] ?? '');
            $decoded = $termsRaw !== '' ? json_decode($termsRaw, true) : [];
            $terms = is_array($decoded) ? array_filter(array_map('strval', $decoded)) : [];
            $row['terms_text'] = $terms !== [] ? implode(', ', $terms) : '-';
        }
        unset($row);

        return $this->render('admin/moderation/index.html.twig', [
            'active' => 'moderation',
            'rows' => $rows,
            'page' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'severity' => $severity,
                'user_id' => $userId > 0 ? $userId : '',
                'lieu_id' => $lieuId > 0 ? $lieuId : '',
            ],
            'users' => $this->fetchAll($connection, 'SELECT id, prenom, nom, email FROM user ORDER BY prenom ASC, nom ASC LIMIT 500'),
            'lieux' => $this->fetchAll($connection, 'SELECT id, nom, ville FROM lieu ORDER BY nom ASC LIMIT 500'),
            'stats' => [
                'today' => (int) $connection->fetchOne('SELECT COUNT(*) FROM moderation_attempt_log WHERE DATE(created_at) = CURDATE()'),
                'severe' => (int) $connection->fetchOne("SELECT COUNT(*) FROM moderation_attempt_log WHERE severity = 'severe'"),
                'moderate' => (int) $connection->fetchOne("SELECT COUNT(*) FROM moderation_attempt_log WHERE severity = 'moderate'"),
            ],
        ]);
    }

    #[Route('/moderation/ban-user', name: 'app_admin_moderation_ban_user', methods: ['POST'])]
    public function banUserFromModeration(
        Request $request,
        Connection $connection,
        HttpClientInterface $httpClient,
    ): Response {
        $userId = (int) $request->request->get('user_id', 0);
        $sourceLogId = (int) $request->request->get('source_log_id', 0);
        $days = max(1, min(365, (int) $request->request->get('duration_days', 7)));
        $reason = trim((string) $request->request->get('reason', 'Contenu toxique ou non conforme'));

        if ($userId <= 0) {
            $this->addFlash('error', 'Utilisateur invalide pour le bannissement.');
            return $this->redirectToRoute('app_admin_moderation');
        }

        if (!$this->isCsrfTokenValid('admin_moderation_ban_'.$userId, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Jeton CSRF invalide pour le bannissement.');
            return $this->redirectToRoute('app_admin_moderation');
        }

        $this->ensureUserBanTable($connection);

        $targetUser = $connection->fetchAssociative(
            'SELECT id, prenom, nom, email FROM user WHERE id = ?',
            [$userId]
        );
        if (!$targetUser) {
            $this->addFlash('error', 'Utilisateur introuvable.');
            return $this->redirectToRoute('app_admin_moderation');
        }

        $admin = $this->getUser();
        $adminId = $admin instanceof User ? (int) $admin->getId() : null;

        try {
            $connection->beginTransaction();
            $connection->executeStatement(
                "UPDATE user_ban SET status = 'replaced' WHERE user_id = ? AND status = 'active' AND end_at > NOW()",
                [$userId]
            );
            $connection->executeStatement(
                'INSERT INTO user_ban (user_id, admin_id, source_log_id, reason, duration_days, start_at, end_at, status, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? DAY), ?, NOW())',
                [$userId, $adminId, $sourceLogId > 0 ? $sourceLogId : null, $reason, $days, $days, 'active']
            );
            $connection->commit();
        } catch (Exception $e) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }

            $this->addFlash('error', 'Erreur bannissement: '.$e->getMessage());
            return $this->redirectToRoute('app_admin_moderation');
        }

        $emailError = $this->sendBanAlertViaMailingApi($request, $httpClient, [
            'to' => (string) ($targetUser['email'] ?? ''),
            'user_id' => (int) ($targetUser['id'] ?? $userId),
            'prenom' => (string) ($targetUser['prenom'] ?? ''),
            'nom' => (string) ($targetUser['nom'] ?? ''),
            'reason' => $reason,
            'duration_days' => $days,
            'source_log_id' => $sourceLogId > 0 ? $sourceLogId : null,
            'banned_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ]);

        if ($emailError !== null) {
            $this->addFlash('warning', 'Utilisateur banni, mais envoi mail échoué: '.$emailError);
        } else {
            $this->addFlash('success', 'Utilisateur banni '.$days.' jour(s) et email d\'alerte envoyé.');
        }

        return $this->redirectToRoute('app_admin_moderation');
    }

    #[Route('/moderation/unban-user', name: 'app_admin_moderation_unban_user', methods: ['POST'])]
    public function unbanUserFromModeration(Request $request, Connection $connection): Response
    {
        $userId = (int) $request->request->get('user_id', 0);

        if ($userId <= 0) {
            $this->addFlash('error', 'Utilisateur invalide pour la levée du ban.');
            return $this->redirectToRoute('app_admin_moderation');
        }

        if (!$this->isCsrfTokenValid('admin_moderation_unban_'.$userId, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Jeton CSRF invalide pour la levée du ban.');
            return $this->redirectToRoute('app_admin_moderation');
        }

        $this->ensureUserBanTable($connection);

        try {
            $affected = $connection->executeStatement(
                "UPDATE user_ban
                 SET status = 'revoked', end_at = NOW()
                 WHERE user_id = ? AND status = 'active' AND end_at > NOW()",
                [$userId]
            );

            if ($affected > 0) {
                $this->addFlash('success', 'Ban levé avec succès pour cet utilisateur.');
            } else {
                $this->addFlash('warning', 'Aucun ban actif trouvé pour cet utilisateur.');
            }
        } catch (Exception $e) {
            $this->addFlash('error', 'Erreur lors de la levée du ban: '.$e->getMessage());
        }

        return $this->redirectToRoute('app_admin_moderation');
    }

    #[Route('/evenements-legacy', name: 'app_admin_evenements_legacy')]
    public function evenements(Connection $connection): Response
    {
        return $this->render('admin/evenement/index.html.twig', [
            'active' => 'evenements',
            'events' => $this->fetchAll($connection, 'SELECT id, titre, date_debut, date_fin, type, prix, statut FROM evenement ORDER BY date_debut ASC'),
        ]);
    }

    private function isValidHumanName(string $value): bool
    {
        return (bool) preg_match('/^[\\p{L}\\s\\-\']{2,50}$/u', $value);
    }

    private function isValidPhoneNumber(string $value): bool
    {
        return (bool) preg_match('/^\\+?[0-9\\s\\-]{8,16}$/', $value);
    }

    private function isStrongPassword(string $value): bool
    {
        return (bool) preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\\d)(?=.*[^\\w\\s]).{8,}$/', $value);
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

    private function ensureModerationLogTable(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS moderation_attempt_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                lieu_id INT NOT NULL,
                action VARCHAR(16) NOT NULL,
                severity VARCHAR(16) NOT NULL,
                score INT NOT NULL,
                terms_json TEXT DEFAULT NULL,
                comment_preview VARCHAR(255) DEFAULT NULL,
                comment_length INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                INDEX idx_modlog_created_at (created_at),
                INDEX idx_modlog_user (user_id),
                INDEX idx_modlog_lieu (lieu_id),
                INDEX idx_modlog_severity (severity)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private function ensureUserBanTable(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS user_ban (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                admin_id INT DEFAULT NULL,
                source_log_id INT DEFAULT NULL,
                reason VARCHAR(255) DEFAULT NULL,
                duration_days INT NOT NULL DEFAULT 1,
                start_at DATETIME NOT NULL,
                end_at DATETIME NOT NULL,
                status VARCHAR(16) NOT NULL DEFAULT "active",
                created_at DATETIME NOT NULL,
                INDEX idx_user_ban_user (user_id),
                INDEX idx_user_ban_status (status),
                INDEX idx_user_ban_end (end_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $connection->executeStatement(
            "UPDATE user_ban SET status = 'expired' WHERE status = 'active' AND end_at <= NOW()"
        );
    }

    private function sendBanAlertViaMailingApi(Request $request, HttpClientInterface $httpClient, array $payload): ?string
    {
        try {
            $apiKey = (string) ($_ENV['INTERNAL_MAILING_API_KEY'] ?? $_SERVER['INTERNAL_MAILING_API_KEY'] ?? 'dev-mailing-key');
            $baseUrl = (string) ($_ENV['MAILING_API_BASE_URL'] ?? $_SERVER['MAILING_API_BASE_URL'] ?? '');
            $endpoint = rtrim($baseUrl !== '' ? $baseUrl : $request->getSchemeAndHttpHost(), '/').'/api/mailing/ban-alert';

            $response = $httpClient->request('POST', $endpoint, [
                'headers' => [
                    'Accept' => 'application/json',
                    'X-Internal-Api-Key' => $apiKey,
                ],
                'json' => $payload,
                'timeout' => 8,
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode < 200 || $statusCode >= 300) {
                $body = $response->getContent(false);
                return 'HTTP '.$statusCode.' '.$body;
            }

            return null;
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }

    private function normalizeOffrePayload(array $payload): array
    {
        $titre = preg_replace('/\s+/', ' ', trim((string) ($payload['titre'] ?? '')));
        $type = preg_replace('/\s+/', ' ', trim((string) ($payload['type'] ?? '')));
        $description = trim((string) ($payload['description'] ?? ''));

        return [
            'titre' => $titre,
            'type' => $type,
            'pourcentage_raw' => trim((string) ($payload['pourcentage'] ?? '')),
            'pourcentage' => isset($payload['pourcentage']) ? (float) $payload['pourcentage'] : -1,
            'date_debut' => trim((string) ($payload['date_debut'] ?? '')),
            'date_fin' => trim((string) ($payload['date_fin'] ?? '')),
            'statut' => $this->normalizeStatus(trim((string) ($payload['statut'] ?? ''))),
            'description' => $description,
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
        } elseif (mb_strlen($payload['titre']) < 3 || mb_strlen($payload['titre']) > 120) {
            $errors[] = 'Le titre doit contenir entre 3 et 120 caractères.';
        }

        if ($payload['type'] === '') {
            $errors[] = 'Le type est obligatoire.';
        } elseif (mb_strlen($payload['type']) < 2 || mb_strlen($payload['type']) > 60) {
            $errors[] = 'Le type doit contenir entre 2 et 60 caractères.';
        }

        if ($payload['description'] !== '' && mb_strlen($payload['description']) > 1000) {
            $errors[] = 'La description ne doit pas dépasser 1000 caractères.';
        }

        if ($payload['statut'] === '') {
            $errors[] = 'Le statut est obligatoire.';
        } elseif (!in_array($payload['statut'], ['ACTIVE', 'EXPIREE', 'DESACTIVEE'], true)) {
            $errors[] = 'Le statut est invalide.';
        }

        if ($payload['pourcentage_raw'] === '' || !is_numeric($payload['pourcentage_raw'])) {
            $errors[] = 'Le pourcentage est obligatoire et doit être un nombre.';
        } elseif ($payload['pourcentage'] < 0 || $payload['pourcentage'] > 100) {
            $errors[] = 'Le pourcentage doit être entre 0 et 100.';
        } elseif (preg_match('/^-?\d+(\.\d{1,2})?$/', $payload['pourcentage_raw']) !== 1) {
            $errors[] = 'Le pourcentage accepte au maximum 2 chiffres après la virgule.';
        }

        if ($requireLieu && $payload['lieu_id'] <= 0) {
            $errors[] = 'Le lieu est obligatoire.';
        }

        $dateDebut = $this->parseStrictYmdDate($payload['date_debut']);
        $dateFin = $this->parseStrictYmdDate($payload['date_fin']);

        if (!$dateDebut || !$dateFin) {
            $errors[] = 'Les dates début/fin sont obligatoires et doivent être au format YYYY-MM-DD.';
        } elseif ($dateDebut > $dateFin) {
            $errors[] = 'La date de début doit être inférieure ou égale à la date de fin.';
        }

        return $errors;
    }

    private function parseStrictYmdDate(string $date): ?\DateTimeImmutable
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }

        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$parsed) {
            return null;
        }

        return $parsed->format('Y-m-d') === $date ? $parsed : null;
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

    private function normalizePromoStatus(string $status): string
    {
        return match (strtolower(trim($status))) {
            'actif', 'active' => 'ACTIF',
            'expire', 'expiré', 'expiree', 'expired' => 'EXPIRE',
            'desactive', 'désactivé', 'desactivee', 'inactive', 'inactif', 'disabled' => 'DESACTIVE',
            'utilise', 'utilisé', 'used' => 'UTILISE',
            'bloque_abus', 'bloqué_abus', 'blocked_abuse' => 'BLOQUE_ABUS',
            default => strtoupper(trim($status)),
        };
    }

    private function normalizeSortDirection(string $direction): string
    {
        return strtolower(trim($direction)) === 'desc' ? 'desc' : 'asc';
    }

    private function offerSortSql(string $sort, string $direction): string
    {
        $column = match ($sort) {
            'id' => 'o.id',
            'titre' => 'o.titre',
            'type' => 'o.type',
            'pourcentage' => 'o.pourcentage',
            'date_debut' => 'o.date_debut',
            'date_fin' => 'o.date_fin',
            'statut' => 'o.statut',
            'lieu' => 'l.nom',
            default => 'o.date_fin',
        };

        return $column.' '.strtoupper($direction);
    }

    private function promoSortSql(string $sort, string $direction): string
    {
        $column = match ($sort) {
            'id' => 'cp.id',
            'offre' => 'o.titre',
            'user' => 'u.prenom',
            'date_generation' => 'cp.date_generation',
            'date_expiration' => 'cp.date_expiration',
            'statut' => 'cp.statut',
            default => 'cp.id',
        };

        return $column.' '.strtoupper($direction);
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

    private function createInAppNotification(
        Connection $connection,
        int $receiverId,
        ?int $senderId,
        string $type,
        string $title,
        string $body,
        string $entityType,
        int $entityId,
        array $metadata = [],
        int $dedupHours = 24
    ): void {
        $alreadyExists = (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM notifications
             WHERE receiver_id = ?
               AND type = ?
               AND entity_type = ?
               AND entity_id = ?
               AND created_at >= DATE_SUB(NOW(), INTERVAL '.$dedupHours.' HOUR)',
            [$receiverId, $type, $entityType, $entityId]
        );

        if ($alreadyExists > 0) {
            return;
        }

        $connection->insert('notifications', [
            'receiver_id' => $receiverId,
            'sender_id' => $senderId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'read_at' => null,
            'metadata_json' => $metadata !== [] ? json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        ]);
    }
}