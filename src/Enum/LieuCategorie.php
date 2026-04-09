<?php

namespace App\Enum;

enum LieuCategorie: string
{
    case RESTAURANT = 'RESTAURANT';
    case CAFE = 'CAFE';
    case HOTEL = 'HOTEL';
    case SALLE = 'SALLE';
    case PARC = 'PARC';
    case AUTRE = 'AUTRE';

    public function getLabel(): string
    {
        return match ($this) {
            self::RESTAURANT => 'Restaurant',
            self::CAFE => 'Café',
            self::HOTEL => 'Hôtel',
            self::SALLE => 'Salle',
            self::PARC => 'Parc',
            self::AUTRE => 'Autre',
        };
    }

    public function label(): string
    {
        return $this->getLabel();
    }

    /**
     * @return array<string, self>
     */
    public static function choices(): array
    {
        return [
            'Restaurant' => self::RESTAURANT,
            'Café' => self::CAFE,
            'Hôtel' => self::HOTEL,
            'Salle' => self::SALLE,
            'Parc' => self::PARC,
            'Autre' => self::AUTRE,
        ];
    }
}
