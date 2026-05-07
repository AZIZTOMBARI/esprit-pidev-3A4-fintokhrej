<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final class GamificationService
{
    public const PTS_VISITE_LIEU = 5;
    public const PTS_AVIS_LAISSE = 15;
    public const PTS_AVIS_FIABLE = 25;
    public const PTS_FAVORI = 3;
    public const PTS_SORTIE_JOINTE = 10;
    public const PTS_INSCRIPTION_EVENT = 8;

    public function __construct(
        private readonly Connection $connection,
        private readonly RecompenseOffreService $recompenseOffreService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getOrCreateUserPoints(int $userId): array
    {
        $this->ensureUserPointsExist($userId);

        $row = $this->connection->fetchAssociative(
            'SELECT user_id, total_points, nb_lieux_visites, nb_avis_laisses, nb_favoris, nb_sorties_jointes, updated_at
             FROM user_points WHERE user_id = ?',
            [$userId]
        ) ?: [];

        return $this->normalizeUserPoints($row, $userId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function awardActionPoints(int $userId, string $action): array
    {
        $this->ensureUserPointsExist($userId);

        $mapping = match ($action) {
            'VISITE_LIEU' => ['points' => self::PTS_VISITE_LIEU, 'column' => 'nb_lieux_visites'],
            'AVIS_LAISSE' => ['points' => self::PTS_AVIS_LAISSE, 'column' => 'nb_avis_laisses'],
            'AVIS_FIABLE' => ['points' => self::PTS_AVIS_FIABLE, 'column' => null],
            'FAVORI' => ['points' => self::PTS_FAVORI, 'column' => 'nb_favoris'],
            'SORTIE_JOINTE' => ['points' => self::PTS_SORTIE_JOINTE, 'column' => 'nb_sorties_jointes'],
            'INSCRIPTION_EVENT' => ['points' => self::PTS_INSCRIPTION_EVENT, 'column' => null],
            default => null,
        };

        if ($mapping === null) {
            return [];
        }

        if ($mapping['column'] !== null) {
            $this->connection->executeStatement(
                sprintf(
                    'UPDATE user_points SET total_points = total_points + ?, %s = %s + 1, updated_at = NOW() WHERE user_id = ?',
                    $mapping['column'],
                    $mapping['column']
                ),
                [$mapping['points'], $userId]
            );
        } else {
            $this->connection->executeStatement(
                'UPDATE user_points SET total_points = total_points + ?, updated_at = NOW() WHERE user_id = ?',
                [$mapping['points'], $userId]
            );
        }

        return $this->verifyAndAwardBadges($userId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function trackLieuVisit(int $userId, int $lieuId): array
    {
        $this->ensureUserPointsExist($userId);

        $inserted = $this->connection->executeStatement(
            'INSERT IGNORE INTO gamification_lieu_visite (user_id, lieu_id, visited_at) VALUES (?, ?, NOW())',
            [$userId, $lieuId]
        );

        if ($inserted <= 0) {
            return [];
        }

        $this->connection->executeStatement(
            'UPDATE user_points SET total_points = total_points + ?, nb_lieux_visites = nb_lieux_visites + 1, updated_at = NOW() WHERE user_id = ?',
            [self::PTS_VISITE_LIEU, $userId]
        );

        return $this->verifyAndAwardBadges($userId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function verifyAndAwardBadges(int $userId): array
    {
        $userPoints = $this->getOrCreateUserPoints($userId);
        $allBadges = $this->getAllBadges();
        $ownedCodes = array_fill_keys(array_map(
            static fn (array $badge): string => (string) $badge['code'],
            $this->getUserBadges($userId)
        ), true);

        $newBadges = [];
        foreach ($allBadges as $badge) {
            $code = (string) ($badge['code'] ?? '');
            if ($code === '' || isset($ownedCodes[$code])) {
                continue;
            }

            if (!$this->userDeservesBadge($userPoints, $badge)) {
                continue;
            }

            $this->connection->insert('user_badge', [
                'user_id' => $userId,
                'badge_id' => (int) $badge['id'],
                'date_obtenu' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);

            $bonus = (int) ($badge['points_bonus'] ?? 0);
            if ($bonus > 0) {
                $this->connection->executeStatement(
                    'UPDATE user_points SET total_points = total_points + ?, updated_at = NOW() WHERE user_id = ?',
                    [$bonus, $userId]
                );
            }

            $reward = $this->recompenseOffreService->genererOffreSiBadgeRecompense($userId, $code);

            $newBadges[] = [
                'id' => (int) $badge['id'],
                'code' => $code,
                'nom' => (string) ($badge['nom'] ?? ''),
                'description' => (string) ($badge['description'] ?? ''),
                'emoji' => (string) ($badge['emoji'] ?? '🏅'),
                'categorie' => (string) ($badge['categorie'] ?? 'GENERAL'),
                'seuil_requis' => (int) ($badge['seuil_requis'] ?? 0),
                'points_bonus' => $bonus,
                'reward_offer' => $reward,
            ];

            $userPoints = $this->getOrCreateUserPoints($userId);
        }

        return $newBadges;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getUserBadges(int $userId): array
    {
        return $this->connection->fetchAllAssociative(
            "SELECT b.id, b.code, b.nom, b.description, b.emoji, b.categorie, b.seuil_requis, b.points_bonus, ub.date_obtenu
             FROM badge b
             INNER JOIN user_badge ub ON ub.badge_id = b.id
             WHERE ub.user_id = ?
             ORDER BY ub.date_obtenu DESC, b.id DESC",
            [$userId]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getAllBadges(): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT id, code, nom, description, emoji, categorie, seuil_requis, points_bonus
             FROM badge
             ORDER BY categorie ASC, seuil_requis ASC, id ASC'
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getLeaderboard(int $limit = 20): array
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT u.id AS user_id, u.nom, u.prenom, u.image_url,
                    up.total_points, up.nb_lieux_visites, up.nb_avis_laisses, up.nb_favoris, up.nb_sorties_jointes,
                    COUNT(ub.badge_id) AS nb_badges
             FROM user_points up
             INNER JOIN user u ON u.id = up.user_id
             LEFT JOIN user_badge ub ON ub.user_id = up.user_id
             GROUP BY u.id, u.nom, u.prenom, u.image_url, up.total_points, up.nb_lieux_visites, up.nb_avis_laisses, up.nb_favoris, up.nb_sorties_jointes
             ORDER BY up.total_points DESC, u.id ASC
             LIMIT ?",
            [$limit],
            [ParameterType::INTEGER]
        );

        $rank = 1;
        foreach ($rows as &$row) {
            $row['rang'] = $rank++;
            $level = $this->computeLevel((int) ($row['total_points'] ?? 0));
            $row['niveau'] = $level['level'];
            $row['niveau_emoji'] = $level['emoji'];
            $row['rang_emoji'] = $this->rankEmoji((int) $row['rang']);
            $row['nom_complet'] = trim(((string) ($row['prenom'] ?? '')).' '.((string) ($row['nom'] ?? '')));
        }
        unset($row);

        return $rows;
    }

    public function getUserRank(int $userId): int
    {
        $points = $this->connection->fetchOne('SELECT total_points FROM user_points WHERE user_id = ?', [$userId]);
        if ($points === false) {
            return -1;
        }

        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) + 1 FROM user_points WHERE total_points > ?',
            [(int) $points]
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function getDashboardData(int $userId): array
    {
        $this->recompenseOffreService->expireOffresEchues();

        $points = $this->getOrCreateUserPoints($userId);
        $rank = $this->getUserRank($userId);
        $badges = $this->getUserBadges($userId);
        $allBadges = $this->getAllBadges();
        $offers = $this->recompenseOffreService->getOffresActivesUser($userId);
        $leaderboard = $this->getLeaderboard(20);

        return [
            'userPoints' => $points,
            'rank' => $rank,
            'level' => $this->computeLevel((int) ($points['total_points'] ?? 0)),
            'badges' => $badges,
            'allBadges' => $allBadges,
            'leaderboard' => $leaderboard,
            'offers' => $offers,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function markAvisUtile(int $evaluationId, int $votantUserId): array
    {
        $inserted = $this->connection->executeStatement(
            'INSERT IGNORE INTO avis_utile (evaluation_id, votant_user_id, date_vote) VALUES (?, ?, NOW())',
            [$evaluationId, $votantUserId]
        );

        if ($inserted <= 0) {
            return [];
        }

        $authorId = $this->connection->fetchOne(
            'SELECT user_id FROM evaluation_lieu WHERE id = ?',
            [$evaluationId]
        );

        if ($authorId === false) {
            return [];
        }

        $newBadges = $this->awardActionPoints((int) $authorId, 'AVIS_FIABLE');

        $usefulCount = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM avis_utile au INNER JOIN evaluation_lieu e ON e.id = au.evaluation_id WHERE e.user_id = ?',
            [(int) $authorId]
        );

        foreach ([
            'AVIS_FIABLE' => 3,
            'SUPER_CRITIQUE' => 5,
        ] as $code => $threshold) {
            if ($usefulCount < $threshold) {
                continue;
            }

            if ((int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM user_badge ub INNER JOIN badge b ON b.id = ub.badge_id WHERE ub.user_id = ? AND b.code = ?',
                [(int) $authorId, $code]
            ) > 0) {
                continue;
            }

            $badge = $this->connection->fetchAssociative('SELECT * FROM badge WHERE code = ?', [$code]);
            if (!$badge) {
                continue;
            }

            $this->connection->insert('user_badge', [
                'user_id' => (int) $authorId,
                'badge_id' => (int) $badge['id'],
                'date_obtenu' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);

            $bonus = (int) ($badge['points_bonus'] ?? 0);
            if ($bonus > 0) {
                $this->connection->executeStatement(
                    'UPDATE user_points SET total_points = total_points + ?, updated_at = NOW() WHERE user_id = ?',
                    [$bonus, (int) $authorId]
                );
            }

            $reward = $this->recompenseOffreService->genererOffreSiBadgeRecompense((int) $authorId, $code);
            $newBadges[] = [
                'id' => (int) $badge['id'],
                'code' => $code,
                'nom' => (string) ($badge['nom'] ?? ''),
                'description' => (string) ($badge['description'] ?? ''),
                'emoji' => (string) ($badge['emoji'] ?? '🏅'),
                'categorie' => (string) ($badge['categorie'] ?? 'GENERAL'),
                'seuil_requis' => (int) ($badge['seuil_requis'] ?? 0),
                'points_bonus' => $bonus,
                'reward_offer' => $reward,
            ];
        }

        return $newBadges;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeUserPoints(array $row, int $userId): array
    {
        $points = (int) ($row['total_points'] ?? 0);
        $level = $this->computeLevel($points);
        $progress = $this->computeProgress($points);

        return [
            'user_id' => $userId,
            'total_points' => $points,
            'nb_lieux_visites' => (int) ($row['nb_lieux_visites'] ?? 0),
            'nb_avis_laisses' => (int) ($row['nb_avis_laisses'] ?? 0),
            'nb_favoris' => (int) ($row['nb_favoris'] ?? 0),
            'nb_sorties_jointes' => (int) ($row['nb_sorties_jointes'] ?? 0),
            'updated_at' => $row['updated_at'] ?? null,
            'niveau' => $level['level'],
            'niveau_emoji' => $level['emoji'],
            'points_niveau_suivant' => $progress['remaining'],
            'progression_niveau' => $progress['ratio'],
        ];
    }

    private function ensureUserPointsExist(int $userId): void
    {
        $this->connection->executeStatement(
            'INSERT IGNORE INTO user_points (user_id, total_points, nb_lieux_visites, nb_avis_laisses, nb_favoris, nb_sorties_jointes, updated_at)
             VALUES (?, 0, 0, 0, 0, 0, NOW())',
            [$userId]
        );
    }

    /**
     * @param array<string, mixed> $userPoints
     * @param array<string, mixed> $badge
     */
    private function userDeservesBadge(array $userPoints, array $badge): bool
    {
        $code = (string) ($badge['code'] ?? '');

        return match ($code) {
            'PREMIER_PAS' => (int) $userPoints['nb_lieux_visites'] >= 1,
            'EXPLORATEUR_5' => (int) $userPoints['nb_lieux_visites'] >= 5,
            'EXPLORATEUR_20' => (int) $userPoints['nb_lieux_visites'] >= 20,
            'EXPLORATEUR_50' => (int) $userPoints['nb_lieux_visites'] >= 50,
            'PREMIER_AVIS' => (int) $userPoints['nb_avis_laisses'] >= 1,
            'AVIS_10' => (int) $userPoints['nb_avis_laisses'] >= 10,
            'PREMIER_FAVORI' => (int) $userPoints['nb_favoris'] >= 1,
            'COLLECTION_10' => (int) $userPoints['nb_favoris'] >= 10,
            'SORTIE_JOINTE' => (int) $userPoints['nb_sorties_jointes'] >= 1,
            'SORTIE_5' => (int) $userPoints['nb_sorties_jointes'] >= 5,
            'NIVEAU_ARGENT' => (int) $userPoints['total_points'] >= 100,
            'NIVEAU_OR' => (int) $userPoints['total_points'] >= 500,
            'NIVEAU_PLATINE' => (int) $userPoints['total_points'] >= 1500,
            default => (int) $userPoints['total_points'] >= (int) ($badge['seuil_requis'] ?? 0),
        };
    }

    /**
     * @return array{level: string, emoji: string}
     */
    private function computeLevel(int $points): array
    {
        return match (true) {
            $points >= 1500 => ['level' => 'PLATINE', 'emoji' => '💎'],
            $points >= 500 => ['level' => 'OR', 'emoji' => '🥇'],
            $points >= 100 => ['level' => 'ARGENT', 'emoji' => '🥈'],
            default => ['level' => 'BRONZE', 'emoji' => '🥉'],
        };
    }

    /**
     * @return array{ratio: float, remaining: int}
     */
    private function computeProgress(int $points): array
    {
        $levels = [0, 100, 500, 1500];
        $max = 1500;

        if ($points >= $max) {
            return ['ratio' => 1.0, 'remaining' => 0];
        }

        $currentFloor = 0;
        $next = 100;
        foreach ($levels as $index => $threshold) {
            if ($points < $threshold) {
                $next = $threshold;
                $currentFloor = $levels[max(0, $index - 1)];
                break;
            }
        }

        $span = max(1, $next - $currentFloor);

        return [
            'ratio' => max(0.0, min(1.0, ($points - $currentFloor) / $span)),
            'remaining' => max(0, $next - $points),
        ];
    }

    private function rankEmoji(int $rank): string
    {
        return match ($rank) {
            1 => '🥇',
            2 => '🥈',
            3 => '🥉',
            default => '#'.$rank,
        };
    }
}
