<?php

namespace App\Service;

final class ReviewContentModerator
{
    public function __construct(
        private readonly int $moderateThreshold = 3,
        private readonly int $severeThreshold = 6,
    ) {
    }

    /**
     * @var array<string, array{category:string, weight:int}>
     */
    private array $lexicon = [
        'connard' => ['category' => 'insulte', 'weight' => 3],
        'connasse' => ['category' => 'insulte', 'weight' => 3],
        'encule' => ['category' => 'insulte', 'weight' => 4],
        'fdp' => ['category' => 'insulte', 'weight' => 4],
        'salope' => ['category' => 'insulte', 'weight' => 4],
        'putain' => ['category' => 'insulte', 'weight' => 2],
        'pute' => ['category' => 'insulte', 'weight' => 4],
        'ta gueule' => ['category' => 'agression', 'weight' => 3],
        'nique' => ['category' => 'agression', 'weight' => 3],
        'merde' => ['category' => 'grossierete', 'weight' => 2],
        'fuck' => ['category' => 'insulte', 'weight' => 3],
        'bitch' => ['category' => 'insulte', 'weight' => 3],
        'asshole' => ['category' => 'insulte', 'weight' => 3],
        'porn' => ['category' => 'sexuel', 'weight' => 2],
        'porno' => ['category' => 'sexuel', 'weight' => 2],
    ];

    /**
     * @return array{hasViolation:bool, score:int, severity:string, thresholds:array{moderate:int,severe:int}, matches:array<int, array{term:string, category:string, confidence:float}>}
     */
    public function analyze(string $text): array
    {
        $clean = trim($text);
        if ($clean == '') {
            return [
                'hasViolation' => false,
                'score' => 0,
                'severity' => 'none',
                'thresholds' => [
                    'moderate' => $this->moderateThreshold,
                    'severe' => $this->severeThreshold,
                ],
                'matches' => [],
            ];
        }

        $normalized = $this->normalize($clean);
        $tokens = array_values(array_filter(explode(' ', $normalized), static fn (string $v): bool => $v !== ''));
        $compact = str_replace(' ', '', $normalized);

        $matches = [];
        $score = 0;

        foreach ($this->lexicon as $term => $meta) {
            $termNormalized = $this->normalize($term);
            $found = false;
            $confidence = 0.0;

            // Match direct (token or phrase)
            if (str_contains($normalized, $termNormalized)) {
                $found = true;
                $confidence = 1.0;
            }

            // Match obfusque: c.o-n_n@rd, f d p, etc.
            if (!$found && $this->matchesObfuscated($compact, str_replace(' ', '', $termNormalized))) {
                $found = true;
                $confidence = 0.95;
            }

            // Match proche: distance faible sur token
            if (!$found && str_contains($termNormalized, ' ') === false && mb_strlen($termNormalized) >= 5) {
                foreach ($tokens as $token) {
                    if (abs(mb_strlen($token) - mb_strlen($termNormalized)) > 1) {
                        continue;
                    }

                    $distance = levenshtein($token, $termNormalized);
                    if ($distance <= 1) {
                        $found = true;
                        $confidence = 0.78;
                        break;
                    }
                }
            }

            if ($found) {
                $score += $meta['weight'];
                $matches[] = [
                    'term' => $term,
                    'category' => $meta['category'],
                    'confidence' => $confidence,
                ];
            }
        }

        // Dedup sur le terme
        $dedup = [];
        foreach ($matches as $match) {
            $dedup[$match['term']] = $match;
        }
        $matches = array_values($dedup);

        $severity = 'none';
        if ($score >= $this->severeThreshold) {
            $severity = 'severe';
        } elseif ($score >= $this->moderateThreshold || count($matches) > 0) {
            $severity = 'moderate';
        }

        $hasViolation = $severity !== 'none';

        return [
            'hasViolation' => $hasViolation,
            'score' => $score,
            'severity' => $severity,
            'thresholds' => [
                'moderate' => $this->moderateThreshold,
                'severe' => $this->severeThreshold,
            ],
            'matches' => $matches,
        ];
    }

    private function normalize(string $value): string
    {
        $v = mb_strtolower($value, 'UTF-8');

        if (function_exists('transliterator_transliterate')) {
            $v = (string) transliterator_transliterate('Any-Latin; Latin-ASCII', $v);
        } else {
            $iconv = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $v);
            if ($iconv !== false) {
                $v = $iconv;
            }
        }

        $leetMap = [
            '0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's', '7' => 't',
            '@' => 'a', '$' => 's', '!' => 'i', '€' => 'e',
        ];
        $v = strtr($v, $leetMap);

        // Limite les repetitions type "coooonnard"
        $v = preg_replace('/([a-z])\1{2,}/', '$1$1', $v) ?? $v;

        // Supprime la ponctuation excessive et normalise espaces
        $v = preg_replace('/[^a-z\s]/', ' ', $v) ?? $v;
        $v = preg_replace('/\s+/', ' ', trim($v)) ?? trim($v);

        return $v;
    }

    private function matchesObfuscated(string $compactText, string $termCompact): bool
    {
        if ($termCompact === '') {
            return false;
        }

        $chars = preg_split('//u', $termCompact, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($chars) || $chars === []) {
            return false;
        }

        $patternParts = [];
        foreach ($chars as $char) {
            $patternParts[] = preg_quote($char, '/').'+';
        }

        $pattern = '/'.implode('[a-z]{0,1}', $patternParts).'/u';

        return preg_match($pattern, $compactText) === 1;
    }
}
