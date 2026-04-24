<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\User;
use App\Service\GamificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/gamification')]
final class GamificationController extends AbstractController
{
    #[Route('', name: 'app_gamification_dashboard', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function index(GamificationService $gamificationService): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('front/gamification/index.html.twig', [
            'active' => 'gamification',
            'gamification' => $gamificationService->getDashboardData($user->getId()),
        ]);
    }
}
