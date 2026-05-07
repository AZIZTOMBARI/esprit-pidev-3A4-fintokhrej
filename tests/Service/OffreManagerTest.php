<?php

namespace App\Tests\Service;

use App\Entity\Offre;
use App\Entity\ReservationOffre;
use App\Service\OffreManager;
use Doctrine\DBAL\Connection;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class OffreManagerTest extends TestCase
{
    private OffreManager $offreManager;

    protected function setUp(): void
    {
        $connection = $this->createMock(Connection::class);
        $this->offreManager = new OffreManager($connection);
    }

    public function testOfferDatesAreValid(): void
    {
        $offre = $this->createOffer(
            pourcentage: 20.0,
            statut: 'active',
            dateDebut: new \DateTimeImmutable('2026-06-01'),
            dateFin: new \DateTimeImmutable('2026-06-05')
        );

        $this->assertTrue($this->offreManager->validateOfferDates($offre));
    }

    public function testOfferDatesFailWhenEndBeforeStart(): void
    {
        $offre = $this->createOffer(
            dateDebut: new \DateTimeImmutable('2026-06-05'),
            dateFin: new \DateTimeImmutable('2026-06-01')
        );

        $this->expectException(InvalidArgumentException::class);
        $this->offreManager->validateOfferDates($offre);
    }

    public function testDiscountValidationPassesForValidValue(): void
    {
        $offre = $this->createOffer(pourcentage: 15.0);
        $this->assertTrue($this->offreManager->validateOfferDiscount($offre));
    }

    public function testDiscountValidationFailsAboveHundred(): void
    {
        $offre = $this->createOffer(pourcentage: 120.0);

        $this->expectException(InvalidArgumentException::class);
        $this->offreManager->validateOfferDiscount($offre);
    }

    public function testCanBeReservedForActiveOffer(): void
    {
        $offre = $this->createOffer(
            pourcentage: 10.0,
            statut: 'active',
            dateDebut: new \DateTimeImmutable('2026-06-01'),
            dateFin: new \DateTimeImmutable('2026-06-10')
        );

        $this->assertTrue($this->offreManager->canBeReserved($offre));
    }

    public function testCalculateFinalPrice(): void
    {
        $offre = $this->createOffer(pourcentage: 25.0);

        $this->assertSame(75.0, $this->offreManager->calculateFinalPrice(100.0, $offre));
    }

    public function testGetOfferSummary(): void
    {
        $offre = $this->createOffer(pourcentage: 20.0, titre: 'Promo Ete');
        $offre->addReservationOffre($this->createMock(ReservationOffre::class));

        $summary = $this->offreManager->getOfferSummary($offre, 50.0);

        $this->assertSame('Promo Ete', $summary['title']);
        $this->assertSame(20.0, $summary['discount']);
        $this->assertSame(40.0, $summary['finalPrice']);
        $this->assertSame(1, $summary['reservationCount']);
    }

    private function createOffer(
        float $pourcentage = 0.0,
        string $statut = 'active',
        string $titre = 'Offre test',
        ?\DateTimeInterface $dateDebut = null,
        ?\DateTimeInterface $dateFin = null
    ): Offre {
        return (new Offre())
            ->setTitre($titre)
            ->setPourcentage($pourcentage)
            ->setStatut($statut)
            ->setDateDebut($dateDebut ?? new \DateTimeImmutable('2026-06-01'))
            ->setDateFin($dateFin ?? new \DateTimeImmutable('2026-06-10'));
    }
}
