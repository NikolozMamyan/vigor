<?php

namespace App\Service\Auth;

use Psr\Cache\CacheItemPoolInterface;

final class MobileAuthTicketStore
{
    private const PREFIX = 'mobile_auth_';

    public function __construct(private readonly CacheItemPoolInterface $cache)
    {
    }

    public function create(string $plainToken, string $deviceId, \DateTimeInterface $expiresAt): string
    {
        $ticket = bin2hex(random_bytes(32));
        $item = $this->cache->getItem(self::PREFIX.hash('sha256', $ticket));
        $item->set([
            'plainToken' => $plainToken,
            'deviceId' => $deviceId,
            'expiresAt' => $expiresAt->getTimestamp(),
        ]);
        $item->expiresAfter(300);
        $this->cache->save($item);

        return $ticket;
    }

    /**
     * @return array{plainToken: string, deviceId: string, expiresAt: \DateTimeImmutable}|null
     */
    public function consume(string $ticket): ?array
    {
        if ('' === trim($ticket)) {
            return null;
        }

        $key = self::PREFIX.hash('sha256', $ticket);
        $item = $this->cache->getItem($key);

        if (!$item->isHit()) {
            return null;
        }

        $payload = $item->get();
        $this->cache->deleteItem($key);

        if (!\is_array($payload)) {
            return null;
        }

        $plainToken = (string) ($payload['plainToken'] ?? '');
        $deviceId = (string) ($payload['deviceId'] ?? '');
        $expiresAt = (int) ($payload['expiresAt'] ?? 0);

        if ('' === $plainToken || '' === $deviceId || $expiresAt <= time()) {
            return null;
        }

        return [
            'plainToken' => $plainToken,
            'deviceId' => $deviceId,
            'expiresAt' => (new \DateTimeImmutable())->setTimestamp($expiresAt),
        ];
    }
}
