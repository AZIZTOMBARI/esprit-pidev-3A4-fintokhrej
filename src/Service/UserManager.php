<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use InvalidArgumentException;

/**
 * @phpstan-type SecuritySummary array{
 *     email: string|null,
 *     role: string|null,
 *     premium: bool,
 *     displayName: string
 * }
 */
final class UserManager
{
    public function validateProfile(User $user): bool
    {
        if (trim((string) $user->getNom()) === '' || trim((string) $user->getPrenom()) === '') {
            throw new InvalidArgumentException('Le nom et le prenom sont obligatoires.');
        }

        if (!filter_var($user->getEmail(), FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Le format de l email est invalide.');
        }

        if (!in_array($user->getRole(), ['admin', 'abonne', 'visiteur', 'ROLE_USER'], true)) {
            throw new InvalidArgumentException('Le role utilisateur est invalide.');
        }

        return true;
    }

    public function validatePasswordHash(User $user): bool
    {
        if (trim((string) $user->getPasswordHash()) === '') {
            throw new InvalidArgumentException('Le mot de passe hache est obligatoire.');
        }

        return true;
    }

    public function canAccessPremiumFeatures(User $user): bool
    {
        return in_array($user->getRole(), ['admin', 'abonne', 'ROLE_USER'], true);
    }

    public function getDisplayName(User $user): string
    {
        return trim(($user->getPrenom() ?? '') . ' ' . ($user->getNom() ?? ''));
    }

    /**
     * @phpstan-return SecuritySummary
     */
    public function getSecuritySummary(User $user): array
    {
        $summary = [
            'email' => $user->getEmail(),
            'role' => $user->getRole(),
            'premium' => $this->canAccessPremiumFeatures($user),
            'displayName' => $this->getDisplayName($user),
        ];

        return $summary;
    }
}
