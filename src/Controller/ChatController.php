<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\ChatMessageRepository;
use App\Service\ChatRealtimePublisher;
use App\Service\ChatService;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class ChatController extends AbstractController
{
    public function __construct(
        private readonly ChatMessageRepository $chatMessageRepository,
        private readonly ChatRealtimePublisher $chatRealtimePublisher,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    #[Route('/sorties/{id}/chat', name: 'app_sorties_chat', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, Connection $connection): Response
    {
        $user = $this->requireChatUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isChatFeatureAvailable($connection)) {
            $this->addFlash('warning', 'Le module de groupe chat n est pas encore disponible. Lancez la migration de la base de donnees.');

            return $this->redirectToRoute($this->isGranted('ROLE_ADMIN') ? 'app_admin_participations' : 'app_sorties_show', ['id' => $id]);
        }

        $access = $this->loadChatAccess($connection, $id, (int) $user->getId());
        if (!$access['allowed']) {
            $this->addFlash('error', 'Ce groupe est reserve a l organisateur et aux participants acceptes.');

            return $this->redirectToRoute('app_sorties_show', ['id' => $id]);
        }

        $members = $this->fetchMembers($connection, $id);
        $messagesPage = $this->chatMessageRepository->findConversationPage($id, (int) $user->getId(), count($members), 30);

        return $this->render('front/sortie/chat.html.twig', [
            'active' => 'sorties',
            'sortie' => $access['sortie'],
            'members' => $members,
            'assignableMembers' => $this->fetchAssignableParticipants($connection, $id),
            'initialMessages' => $messagesPage['items'],
            'hasMoreMessages' => $messagesPage['hasMore'],
            'polls' => $this->fetchPolls($connection, $id, (int) $user->getId()),
            'tasks' => $this->fetchTasks($connection, $id),
            'isCreator' => $access['isCreator'],
            'isAdminViewer' => $access['isAdmin'],
            'canManageTasks' => $access['isAdmin'] || $access['isCreator'],
            'currentUserId' => (int) $user->getId(),
            'conversationActionToken' => $this->csrfTokenManager->getToken('chat_conversation_'.$id)->getValue(),
            'messageFormToken' => $this->csrfTokenManager->getToken('chat_message_'.$id)->getValue(),
            'messageApiUrl' => $this->generateUrl('app_sorties_chat_api_messages', ['id' => $id]),
            'messageSendUrl' => $this->generateUrl('app_sorties_chat_message', ['id' => $id]),
            'messageEditUrlPattern' => '/sorties/'.$id.'/chat/messages/__MESSAGE__/edit',
            'messageDeleteUrlPattern' => '/sorties/'.$id.'/chat/messages/__MESSAGE__/delete',
            'messageReadUrl' => $this->generateUrl('app_sorties_chat_mark_read', ['id' => $id]),
            'typingUrl' => $this->generateUrl('app_sorties_chat_typing', ['id' => $id]),
            'unreadCount' => $this->chatMessageRepository->countUnreadMessages($id, (int) $user->getId()),
        ]);
    }

    #[Route('/sorties/{id}/chat/api/messages', name: 'app_sorties_chat_api_messages', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function apiMessages(int $id, Request $request, Connection $connection): JsonResponse
    {
        $user = $this->requireChatUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $access = $this->loadChatAccess($connection, $id, (int) $user->getId());
        if (!$access['allowed']) {
            return $this->json(['error' => 'forbidden'], Response::HTTP_FORBIDDEN);
        }

        $memberCount = count($this->fetchMembers($connection, $id));
        $limit = max(1, min(50, (int) $request->query->get('limit', 30)));
        $beforeId = $request->query->getInt('before', 0);
        $search = trim((string) $request->query->get('q', ''));

        $page = $this->chatMessageRepository->findConversationPage(
            $id,
            (int) $user->getId(),
            $memberCount,
            $limit,
            $beforeId > 0 ? $beforeId : null,
            $search !== '' ? $search : null,
        );

        return $this->json($page);
    }

    #[Route('/sorties/{id}/chat/message', name: 'app_sorties_chat_message', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function sendMessage(int $id, Request $request, Connection $connection): Response
    {
        $user = $this->requireChatUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isChatFeatureAvailable($connection)) {
            return $this->chatErrorResponse($request, 'Le module de groupe chat n est pas encore disponible.', $id);
        }

        if (!$this->isCsrfTokenValid('chat_message_'.$id, (string) $request->request->get('_token'))) {
            return $this->chatErrorResponse($request, 'Jeton CSRF invalide.', $id, Response::HTTP_FORBIDDEN);
        }

        $access = $this->loadChatAccess($connection, $id, (int) $user->getId());
        if (!$access['allowed']) {
            return $this->chatErrorResponse($request, 'Acces non autorise.', $id, Response::HTTP_FORBIDDEN);
        }

        $content = trim((string) $request->request->get('content', ''));
        $attachment = $request->files->get('attachment');
        if (!$attachment instanceof UploadedFile && $content === '') {
            return $this->chatErrorResponse($request, 'Le message est vide.', $id, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        [$attachmentPath, $attachmentType] = $this->storeAttachment($attachment);
        $groupId = (int) $connection->fetchOne('SELECT id FROM chat_groupe WHERE annonce_id = ? LIMIT 1', [$id]);
        $now = new \DateTimeImmutable();

        $connection->insert('chat_message', [
            'annonce_id' => $id,
            'chat_groupe_id' => $groupId > 0 ? $groupId : null,
            'sender_id' => (int) $user->getId(),
            'content' => $content !== '' ? $content : '',
            'message_type' => $attachmentPath !== null ? 'MEDIA' : 'TEXT',
            'poll_id' => null,
            'meta_json' => null,
            'sent_at' => $now->format('Y-m-d H:i:s'),
            'edited_at' => null,
            'deleted_at' => null,
            'attachment_path' => $attachmentPath,
            'attachment_type' => $attachmentType,
        ]);

        $messageId = (int) $connection->lastInsertId();
        $payload = $this->chatMessageRepository->findMessagePayload($messageId, (int) $user->getId(), count($this->fetchMembers($connection, $id)));

        if ($payload !== null) {
            $this->chatRealtimePublisher->publishChatEvent($id, [
                'event' => 'message.created',
                'message' => $payload,
            ]);
        }

        if ($this->isApiRequest($request)) {
            return $this->json(['message' => $payload], Response::HTTP_CREATED);
        }

        return $this->redirectToRoute('app_sorties_chat', ['id' => $id]);
    }

    #[Route('/sorties/{id}/chat/messages/{messageId}/edit', name: 'app_sorties_chat_message_edit', requirements: ['id' => '\d+', 'messageId' => '\d+'], methods: ['POST'])]
    public function editMessage(int $id, int $messageId, Request $request, Connection $connection): JsonResponse
    {
        $user = $this->requireChatUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        if (!$this->isCsrfTokenValid('chat_conversation_'.$id, (string) $request->request->get('_token'))) {
            return $this->json(['error' => 'csrf'], Response::HTTP_FORBIDDEN);
        }

        $access = $this->loadChatAccess($connection, $id, (int) $user->getId());
        if (!$access['allowed']) {
            return $this->json(['error' => 'forbidden'], Response::HTTP_FORBIDDEN);
        }

        $message = $connection->fetchAssociative(
            'SELECT id, sender_id, deleted_at FROM chat_message WHERE id = ? AND annonce_id = ? LIMIT 1',
            [$messageId, $id]
        );

        if (!$message) {
            return $this->json(['error' => 'not_found'], Response::HTTP_NOT_FOUND);
        }

        $isOwner = (int) ($message['sender_id'] ?? 0) === (int) $user->getId();
        if (!$isOwner && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['error' => 'forbidden'], Response::HTTP_FORBIDDEN);
        }

        if (!empty($message['deleted_at'])) {
            return $this->json(['error' => 'deleted'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $content = trim((string) $request->request->get('content', ''));
        if ($content === '') {
            return $this->json(['error' => 'empty'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $connection->update('chat_message', [
            'content' => $content,
            'edited_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ], ['id' => $messageId]);

        $payload = $this->chatMessageRepository->findMessagePayload($messageId, (int) $user->getId(), count($this->fetchMembers($connection, $id)));
        if ($payload !== null) {
            $this->chatRealtimePublisher->publishChatEvent($id, [
                'event' => 'message.updated',
                'message' => $payload,
            ]);
        }

        return $this->json(['message' => $payload]);
    }

    #[Route('/sorties/{id}/chat/messages/{messageId}/delete', name: 'app_sorties_chat_message_delete', requirements: ['id' => '\d+', 'messageId' => '\d+'], methods: ['POST'])]
    public function deleteMessage(int $id, int $messageId, Request $request, Connection $connection): JsonResponse
    {
        $user = $this->requireChatUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        if (!$this->isCsrfTokenValid('chat_conversation_'.$id, (string) $request->request->get('_token'))) {
            return $this->json(['error' => 'csrf'], Response::HTTP_FORBIDDEN);
        }

        $access = $this->loadChatAccess($connection, $id, (int) $user->getId());
        if (!$access['allowed']) {
            return $this->json(['error' => 'forbidden'], Response::HTTP_FORBIDDEN);
        }

        $message = $connection->fetchAssociative(
            'SELECT id, sender_id FROM chat_message WHERE id = ? AND annonce_id = ? LIMIT 1',
            [$messageId, $id]
        );

        if (!$message) {
            return $this->json(['error' => 'not_found'], Response::HTTP_NOT_FOUND);
        }

        $isOwner = (int) ($message['sender_id'] ?? 0) === (int) $user->getId();
        if (!$isOwner && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['error' => 'forbidden'], Response::HTTP_FORBIDDEN);
        }

        $connection->update('chat_message', [
            'content' => 'Ce message a ete supprime.',
            'deleted_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'attachment_path' => null,
            'attachment_type' => null,
        ], ['id' => $messageId]);

        $payload = $this->chatMessageRepository->findMessagePayload($messageId, (int) $user->getId(), count($this->fetchMembers($connection, $id)));
        if ($payload !== null) {
            $this->chatRealtimePublisher->publishChatEvent($id, [
                'event' => 'message.deleted',
                'message' => $payload,
            ]);
        }

        return $this->json(['message' => $payload]);
    }

    #[Route('/sorties/{id}/chat/read', name: 'app_sorties_chat_mark_read', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function markRead(int $id, Request $request, Connection $connection): JsonResponse
    {
        $user = $this->requireChatUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        if (!$this->isCsrfTokenValid('chat_conversation_'.$id, (string) $request->request->get('_token'))) {
            return $this->json(['error' => 'csrf'], Response::HTTP_FORBIDDEN);
        }

        $access = $this->loadChatAccess($connection, $id, (int) $user->getId());
        if (!$access['allowed']) {
            return $this->json(['error' => 'forbidden'], Response::HTTP_FORBIDDEN);
        }

        $inserted = $this->chatMessageRepository->markConversationRead($id, (int) $user->getId());
        $this->chatRealtimePublisher->publishChatEvent($id, [
            'event' => 'conversation.read',
            'userId' => (int) $user->getId(),
            'userName' => trim(($user->getPrenom() ?? '').' '.($user->getNom() ?? '')) ?: 'Membre',
        ]);

        return $this->json(['inserted' => $inserted]);
    }

    #[Route('/sorties/{id}/chat/typing', name: 'app_sorties_chat_typing', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function typing(int $id, Request $request, Connection $connection): JsonResponse
    {
        $user = $this->requireChatUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        if (!$this->isCsrfTokenValid('chat_conversation_'.$id, (string) $request->request->get('_token'))) {
            return $this->json(['error' => 'csrf'], Response::HTTP_FORBIDDEN);
        }

        $access = $this->loadChatAccess($connection, $id, (int) $user->getId());
        if (!$access['allowed']) {
            return $this->json(['error' => 'forbidden'], Response::HTTP_FORBIDDEN);
        }

        $isTyping = filter_var($request->request->get('typing', false), FILTER_VALIDATE_BOOL);
        $this->chatRealtimePublisher->publishChatEvent($id, [
            'event' => 'typing',
            'userId' => (int) $user->getId(),
            'userName' => trim(($user->getPrenom() ?? '').' '.($user->getNom() ?? '')) ?: 'Membre',
            'typing' => $isTyping,
        ]);

        return $this->json(['ok' => true]);
    }

    #[Route('/sorties/{id}/chat/polls', name: 'app_sorties_chat_poll_create', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function createPoll(int $id, Request $request, Connection $connection): RedirectResponse
    {
        $user = $this->requireChatUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isChatFeatureAvailable($connection)) {
            $this->addFlash('warning', 'Le module de groupe chat n est pas encore disponible.');

            return $this->redirectToRoute($this->isGranted('ROLE_ADMIN') ? 'app_admin_participations' : 'app_sorties_show', ['id' => $id]);
        }

        if (!$this->isCsrfTokenValid('chat_poll_'.$id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('app_sorties_chat', ['id' => $id]);
        }

        $access = $this->loadChatAccess($connection, $id, (int) $user->getId());
        if (!$access['allowed']) {
            $this->addFlash('error', 'Acces non autorise.');

            return $this->redirectToRoute('app_sorties_show', ['id' => $id]);
        }

        $question = trim((string) $request->request->get('question', ''));
        $options = array_values(array_filter(array_map(
            static fn ($item): string => trim((string) $item),
            (array) $request->request->all('options')
        ), static fn (string $item): bool => $item !== ''));

        if ($question === '' || count($options) < 2) {
            $this->addFlash('warning', 'Le sondage doit contenir une question et au moins 2 options.');

            return $this->redirectToRoute('app_sorties_chat', ['id' => $id]);
        }

        $connection->insert('poll', [
            'annonce_id' => $id,
            'question' => $question,
            'created_by' => (int) $user->getId(),
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'is_open' => 1,
            'allow_multi' => 0,
            'allow_add_options' => 0,
            'is_pinned' => 0,
            'closed_at' => null,
        ]);
        $pollId = (int) $connection->lastInsertId();

        foreach ($options as $option) {
            $connection->insert('poll_option', [
                'poll_id' => $pollId,
                'text' => $option,
                'created_by' => (int) $user->getId(),
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);
        }

        $groupId = $connection->fetchOne('SELECT id FROM chat_groupe WHERE annonce_id = ? LIMIT 1', [$id]);
        $connection->insert('chat_message', [
            'annonce_id' => $id,
            'chat_groupe_id' => $groupId !== false && $groupId !== null ? (int) $groupId : null,
            'sender_id' => (int) $user->getId(),
            'content' => sprintf('Nouveau sondage : %s', $question),
            'message_type' => 'POLL',
            'poll_id' => $pollId,
            'meta_json' => null,
            'sent_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'edited_at' => null,
            'deleted_at' => null,
            'attachment_path' => null,
            'attachment_type' => null,
        ]);

        $messageId = (int) $connection->lastInsertId();
        $payload = $this->chatMessageRepository->findMessagePayload($messageId, (int) $user->getId(), count($this->fetchMembers($connection, $id)));
        if ($payload !== null) {
            $this->chatRealtimePublisher->publishChatEvent($id, [
                'event' => 'message.created',
                'message' => $payload,
            ]);
        }

        return $this->redirectToRoute('app_sorties_chat', ['id' => $id]);
    }

    #[Route('/sorties/{id}/chat/polls/{pollId}/vote', name: 'app_sorties_chat_poll_vote', requirements: ['id' => '\d+', 'pollId' => '\d+'], methods: ['POST'])]
    public function votePoll(int $id, int $pollId, Request $request, Connection $connection): RedirectResponse
    {
        $user = $this->requireChatUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isChatFeatureAvailable($connection)) {
            $this->addFlash('warning', 'Le module de groupe chat n est pas encore disponible.');

            return $this->redirectToRoute($this->isGranted('ROLE_ADMIN') ? 'app_admin_participations' : 'app_sorties_show', ['id' => $id]);
        }

        if (!$this->isCsrfTokenValid('chat_poll_vote_'.$pollId, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('app_sorties_chat', ['id' => $id]);
        }

        $access = $this->loadChatAccess($connection, $id, (int) $user->getId());
        if (!$access['allowed']) {
            $this->addFlash('error', 'Acces non autorise.');

            return $this->redirectToRoute('app_sorties_show', ['id' => $id]);
        }

        $poll = $connection->fetchAssociative(
            'SELECT id, is_open FROM poll WHERE id = ? AND annonce_id = ? LIMIT 1',
            [$pollId, $id]
        );

        if (!$poll || !(bool) $poll['is_open']) {
            $this->addFlash('warning', 'Ce sondage est indisponible.');

            return $this->redirectToRoute('app_sorties_chat', ['id' => $id]);
        }

        $optionId = (int) $request->request->get('option_id', 0);
        $optionExists = $connection->fetchOne(
            'SELECT id FROM poll_option WHERE id = ? AND poll_id = ? LIMIT 1',
            [$optionId, $pollId]
        );

        if (!$optionExists) {
            $this->addFlash('warning', 'Option invalide.');

            return $this->redirectToRoute('app_sorties_chat', ['id' => $id]);
        }

        $connection->delete('poll_vote', [
            'poll_id' => $pollId,
            'user_id' => (int) $user->getId(),
        ]);

        $connection->insert('poll_vote', [
            'poll_id' => $pollId,
            'option_id' => $optionId,
            'user_id' => (int) $user->getId(),
            'voted_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        return $this->redirectToRoute('app_sorties_chat', ['id' => $id]);
    }

    #[Route('/sorties/{id}/chat/tasks', name: 'app_sorties_chat_task_create', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function createTask(int $id, Request $request, Connection $connection): RedirectResponse
    {
        $user = $this->requireChatUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isChatFeatureAvailable($connection)) {
            $this->addFlash('warning', 'Le module de groupe chat n est pas encore disponible.');

            return $this->redirectToRoute($this->isGranted('ROLE_ADMIN') ? 'app_admin_participations' : 'app_sorties_show', ['id' => $id]);
        }

        if (!$this->isCsrfTokenValid('chat_task_'.$id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('app_sorties_chat', ['id' => $id]);
        }

        $access = $this->loadChatAccess($connection, $id, (int) $user->getId());
        if (!$access['allowed']) {
            $this->addFlash('error', 'Acces non autorise.');

            return $this->redirectToRoute('app_sorties_show', ['id' => $id]);
        }

        if (!$access['isAdmin'] && !$access['isCreator']) {
            $this->addFlash('error', 'Seuls les admins du groupe peuvent creer et assigner des taches.');

            return $this->redirectToRoute('app_sorties_chat', ['id' => $id]);
        }

        $title = trim((string) $request->request->get('title', ''));
        $description = trim((string) $request->request->get('description', ''));
        $assignedTo = (int) $request->request->get('assigned_to', 0);

        if ($title === '') {
            $this->addFlash('warning', 'Le titre de la tache est obligatoire.');

            return $this->redirectToRoute('app_sorties_chat', ['id' => $id]);
        }

        if ($assignedTo > 0 && !$this->isAcceptedParticipant($connection, $id, $assignedTo)) {
            $assignedTo = 0;
        }

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $connection->insert('sortie_task', [
            'annonce_id' => $id,
            'created_by' => (int) $user->getId(),
            'title' => $title,
            'description' => $description !== '' ? $description : null,
            'status' => 'TODO',
            'assigned_to' => $assignedTo > 0 ? $assignedTo : null,
            'created_at' => $now,
            'updated_at' => $now,
            'done_at' => null,
        ]);

        $taskId = (int) $connection->lastInsertId();
        $groupId = $connection->fetchOne('SELECT id FROM chat_groupe WHERE annonce_id = ? LIMIT 1', [$id]);
        $assignedName = null;
        if ($assignedTo > 0) {
            $assignedName = $connection->fetchOne(
                "SELECT TRIM(CONCAT(COALESCE(prenom, ''), ' ', COALESCE(nom, ''))) FROM user WHERE id = ? LIMIT 1",
                [$assignedTo]
            );
        }

        $taskContentLines = [sprintf('Nouvelle tache : %s', $title)];
        if ($description !== '') {
            $taskContentLines[] = $description;
        }
        if (is_string($assignedName) && trim($assignedName) !== '') {
            $taskContentLines[] = sprintf('Assignee a : %s', trim($assignedName));
        }

        $connection->insert('chat_message', [
            'annonce_id' => $id,
            'chat_groupe_id' => $groupId !== false && $groupId !== null ? (int) $groupId : null,
            'sender_id' => (int) $user->getId(),
            'content' => implode("\n", $taskContentLines),
            'message_type' => 'TASK',
            'poll_id' => null,
            'meta_json' => json_encode(['task_id' => $taskId], JSON_THROW_ON_ERROR),
            'sent_at' => $now,
            'edited_at' => null,
            'deleted_at' => null,
            'attachment_path' => null,
            'attachment_type' => null,
        ]);

        $messageId = (int) $connection->lastInsertId();
        $payload = $this->chatMessageRepository->findMessagePayload($messageId, (int) $user->getId(), count($this->fetchMembers($connection, $id)));
        if ($payload !== null) {
            $this->chatRealtimePublisher->publishChatEvent($id, [
                'event' => 'message.created',
                'message' => $payload,
            ]);
        }

        return $this->redirectToRoute('app_sorties_chat', ['id' => $id]);
    }

    #[Route('/sorties/{id}/chat/tasks/{taskId}/toggle', name: 'app_sorties_chat_task_toggle', requirements: ['id' => '\d+', 'taskId' => '\d+'], methods: ['POST'])]
    public function toggleTask(int $id, int $taskId, Request $request, Connection $connection): RedirectResponse
    {
        $user = $this->requireChatUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isChatFeatureAvailable($connection)) {
            $this->addFlash('warning', 'Le module de groupe chat n est pas encore disponible.');

            return $this->redirectToRoute($this->isGranted('ROLE_ADMIN') ? 'app_admin_participations' : 'app_sorties_show', ['id' => $id]);
        }

        if (!$this->isCsrfTokenValid('chat_task_toggle_'.$taskId, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('app_sorties_chat', ['id' => $id]);
        }

        $access = $this->loadChatAccess($connection, $id, (int) $user->getId());
        if (!$access['allowed']) {
            $this->addFlash('error', 'Acces non autorise.');

            return $this->redirectToRoute('app_sorties_show', ['id' => $id]);
        }

        $task = $connection->fetchAssociative(
            'SELECT st.id, st.status, st.assigned_to, an.user_id AS sortie_creator_id
             FROM sortie_task st
             INNER JOIN annonce_sortie an ON an.id = st.annonce_id
             WHERE st.id = ? AND st.annonce_id = ? LIMIT 1',
            [$taskId, $id]
        );

        if (!$task) {
            $this->addFlash('warning', 'Tache introuvable.');

            return $this->redirectToRoute('app_sorties_chat', ['id' => $id]);
        }

        $assignedTo      = $task['assigned_to'] !== null ? (int) $task['assigned_to'] : null;
        $currentUserId = (int) $user->getId();
        $canManageTasks = $access['isAdmin'] || $access['isCreator'];

        $canToggle = $assignedTo !== null
            ? $currentUserId === $assignedTo || $canManageTasks
            : $canManageTasks;

        if (!$canToggle) {
            $this->addFlash('error', 'Seule la personne assignée ou le créateur peut modifier le statut de cette tâche.');

            return $this->redirectToRoute('app_sorties_chat', ['id' => $id]);
        }

        $isDone = strtoupper((string) $task['status']) === 'DONE';
        $connection->update('sortie_task', [
            'status' => $isDone ? 'TODO' : 'DONE',
            'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'done_at' => $isDone ? null : (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ], ['id' => $taskId]);

        return $this->redirectToRoute('app_sorties_chat', ['id' => $id]);
    }

    private function requireChatUser(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }

    private function loadChatAccess(Connection $connection, int $sortieId, int $userId): array
    {
        $sortie = $connection->fetchAssociative(
            'SELECT s.id, s.user_id, s.titre, s.ville, s.type_activite, s.date_sortie, s.statut,
                    u.prenom, u.nom
             FROM annonce_sortie s
             LEFT JOIN user u ON u.id = s.user_id
             WHERE s.id = ? LIMIT 1',
            [$sortieId]
        );

        if (!$sortie) {
            throw $this->createNotFoundException('Sortie introuvable.');
        }

        $currentUser = $this->getUser();
        $isAdmin = $currentUser instanceof User && in_array('ROLE_ADMIN', $currentUser->getRoles(), true);
        $isCreator = (int) $sortie['user_id'] === $userId;
        $isConfirmedParticipant = (bool) $connection->fetchOne(
            "SELECT id FROM participation_annonce WHERE annonce_id = ? AND user_id = ? AND statut = 'CONFIRMEE' LIMIT 1",
            [$sortieId, $userId]
        );

        return [
            'allowed' => $isAdmin || $isCreator || $isConfirmedParticipant,
            'isAdmin' => $isAdmin,
            'isCreator' => $isCreator,
            'sortie' => $sortie,
        ];
    }

    private function fetchMembers(Connection $connection, int $sortieId): array
    {
        return $connection->fetchAllAssociative(
            "SELECT member.id, member.prenom, member.nom, member.image_url, member.role,
                    MAX(member.is_creator) AS is_creator,
                    MAX(CASE WHEN member.role = 'admin' OR member.is_creator = 1 THEN 1 ELSE 0 END) AS is_group_admin
             FROM (
                SELECT DISTINCT u.id, u.prenom, u.nom, u.imageUrl AS image_url, u.role, 0 AS is_creator
                FROM chat_groupe cg
                INNER JOIN chat_groupe_membre cgm ON cgm.chat_groupe_id = cg.id
                INNER JOIN user u ON u.id = cgm.user_id
                WHERE cg.annonce_id = ?

                UNION

                SELECT u.id, u.prenom, u.nom, u.imageUrl AS image_url, u.role, 1 AS is_creator
                FROM annonce_sortie s
                INNER JOIN user u ON u.id = s.user_id
                WHERE s.id = ?
             ) AS member
             GROUP BY member.id, member.prenom, member.nom, member.image_url, member.role
             ORDER BY is_group_admin DESC, is_creator DESC, member.prenom ASC, member.nom ASC",
            [$sortieId, $sortieId]
        );
    }

    private function fetchPolls(Connection $connection, int $sortieId, int $userId): array
    {
        $polls = $connection->fetchAllAssociative(
            'SELECT p.id, p.question, p.created_at, p.is_open, p.created_by,
                    u.prenom, u.nom, u.imageUrl AS image_url
             FROM poll p
             LEFT JOIN user u ON u.id = p.created_by
             WHERE p.annonce_id = ?
             ORDER BY p.created_at DESC, p.id DESC',
            [$sortieId]
        );

        foreach ($polls as &$poll) {
            $poll['options'] = $connection->fetchAllAssociative(
                'SELECT po.id, po.text,
                        COUNT(pv.user_id) AS votes,
                        MAX(CASE WHEN pv.user_id = ? THEN 1 ELSE 0 END) AS selected_by_me
                 FROM poll_option po
                 LEFT JOIN poll_vote pv ON pv.option_id = po.id AND pv.poll_id = po.poll_id
                 WHERE po.poll_id = ?
                 GROUP BY po.id, po.text
                 ORDER BY po.id ASC',
                [$userId, (int) $poll['id']]
            );
        }
        unset($poll);

        return $polls;
    }

    private function fetchTasks(Connection $connection, int $sortieId): array
    {
        return $connection->fetchAllAssociative(
            'SELECT st.id, st.title, st.description, st.status, st.assigned_to,
                    st.created_at, st.updated_at, st.done_at,
                    creator.prenom AS creator_prenom, creator.nom AS creator_nom,
                    assigned.prenom AS assigned_prenom, assigned.nom AS assigned_nom
             FROM sortie_task st
             LEFT JOIN user creator ON creator.id = st.created_by
             LEFT JOIN user assigned ON assigned.id = st.assigned_to
             WHERE st.annonce_id = ?
             ORDER BY CASE WHEN st.status = "DONE" THEN 1 ELSE 0 END ASC, st.id DESC',
            [$sortieId]
        );
    }

    private function fetchAssignableParticipants(Connection $connection, int $sortieId): array
    {
        return $connection->fetchAllAssociative(
            "SELECT u.id, u.prenom, u.nom
             FROM participation_annonce pa
             INNER JOIN user u ON u.id = pa.user_id
             WHERE pa.annonce_id = ? AND pa.statut = 'CONFIRMEE'
             ORDER BY u.prenom ASC, u.nom ASC",
            [$sortieId]
        );
    }

    private function isAcceptedParticipant(Connection $connection, int $sortieId, int $userId): bool
    {
        return (bool) $connection->fetchOne(
            "SELECT id FROM participation_annonce WHERE annonce_id = ? AND user_id = ? AND statut = 'CONFIRMEE' LIMIT 1",
            [$sortieId, $userId]
        );
    }

    private function isChatMember(Connection $connection, int $sortieId, int $userId): bool
    {
        $isCreator = (bool) $connection->fetchOne(
            'SELECT id FROM annonce_sortie WHERE id = ? AND user_id = ? LIMIT 1',
            [$sortieId, $userId]
        );

        if ($isCreator) {
            return true;
        }

        return (bool) $connection->fetchOne(
            "SELECT id FROM participation_annonce WHERE annonce_id = ? AND user_id = ? AND statut = 'CONFIRMEE' LIMIT 1",
            [$sortieId, $userId]
        );
    }

    private function isChatFeatureAvailable(Connection $connection): bool
    {
        try {
            return $connection->fetchOne("SHOW TABLES LIKE 'chat_groupe'") !== false;
        } catch (\Throwable) {
            return false;
        }
    }

    private function isApiRequest(Request $request): bool
    {
        return $request->isXmlHttpRequest()
            || str_contains((string) $request->headers->get('Accept', ''), 'application/json');
    }

    private function chatErrorResponse(Request $request, string $message, int $sortieId, int $status = Response::HTTP_BAD_REQUEST): Response
    {
        if ($this->isApiRequest($request)) {
            return $this->json(['error' => $message], $status);
        }

        $this->addFlash($status >= 400 ? 'error' : 'warning', $message);

        return $this->redirectToRoute('app_sorties_chat', ['id' => $sortieId]);
    }

    private function storeAttachment(mixed $attachment): array
    {
        if (!$attachment instanceof UploadedFile) {
            return [null, null];
        }

        if (!$attachment->isValid()) {
            return [null, null];
        }

        $uploadDir = $this->projectDir.'/public/uploads/chat';
        if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            return [null, null];
        }

        $extension = $attachment->guessExtension() ?: $attachment->getClientOriginalExtension() ?: 'bin';
        $filename = sprintf('chat-%s.%s', bin2hex(random_bytes(10)), preg_replace('/[^a-zA-Z0-9]+/', '', (string) $extension));
        $attachment->move($uploadDir, $filename);

        return ['/uploads/chat/'.$filename, $attachment->getMimeType() ?: 'application/octet-stream'];
    }
}
