<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AnnonceSortie;
use InvalidArgumentException;

/**
 * @phpstan-type SortieSummary array{
 *     title: string|null,
 *     status: string|null,
 *     capacity: int|null,
 *     participants: int,
 *     remainingPlaces: int
 * }
 */
final class SortieManager
{
    public function validateSortieBasics(AnnonceSortie $sortie): bool
    {
        if (trim((string) $sortie->getTitre()) === '') {
            throw new InvalidArgumentException('Le titre de la sortie est obligatoire.');
        }

        if ($sortie->getNb_places() === null || $sortie->getNb_places() <= 0) {
            throw new InvalidArgumentException('Le nombre de places doit etre strictement positif.');
        }

        if ($sortie->getBudget_max() === null || $sortie->getBudget_max() < 0) {
            throw new InvalidArgumentException('Le budget doit etre positif ou nul.');
        }

        return true;
    }

    public function validateSortieDate(AnnonceSortie $sortie): bool
    {
        $dateSortie = $sortie->getDate_sortie();

        if ($dateSortie === null) {
            throw new InvalidArgumentException('La date de sortie est obligatoire.');
        }

        if ($dateSortie <= new \DateTimeImmutable('today')) {
            throw new InvalidArgumentException('La date de sortie doit etre superieure a aujourd hui.');
        }

        return true;
    }

    public function isPublishable(AnnonceSortie $sortie): bool
    {
        if ($sortie->getStatut() !== 'OUVERTE') {
            throw new InvalidArgumentException('La sortie doit etre ouverte pour etre publiee.');
        }

        $this->validateSortieBasics($sortie);
        $this->validateSortieDate($sortie);

        return true;
    }

    public function calculateRemainingPlaces(AnnonceSortie $sortie): int
    {
        return max(0, $sortie->getNb_places() - $sortie->getUsers()->count());
    }

    public function isFull(AnnonceSortie $sortie): bool
    {
        return $this->calculateRemainingPlaces($sortie) === 0;
    }

    /**
     * @phpstan-return SortieSummary
     */
    public function getSortieSummary(AnnonceSortie $sortie): array
    {
        $summary = [
            'title' => $sortie->getTitre(),
            'status' => $sortie->getStatut(),
            'capacity' => $sortie->getNb_places(),
            'participants' => $sortie->getUsers()->count(),
            'remainingPlaces' => $this->calculateRemainingPlaces($sortie),
        ];

        return $summary;
    }
}
