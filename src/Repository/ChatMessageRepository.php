<?php

namespace App\Repository;

use App\Entity\ChatMessage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ChatMessage>
 */
class ChatMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChatMessage::class);
    }

    public function findConversationPage(
        int $sortieId,
        int $viewerId,
        int $memberCount,
        int $limit = 30,
        ?int $beforeId = null,
        ?string $search = null,
    ): array {
        $params = [
            'sortieId' => $sortieId,
            'viewerId' => $viewerId,
            'limitPlusOne' => $limit + 1,
        ];

        $where = ['cm.annonce_id = :sortieId'];
        if ($beforeId !== null) {
            $where[] = 'cm.id < :beforeId';
            $params['beforeId'] = $beforeId;
        }

        if ($search !== null && $search !== '') {
            $where[] = '(cm.content LIKE :search OR COALESCE(u.prenom, \'\') LIKE :search OR COALESCE(u.nom, \'\') LIKE :search)';
            $params['search'] = '%'.$search.'%';
        }

        $types = [
            'limitPlusOne' => Connection::PARAM_INT,
        ];

        if ($beforeId !== null) {
            $types['beforeId'] = Connection::PARAM_INT;
        }

        $sql = '
            SELECT
                cm.id,
                cm.content,
                cm.message_type,
                cm.sent_at,
                cm.edited_at,
                cm.deleted_at,
                cm.poll_id,
                cm.sender_id,
                cm.attachment_path,
                cm.attachment_type,
                u.prenom,
                u.nom,
                u.imageUrl AS image_url,
                COUNT(DISTINCT r.user_id) AS seen_count,
                MAX(CASE WHEN r.user_id = :viewerId THEN 1 ELSE 0 END) AS is_read_by_me
            FROM chat_message cm
            LEFT JOIN user u ON u.id = cm.sender_id
            LEFT JOIN chat_message_read r ON r.chat_message_id = cm.id
            WHERE '.implode(' AND ', $where).'
            GROUP BY cm.id, cm.content, cm.message_type, cm.sent_at, cm.edited_at, cm.deleted_at, cm.poll_id, cm.sender_id, cm.attachment_path, cm.attachment_type, u.prenom, u.nom, u.imageUrl
            ORDER BY cm.id DESC
            LIMIT :limitPlusOne
        ';

        $rows = $this->getEntityManager()->getConnection()->executeQuery($sql, $params, $types)->fetchAllAssociative();

        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            array_pop($rows);
        }

        $rows = array_reverse($rows);

        return [
            'items' => array_map(fn (array $row): array => $this->hydrateMessageRow($row, $viewerId, $memberCount), $rows),
            'hasMore' => $hasMore,
        ];
    }

    public function findMessagePayload(int $messageId, int $viewerId, int $memberCount): ?array
    {
        $row = $this->getEntityManager()->getConnection()->fetchAssociative(
            '
                SELECT
                    cm.id,
                    cm.content,
                    cm.message_type,
                    cm.sent_at,
                    cm.edited_at,
                    cm.deleted_at,
                    cm.poll_id,
                    cm.sender_id,
                    cm.attachment_path,
                    cm.attachment_type,
                    cm.annonce_id,
                    u.prenom,
                    u.nom,
                    u.imageUrl AS image_url,
                    COUNT(DISTINCT r.user_id) AS seen_count,
                    MAX(CASE WHEN r.user_id = :viewerId THEN 1 ELSE 0 END) AS is_read_by_me
                FROM chat_message cm
                LEFT JOIN user u ON u.id = cm.sender_id
                LEFT JOIN chat_message_read r ON r.chat_message_id = cm.id
                WHERE cm.id = :messageId
                GROUP BY cm.id, cm.content, cm.message_type, cm.sent_at, cm.edited_at, cm.deleted_at, cm.poll_id, cm.sender_id, cm.attachment_path, cm.attachment_type, cm.annonce_id, u.prenom, u.nom, u.imageUrl
                LIMIT 1
            ',
            [
                'messageId' => $messageId,
                'viewerId' => $viewerId,
            ]
        );

        return $row ? $this->hydrateMessageRow($row, $viewerId, $memberCount) : null;
    }

    public function countUnreadMessages(int $sortieId, int $viewerId): int
    {
        return (int) $this->getEntityManager()->getConnection()->fetchOne(
            '
                SELECT COUNT(*)
                FROM chat_message cm
                LEFT JOIN chat_message_read mr
                    ON mr.chat_message_id = cm.id
                    AND mr.user_id = :viewerId
                WHERE cm.annonce_id = :sortieId
                    AND cm.sender_id IS NOT NULL
                    AND cm.sender_id <> :viewerId
                    AND cm.deleted_at IS NULL
                    AND mr.id IS NULL
            ',
            [
                'sortieId' => $sortieId,
                'viewerId' => $viewerId,
            ]
        );
    }

    public function markConversationRead(int $sortieId, int $viewerId): int
    {
        return $this->getEntityManager()->getConnection()->executeStatement(
            '
                INSERT INTO chat_message_read (chat_message_id, user_id, read_at)
                SELECT cm.id, :viewerId, NOW()
                FROM chat_message cm
                LEFT JOIN chat_message_read mr
                    ON mr.chat_message_id = cm.id
                    AND mr.user_id = :viewerId
                WHERE cm.annonce_id = :sortieId
                    AND cm.sender_id IS NOT NULL
                    AND cm.sender_id <> :viewerId
                    AND cm.deleted_at IS NULL
                    AND mr.id IS NULL
            ',
            [
                'sortieId' => $sortieId,
                'viewerId' => $viewerId,
            ]
        );
    }

    private function hydrateMessageRow(array $row, int $viewerId, int $memberCount): array
    {
        $senderId = isset($row['sender_id']) ? (int) $row['sender_id'] : null;
        $seenCount = (int) ($row['seen_count'] ?? 0);
        $otherMembersCount = max(0, $memberCount - 1);
        $imageUrl = isset($row['image_url']) ? trim((string) $row['image_url']) : '';

        return [
            'id' => (int) $row['id'],
            'content' => (string) ($row['content'] ?? ''),
            'messageType' => (string) ($row['message_type'] ?? 'TEXT'),
            'sentAt' => isset($row['sent_at']) && $row['sent_at'] !== null ? (new \DateTimeImmutable((string) $row['sent_at']))->format(DATE_ATOM) : null,
            'editedAt' => isset($row['edited_at']) && $row['edited_at'] !== null ? (new \DateTimeImmutable((string) $row['edited_at']))->format(DATE_ATOM) : null,
            'deletedAt' => isset($row['deleted_at']) && $row['deleted_at'] !== null ? (new \DateTimeImmutable((string) $row['deleted_at']))->format(DATE_ATOM) : null,
            'senderId' => $senderId,
            'senderName' => trim(((string) ($row['prenom'] ?? '')).' '.((string) ($row['nom'] ?? ''))) ?: 'Membre',
            'senderAvatar' => $imageUrl !== '' ? $imageUrl : null,
            'pollId' => isset($row['poll_id']) && $row['poll_id'] !== null ? (int) $row['poll_id'] : null,
            'attachmentPath' => isset($row['attachment_path']) && $row['attachment_path'] !== null ? (string) $row['attachment_path'] : null,
            'attachmentType' => isset($row['attachment_type']) && $row['attachment_type'] !== null ? (string) $row['attachment_type'] : null,
            'isMine' => $senderId !== null && $senderId === $viewerId,
            'isEdited' => !empty($row['edited_at']),
            'isReadByMe' => (int) ($row['is_read_by_me'] ?? 0) === 1,
            'seenCount' => $seenCount,
            'seenByAll' => $senderId !== null && $senderId === $viewerId && $otherMembersCount > 0 && $seenCount >= $otherMembersCount,
        ];
    }
}
