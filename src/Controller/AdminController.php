<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
    public function offres(Connection $connection): Response
    {
        return $this->render('admin/offre/index.html.twig', [
            'active' => 'offres',
            'offres' => $this->fetchAll($connection, 'SELECT id, titre, type, pourcentage, date_debut, date_fin, statut FROM offre ORDER BY date_fin ASC'),
        ]);
    }

    #[Route('/evenements', name: 'app_admin_evenements')]
    public function evenements(Connection $connection): Response
    {
        return $this->render('admin/evenement/index.html.twig', [
            'active' => 'evenements',
            'evenements' => $this->fetchAll($connection, 'SELECT id, titre, description, date_debut as dateDebut, date_fin as dateFin, type, prix, statut, capacite_max as capaciteMax, image_url as imageUrl FROM evenement ORDER BY date_debut ASC'),
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
}