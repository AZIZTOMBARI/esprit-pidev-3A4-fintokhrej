<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

class ChatRealtimePublisher
{
    public function __construct(
        private readonly HubInterface $hub,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function publishChatEvent(int $sortieId, array $payload): void
    {
        try {
            $this->hub->publish(new Update(
                sprintf('sortie-chat-%d', $sortieId),
                json_encode($payload, JSON_THROW_ON_ERROR)
            ));
        } catch (\Throwable $exception) {
            $this->logger->warning('Unable to publish chat realtime event.', [
                'sortie_id' => $sortieId,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
