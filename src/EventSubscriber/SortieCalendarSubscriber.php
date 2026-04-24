<?php

namespace App\EventSubscriber;

use CalendarBundle\CalendarEvents;
use CalendarBundle\Entity\Event;
use CalendarBundle\Event\CalendarEvent;
use Doctrine\DBAL\Connection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SortieCalendarSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Connection $connection,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CalendarEvents::SET_DATA => 'onCalendarSetData',
        ];
    }

    public function onCalendarSetData(CalendarEvent $calendar): void
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, titre, ville, type_activite, statut, date_sortie
             FROM annonce_sortie
             WHERE date_sortie BETWEEN ? AND ?
             ORDER BY date_sortie ASC',
            [
                $calendar->getStart()->format('Y-m-d H:i:s'),
                $calendar->getEnd()->format('Y-m-d H:i:s'),
            ]
        );

        foreach ($rows as $row) {
            $start = new \DateTimeImmutable((string) $row['date_sortie']);
            $end = $start->modify('+2 hours');

            $event = new Event(
                (string) $row['titre'],
                $start,
                $end,
                null,
                [
                    'url' => $this->urlGenerator->generate('app_admin_sorties_show', ['id' => (int) $row['id']]),
                    'backgroundColor' => $this->colorForStatus((string) $row['statut']),
                    'borderColor' => $this->colorForStatus((string) $row['statut']),
                    'textColor' => '#ffffff',
                    'extendedProps' => [
                        'ville' => (string) ($row['ville'] ?? ''),
                        'typeActivite' => (string) ($row['type_activite'] ?? ''),
                        'statut' => (string) ($row['statut'] ?? ''),
                    ],
                ]
            );

            $calendar->addEvent($event);
        }
    }

    private function colorForStatus(string $status): string
    {
        return match (strtoupper(trim($status))) {
            'OUVERTE' => '#2276f5',
            'CLOTUREE' => '#f59e0b',
            'TERMINEE' => '#10b981',
            'ANNULEE' => '#ef4444',
            default => '#64748b',
        };
    }
}
