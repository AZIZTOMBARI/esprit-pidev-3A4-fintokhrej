<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
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

    #[Route('/lieux/{id}', name: 'app_lieu_show', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function lieuShow(int $id, Connection $connection): Response
    {
        $lieu = $this->fetchOne(
            $connection,
            "
                SELECT id, id_offre, nom, ville, adresse, telephone, site_web, instagram, description,
                       budget_min, budget_max, categorie, type, latitude, longitude, image_url
                FROM lieu
                WHERE id = ?
                LIMIT 1
            ",
            [$id]
        );

        if ($lieu === null) {
            throw $this->createNotFoundException('Lieu introuvable.');
        }

        $images = $this->fetchAll(
            $connection,
            "
                SELECT image_url, ordre
                FROM lieu_image
                WHERE lieu_id = ?
                ORDER BY ordre ASC, id ASC
            ",
            [$id]
        );

        $galleryImages = [];
        if (!empty($lieu['image_url'])) {
            $galleryImages[] = (string) $lieu['image_url'];
        }
        foreach ($images as $image) {
            $value = trim((string) ($image['image_url'] ?? ''));
            if ($value !== '') {
                $galleryImages[] = $value;
            }
        }
        $galleryImages = array_values(array_unique($galleryImages));

        $horaires = $this->fetchAll(
            $connection,
            "
                SELECT jour, ouvert, heure_ouverture_1, heure_fermeture_1, heure_ouverture_2, heure_fermeture_2
                FROM lieu_horaire
                WHERE lieu_id = ?
                ORDER BY FIELD(jour, 'LUNDI','MARDI','MERCREDI','JEUDI','VENDREDI','SAMEDI','DIMANCHE'), id ASC
            ",
            [$id]
        );

        $offres = $this->fetchAll(
            $connection,
            "
                SELECT id, titre, type, pourcentage, date_fin, statut
                FROM offre
                WHERE lieu_id = ?
                ORDER BY date_fin ASC
            ",
            [$id]
        );

        if (empty($offres) && !empty($lieu['id_offre'] ?? null)) {
            $offres = $this->fetchAll(
                $connection,
                "
                    SELECT id, titre, type, pourcentage, date_fin, statut
                    FROM offre
                    WHERE id = ?
                    ORDER BY date_fin ASC
                ",
                [(int) $lieu['id_offre']]
            );
        }

        $evaluations = $this->fetchAll(
            $connection,
            "
                SELECT e.id, e.user_id, e.note, e.commentaire, e.date_evaluation, e.updated_at,
                       u.prenom, u.nom
                FROM evaluation_lieu e
                LEFT JOIN user u ON u.id = e.user_id
                WHERE e.lieu_id = ?
                ORDER BY COALESCE(e.updated_at, e.date_evaluation) DESC, e.id DESC
            ",
            [$id]
        );

        $stats = $this->fetchOne(
            $connection,
            "
                SELECT COUNT(*) AS total, ROUND(AVG(note), 2) AS moyenne
                FROM evaluation_lieu
                WHERE lieu_id = ?
            ",
            [$id]
        ) ?? ['total' => 0, 'moyenne' => null];

        $currentUserEvaluation = null;
        $currentUser = $this->getUser();
        if ($currentUser instanceof User) {
            $currentUserEvaluation = $this->fetchOne(
                $connection,
                "
                    SELECT id, note, commentaire, date_evaluation, updated_at
                    FROM evaluation_lieu
                    WHERE lieu_id = ? AND user_id = ?
                    LIMIT 1
                ",
                [$id, $currentUser->getId()]
            );
        }

        return $this->render('front/lieu/show.html.twig', [
            'active' => 'lieux',
            'lieu' => $lieu,
            'images' => $images,
            'galleryImages' => $galleryImages,
            'horaires' => $horaires,
            'offres' => $offres,
            'evaluations' => $evaluations,
            'evaluationStats' => $stats,
            'currentUserEvaluation' => $currentUserEvaluation,
        ]);
    }

    #[Route('/lieux/{id}/evaluations', name: 'app_lieu_evaluation_create', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function lieuEvaluationCreate(int $id, Request $request, Connection $connection): RedirectResponse
    {
        $user = $this->requireFrontUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('front_eval_create_'.$id, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_lieu_show', ['id' => $id]);
        }

        [$note, $commentaire, $errors] = $this->validateEvaluationPayload($request);
        if ($errors !== []) {
            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }
            return $this->redirectToRoute('app_lieu_show', ['id' => $id]);
        }

        try {
            $exists = (int) $connection->fetchOne(
                'SELECT COUNT(*) FROM evaluation_lieu WHERE lieu_id = ? AND user_id = ?',
                [$id, $user->getId()]
            );

            if ($exists > 0) {
                $this->addFlash('error', 'Vous avez déjà publié un avis pour ce lieu. Modifiez-le au lieu d\'en créer un nouveau.');
                return $this->redirectToRoute('app_lieu_show', ['id' => $id]);
            }

            $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
            $connection->insert('evaluation_lieu', [
                'lieu_id' => $id,
                'user_id' => $user->getId(),
                'note' => $note,
                'commentaire' => $commentaire,
                'date_evaluation' => $now,
                'updated_at' => $now,
            ]);

            $this->addFlash('success', 'Votre avis a été ajouté.');
        } catch (Exception $e) {
            $this->addFlash('error', 'Erreur lors de l\'ajout de l\'avis : '.$e->getMessage());
        }

        return $this->redirectToRoute('app_lieu_show', ['id' => $id]);
    }

    #[Route('/lieux/{id}/evaluations/{evaluationId}/update', name: 'app_lieu_evaluation_update', requirements: ['id' => '\\d+', 'evaluationId' => '\\d+'], methods: ['POST'])]
    public function lieuEvaluationUpdate(int $id, int $evaluationId, Request $request, Connection $connection): RedirectResponse
    {
        $user = $this->requireFrontUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('front_eval_update_'.$evaluationId, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_lieu_show', ['id' => $id]);
        }

        [$note, $commentaire, $errors] = $this->validateEvaluationPayload($request);
        if ($errors !== []) {
            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }
            return $this->redirectToRoute('app_lieu_show', ['id' => $id]);
        }

        try {
            $owner = $this->fetchOne(
                $connection,
                'SELECT id FROM evaluation_lieu WHERE id = ? AND lieu_id = ? AND user_id = ? LIMIT 1',
                [$evaluationId, $id, $user->getId()]
            );

            if ($owner === null) {
                $this->addFlash('error', 'Modification non autorisée pour cet avis.');
                return $this->redirectToRoute('app_lieu_show', ['id' => $id]);
            }

            $connection->update('evaluation_lieu', [
                'note' => $note,
                'commentaire' => $commentaire,
                'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ], ['id' => $evaluationId]);

            $this->addFlash('success', 'Votre avis a été modifié.');
        } catch (Exception $e) {
            $this->addFlash('error', 'Erreur lors de la modification : '.$e->getMessage());
        }

        return $this->redirectToRoute('app_lieu_show', ['id' => $id]);
    }

    #[Route('/lieux/{id}/evaluations/{evaluationId}/delete', name: 'app_lieu_evaluation_delete', requirements: ['id' => '\\d+', 'evaluationId' => '\\d+'], methods: ['POST'])]
    public function lieuEvaluationDelete(int $id, int $evaluationId, Request $request, Connection $connection): RedirectResponse
    {
        $user = $this->requireFrontUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('front_eval_delete_'.$evaluationId, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_lieu_show', ['id' => $id]);
        }

        try {
            $deleted = $connection->delete('evaluation_lieu', [
                'id' => $evaluationId,
                'lieu_id' => $id,
                'user_id' => $user->getId(),
            ]);

            if ($deleted === 0) {
                $this->addFlash('error', 'Suppression non autorisée pour cet avis.');
            } else {
                $this->addFlash('success', 'Votre avis a été supprimé.');
            }
        } catch (Exception $e) {
            $this->addFlash('error', 'Erreur lors de la suppression : '.$e->getMessage());
        }

        return $this->redirectToRoute('app_lieu_show', ['id' => $id]);
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

    private function fetchAll(Connection $connection, string $sql, array $params = []): array
    {
        try {
            return $connection->fetchAllAssociative($sql, $params);
        } catch (Exception) {
            return [];
        }
    }

    private function fetchOne(Connection $connection, string $sql, array $params = []): ?array
    {
        try {
            return $connection->fetchAssociative($sql, $params) ?: null;
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

    private function requireFrontUser(): ?object
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        return $this->getUser();
    }

    /**
     * @return array{0:int,1:?string,2:array<int,string>}
     */
    private function validateEvaluationPayload(Request $request): array
    {
        $errors = [];
        $rawNote = trim((string) $request->request->get('note', ''));
        $rawCommentaire = trim((string) $request->request->get('commentaire', ''));

        if ($rawNote === '' || !ctype_digit($rawNote)) {
            $errors[] = 'La note est obligatoire et doit être un nombre entier.';
            $note = 0;
        } else {
            $note = (int) $rawNote;
            if ($note < 1 || $note > 5) {
                $errors[] = 'La note doit être comprise entre 1 et 5.';
            }
        }

        if ($rawCommentaire !== '' && mb_strlen($rawCommentaire) < 3) {
            $errors[] = 'Le commentaire doit contenir au moins 3 caractères.';
        }

        if (mb_strlen($rawCommentaire) > 1000) {
            $errors[] = 'Le commentaire ne peut pas dépasser 1000 caractères.';
        }

        $commentaire = $rawCommentaire === '' ? null : $rawCommentaire;

        return [$note, $commentaire, $errors];
    }
}