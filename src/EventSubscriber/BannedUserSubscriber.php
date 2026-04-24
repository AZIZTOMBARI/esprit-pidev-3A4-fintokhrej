<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Doctrine\DBAL\Connection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class BannedUserSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly Connection $connection,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 8],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if ($path === '/login' || $path === '/logout') {
            return;
        }

        $token = $this->tokenStorage->getToken();
        if ($token === null) {
            return;
        }

        $user = $token->getUser();
        if (!$user instanceof User || $user->getId() === null) {
            return;
        }

        $activeBanUntil = $this->connection->fetchOne(
            'SELECT banned_until FROM user WHERE id = ? LIMIT 1',
            [$user->getId()]
        );

        if ($activeBanUntil === false || $activeBanUntil === null || (string) $activeBanUntil === '') {
            return;
        }

        $banUntil = new \DateTimeImmutable((string) $activeBanUntil);
        if ($banUntil < new \DateTimeImmutable()) {
            return;
        }

        $this->tokenStorage->setToken(null);

        if ($request->hasSession()) {
            $session = $request->getSession();
            $session->invalidate();
        }

        $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_login')));
    }
}
