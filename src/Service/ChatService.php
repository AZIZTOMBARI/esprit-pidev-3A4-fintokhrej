<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

class ChatService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ChatRealtimePublisher $realtimePublisher,
    ) {
    }

    public function ensureGroupExists(int $sortieId, int $creatorUserId): int
    {
        if (!$this->isChatStorageAvailable()) {
            return 0;
        }

        $groupId = $this->getGroupId($sortieId);
        if ($groupId > 0) {
            $this->addMember($sortieId, $creatorUserId);

            return $groupId;
        }

        $this->connection->insert('chat_groupe', [
            'annonce_id' => $sortieId,
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        $groupId = (int) $this->connection->lastInsertId();
        $this->addMember($sortieId, $creatorUserId);
        $this->addSystemMessage($sortieId, 'Le groupe de sortie a ete cree. Vous pouvez maintenant organiser la sortie ici.');

        return $groupId;
    }

    public function addMember(int $sortieId, int $userId): void
    {
        if (!$this->isChatStorageAvailable()) {
            return;
        }

        $groupId = $this->getGroupId($sortieId);
        if ($groupId <= 0) {
            return;
        }

        $exists = $this->connection->fetchOne(
            'SELECT id FROM chat_groupe_membre WHERE chat_groupe_id = ? AND user_id = ? LIMIT 1',
            [$groupId, $userId]
        );

        if ($exists !== false && $exists !== null) {
            return;
        }

        $this->connection->insert('chat_groupe_membre', [
            'chat_groupe_id' => $groupId,
            'user_id' => $userId,
            'joined_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        $fullName = $this->resolveUserFullName($userId);
        $this->addSystemMessage($sortieId, $fullName.' a rejoint le groupe.');
    }

    public function removeMember(int $sortieId, int $userId): void
    {
        if (!$this->isChatStorageAvailable()) {
            return;
        }

        $sortie = $this->connection->fetchAssociative(
            'SELECT user_id FROM annonce_sortie WHERE id = ? LIMIT 1',
            [$sortieId]
        );

        if (!$sortie || (int) $sortie['user_id'] === $userId) {
            return;
        }

        $groupId = $this->getGroupId($sortieId);
        if ($groupId <= 0) {
            return;
        }

        $deleted = $this->connection->delete('chat_groupe_membre', [
            'chat_groupe_id' => $groupId,
            'user_id' => $userId,
        ]);

        if ($deleted > 0) {
            $fullName = $this->resolveUserFullName($userId);
            $this->addSystemMessage($sortieId, $fullName.' a quitte le groupe.');
        }
    }

    public function addSystemMessage(int $sortieId, string $content): int
    {
        if (!$this->isChatStorageAvailable()) {
            return 0;
        }

        $groupId = $this->getGroupId($sortieId);
        $senderId = $this->getSortieOwnerId($sortieId);
        if ($senderId <= 0) {
            return 0;
        }

        $now = new \DateTimeImmutable();
        $senderName = $this->resolveUserFullName($senderId);

        $this->connection->insert('chat_message', [
            'annonce_id' => $sortieId,
            'chat_groupe_id' => $groupId > 0 ? $groupId : null,
            'sender_id' => $senderId,
            'content' => $content,
            'message_type' => 'SYSTEM',
            'poll_id' => null,
            'meta_json' => null,
            'sent_at' => $now->format('Y-m-d H:i:s'),
            'edited_at' => null,
            'deleted_at' => null,
            'attachment_path' => null,
            'attachment_type' => null,
        ]);

        $messageId = (int) $this->connection->lastInsertId();
        $this->realtimePublisher->publishChatEvent($sortieId, [
            'event' => 'message.created',
            'message' => [
                'id' => $messageId,
                'content' => $content,
                'messageType' => 'SYSTEM',
                'sentAt' => $now->format(DATE_ATOM),
                'senderId' => $senderId,
                'senderName' => $senderName,
                'senderAvatar' => null,
                'isMine' => false,
                'isEdited' => false,
                'deletedAt' => null,
                'editedAt' => null,
                'attachmentPath' => null,
                'attachmentType' => null,
                'seenCount' => 0,
                'seenByAll' => false,
            ],
        ]);

        return $messageId;
    }

    public function getGroupId(int $sortieId): int
    {
        $groupId = $this->connection->fetchOne(
            'SELECT id FROM chat_groupe WHERE annonce_id = ? LIMIT 1',
            [$sortieId]
        );

        return $groupId !== false && $groupId !== null ? (int) $groupId : 0;
    }

    public function resolveUserFullName(int $userId): string
    {
        $row = $this->connection->fetchAssociative(
            'SELECT prenom, nom FROM user WHERE id = ? LIMIT 1',
            [$userId]
        );

        if (!$row) {
            return 'Un membre';
        }

        return trim(((string) ($row['prenom'] ?? '')).' '.((string) ($row['nom'] ?? ''))) ?: 'Un membre';
    }

    private function getSortieOwnerId(int $sortieId): int
    {
        $ownerId = $this->connection->fetchOne(
            'SELECT user_id FROM annonce_sortie WHERE id = ? LIMIT 1',
            [$sortieId]
        );

        return $ownerId !== false && $ownerId !== null ? (int) $ownerId : 0;
    }

    public function isChatStorageAvailable(): bool
    {
        try {
            return $this->connection->fetchOne("SHOW TABLES LIKE 'chat_groupe'") !== false;
        } catch (\Throwable) {
            return false;
        }
    }
}
