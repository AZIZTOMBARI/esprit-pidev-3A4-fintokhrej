<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;

final class RecompenseOffreService
{
    public function __construct(
        private readonly Connection $connection,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function genererOffreSiBadgeRecompense(int $userId, string $badgeCode): ?array
    {
        $reward = $this->connection->fetchAssociative(
            'SELECT badge_code, titre, description, pourcentage, duree_jours FROM badge_recompense WHERE badge_code = ?',
            [$badgeCode]
        );

        if (!$reward) {
            return null;
        }

        $existing = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM offre_badge_user WHERE user_id = ? AND badge_code = ?',
            [$userId, $badgeCode]
        );

        if ($existing > 0) {
            return null;
        }

        $start = new \DateTimeImmutable('today');
        $end = $start->modify(sprintf('+%d days', max(1, (int) ($reward['duree_jours'] ?? 7))));

        $this->connection->insert('offre_badge_user', [
            'user_id' => $userId,
            'badge_code' => $badgeCode,
            'titre' => (string) ($reward['titre'] ?? 'Offre badge'),
            'pourcentage' => (float) ($reward['pourcentage'] ?? 0),
            'date_debut' => $start->format('Y-m-d'),
            'date_fin' => $end->format('Y-m-d'),
            'statut' => 'ACTIVE',
            'date_created' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        return [
            'badge_code' => $badgeCode,
            'titre' => (string) ($reward['titre'] ?? 'Offre badge'),
            'description' => (string) ($reward['description'] ?? ''),
            'pourcentage' => (float) ($reward['pourcentage'] ?? 0),
            'date_fin' => $end,
            'jours_restants' => (int) $start->diff($end)->days,
        ];
    }

    public function expireOffresEchues(): void
    {
        $this->connection->executeStatement(
            "UPDATE offre_badge_user
             SET statut = 'EXPIREE'
             WHERE statut = 'ACTIVE' AND date_fin < CURDATE()"
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getOffresActivesUser(int $userId): array
    {
        $this->expireOffresEchues();

        return $this->connection->fetchAllAssociative(
            "SELECT obu.id, obu.badge_code, obu.titre, obu.pourcentage, obu.date_debut, obu.date_fin, obu.statut,
                    br.description,
                    DATEDIFF(obu.date_fin, CURDATE()) AS jours_restants
             FROM offre_badge_user obu
             LEFT JOIN badge_recompense br ON br.badge_code = obu.badge_code
             WHERE obu.user_id = ?
               AND obu.statut = 'ACTIVE'
               AND obu.date_fin >= CURDATE()
             ORDER BY obu.date_fin ASC, obu.id ASC",
            [$userId]
        );
    }
}
