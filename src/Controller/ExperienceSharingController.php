<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\ExperienceSharingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ExperienceSharingController extends AbstractController
{
    #[Route('/sorties/{id}/experience/media', name: 'app_sorties_experience_upload', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function upload(int $id, Request $request, ExperienceSharingService $experienceSharingService): RedirectResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            $this->addFlash('error', 'Vous devez etre connecte pour partager un souvenir.');

            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('experience_upload_'.$id, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Jeton invalide. Veuillez reessayer.');

            return $this->redirectToRoute('app_sorties_show', ['id' => $id]);
        }

        $files = $request->files->get('experience_media');
        if (!$files) {
            $this->addFlash('warning', 'Ajoutez au moins une photo ou une video avant de publier.');
            return $this->redirectWithAnchor($id);
        }

        $success = 0;
        $errors = [];
        foreach ((array)$files as $file) {
            if (!$file) continue;
            try {
                $experienceSharingService->handleUpload($id, $user, $file);
                $success++;
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }
        if ($success > 0) {
            $this->addFlash('success', $success . ' souvenir(s) publie(s). Le recap a ete regenere automatiquement.');
        }
        foreach ($errors as $err) {
            $this->addFlash('error', $err);
        }
        return $this->redirectWithAnchor($id);
    }

    #[Route('/sorties/{id}/experience/media/{mediaId}/delete', name: 'app_sorties_experience_media_delete', requirements: ['id' => '\d+', 'mediaId' => '\d+'], methods: ['POST'])]
    public function deleteMedia(int $id, int $mediaId, Request $request, ExperienceSharingService $experienceSharingService): RedirectResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            $this->addFlash('error', 'Vous devez etre connecte.');
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('experience_delete_'.$mediaId, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Jeton invalide. Veuillez reessayer.');
            return $this->redirectToRoute('app_sorties_show', ['id' => $id]);
        }

        try {
            $experienceSharingService->deleteMedia($id, $mediaId, $user);
            $this->addFlash('success', 'Media supprime. Le recap a ete regenere automatiquement.');
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectWithAnchor($id);
    }

    private function redirectWithAnchor(int $id): RedirectResponse
    {
        return new RedirectResponse($this->generateUrl('app_sorties_show', ['id' => $id]).'#experience-sharing');
    }
}
