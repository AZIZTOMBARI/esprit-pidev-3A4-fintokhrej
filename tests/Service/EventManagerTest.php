<?php

namespace App\Tests\Service;

use App\Entity\Evenement;
use App\Entity\Inscription;
use App\Entity\User;
use App\Repository\InscriptionRepository;
use App\Service\EvenementService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Ce fichier conserve son nom historique pour faciliter la transition,
 * mais teste maintenant le vrai service metier EvenementService.
 *
 * @covers \App\Service\EvenementService
 */
class EventManagerTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;

    private InscriptionRepository&MockObject $inscriptionRepository;

    private EvenementService $evenementService;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->inscriptionRepository = $this->getMockBuilder(InscriptionRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['isUserAlreadyRegistered', 'findInscriptionsEnAttente'])
            ->getMock();

        $this->entityManager
            ->method('getRepository')
            ->with(Inscription::class)
            ->willReturn($this->inscriptionRepository);

        $this->evenementService = new EvenementService($this->entityManager);
    }

    public function testDemanderInscriptionCreatesAConfirmedRegistration(): void
    {
        $event = $this->createEvent(capacity: 10, status: Evenement::STATUT_OUVERT);
        $user = $this->createUser(id: 9);

        $this->inscriptionRepository
            ->expects($this->once())
            ->method('isUserAlreadyRegistered')
            ->with(9, 15)
            ->willReturn(false);

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (object $entity) use ($event, $user): bool {
                if (!$entity instanceof Inscription) {
                    return false;
                }

                $this->assertSame($event, $entity->getEvenement());
                $this->assertSame($user, $entity->getUser());
                $this->assertSame(2, $entity->getNbTickets());
                $this->assertSame(Inscription::STATUT_CONFIRMEE, $entity->getStatut());
                $this->assertSame(0.0, $entity->getPaiement());

                return true;
            }));

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $inscription = $this->evenementService->demanderInscription($event, $user, 2);

        $this->assertSame($event, $inscription->getEvenement());
        $this->assertSame($user, $inscription->getUser());
        $this->assertSame(2, $inscription->getNbTickets());
        $this->assertSame(Inscription::STATUT_CONFIRMEE, $inscription->getStatut());
    }

    public function testDemanderInscriptionFailsWhenUserIsAlreadyRegistered(): void
    {
        $event = $this->createEvent();
        $user = $this->createUser(id: 4);

        $this->inscriptionRepository
            ->expects($this->once())
            ->method('isUserAlreadyRegistered')
            ->with(4, 15)
            ->willReturn(true);

        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->never())->method('flush');

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Vous etes deja inscrit a cet evenement.');

        $this->evenementService->demanderInscription($event, $user, 1);
    }

    public function testDemanderInscriptionFailsWhenCapacityIsInsufficient(): void
    {
        $event = $this->createEvent(capacity: 3, confirmedTickets: [2, 1]);
        $user = $this->createUser(id: 6);

        $this->inscriptionRepository
            ->expects($this->once())
            ->method('isUserAlreadyRegistered')
            ->willReturn(false);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Il ne reste que 0 place(s) disponible(s).');

        $this->evenementService->demanderInscription($event, $user, 1);
    }

    public function testDemanderInscriptionFailsWhenEventIsClosed(): void
    {
        $event = $this->createEvent(status: Evenement::STATUT_FERME);
        $user = $this->createUser(id: 3);

        $this->inscriptionRepository
            ->expects($this->once())
            ->method('isUserAlreadyRegistered')
            ->willReturn(false);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Les inscriptions a cet evenement sont fermees.');

        $this->evenementService->demanderInscription($event, $user, 1);
    }

    public function testAccepterInscriptionFailsWhenNoCapacityRemains(): void
    {
        $event = $this->createEvent(capacity: 2, confirmedTickets: [2]);
        $inscription = (new Inscription())
            ->setEvenement($event)
            ->setUser($this->createUser())
            ->setNbTickets(1)
            ->setStatut(Inscription::STATUT_EN_ATTENTE);

        $this->entityManager->expects($this->never())->method('flush');

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Plus assez de places disponibles.');

        $this->evenementService->accepterInscription($inscription);
    }

    public function testAnnulerInscriptionUpdatesStatusAndFlushes(): void
    {
        $inscription = (new Inscription())
            ->setEvenement($this->createEvent())
            ->setUser($this->createUser())
            ->setNbTickets(1)
            ->setStatut(Inscription::STATUT_CONFIRMEE);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $result = $this->evenementService->annulerInscription($inscription);

        $this->assertSame($inscription, $result);
        $this->assertSame(Inscription::STATUT_ANNULEE, $inscription->getStatut());
    }

    public function testGetStatistiquesEvenementReturnsExpectedCounters(): void
    {
        $event = $this->createEvent(capacity: 20);
        $event->getInscriptions()->add($this->createInscription($event, Inscription::STATUT_CONFIRMEE, 2));
        $event->getInscriptions()->add($this->createInscription($event, Inscription::STATUT_PAYEE, 3));
        $event->getInscriptions()->add($this->createInscription($event, Inscription::STATUT_EN_ATTENTE, 1));
        $event->getInscriptions()->add($this->createInscription($event, Inscription::STATUT_REJETEE, 1));
        $event->getInscriptions()->add($this->createInscription($event, Inscription::STATUT_ANNULEE, 4));

        $stats = $this->evenementService->getStatistiquesEvenement($event);

        $this->assertSame(5, $stats['total_demandes']);
        $this->assertSame(1, $stats['confirmees']);
        $this->assertSame(1, $stats['payees']);
        $this->assertSame(1, $stats['en_attente']);
        $this->assertSame(1, $stats['rejetees']);
        $this->assertSame(1, $stats['annulees']);
        $this->assertSame(1, $stats['non_payees']);
        $this->assertSame(15, $stats['places_restantes']);
        $this->assertSame(25.0, $stats['taux_remplissage']);
    }

    /**
     * @param list<int> $confirmedTickets
     */
    private function createEvent(
        int $capacity = 10,
        string $status = Evenement::STATUT_OUVERT,
        array $confirmedTickets = []
    ): Evenement {
        $event = (new Evenement())
            ->setTitre('Evenement de test')
            ->setDescription('Description')
            ->setStatut($status)
            ->setCapaciteMax($capacity)
            ->setPrix(30.0)
            ->setDateDebut(new \DateTimeImmutable('+10 days'))
            ->setDateFin(new \DateTimeImmutable('+10 days +2 hours'));

        $this->setPrivateProperty($event, 'id', 15);

        foreach ($confirmedTickets as $ticketCount) {
            $event->getInscriptions()->add(
                $this->createInscription($event, Inscription::STATUT_CONFIRMEE, $ticketCount)
            );
        }

        return $event;
    }

    private function createUser(int $id = 1): User
    {
        return (new User())
            ->setId($id)
            ->setNom('Dupont')
            ->setPrenom('Alice')
            ->setEmail('alice@example.com')
            ->setRole('ROLE_USER')
            ->setPasswordHash('hash')
            ->setImageUrl('uploads/users/alice.jpg');
    }

    private function createInscription(Evenement $event, string $status, int $tickets): Inscription
    {
        return (new Inscription())
            ->setEvenement($event)
            ->setUser($this->createUser())
            ->setStatut($status)
            ->setNbTickets($tickets)
            ->setPaiement(0.0);
    }

    private function setPrivateProperty(object $object, string $property, mixed $value): void
    {
        $reflection = new \ReflectionProperty($object, $property);
        $reflection->setValue($object, $value);
    }
}
