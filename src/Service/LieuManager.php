<?php

namespace App\Service;

use App\Entity\Lieu;

class LieuManager
{
    public function validate(Lieu $lieu): bool
    {
        $nom = trim((string) $lieu->getNom());
        if ($nom === '' || mb_strlen($nom) < 2 || mb_strlen($nom) > 120) {
            return false;
        }

        $ville = trim((string) $lieu->getVille());
        if ($ville === '' || mb_strlen($ville) < 2 || mb_strlen($ville) > 80) {
            return false;
        }

        $adresse = $lieu->getAdresse();
        if ($adresse !== null && mb_strlen($adresse) > 200) {
            return false;
        }

        $telephone = $lieu->getTelephone();
        if ($telephone !== null && !preg_match('/^[0-9+\s().-]{6,30}$/', $telephone)) {
            return false;
        }

        $siteWeb = $lieu->getSiteWeb();
        if ($siteWeb !== null && $siteWeb !== '' && filter_var($siteWeb, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $instagram = $lieu->getInstagram();
        if ($instagram !== null && mb_strlen($instagram) > 255) {
            return false;
        }

        $description = $lieu->getDescription();
        if ($description !== null && mb_strlen($description) > 5000) {
            return false;
        }

        $budgetMin = $lieu->getBudgetMin();
        if ($budgetMin !== null && $budgetMin < 0) {
            return false;
        }

        $budgetMax = $lieu->getBudgetMax();
        if ($budgetMax !== null && $budgetMax < 0) {
            return false;
        }

        if ($budgetMin !== null && $budgetMax !== null && $budgetMin > $budgetMax) {
            return false;
        }

        if ($lieu->getCategorie() === null) {
            return false;
        }

        if ($lieu->getType() === null) {
            return false;
        }

        $latitude = $lieu->getLatitude();
        if ($latitude !== null && ($latitude < -90 || $latitude > 90)) {
            return false;
        }

        $longitude = $lieu->getLongitude();
        if ($longitude !== null && ($longitude < -180 || $longitude > 180)) {
            return false;
        }

        $imageUrl = $lieu->getImageUrl();
        if ($imageUrl !== null && mb_strlen($imageUrl) > 500) {
            return false;
        }

        return true;
    }
}
