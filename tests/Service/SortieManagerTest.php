<?php

namespace App\Tests\Service;

use App\Entity\AnnonceSortie;
use App\Entity\User;
use App\Service\SortieManager;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class SortieManagerTest extends TestCase
{
    private SortieManager $sortieManager;

    protected function setUp(): void
    {
        $this->sortieManager = new SortieManager();
    }

    public function testSortieBasicsAreValid(): void
    {
        $sortie = $this->createSortie();

        $this->assertTrue($this->sortieManager->validateSortieBasics($sortie));
    }

    public function testSortieBasicsFailWhenCapacityIsZero(): void
    {
        $sortie = $this->createSortie(nbPlaces: 0);

        $this->expectException(InvalidArgumentException::class);
        $this->sortieManager->validateSortieBasics($sortie);
    }

    public function testSortieDateValidationPasses(): void
    {
        $sortie = $this->createSortie(dateSortie: new \DateTimeImmutable('2026-06-10'));

        $this->assertTrue($this->sortieManager->validateSortieDate($sortie));
    }

    public function testSortieDateValidationFailsWhenDateIsToday(): void
    {
        $sortie = $this->createSortie(dateSortie: new \DateTimeImmutable('today'));

        $this->expectException(InvalidArgumentException::class);
        $this->sortieManager->validateSortieDate($sortie);
    }

    public function testPublishableSortiePasses(): void
    {
        $sortie = $this->createSortie(statut: 'OUVERTE');

        $this->assertTrue($this->sortieManager->isPublishable($sortie));
    }

    public function testRemainingPlacesAreCalculatedCorrectly(): void
    {
        $sortie = $this->createSortie(nbPlaces: 10, participants: 4);

        $this->assertSame(6, $this->sortieManager->calculateRemainingPlaces($sortie));
    }

    public function testSortieSummaryReturnsExpectedData(): void
    {
        $sortie = $this->createSortie(titre: 'Randonnee', statut: 'OUVERTE', nbPlaces: 12, participants: 5);

        $summary = $this->sortieManager->getSortieSummary($sortie);

        $this->assertSame('Randonnee', $summary['title']);
        $this->assertSame('OUVERTE', $summary['status']);
        $this->assertSame(12, $summary['capacity']);
        $this->assertSame(5, $summary['participants']);
        $this->assertSame(7, $summary['remainingPlaces']);
    }

    private function createSortie(
        string $titre = 'Sortie test',
        string $statut = 'OUVERTE',
        int $nbPlaces = 10,
        int $participants = 0,
        float $budgetMax = 20.0,
        ?\DateTimeInterface $dateSortie = null
    ): AnnonceSortie {
        $sortie = (new AnnonceSortie())
            ->setTitre($titre)
            ->setStatut($statut)
            ->setNbPlaces($nbPlaces)
            ->setBudgetMax($budgetMax)
            ->setDateSortie($dateSortie ?? new \DateTimeImmutable('2026-06-10'));

        for ($index = 0; $index < $participants; $index++) {
            $sortie->addUser(
                (new User())
                    ->setId($index + 1)
                    ->setNom('User')
                    ->setPrenom('Test')
                    ->setEmail(sprintf('user%d@example.com', $index + 1))
                    ->setRole('ROLE_USER')
                    ->setPasswordHash('hash')
                    ->setImageUrl('uploads/users/default.jpg')
            );
        }

        return $sortie;
    }
}
