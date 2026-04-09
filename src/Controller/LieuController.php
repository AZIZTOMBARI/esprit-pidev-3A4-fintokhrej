<?php

namespace App\Controller;

use App\Entity\Lieu;
use App\Form\LieuType;
use App\Repository\LieuRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/lieux')]
class LieuController extends AbstractController
{
    #[Route('', name: 'app_admin_lieux', methods: ['GET'])]
    public function index(LieuRepository $lieuRepository): Response
    {
        return $this->render('admin/lieu/index.html.twig', [
            'active' => 'lieux',
            'lieux' => $lieuRepository->findAllOrdered(),
        ]);
    }

    #[Route('/new', name: 'app_admin_lieu_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $lieu = new Lieu();
        $form = $this->createForm(LieuType::class, $lieu);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($lieu);
            $entityManager->flush();

            $this->addFlash('success', 'Lieu créé avec succès.');

            return $this->redirectToRoute('app_admin_lieu_show', ['id' => $lieu->getId()]);
        }

        return $this->render('admin/lieu/new.html.twig', [
            'active' => 'lieux',
            'lieu' => $lieu,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_admin_lieu_show', methods: ['GET'], requirements: ['id' => '\\d+'])]
    public function show(Lieu $lieu): Response
    {
        return $this->render('admin/lieu/show.html.twig', [
            'active' => 'lieux',
            'lieu' => $lieu,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_lieu_edit', methods: ['GET', 'POST'], requirements: ['id' => '\\d+'])]
    public function edit(Request $request, Lieu $lieu, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(LieuType::class, $lieu);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Lieu modifié avec succès.');

            return $this->redirectToRoute('app_admin_lieu_show', ['id' => $lieu->getId()]);
        }

        return $this->render('admin/lieu/edit.html.twig', [
            'active' => 'lieux',
            'lieu' => $lieu,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'app_admin_lieu_delete', methods: ['GET', 'POST'], requirements: ['id' => '\\d+'])]
    public function delete(Request $request, Lieu $lieu, EntityManagerInterface $entityManager): Response
    {
        if ($request->isMethod('POST')) {
            if ($this->isCsrfTokenValid('delete_lieu_'.$lieu->getId(), (string) $request->request->get('_token', ''))) {
                $entityManager->remove($lieu);
                $entityManager->flush();

                $this->addFlash('success', 'Lieu supprimé avec succès.');

                return $this->redirectToRoute('app_admin_lieux');
            }

            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_admin_lieu_show', ['id' => $lieu->getId()]);
        }

        return $this->render('admin/lieu/delete.html.twig', [
            'active' => 'lieux',
            'lieu' => $lieu,
        ]);
    }
}
