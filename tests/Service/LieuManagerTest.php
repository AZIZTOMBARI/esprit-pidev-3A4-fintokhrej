<?php

namespace App\Tests\Service;

use App\Entity\Lieu;
use App\Enum\LieuCategorie;
use App\Enum\LieuType;
use App\Service\LieuManager;
use PHPUnit\Framework\TestCase;

class LieuManagerTest extends TestCase
{
    public function testValidateReturnsTrueForValidLieu(): void
    {
        $manager = new LieuManager();
        $lieu = $this->createValidLieu();

        self::assertTrue($manager->validate($lieu));
    }

    public function testValidateReturnsFalseWhenNomIsEmpty(): void
    {
        $manager = new LieuManager();
        $lieu = $this->createValidLieu();
        $lieu->setNom('');

        self::assertFalse($manager->validate($lieu));
    }

    public function testValidateReturnsFalseWhenVilleIsTooShort(): void
    {
        $manager = new LieuManager();
        $lieu = $this->createValidLieu();
        $lieu->setVille('A');

        self::assertFalse($manager->validate($lieu));
    }

    public function testValidateReturnsFalseWhenCategorieIsNull(): void
    {
        $manager = new LieuManager();
        $lieu = $this->createValidLieu();
        $lieu->setCategorie(null);

        self::assertFalse($manager->validate($lieu));
    }

    public function testValidateReturnsFalseWhenTypeIsNull(): void
    {
        $manager = new LieuManager();
        $lieu = $this->createValidLieu();
        $lieu->setType(null);

        self::assertFalse($manager->validate($lieu));
    }

    public function testValidateReturnsFalseWhenBudgetMinIsGreaterThanBudgetMax(): void
    {
        $manager = new LieuManager();
        $lieu = $this->createValidLieu();
        $lieu->setBudgetMin(150.0);
        $lieu->setBudgetMax(100.0);

        self::assertFalse($manager->validate($lieu));
    }

    public function testValidateReturnsFalseWhenLatitudeIsOutOfRange(): void
    {
        $manager = new LieuManager();
        $lieu = $this->createValidLieu();
        $lieu->setLatitude(120.0);

        self::assertFalse($manager->validate($lieu));
    }

    public function testValidateReturnsFalseWhenTelephoneIsInvalid(): void
    {
        $manager = new LieuManager();
        $lieu = $this->createValidLieu();
        $lieu->setTelephone('abc');

        self::assertFalse($manager->validate($lieu));
    }

    private function createValidLieu(): Lieu
    {
        $lieu = new Lieu();
        $lieu->setNom('Cafe des Arts');
        $lieu->setVille('Tunis');
        $lieu->setAdresse('12 Avenue Habib Bourguiba');
        $lieu->setTelephone('+216 20 123 456');
        $lieu->setSiteWeb('https://example.com');
        $lieu->setInstagram('cafe_des_arts');
        $lieu->setDescription('Un lieu agreable pour se detendre.');
        $lieu->setBudgetMin(10.0);
        $lieu->setBudgetMax(50.0);
        $lieu->setCategorie(LieuCategorie::CAFE);
        $lieu->setType(LieuType::PUBLIC);
        $lieu->setLatitude(36.8065);
        $lieu->setLongitude(10.1815);
        $lieu->setImageUrl('uploads/lieux/cafe.jpg');

        return $lieu;
    }
}
