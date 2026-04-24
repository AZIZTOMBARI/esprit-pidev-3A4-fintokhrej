<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Pyrrah\OpenWeatherMapBundle\Services\Client;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class LieuWeatherService
{
    public function __construct(
        private readonly Client $client,
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $openWeatherApiKey,
    ) {}

    /**
     * @return array{
     *     available: bool,
     *     temperature: ?int,
     *     humidity: ?int,
     *     windKmH: ?float,
     *     icon: string,
     *     label: string,
     *     message: string
     * }
     */
    public function getCurrentWeather(?float $latitude, ?float $longitude, ?string $city = null): array
    {
        if ($latitude === null || $longitude === null) {
            return $this->unavailable('Meteo indisponible: coordonnees du lieu non renseignees.');
        }

        if ($this->isMissingApiKey()) {
            return $this->fetchFromOpenMeteo($latitude, $longitude, $city);
        }

        try {
            $response = $this->client->query('weather', [
                'lat' => $latitude,
                'lon' => $longitude,
            ]);
        } catch (\Throwable $exception) {
            $this->logger->warning('OpenWeatherMap weather query failed.', [
                'exception' => $exception,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'city' => $city,
            ]);

            return $this->fetchFromOpenMeteo($latitude, $longitude, $city);
        }

        $code = (int) ($response->cod ?? 0);
        if ($code !== 200) {
            $this->logger->warning('OpenWeatherMap returned a non-success code.', [
                'code' => $response->cod ?? null,
                'message' => $response->message ?? null,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'city' => $city,
            ]);

            return $this->fetchFromOpenMeteo($latitude, $longitude, $city);
        }

        $weatherEntry = isset($response->weather[0]) && is_object($response->weather[0]) ? $response->weather[0] : null;
        $main = isset($response->main) && is_object($response->main) ? $response->main : null;
        $wind = isset($response->wind) && is_object($response->wind) ? $response->wind : null;

        $temperature = isset($main->temp) && is_numeric($main->temp) ? (int) round((float) $main->temp) : null;
        $humidity = isset($main->humidity) && is_numeric($main->humidity) ? (int) round((float) $main->humidity) : null;
        $windKmH = isset($wind->speed) && is_numeric($wind->speed) ? round(((float) $wind->speed) * 3.6, 1) : null;
        $iconCode = isset($weatherEntry->icon) ? (string) $weatherEntry->icon : '';
        $label = isset($weatherEntry->description) ? $this->humanizeLabel((string) $weatherEntry->description) : 'Meteo actuelle';

        return [
            'available' => true,
            'temperature' => $temperature,
            'humidity' => $humidity,
            'windKmH' => $windKmH,
            'icon' => $this->mapIcon($iconCode),
            'label' => $label,
            'message' => $label,
        ];
    }

    /**
     * @return array{
     *     available: bool,
     *     temperature: ?int,
     *     humidity: ?int,
     *     windKmH: ?float,
     *     icon: string,
     *     label: string,
     *     message: string
     * }
     */
    private function fetchFromOpenMeteo(float $latitude, float $longitude, ?string $city = null): array
    {
        try {
            $response = $this->httpClient->request('GET', 'https://api.open-meteo.com/v1/forecast', [
                'query' => [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'current' => 'temperature_2m,relative_humidity_2m,wind_speed_10m,weather_code',
                    'timezone' => 'auto',
                ],
                'timeout' => 15,
            ]);

            $data = $response->toArray(false);
            $current = is_array($data['current'] ?? null) ? $data['current'] : null;
            if ($current === null) {
                return $this->unavailable('Impossible de charger la meteo en temps reel.');
            }

            $temperature = isset($current['temperature_2m']) && is_numeric($current['temperature_2m']) ? (int) round((float) $current['temperature_2m']) : null;
            $humidity = isset($current['relative_humidity_2m']) && is_numeric($current['relative_humidity_2m']) ? (int) round((float) $current['relative_humidity_2m']) : null;
            $windKmH = isset($current['wind_speed_10m']) && is_numeric($current['wind_speed_10m']) ? round((float) $current['wind_speed_10m'], 1) : null;
            $weatherCode = isset($current['weather_code']) && is_numeric($current['weather_code']) ? (int) $current['weather_code'] : null;
            $weatherMeta = $this->mapOpenMeteoCode($weatherCode);

            return [
                'available' => true,
                'temperature' => $temperature,
                'humidity' => $humidity,
                'windKmH' => $windKmH,
                'icon' => $weatherMeta['icon'],
                'label' => $weatherMeta['label'],
                'message' => $weatherMeta['label'],
            ];
        } catch (\Throwable $exception) {
            $this->logger->warning('Open-Meteo fallback weather query failed.', [
                'exception' => $exception,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'city' => $city,
            ]);

            return $this->unavailable('Impossible de charger la meteo en temps reel.');
        }
    }

    /**
     * @return array{
     *     available: bool,
     *     temperature: ?int,
     *     humidity: ?int,
     *     windKmH: ?float,
     *     icon: string,
     *     label: string,
     *     message: string
     * }
     */
    private function unavailable(string $message): array
    {
        return [
            'available' => false,
            'temperature' => null,
            'humidity' => null,
            'windKmH' => null,
            'icon' => '🌍',
            'label' => 'Meteo indisponible',
            'message' => $message,
        ];
    }

    private function isMissingApiKey(): bool
    {
        $apiKey = trim($this->openWeatherApiKey);

        return $apiKey === '' || $apiKey === 'your_api_key';
    }

    private function humanizeLabel(string $label): string
    {
        $label = trim($label);
        if ($label === '') {
            return 'Meteo actuelle';
        }

        $first = mb_substr($label, 0, 1);
        $rest = mb_substr($label, 1);

        return mb_strtoupper($first).$rest;
    }

    private function mapIcon(string $iconCode): string
    {
        return match ($iconCode) {
            '01d', '01n' => '☀️',
            '02d', '02n' => '🌤️',
            '03d', '03n', '04d', '04n' => '☁️',
            '09d', '09n', '10d', '10n' => '🌧️',
            '11d', '11n' => '⛈️',
            '13d', '13n' => '❄️',
            '50d', '50n' => '🌫️',
            default => '🌍',
        };
    }

    /**
     * @return array{icon: string, label: string}
     */
    private function mapOpenMeteoCode(?int $weatherCode): array
    {
        return match ($weatherCode) {
            0 => ['icon' => '☀️', 'label' => 'Ciel degage'],
            1, 2 => ['icon' => '🌤️', 'label' => 'Partiellement nuageux'],
            3 => ['icon' => '☁️', 'label' => 'Couvert'],
            45, 48 => ['icon' => '🌫️', 'label' => 'Brouillard'],
            51, 53, 55, 56, 57 => ['icon' => '🌦️', 'label' => 'Bruine'],
            61, 63, 65, 66, 67, 80, 81, 82 => ['icon' => '🌧️', 'label' => 'Pluie'],
            71, 73, 75, 77, 85, 86 => ['icon' => '❄️', 'label' => 'Neige'],
            95, 96, 99 => ['icon' => '⛈️', 'label' => 'Orage'],
            default => ['icon' => '🌍', 'label' => 'Meteo actuelle'],
        };
    }
}
