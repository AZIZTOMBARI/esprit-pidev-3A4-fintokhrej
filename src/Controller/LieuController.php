<?php

namespace App\Controller;

use App\Entity\Lieu;
use App\Enum\LieuCategorie;
use App\Enum\LieuType as LieuTypeEnum;
use App\Form\LieuType;
use App\Repository\LieuRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[IsGranted('ROLE_ADMIN')]
class LieuController extends AbstractController
{
    private const PER_PAGE = 12;

    #[Route('/admin/lieu', name: 'app_admin_lieu_index', methods: ['GET'])]
    #[Route('/admin/lieux', name: 'app_admin_lieux', methods: ['GET'])]
    public function index(Request $request, LieuRepository $lieuRepository): Response
    {
        $filters = $this->extractFilters($request);
        $page = max(1, (int) $request->query->get('page', 1));
        $pagination = $lieuRepository->paginateFiltered($filters, $page, self::PER_PAGE);
        $totalItems = count($pagination);
        $pageCount = max(1, (int) ceil($totalItems / self::PER_PAGE));

        return $this->render('lieu/index.html.twig', [
            'active' => 'lieux',
            'lieux' => iterator_to_array($pagination, false),
            'searchQuery' => $filters['q'],
            'currentCategorie' => $filters['categorie']?->value,
            'currentType' => $filters['type']?->value,
            'currentSort' => $filters['sort'],
            'currentDirection' => $filters['dir'],
            'page' => $page,
            'pageCount' => $pageCount,
            'totalItems' => $totalItems,
            'perPage' => self::PER_PAGE,
            'categorieCases' => LieuCategorie::cases(),
            'typeCases' => LieuTypeEnum::cases(),
        ]);
    }

