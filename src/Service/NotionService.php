<?php

namespace App\Service;

use App\Entity\Evenement;
use Brd6\NotionSdkPhp\Client;
use Brd6\NotionSdkPhp\ClientOptions;
use Brd6\NotionSdkPhp\RequestParameters;
use Brd6\NotionSdkPhp\Resource\Database\DatabaseRequest;
use Brd6\NotionSdkPhp\Resource\Page;
use Brd6\NotionSdkPhp\Resource\Pagination\PaginationRequest;
use Psr\Log\LoggerInterface;

class NotionService
{
    private const NOTION_VERSION = '2022-06-28';

    private string $titlePropertyName = 'Titre';
    private ?string $lastError = null;
    private Client $notionClient;

    public function __construct(
        private LoggerInterface $logger,
        private string $notionApiKey,
        private string $notionDatabaseId,
    ) {
        $options = (new ClientOptions())
            ->setAuth(trim($this->notionApiKey))
            ->setNotionVersion(self::NOTION_VERSION);

        $this->notionClient = new Client($options);
    }

    public function isConfigured(): bool
    {
        return trim($this->notionApiKey) !== '' && trim($this->notionDatabaseId) !== '';
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * @return array{ok: bool, message: string, properties: string[]}
     */
    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            $this->lastError = 'NOTION_TOKEN ou NOTION_DATABASE_ID non configure.';

            return ['ok' => false, 'message' => $this->lastError, 'properties' => []];
        }

