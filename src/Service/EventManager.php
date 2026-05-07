<?php

namespace App\Service;

use App\Entity\Evenement;
use App\Entity\Inscription;
use InvalidArgumentException;

/**
 * EventManager - Service métier pour la gestion des événements
 * 
 * Responsabilités :
 * 1. Valider les règles métier d'événement
 * 2. Gérer les réservations et les places disponibles
 * 3. Calculer les statistiques d'événement
 */
class EventManager
{
    /**
     * Règle métier 1 : Valider qu'un événement est réservable
     * 
     * Conditions requises :
     * - L'événement doit être en statut OUVERT
     * - L'événement doit avoir des places disponibles
     * - Le nombre de tickets demandés doit être positif
     * 
     * @param Evenement $event
     * @param int $nbTickets Nombre de places à réserver
     * @throws InvalidArgumentException
     * @return true
     */
    public function validateEventBooking(Evenement $event, int $nbTickets = 1): bool
    {
        // Vérifier que le nombre de tickets est positif
        if ($nbTickets <= 0) {
            throw new InvalidArgumentException(
                sprintf('Le nombre de tickets doit être positif, reçu : %d', $nbTickets)
            );
        }

        // Vérifier que l'événement est en statut OUVERT
        if ($event->getStatut() !== Evenement::STATUT_OUVERT) {
            throw new InvalidArgumentException(
                sprintf(
                    'L\'événement "%s" est en statut "%s", seul le statut "%s" permet les réservations',
                    $event->getTitre(),
                    $event->getStatut(),
                    Evenement::STATUT_OUVERT
                )
            );
        }

        // Vérifier qu'il y a assez de places
        if (!$event->avoirPlacesPour($nbTickets)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Pas assez de places disponibles. Demandé : %d, Disponible : %d',
                    $nbTickets,
                    $event->getPlacesRestantes()
                )
            );
        }

        return true;
    }

    /**
     * Règle métier 2 : Valider la cohérence des dates d'un événement
     * 
     * Conditions requises :
     * - dateDebut doit être défini et être un objet DateTimeInterface
     * - dateFin doit être défini et être un objet DateTimeInterface
     * - dateFin doit être strictement APRÈS dateDebut
     * - L'intervalle entre début et fin doit être > 0 secondes
     * 
     * @param Evenement $event
     * @throws InvalidArgumentException
     * @return true
     */
    public function validateEventDateRange(Evenement $event): bool
    {
        $dateDebut = $event->getDateDebut();
        $dateFin = $event->getDateFin();

        // Vérifier que les dates existent
        if ($dateDebut === null || $dateFin === null) {
            throw new InvalidArgumentException(
                'Les dates de début et fin sont obligatoires'
            );
        }

        // Vérifier que la fin est après le début
        if ($dateFin <= $dateDebut) {
            throw new InvalidArgumentException(
                sprintf(
                    'La date de fin (%s) doit être après la date de début (%s)',
                    $dateFin->format('Y-m-d H:i:s'),
                    $dateDebut->format('Y-m-d H:i:s')
                )
            );
        }

        return true;
    }

    /**
     * Règle métier 3 : Calculer et valider le taux de remplissage
     * 
     * Le taux de remplissage doit être :
     * - Entre 0 et 100%
     * - Cohérent avec les places restantes
     * - Arrondi à 2 décimales
     * 
     * Calcul : (places utilisées / capacité maximale) * 100
     * 
     * @param Evenement $event
     * @throws InvalidArgumentException
     * @return float Taux de remplissage (0-100)
     */
    public function calculateFillRate(Evenement $event): float
    {
        $capaciteMax = $event->getCapaciteMax();

        // Vérifier que la capacité est définie et positive
        if ($capaciteMax <= 0) {
            throw new InvalidArgumentException(
                sprintf('La capacité maximale doit être positive, reçue : %d', $capaciteMax)
            );
        }

        $fillRate = (float)$event->getTauxRemplissage();

        // Valider que le taux est entre 0 et 100
        if ($fillRate < 0 || $fillRate > 100) {
            throw new InvalidArgumentException(
                sprintf(
                    'Le taux de remplissage doit être entre 0 et 100, reçu : %.2f',
                    $fillRate
                )
            );
        }

        return $fillRate;
    }

    /**
     * Helper : Vérifier si un événement peut accueillir des réservations
     * (combine booking validation + date range validation)
     * 
     * @param Evenement $event
     * @param int $nbTickets
     * @return bool
     * @throws InvalidArgumentException
     */
    public function canAcceptBookings(Evenement $event, int $nbTickets = 1): bool
    {
        $this->validateEventDateRange($event);
        $this->validateEventBooking($event, $nbTickets);
        return true;
    }

    /**
     * Calculer le nombre de réservations confirmées ou payées
     * 
     * @param Evenement $event
     * @return int
     */
    public function getConfirmedBookingsCount(Evenement $event): int
    {
        $inscriptions = $event->getInscriptions();
        $count = 0;

        foreach ($inscriptions as $inscription) {
            if (in_array($inscription->getStatut(), [
                Inscription::STATUT_CONFIRMEE,
                Inscription::STATUT_PAYEE,
            ], true)) {
                $count += $inscription->getNbTickets();
            }
        }

        return $count;
    }

    /**
     * Vérifier si un événement est complet
     * 
     * @param Evenement $event
     * @return bool true si aucune place disponible
     */
    public function isEventFull(Evenement $event): bool
    {
        return $event->getPlacesRestantes() === 0;
    }

    /**
     * Obtenir un résumé lisible des places de l'événement
     * 
     * @param Evenement $event
     * @return array{capacity: int, booked: int, remaining: int, fillRate: float}
     */
    public function getCapacitySummary(Evenement $event): array
    {
        $rawCapaciteMax = $event->getCapaciteMax();
        $capaciteMax = $rawCapaciteMax ?? 0;
        $placesRestantes = $event->getPlacesRestantes();
        $booked = $capaciteMax - $placesRestantes;
        $fillRate = $this->calculateFillRate($event);

        return [
            'capacity' => $capaciteMax,
            'booked' => $booked,
            'remaining' => $placesRestantes,
            'fillRate' => $fillRate,
        ];
    }
}
