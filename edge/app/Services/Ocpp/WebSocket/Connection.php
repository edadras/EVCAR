<?php

namespace App\Services\Ocpp\WebSocket;

/**
 * یک اتصال WebSocket سمت سرور (غیرمسدود).
 */
final class Connection
{
    public string $readBuffer = '';

    public string $writeBuffer = '';

    public bool $handshakeDone = false;

    public bool $closing = false;

    public string $fragments = '';

    public ?int $fragmentOpcode = null;

    public float $lastActivity;

    public float $lastPingAt;

    public string $path = '';

    /** @var array<string, string> */
    public array $headers = [];

    /** @var array<string, mixed> */
    public array $attributes = [];

    /** @param resource $stream */
    public function __construct(
        public readonly int $id,
        public $stream,
        public readonly string $remoteAddress,
    ) {
        $this->lastActivity = microtime(true);
        $this->lastPingAt = $this->lastActivity;
    }

    public function sendText(string $text): void
    {
        if ($this->closing) {
            return;
        }

        $this->writeBuffer .= Frame::encode($text, Frame::OP_TEXT);
        $this->flush();
    }

    public function sendFrame(string $payload, int $opcode): void
    {
        $this->writeBuffer .= Frame::encode($payload, $opcode);
        $this->flush();
    }

    public function close(int $code = 1000, string $reason = ''): void
    {
        if ($this->closing) {
            return;
        }

        if ($this->handshakeDone) {
            $this->writeBuffer .= Frame::encode(pack('n', $code).$reason, Frame::OP_CLOSE);
        }
        $this->closing = true;
        $this->flush();
    }

    /** Writes as much of the pending buffer as the socket accepts. */
    public function flush(): void
    {
        if ($this->writeBuffer === '' || ! is_resource($this->stream)) {
            return;
        }

        $written = @fwrite($this->stream, $this->writeBuffer);
        if ($written === false) {
            $this->writeBuffer = '';
            $this->closing = true;

            return;
        }

        $this->writeBuffer = (string) substr($this->writeBuffer, $written);
    }
}
