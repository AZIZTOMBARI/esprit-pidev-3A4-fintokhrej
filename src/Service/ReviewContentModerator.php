<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Modère le contenu textuel (commentaires d'évaluation) avec une liste
 * de mots/expressions interdits et un score de sévérité.
 *
 * Retourne un tableau : ['hasViolation' => bool, 'score' => int, 'severity' => string, 'matches' => string[]]
 */
final class ReviewContentModerator
{
    /** Mots/expressions bannis (regex insensible à la casse) */
    private const BANNED_PATTERNS = [
        // Injures en français
        'connard', 'salaud', 'putain', 'merde', 'enculé', 'fils de pute',
        'idiot', 'crétin', 'imbécile', 'abruti',
        // Injures en arabe translittéré courant
        'kalb', 'ibn el', 'nikou', 'zebi', 'kess',
        // Spam / hors-sujet
        'achetez maintenant', 'cliquez ici', 'lien promo', 'offre spéciale',
        // Menaces
        'je vais te tuer', 'je vais te retrouver',
    ];

    /**
     * @return array{hasViolation: bool, score: int, severity: string, matches: string[]}
     */
    public function analyze(string $text): array
    {
        $matches = [];
        $score   = 0;

        foreach (self::BANNED_PATTERNS as $pattern) {
            if (preg_match('/'.preg_quote($pattern, '/').'/iu', $text)) {
                $matches[] = $pattern;
                $score    += 25;
            }
        }

        $score   = min($score, 100);
        $severity = match (true) {
            $score === 0         => 'none',
            $score <= 25         => 'low',
            $score <= 50         => 'medium',
            $score <= 75         => 'high',
            default              => 'critical',
        };

        return [
            'hasViolation' => $score > 0,
            'score'        => $score,
            'severity'     => $severity,
            'matches'      => array_values(array_unique($matches)),
        ];
    }
}
