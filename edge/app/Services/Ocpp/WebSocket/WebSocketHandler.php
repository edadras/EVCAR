<?php

namespace App\Services\Ocpp\WebSocket;

interface WebSocketHandler
{
    /**
     * تصمیم دربارهٔ پذیرش اتصال جدید.
     *
     * @return array{accept: bool, status?: int, protocol?: string|null}
     */
    public function onHandshake(Connection $connection): array;

    public function onOpen(Connection $connection): void;

    public function onMessage(Connection $connection, string $message): void;

    public function onClose(Connection $connection, string $reason): void;

    /** Called periodically (about every 200ms) from the event loop. */
    public function tick(): void;
}
