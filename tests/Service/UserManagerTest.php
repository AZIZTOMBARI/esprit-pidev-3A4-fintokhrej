<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Service\UserManager;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class UserManagerTest extends TestCase
{
    private UserManager $userManager;

    protected function setUp(): void
    {
        $this->userManager = new UserManager();
    }

    public function testValidProfileIsAccepted(): void
    {
        $user = $this->createUser();

        $this->assertTrue($this->userManager->validateProfile($user));
    }

    public function testProfileFailsWithInvalidEmail(): void
    {
        $user = $this->createUser(email: 'invalid-email');

        $this->expectException(InvalidArgumentException::class);
        $this->userManager->validateProfile($user);
    }

    public function testPasswordHashValidationPasses(): void
    {
        $user = $this->createUser();
        $this->assertTrue($this->userManager->validatePasswordHash($user));
    }

    public function testPasswordHashValidationFailsWhenEmpty(): void
    {
        $user = $this->createUser(passwordHash: '');

        $this->expectException(InvalidArgumentException::class);
        $this->userManager->validatePasswordHash($user);
    }

    public function testPremiumAccessAllowedForSubscriber(): void
    {
        $user = $this->createUser(role: 'abonne');

        $this->assertTrue($this->userManager->canAccessPremiumFeatures($user));
    }

    public function testDisplayNameIsComposedFromFirstAndLastName(): void
    {
        $user = $this->createUser(nom: 'Dupont', prenom: 'Alice');

        $this->assertSame('Alice Dupont', $this->userManager->getDisplayName($user));
    }

    public function testSecuritySummaryReturnsExpectedValues(): void
    {
        $user = $this->createUser(nom: 'Dupont', prenom: 'Alice', email: 'alice@example.com', role: 'admin');

        $summary = $this->userManager->getSecuritySummary($user);

        $this->assertSame('alice@example.com', $summary['email']);
        $this->assertSame('admin', $summary['role']);
        $this->assertTrue($summary['premium']);
        $this->assertSame('Alice Dupont', $summary['displayName']);
    }

    private function createUser(
        string $nom = 'Dupont',
        string $prenom = 'Alice',
        string $email = 'alice@example.com',
        string $role = 'ROLE_USER',
        string $passwordHash = 'hashed-password'
    ): User {
        return (new User())
            ->setNom($nom)
            ->setPrenom($prenom)
            ->setEmail($email)
            ->setRole($role)
            ->setPasswordHash($passwordHash)
            ->setImageUrl('uploads/users/default.jpg');
    }
}
