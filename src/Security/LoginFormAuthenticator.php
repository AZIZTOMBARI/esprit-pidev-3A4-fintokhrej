<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class LoginFormAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly UserRepository $userRepository,
        private readonly Connection $connection,
    )
    {
    }

    public function authenticate(Request $request): Passport
    {
        $email = strtolower(trim($request->request->getString('email')));

        return new Passport(
            new UserBadge($email, function (string $userIdentifier): User {
                $user = $this->userRepository->findOneBy(['email' => $userIdentifier]);
                if (!$user instanceof User) {
                    throw new CustomUserMessageAuthenticationException('Identifiants invalides.');
                }

                $activeBan = $this->findActiveBan((int) $user->getId());
                if ($activeBan !== null) {
                    $remainingDays = max(1, (int) ceil(((int) $activeBan['remaining_seconds']) / 86400));
                    $reason = trim((string) ($activeBan['reason'] ?? 'Violation des règles de la communauté'));

                    throw new CustomUserMessageAuthenticationException(
                        'Vous êtes banni pendant '.$remainingDays.' jour(s)'.($reason !== '' ? ' car: '.$reason : '.').
                        ' Contactez le support si nécessaire.'
                    );
                }

                return $user;
            }),
            new PasswordCredentials($request->request->getString('password')),
            [
                new CsrfTokenBadge('authenticate', $request->request->getString('_csrf_token')),
                new RememberMeBadge(),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        $user = $token->getUser();
        if ($user instanceof User && in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return new RedirectResponse($this->urlGenerator->generate('app_admin_dashboard'));
        }

        return new RedirectResponse($this->urlGenerator->generate('app_home'));
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate('app_login');
    }

    /**
     * @return array{reason:?string, remaining_seconds:int}|null
     */
    private function findActiveBan(int $userId): ?array
    {
        try {
            $tableExists = (int) $this->connection->fetchOne("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'user_ban'");
            if ($tableExists === 0) {
                return null;
            }

            $row = $this->connection->fetchAssociative(
                "SELECT reason, GREATEST(TIMESTAMPDIFF(SECOND, NOW(), end_at), 0) AS remaining_seconds
                 FROM user_ban
                 WHERE user_id = ? AND status = 'active' AND end_at > NOW()
                 ORDER BY end_at DESC
                 LIMIT 1",
                [$userId]
            );

            if (!$row) {
                return null;
            }

            return [
                'reason' => isset($row['reason']) ? (string) $row['reason'] : null,
                'remaining_seconds' => (int) ($row['remaining_seconds'] ?? 0),
            ];
        } catch (\Throwable) {
            return null;
        }
    }
}
