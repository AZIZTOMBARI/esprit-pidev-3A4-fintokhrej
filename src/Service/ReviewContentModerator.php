<?php

declare(strict_types=1);

namespace App\Service;

final class ReviewContentModerator
{
    /**
     * @var array<int, array{label:string, pattern:string, weight:int}>
     */
    private const RULES = [
        ['label' => 'fuck', 'pattern' => '/\bf[\W_]*u[\W_]*c[\W_]*k(?:er|ing)?\b/iu', 'weight' => 45],
        ['label' => 'fuck you', 'pattern' => '/\bf[\W_]*u[\W_]*c[\W_]*k[\W_]*(?:yo?u|u)\b/iu', 'weight' => 60],
        ['label' => 'bitch', 'pattern' => '/\bb[\W_]*i[\W_]*t[\W_]*c[\W_]*h(?:es)?\b/iu', 'weight' => 45],
        ['label' => 'shit', 'pattern' => '/\bs[\W_]*h[\W_]*i[\W_]*t\b/iu', 'weight' => 35],
        ['label' => 'asshole', 'pattern' => '/\ba[\W_]*s[\W_]*s[\W_]*h[\W_]*o[\W_]*l[\W_]*e\b/iu', 'weight' => 50],
        ['label' => 'connard', 'pattern' => '/\bc[\W_]*o[\W_]*n[\W_]*n[\W_]*a[\W_]*r[\W_]*d\b/iu', 'weight' => 50],
        ['label' => 'salaud', 'pattern' => '/\bs[\W_]*a[\W_]*l[\W_]*a[\W_]*u[\W_]*d\b/iu', 'weight' => 42],
        ['label' => 'pute', 'pattern' => '/\bp[\W_]*u[\W_]*t[\W_]*e\b/iu', 'weight' => 42],
        ['label' => 'fils de pute', 'pattern' => '/\bfils[\W_]*de[\W_]*p[\W_]*u[\W_]*t[\W_]*e\b/iu', 'weight' => 65],
        ['label' => 'encule', 'pattern' => '/\be[\W_]*n[\W_]*c[\W_]*u[\W_]*l(?:e|é|er|ee)?\b/iu', 'weight' => 55],
        ['label' => 'merde', 'pattern' => '/\bm[\W_]*e[\W_]*r[\W_]*d[\W_]*e\b/iu', 'weight' => 28],
        ['label' => 'abruti', 'pattern' => '/\ba[\W_]*b[\W_]*r[\W_]*u[\W_]*t[\W_]*i\b/iu', 'weight' => 34],
        ['label' => 'idiot', 'pattern' => '/\bi[\W_]*d[\W_]*i[\W_]*o[\W_]*t\b/iu', 'weight' => 25],
        ['label' => 'cretin', 'pattern' => '/\bc[\W_]*r[\W_]*e[\W_]*t[\W_]*i[\W_]*n\b/iu', 'weight' => 30],
        ['label' => 'imbecile', 'pattern' => '/\bi[\W_]*m[\W_]*b[\W_]*e[\W_]*c[\W_]*i[\W_]*l[\W_]*e\b/iu', 'weight' => 30],
        ['label' => 'kalb', 'pattern' => '/\bk[\W_]*a[\W_]*l[\W_]*b\b/iu', 'weight' => 35],
        ['label' => 'zebi', 'pattern' => '/\bz[\W_]*e[\W_]*b[\W_]*i\b/iu', 'weight' => 45],
        ['label' => 'nik', 'pattern' => '/\bn[\W_]*i[\W_]*k(?:ou|ek|om)?\b/iu', 'weight' => 55],
        ['label' => 'kess', 'pattern' => '/\bk[\W_]*e[\W_]*s[\W_]*s\b/iu', 'weight' => 40],
        ['label' => 'spam promo', 'pattern' => '/\b(?:achetez[\W_]*maintenant|cliquez[\W_]*ici|offre[\W_]*speciale|lien[\W_]*promo)\b/iu', 'weight' => 40],
        ['label' => 'menace', 'pattern' => '/\b(?:je[\W_]*vais[\W_]*te[\W_]*tuer|je[\W_]*vais[\W_]*te[\W_]*retrouver)\b/iu', 'weight' => 90],
    ];

    /**
     * @return array{hasViolation: bool, score: int, severity: string, matches: string[]}
     */
    public function analyze(string $text): array
    {
        $normalized = $this->normalize($text);
        $score = 0;
        $matches = [];

        foreach (self::RULES as $rule) {
            if (preg_match($rule['pattern'], $normalized) === 1) {
                $matches[] = $rule['label'];
                $score += $rule['weight'];
            }
        }

        if ($this->hasAggressiveRepetition($normalized)) {
            $matches[] = 'aggressive_repetition';
            $score += 15;
        }

        $matches = array_values(array_unique($matches));
        $score = min($score, 100);

        $severity = match (true) {
            $score === 0 => 'none',
            $score <= 24 => 'low',
            $score <= 49 => 'medium',
            $score <= 74 => 'high',
            default => 'critical',
        };

        return [
            'hasViolation' => $score > 0,
            'score' => $score,
            'severity' => $severity,
            'matches' => $matches,
        ];
    }

    private function normalize(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        $text = strtr($text, [
            '0' => 'o',
            '1' => 'i',
            '3' => 'e',
            '4' => 'a',
            '5' => 's',
            '7' => 't',
            '@' => 'a',
            '$' => 's',
        ]);

        $text = str_replace(
            ['à', 'á', 'â', 'ä', 'ã', 'å', 'ç', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï', 'ñ', 'ò', 'ó', 'ô', 'ö', 'õ', 'ù', 'ú', 'û', 'ü', 'ý', 'ÿ'],
            ['a', 'a', 'a', 'a', 'a', 'a', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'n', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y', 'y'],
            $text
        );

        return preg_replace('/\s+/u', ' ', $text) ?? $text;
    }

    private function hasAggressiveRepetition(string $text): bool
    {
        return preg_match('/([!?*#])\1{3,}/u', $text) === 1
            || preg_match('/(.)\1{5,}/u', preg_replace('/\s+/u', '', $text) ?? '') === 1;
    }
}