    #[Route('/admin/lieu/new', name: 'app_admin_lieu_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $lieu = new Lieu();
        $form = $this->createForm(LieuType::class, $lieu);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                if ($error = $this->applyBudgetValidation($lieu)) {
                    $this->addFlash('error', $error);
                } elseif (!$this->handleLieuImageUpload($form, $lieu, $slugger)) {
                    return $this->render('lieu/new.html.twig', [
                        'active' => 'lieux',
                        'lieu' => $lieu,
                        'form' => $form->createView(),
                    ]);
                } else {
                    $entityManager->persist($lieu);
                    $entityManager->flush();

                    $this->addFlash('success', 'Lieu créé avec succès.');

                    return $this->redirectToRoute('app_admin_lieu_show', ['id' => $lieu->getId()]);
                }
            } else {
                $this->addFlash('error', 'Veuillez corriger les erreurs du formulaire.');
            }
        }

        return $this->render('lieu/new.html.twig', [
            'active' => 'lieux',
            'lieu' => $lieu,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/admin/lieu/{id}', name: 'app_admin_lieu_show', methods: ['GET'], requirements: ['id' => '\\d+'])]
    public function show(int $id, LieuRepository $lieuRepository): Response
    {
        $lieu = $lieuRepository->findDetailed($id);

        if ($lieu === null) {
            throw $this->createNotFoundException('Lieu introuvable.');
        }

        return $this->render('lieu/show.html.twig', [
            'active' => 'lieux',
            'lieu' => $lieu,
        ]);
    }

    #[Route('/admin/lieu/{id}/edit', name: 'app_admin_lieu_edit', methods: ['GET', 'POST'], requirements: ['id' => '\\d+'])]
    public function edit(int $id, Request $request, LieuRepository $lieuRepository, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $lieu = $lieuRepository->findDetailed($id);

        if ($lieu === null) {
            throw $this->createNotFoundException('Lieu introuvable.');
        }

        $existingImageUrl = $lieu->getImageUrl();
        $form = $this->createForm(LieuType::class, $lieu);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                if ($error = $this->applyBudgetValidation($lieu)) {
                    $this->addFlash('error', $error);
                } elseif (!$this->handleLieuImageUpload($form, $lieu, $slugger, $existingImageUrl)) {
                    return $this->render('lieu/edit.html.twig', [
                        'active' => 'lieux',
                        'lieu' => $lieu,
                        'form' => $form->createView(),
                    ]);
                } else {
                    $entityManager->flush();

                    $this->addFlash('success', 'Lieu modifié avec succès.');

                    return $this->redirectToRoute('app_admin_lieu_show', ['id' => $lieu->getId()]);
                }
            } else {
                $this->addFlash('error', 'Veuillez corriger les erreurs du formulaire.');
            }
        }

        return $this->render('lieu/edit.html.twig', [
            'active' => 'lieux',
            'lieu' => $lieu,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/admin/lieu/{id}/delete', name: 'app_admin_lieu_delete', methods: ['POST'], requirements: ['id' => '\\d+'])]
    public function delete(int $id, Request $request, LieuRepository $lieuRepository, EntityManagerInterface $entityManager): Response
    {
        $lieu = $lieuRepository->find($id);

        if ($lieu === null) {
            throw $this->createNotFoundException('Lieu introuvable.');
        }

        if (!$this->isCsrfTokenValid('delete_lieu_'.$lieu->getId(), (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Jeton CSRF invalide pour la suppression.');

            return $this->redirectToRoute('app_admin_lieu_show', ['id' => $lieu->getId()]);
        }

        $blockers = $this->collectDeletionBlockers($lieu);
        if ($blockers !== []) {
            $this->addFlash('error', 'Suppression impossible: '.implode(', ', $blockers).'.');

            return $this->redirectToRoute('app_admin_lieu_show', ['id' => $lieu->getId()]);
        }

        $entityManager->remove($lieu);
        $entityManager->flush();

        $this->addFlash('success', 'Lieu supprimé avec succès.');

        return $this->redirectToRoute('app_admin_lieu_index');
    }

    private function extractFilters(Request $request): array
    {
        $categorie = LieuCategorie::tryFrom((string) $request->query->get('categorie', ''));
        $type = LieuTypeEnum::tryFrom((string) $request->query->get('type', ''));

        return [
            'q' => trim((string) $request->query->get('q', '')),
            'categorie' => $categorie,
            'type' => $type,
            'sort' => (string) $request->query->get('sort', 'id'),
            'dir' => strtoupper((string) $request->query->get('dir', 'DESC')) === 'ASC' ? 'ASC' : 'DESC',
        ];
    }

    private function applyBudgetValidation(Lieu $lieu): ?string
    {
        if ($lieu->getBudgetMin() !== null && $lieu->getBudgetMax() !== null && $lieu->getBudgetMin() > $lieu->getBudgetMax()) {
            return 'Le budget minimum doit être inférieur ou égal au budget maximum.';
        }

        return null;
    }

    private function handleLieuImageUpload(FormInterface $form, Lieu $lieu, SluggerInterface $slugger, ?string $existingImageUrl = null): bool
    {
        $imageFile = $form->get('imageFile')->getData();

        if ($imageFile !== null) {
            $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $slugger->slug($originalFilename);
            $newFilename = $safeFilename.'-'.uniqid('', true).'.'.$imageFile->guessExtension();

            $uploadDir = $this->getParameter('kernel.project_dir').'/public/uploads/lieux';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0775, true);
            }

            try {
                $imageFile->move($uploadDir, $newFilename);
                $lieu->setImageUrl('uploads/lieux/'.$newFilename);
            } catch (FileException) {
                $form->get('imageFile')->addError(new FormError('Erreur pendant l\'upload de l\'image.'));

                return false;
            }
        } elseif ($existingImageUrl !== null && trim((string) $lieu->getImageUrl()) === '') {
            $lieu->setImageUrl($existingImageUrl);
        }

        return true;
    }

    /**
     * @return string[]
     */
    private function collectDeletionBlockers(Lieu $lieu): array
    {
        $blockers = [];

        if ($lieu->getEvaluationLieu() !== null) {
            $blockers[] = 'une évaluation liée';
        }

        if ($lieu->getLieuHoraire() !== null) {
            $blockers[] = 'des horaires liés';
        }

        if (!$lieu->getLieuImages()->isEmpty()) {
            $blockers[] = 'des images associées';
        }

        if (!$lieu->getOffres()->isEmpty()) {
            $blockers[] = 'des offres associées';
        }

        if (!$lieu->getReservationOffres()->isEmpty()) {
            $blockers[] = 'des réservations associées';
        }

        if (!$lieu->getEvenements()->isEmpty()) {
            $blockers[] = 'des événements liés';
        }

        if (!$lieu->getUsers()->isEmpty()) {
            $blockers[] = 'des favoris utilisateurs';
        }

        return $blockers;
    }
}