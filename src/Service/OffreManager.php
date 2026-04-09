<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

class OffreManager
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function findActiveByLieu(?int $lieuId = null): array
    {
        $params = [];
        $sql = "
            SELECT o.id, o.titre, o.description, o.type, o.pourcentage, o.date_debut, o.date_fin, o.statut,
                   o.lieu_id, l.nom AS lieu_nom, l.ville,
                   CASE
                       WHEN TIMESTAMPDIFF(HOUR, NOW(), CONCAT(o.date_fin, ' 23:59:59')) BETWEEN 0 AND 24 THEN 1
                       ELSE 0
                   END AS expiring_soon
            FROM offre o
            LEFT JOIN lieu l ON l.id = o.lieu_id
                        WHERE (LOWER(o.statut) IN ('active', 'actif') OR o.statut IS NULL OR o.statut = '')
                  AND o.date_fin >= CURDATE()
        ";

        if ($lieuId !== null && $lieuId > 0) {
            $sql .= ' AND o.lieu_id = ?';
            $params[] = $lieuId;
        }

        $sql .= ' ORDER BY expiring_soon DESC, o.date_fin ASC, o.id DESC';

        try {
            return $this->connection->fetchAllAssociative($sql, $params);
        } catch (Exception) {
            return [];
        }
    }
}
