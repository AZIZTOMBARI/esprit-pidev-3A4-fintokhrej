<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Offre;
use App\Entity\ReservationOffre;
use Doctrine\DBAL\Connection;
use InvalidArgumentException;

/**
 * Gestion des offres actives avec filtrage par lieu et tri.
 *
 * @phpstan-type OfferSummary array{
 *     title: string|null,
 *     discount: float|null,
 *     finalPrice: float,
 *     reservationCount: int
 * }
 */
final class OffreManager
{
    public function __construct(
        private readonly Connection $connection,
    ) {}

    public function validateOfferDates(Offre $offre): bool
    {
        $dateDebut = $offre->getDateDebut();
        $dateFin = $offre->getDateFin();

        if ($dateDebut === null || $dateFin === null) {
            throw new InvalidArgumentException('Les dates de debut et de fin sont obligatoires.');
        }

        if ($dateFin <= $dateDebut) {
            throw new InvalidArgumentException('La date de fin doit etre posterieure a la date de debut.');
        }

        return true;
    }

    public function validateOfferDiscount(Offre $offre): bool
    {
        $pourcentage = $offre->getPourcentage();

        if ($pourcentage < 0 || $pourcentage > 100) {
            throw new InvalidArgumentException('Le pourcentage de reduction doit etre compris entre 0 et 100.');
        }

        return true;
    }

    public function canBeReserved(Offre $offre): bool
    {
        if (!in_array($offre->getStatut(), ['active', 'actif', 'OUVERTE'], true)) {
            throw new InvalidArgumentException('Le statut de l offre ne permet pas la reservation.');
        }

        $this->validateOfferDates($offre);
        $this->validateOfferDiscount($offre);

        return true;
    }

    public function calculateFinalPrice(float $prixInitial, Offre $offre): float
    {
        if ($prixInitial < 0) {
            throw new InvalidArgumentException('Le prix initial doit etre positif ou nul.');
        }

        $this->validateOfferDiscount($offre);

        return round($prixInitial * (1 - ($offre->getPourcentage() / 100)), 2);
    }

    public function countReservations(Offre $offre): int
    {
        return $offre->getReservationOffres()->count();
    }

    /**
     * @phpstan-return OfferSummary
     */
    public function getOfferSummary(Offre $offre, float $prixInitial): array
    {
        $summary = [
            'title' => $offre->getTitre(),
            'discount' => $offre->getPourcentage(),
            'finalPrice' => $this->calculateFinalPrice($prixInitial, $offre),
            'reservationCount' => $this->countReservations($offre),
        ];

        return $summary;
    }

    /**
     * Retourne les offres actives, optionnellement filtrées par lieu, triées selon $sort.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findActiveByLieu(?int $lieuId, string $sort = 'urgent'): array
    {
        $orderBy = match ($sort) {
            'date_fin_asc'    => 'o.date_fin ASC',
            'date_fin_desc'   => 'o.date_fin DESC',
            'reduction_desc'  => 'o.pourcentage DESC',
            'reduction_asc'   => 'o.pourcentage ASC',
            'titre_asc'       => 'o.titre ASC',
            'titre_desc'      => 'o.titre DESC',
            default           => 'expiring_soon DESC, o.date_fin ASC', // 'urgent'
        };

        $sql = "
            SELECT
                o.id, o.titre, o.type, o.pourcentage, o.date_debut, o.date_fin, o.statut, o.lieu_id,
                l.nom  AS lieu_nom,
                l.ville,
                CASE
                    WHEN TIMESTAMPDIFF(HOUR, NOW(), CONCAT(o.date_fin, ' 23:59:59')) BETWEEN 0 AND 24 THEN 1
                    ELSE 0
                END AS expiring_soon
            FROM offre o
            LEFT JOIN lieu l ON l.id = o.lieu_id
            WHERE (LOWER(o.statut) IN ('active', 'actif') OR o.statut IS NULL OR o.statut = '')
              AND o.date_fin >= CURDATE()
        ";

        $params = [];

        if ($lieuId !== null) {
            $sql    .= ' AND o.lieu_id = ?';
            $params[] = $lieuId;
        }

        $sql .= ' ORDER BY ' . $orderBy;

        return $this->connection->fetchAllAssociative($sql, $params);
    }
}
