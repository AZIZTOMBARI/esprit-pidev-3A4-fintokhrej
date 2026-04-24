<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ExperienceSharingService
{
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'heic', 'webp'];
    private const VIDEO_EXTENSIONS = ['mp4', 'mov'];
    private const MAX_VIDEO_BYTES = 104857600;

    public function __construct(
        private readonly Connection $connection,
        private readonly SortieRecapGenerator $recapGenerator,
    ) {
    }

    /**
     * @return array{
     *     experienceActive: bool,
     *     experienceSharing: ?array<string,mixed>,
     *     experienceMedia: array<int,array<string,mixed>>,
     *     experienceRecap: ?array<string,mixed>,
     *     canUploadExperience: bool
     * }
     */
    public function syncAndLoadContext(array $sortie, ?User $viewer): array
    {
        $now = new \DateTimeImmutable();
        $sortieDate = new \DateTimeImmutable((string) $sortie['date_sortie']);
        $status = (string) ($sortie['statut'] ?? '');
        $experienceActive = $status !== 'ANNULEE' && ($sortieDate <= $now || $status === 'TERMINEE');

        if (!$experienceActive) {
            return [
                'experienceActive' => false,
                'experienceSharing' => null,
                'experienceMedia' => [],
                'experienceRecap' => null,
                'canUploadExperience' => false,
            ];
        }

        $this->activateForSortie((int) $sortie['id'], $sortieDate);

        $experienceSharing = $this->connection->fetchAssociative(
            'SELECT id, sortie_id, activated_at, is_open, media_count, last_media_added_at, last_generated_at, recap_mode
             FROM experience_sharing
             WHERE sortie_id = ?',
            [(int) $sortie['id']],
            [ParameterType::INTEGER]
        ) ?: null;

        $experienceMedia = $this->connection->fetchAllAssociative(
            'SELECT sm.id, sm.file_path, sm.media_type, sm.uploaded_at, sm.user_id, u.prenom, u.nom
             FROM sortie_media sm
             INNER JOIN user u ON u.id = sm.user_id
             WHERE sm.sortie_id = ?
             ORDER BY sm.uploaded_at DESC, sm.id DESC',
            [(int) $sortie['id']],
            [ParameterType::INTEGER]
        );

        $experienceRecap = $this->connection->fetchAssociative(
            'SELECT id, video_path, generated_at, version, version_label
             FROM sortie_recap
             WHERE sortie_id = ?
             ORDER BY version DESC, generated_at DESC
             LIMIT 1',
            [(int) $sortie['id']],
            [ParameterType::INTEGER]
        ) ?: null;

        return [
            'experienceActive' => true,
            'experienceSharing' => $experienceSharing,
            'experienceMedia' => $experienceMedia,
            'experienceRecap' => $experienceRecap,
            'canUploadExperience' => $viewer instanceof User ? $this->canContribute((int) $sortie['id'], (int) $sortie['user_id'], $viewer->getId()) : false,
        ];
    }

    public function handleUpload(int $sortieId, User $user, UploadedFile $file): void
    {
        $sortie = $this->connection->fetchAssociative(
            'SELECT id, user_id, titre, ville, date_sortie, statut
             FROM annonce_sortie
             WHERE id = ?',
            [$sortieId],
            [ParameterType::INTEGER]
        );

        if (!$sortie) {
            throw new \RuntimeException('Sortie introuvable.');
        }

        $sortieDate = new \DateTimeImmutable((string) $sortie['date_sortie']);
        if ($sortieDate > new \DateTimeImmutable()) {
            throw new \RuntimeException('Le partage de souvenirs s ouvre uniquement apres la fin de la sortie.');
        }

        $this->activateForSortie($sortieId, $sortieDate);

        if (!$this->canContribute($sortieId, (int) $sortie['user_id'], $user->getId())) {
            throw new \RuntimeException('Seuls le createur et les participants confirmes peuvent publier.');
        }

        $extension = strtolower((string) ($file->getClientOriginalExtension() ?: $file->guessExtension() ?: ''));

        if (in_array($extension, self::IMAGE_EXTENSIONS, true)) {
            $mediaType = 'image';
        } elseif (in_array($extension, self::VIDEO_EXTENSIONS, true)) {
            $mediaType = 'video';
        } else {
            throw new \RuntimeException('Format non autorise. Utilisez jpg, png, heic, webp, mp4 ou mov.');
        }

        if ($mediaType === 'video' && $file->getSize() > self::MAX_VIDEO_BYTES) {
            throw new \RuntimeException('La video depasse la limite de 100MB.');
        }

        if ($mediaType === 'video' && !$this->recapGenerator->validateVideoDuration($file)) {
            throw new \RuntimeException('La video doit durer 60 secondes maximum.');
        }

        $publicDir = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'public';
        $targetDirectory = $publicDir.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'experiences'.DIRECTORY_SEPARATOR.'sortie-'.$sortieId;
        if (!is_dir($targetDirectory)) {
            mkdir($targetDirectory, 0777, true);
        }

        $safeBase = trim((string) preg_replace('/[^A-Za-z0-9\-]+/', '-', pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)), '-');
        $safeBase = $safeBase !== '' ? $safeBase : 'souvenir';
        $filename = sprintf('%s-%s.%s', $safeBase, bin2hex(random_bytes(6)), $extension);

        $file->move($targetDirectory, $filename);

        $relativePath = '/uploads/experiences/sortie-'.$sortieId.'/'.$filename;
        $now = new \DateTimeImmutable();

        $this->connection->insert('sortie_media', [
            'sortie_id' => $sortieId,
            'user_id' => $user->getId(),
            'file_path' => $relativePath,
            'media_type' => $mediaType,
            'uploaded_at' => $now->format('Y-m-d H:i:s'),
        ], [
            ParameterType::INTEGER,
            ParameterType::INTEGER,
            ParameterType::STRING,
            ParameterType::STRING,
            ParameterType::STRING,
        ]);

        $mediaRows = $this->connection->fetchAllAssociative(
            'SELECT sm.id, sm.file_path, sm.media_type, sm.uploaded_at, u.prenom, u.nom
             FROM sortie_media sm
             INNER JOIN user u ON u.id = sm.user_id
             WHERE sm.sortie_id = ?
             ORDER BY sm.uploaded_at ASC, sm.id ASC',
            [$sortieId],
            [ParameterType::INTEGER]
        );

        $nextVersion = (int) $this->connection->fetchOne(
            'SELECT COALESCE(MAX(version), 0) + 1 FROM sortie_recap WHERE sortie_id = ?',
            [$sortieId],
            [ParameterType::INTEGER]
        );

        $generated = $this->recapGenerator->generate($sortie, $mediaRows, $nextVersion);

        $this->connection->insert('sortie_recap', [
            'sortie_id' => $sortieId,
            'video_path' => $generated['path'],
            'generated_at' => $now->format('Y-m-d H:i:s'),
            'version' => $nextVersion,
            'version_label' => sprintf('Version %d · %d medias', $nextVersion, count($mediaRows)),
        ], [
            ParameterType::INTEGER,
            ParameterType::STRING,
            ParameterType::STRING,
            ParameterType::INTEGER,
            ParameterType::STRING,
        ]);

        $this->connection->update('experience_sharing', [
            'media_count' => count($mediaRows),
            'last_media_added_at' => $now->format('Y-m-d H:i:s'),
            'last_generated_at' => $now->format('Y-m-d H:i:s'),
            'recap_mode' => $generated['mode'],
        ], [
            'sortie_id' => $sortieId,
        ]);
    }

    public function deleteMedia(int $sortieId, int $mediaId, User $user): void
    {
        $media = $this->connection->fetchAssociative(
            'SELECT id, sortie_id, user_id, file_path, media_type FROM sortie_media WHERE id = ? AND sortie_id = ?',
            [$mediaId, $sortieId],
            [ParameterType::INTEGER, ParameterType::INTEGER]
        );

        if (!$media) {
            throw new \RuntimeException('Media introuvable.');
        }

        $sortie = $this->connection->fetchAssociative(
            'SELECT id, user_id, titre, ville, date_sortie, statut FROM annonce_sortie WHERE id = ?',
            [$sortieId],
            [ParameterType::INTEGER]
        );

        if (!$sortie) {
            throw new \RuntimeException('Sortie introuvable.');
        }

        if ((int) $media['user_id'] !== $user->getId() && (int) $sortie['user_id'] !== $user->getId()) {
            throw new \RuntimeException('Vous ne pouvez supprimer que vos propres medias.');
        }

        $publicDir = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'public';
        $absoluteFilePath = $publicDir.str_replace('/', DIRECTORY_SEPARATOR, (string) $media['file_path']);
        if (file_exists($absoluteFilePath)) {
            @unlink($absoluteFilePath);
        }

        $this->connection->delete('sortie_media', ['id' => $mediaId]);

        $mediaRows = $this->connection->fetchAllAssociative(
            'SELECT sm.id, sm.file_path, sm.media_type, sm.uploaded_at, u.prenom, u.nom
             FROM sortie_media sm
             INNER JOIN user u ON u.id = sm.user_id
             WHERE sm.sortie_id = ?
             ORDER BY sm.uploaded_at ASC, sm.id ASC',
            [$sortieId],
            [ParameterType::INTEGER]
        );

        $now = new \DateTimeImmutable();

        if (!empty($mediaRows)) {
            $nextVersion = (int) $this->connection->fetchOne(
                'SELECT COALESCE(MAX(version), 0) + 1 FROM sortie_recap WHERE sortie_id = ?',
                [$sortieId],
                [ParameterType::INTEGER]
            );

            $generated = $this->recapGenerator->generate($sortie, $mediaRows, $nextVersion);

            $this->connection->insert('sortie_recap', [
                'sortie_id' => $sortieId,
                'video_path' => $generated['path'],
                'generated_at' => $now->format('Y-m-d H:i:s'),
                'version' => $nextVersion,
                'version_label' => sprintf('Version %d · %d medias', $nextVersion, count($mediaRows)),
            ], [
                ParameterType::INTEGER,
                ParameterType::STRING,
                ParameterType::STRING,
                ParameterType::INTEGER,
                ParameterType::STRING,
            ]);

            $this->connection->update('experience_sharing', [
                'media_count' => count($mediaRows),
                'last_generated_at' => $now->format('Y-m-d H:i:s'),
                'recap_mode' => $generated['mode'],
            ], ['sortie_id' => $sortieId]);
        } else {
            $this->connection->update('experience_sharing', [
                'media_count' => 0,
            ], ['sortie_id' => $sortieId]);
        }
    }

    public function syncFinishedSorties(): int
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT id, date_sortie
             FROM annonce_sortie
             WHERE date_sortie < NOW()"
        );

        $count = 0;
        foreach ($rows as $row) {
            $this->activateForSortie((int) $row['id'], new \DateTimeImmutable((string) $row['date_sortie']));
            ++$count;
        }

        return $count;
    }

    private function activateForSortie(int $sortieId, \DateTimeImmutable $sortieDate): void
    {
        $this->connection->update('annonce_sortie', [
            'statut' => 'TERMINEE',
        ], [
            'id' => $sortieId,
        ]);

        $existingId = $this->connection->fetchOne(
            'SELECT id FROM experience_sharing WHERE sortie_id = ?',
            [$sortieId],
            [ParameterType::INTEGER]
        );

        if ($existingId !== false && $existingId !== null) {
            return;
        }

        $this->connection->insert('experience_sharing', [
            'sortie_id' => $sortieId,
            'activated_at' => $sortieDate->format('Y-m-d H:i:s'),
            'is_open' => 1,
            'media_count' => 0,
            'last_media_added_at' => null,
            'last_generated_at' => null,
            'recap_mode' => $this->recapGenerator->hasFfmpeg() ? 'hybrid' : 'immersive_html',
        ], [
            ParameterType::INTEGER,
            ParameterType::STRING,
            ParameterType::INTEGER,
            ParameterType::INTEGER,
            ParameterType::NULL,
            ParameterType::NULL,
            ParameterType::STRING,
        ]);
    }

    private function canContribute(int $sortieId, int $creatorId, ?int $viewerId): bool
    {
        if ($viewerId === null) {
            return false;
        }

        if ($viewerId === $creatorId) {
            return true;
        }

        $isConfirmed = $this->connection->fetchOne(
            "SELECT id
             FROM participation_annonce
             WHERE annonce_id = ? AND user_id = ? AND statut = 'CONFIRMEE'
             LIMIT 1",
            [$sortieId, $viewerId],
            [ParameterType::INTEGER, ParameterType::INTEGER]
        );

        return $isConfirmed !== false && $isConfirmed !== null;
    }
}