        try {
            $data = $this->notionClient->databases()->retrieve($this->notionDatabaseId)->toArray();
            $properties = $data['properties'] ?? [];

            foreach ($properties as $name => $prop) {
                if (($prop['type'] ?? '') === 'title') {
                    $this->titlePropertyName = $name;
                    break;
                }
            }

            $existingProps = array_keys($properties);
            $created = $this->ensureDatabaseSchema($existingProps);

            $this->lastError = null;

            return [
                'ok' => true,
                'message' => 'Connexion reussie. Propriete titre: "' . $this->titlePropertyName . '".'
                    . ($created > 0 ? ' ' . $created . ' propriete(s) creee(s).' : ''),
                'properties' => array_merge($existingProps, $created > 0 ? ['(schema mis a jour)'] : []),
            ];
        } catch (\Throwable $e) {
            $this->lastError = 'Erreur de connexion: ' . $e->getMessage();

            return ['ok' => false, 'message' => $this->lastError, 'properties' => []];
        }
    }

    private function ensureDatabaseSchema(array $existingProps): int
    {
        $required = [
            'Date Début' => 'date',
            'Date Fin' => 'date',
            'Statut' => 'select',
            'Type' => 'select',
            'Lieu' => 'rich_text',
            'Prix' => 'number',
            'Capacité' => 'number',
            'EventID' => 'number',
            'Description' => 'rich_text',
        ];

        $missing = [];
        foreach ($required as $name => $type) {
            if (!in_array($name, $existingProps, true)) {
                $missing[$name] = [$type => new \stdClass()];
            }
        }

        if ($missing === []) {
            return 0;
        }

        try {
            $this->request(
                'PATCH',
                sprintf('databases/%s', $this->notionDatabaseId),
                ['properties' => $missing]
            );

            return count($missing);
        } catch (\Throwable $e) {
            $this->logger->warning('Notion schema update failed', ['error' => $e->getMessage()]);

            return 0;
        }
    }

    /**
     * @param Evenement[] $evenements
     * @return array{created: int, updated: int, deleted: int, failed: int, errors: string[], total: int}
     */
    public function syncAll(array $evenements): array
    {
        $result = [
            'created' => 0,
            'updated' => 0,
            'deleted' => 0,
            'failed' => 0,
            'errors' => [],
            'total' => count($evenements),
        ];

        if (!$this->isConfigured()) {
            $result['errors'][] = 'Service non configure.';

            return $result;
        }

        $test = $this->testConnection();
        if (!$test['ok']) {
            $result['errors'][] = $test['message'];

            return $result;
        }

        $notionPages = $this->fetchAllNotionPages();

        $localIds = [];
        foreach ($evenements as $ev) {
            $localIds[$ev->getId()] = true;
        }

        foreach ($evenements as $ev) {
            try {
                $existingPageId = $notionPages[$ev->getId()] ?? null;

                if ($existingPageId !== null) {
                    $this->updatePage($existingPageId, $ev);
                    $result['updated']++;
                } else {
                    $this->createPage($ev);
                    $result['created']++;
                }
            } catch (\Throwable $e) {
                $result['failed']++;
                $result['errors'][] = sprintf(
                    '[%d] %s: %s',
                    $ev->getId(),
                    mb_substr($ev->getTitre() ?? '', 0, 40),
                    $e->getMessage()
                );
            }
        }

        foreach ($notionPages as $eventId => $pageId) {
            if (!isset($localIds[$eventId])) {
                try {
                    $this->archivePage($pageId);
                    $result['deleted']++;
                } catch (\Throwable $e) {
                    $result['errors'][] = 'Orphelin ' . $pageId . ': ' . $e->getMessage();
                }
            }
        }

        return $result;
    }

    public function syncEvenement(Evenement $evenement): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        try {
            $pageId = $this->findPageIdByEventId($evenement->getId());

            if ($pageId !== null) {
                $this->updatePage($pageId, $evenement);
            } else {
                $this->createPage($evenement);
            }

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Notion sync failed', [
                'evenement_id' => $evenement->getId(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function createPage(Evenement $ev): void
    {
        $this->request('POST', 'pages', [
            'parent' => ['database_id' => $this->notionDatabaseId],
            'properties' => $this->buildProperties($ev),
        ]);
    }

    private function updatePage(string $pageId, Evenement $ev): void
    {
        $this->request('PATCH', sprintf('pages/%s', $pageId), [
            'properties' => $this->buildProperties($ev),
        ]);
    }

    private function archivePage(string $pageId): void
    {
        $this->request('PATCH', sprintf('pages/%s', $pageId), [
            'archived' => true,
        ]);
    }

    private function findPageIdByEventId(int $id): ?string
    {
        try {
            $results = $this->queryDatabaseResults([
                'property' => 'EventID',
                'number' => ['equals' => $id],
            ]);

            return $results[0]?->getId() ?? null;
        } catch (\Throwable) {
            try {
                $results = $this->queryDatabaseResults([
                    'property' => 'SymfonyId',
                    'number' => ['equals' => $id],
                ]);

                return $results[0]?->getId() ?? null;
            } catch (\Throwable) {
                return null;
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function fetchAllNotionPages(): array
    {
        $map = [];
        $startCursor = null;

        do {
            try {
                $pagination = new PaginationRequest();
                if ($startCursor !== null) {
                    $pagination->setStartCursor($startCursor);
                }

                $response = $this->notionClient->databases()->query(
                    $this->notionDatabaseId,
                    null,
                    $pagination
                );

                foreach ($response->getResults() as $page) {
                    if (!$page instanceof Page || $page->getId() === '') {
                        continue;
                    }

                    $properties = $page->toArray()['properties'] ?? [];
                    $eventId = $properties['EventID']['number'] ?? null;
                    if ($eventId === null) {
                        $eventId = $properties['SymfonyId']['number'] ?? null;
                    }

                    if ($eventId !== null) {
                        $map[(int) $eventId] = $page->getId();
                    }
                }

                $startCursor = $response->isHasMore() ? $response->getNextCursor() : null;
            } catch (\Throwable) {
                break;
            }
        } while ($startCursor !== null);

        return $map;
    }

    private function buildProperties(Evenement $ev): array
    {
        $props = [
            $this->titlePropertyName => [
                'title' => [[
                    'text' => [
                        'content' => (string) ($ev->getTitre() ?? ''),
                    ],
                ]],
            ],
            'EventID' => [
                'number' => $ev->getId(),
            ],
            'Statut' => [
                'select' => [
                    'name' => $this->normalizeStatut($ev->getStatut()),
                ],
            ],
            'Type' => [
                'select' => [
                    'name' => $this->normalizeType($ev->getType()),
                ],
            ],
            'Prix' => [
                'number' => $ev->getPrix(),
            ],
        ];

        if ($ev->getCapaciteMax() !== null) {
            $props['Capacité'] = [
                'number' => $ev->getCapaciteMax(),
            ];
        }

        if ($ev->getDateDebut() !== null) {
            $props['Date Début'] = [
                'date' => [
                    'start' => $ev->getDateDebut()->format(\DateTimeInterface::ATOM),
                ],
            ];
        }

        if ($ev->getDateFin() !== null) {
            $props['Date Fin'] = [
                'date' => [
                    'start' => $ev->getDateFin()->format(\DateTimeInterface::ATOM),
                ],
            ];
        }

        $lieu = $ev->getLieu()?->getNom() ?? '';
        $props['Lieu'] = [
            'rich_text' => $lieu !== '' ? [[
                'text' => [
                    'content' => $lieu,
                ],
            ]] : [],
        ];

        $desc = mb_substr($ev->getDescription() ?? '', 0, 1900);
        $props['Description'] = [
            'rich_text' => $desc !== '' ? [[
                'text' => [
                    'content' => $desc,
                ],
            ]] : [],
        ];

        return $props;
    }

    private function normalizeStatut(?string $statut): string
    {
        $statut = mb_strtoupper(trim((string) $statut));

        if (in_array($statut, Evenement::STATUTS_VALIDES, true)) {
            return $statut;
        }

        return Evenement::STATUT_OUVERT;
    }

    private function normalizeType(?string $type): string
    {
        $type = mb_strtoupper(trim((string) $type));

        if (in_array($type, Evenement::TYPES_VALIDES, true)) {
            return $type;
        }

        return Evenement::TYPE_PUBLIC;
    }

    /**
     * @return Page[]
     */
    private function queryDatabaseResults(array $filter): array
    {
        $request = (new DatabaseRequest())->setFilter($filter);
        $response = $this->notionClient->databases()->query($this->notionDatabaseId, $request);

        return array_values(array_filter(
            $response->getResults(),
            static fn (mixed $result): bool => $result instanceof Page
        ));
    }

    private function request(string $method, string $path, array $body = []): array
    {
        $parameters = (new RequestParameters())
            ->setMethod($method)
            ->setPath($path);

        if ($body !== []) {
            $parameters->setBody($body);
        }

        return $this->notionClient->request($parameters);
    }
}
