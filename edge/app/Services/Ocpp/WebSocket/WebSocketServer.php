<?php

namespace App\Services\Ocpp\WebSocket;

use Psr\Log\LoggerInterface;

/**
 * سرور WebSocket تک‌پروسه‌ای مبتنی بر stream_select (بدون وابستگی خارجی).
 * برای تعداد محدود شارژرهای یک ایستگاه طراحی شده است.
 */
final class WebSocketServer
{
    private const GUID = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';

    private const MAX_HANDSHAKE_BYTES = 16384;

    /** @var resource|null */
    private $server = null;

    /** @var array<int, Connection> */
    private array $connections = [];

    private int $nextId = 1;

    private bool $running = false;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly WebSocketHandler $handler,
        private readonly LoggerInterface $logger,
        private readonly int $maxMessageBytes = 1_048_576,
        private readonly int $pingIntervalSeconds = 60,
        private readonly int $idleTimeoutSeconds = 600,
    ) {}

    public function listen(): void
    {
        $context = stream_context_create(['socket' => ['so_reuseaddr' => true, 'backlog' => 128]]);
        $address = sprintf('tcp://%s:%d', $this->host, $this->port);
        $server = @stream_socket_server($address, $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);

        if ($server === false) {
            throw new \RuntimeException("Cannot listen on {$address}: {$errstr} ({$errno})");
        }

        stream_set_blocking($server, false);
        $this->server = $server;
    }

    public function port(): int
    {
        if (! $this->server) {
            return $this->port;
        }

        $name = (string) stream_socket_get_name($this->server, false);

        return (int) substr($name, strrpos($name, ':') + 1);
    }

    public function stop(): void
    {
        $this->running = false;
    }

    public function run(): void
    {
        if (! $this->server) {
            $this->listen();
        }

        $this->running = true;
        $lastTick = 0.0;

        while ($this->running) {
            $this->runOnce(0.2);

            $now = microtime(true);
            if ($now - $lastTick >= 0.2) {
                $lastTick = $now;
                try {
                    $this->handler->tick();
                } catch (\Throwable $e) {
                    $this->logger->error('ocpp.ws.tick_failed', ['error' => $e->getMessage()]);
                }
                $this->keepAlive($now);
            }

            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }
        }

        foreach ($this->connections as $connection) {
            $connection->close(1001, 'Server shutdown');
            $this->drop($connection, 'server shutdown');
        }

        if ($this->server) {
            fclose($this->server);
            $this->server = null;
        }
    }

    public function runOnce(float $timeoutSeconds): void
    {
        $read = [$this->server];
        $write = [];
        foreach ($this->connections as $connection) {
            $read[] = $connection->stream;
            if ($connection->writeBuffer !== '') {
                $write[] = $connection->stream;
            }
        }
        $except = null;

        $seconds = (int) $timeoutSeconds;
        $micro = (int) (($timeoutSeconds - $seconds) * 1_000_000);
        $changed = @stream_select($read, $write, $except, $seconds, $micro);

        if ($changed === false || $changed === 0) {
            $this->reapClosing();

            return;
        }

        foreach ($read as $stream) {
            if ($stream === $this->server) {
                $this->accept();

                continue;
            }

            $connection = $this->find($stream);
            if ($connection) {
                $this->read($connection);
            }
        }

        foreach ($write as $stream) {
            $this->find($stream)?->flush();
        }

        $this->reapClosing();
    }

    private function accept(): void
    {
        $stream = @stream_socket_accept($this->server, 0, $peer);
        if ($stream === false) {
            return;
        }

        stream_set_blocking($stream, false);
        $connection = new Connection($this->nextId++, $stream, (string) $peer);
        $this->connections[$connection->id] = $connection;
    }

    private function read(Connection $connection): void
    {
        $data = @fread($connection->stream, 65536);

        if ($data === false || ($data === '' && feof($connection->stream))) {
            $this->drop($connection, 'connection closed by peer');

            return;
        }

        $connection->lastActivity = microtime(true);
        $connection->readBuffer .= $data;

        try {
            if (! $connection->handshakeDone) {
                $this->handshake($connection);
                if (! $connection->handshakeDone) {
                    return;
                }
            }

            while (! $connection->closing && ($frame = Frame::decode($connection->readBuffer, $this->maxMessageBytes)) !== null) {
                $this->handleFrame($connection, $frame);
            }
        } catch (\Throwable $e) {
            $this->logger->warning('ocpp.ws.protocol_error', ['connection' => $connection->id, 'error' => $e->getMessage()]);
            $connection->close(1002, 'Protocol error');
        }
    }

    private function handshake(Connection $connection): void
    {
        $end = strpos($connection->readBuffer, "\r\n\r\n");
        if ($end === false) {
            if (strlen($connection->readBuffer) > self::MAX_HANDSHAKE_BYTES) {
                $this->rejectHttp($connection, 431, 'Request Header Fields Too Large');
            }

            return;
        }

        $request = substr($connection->readBuffer, 0, $end);
        $connection->readBuffer = (string) substr($connection->readBuffer, $end + 4);

        $lines = explode("\r\n", $request);
        $requestLine = array_shift($lines);
        if (! preg_match('#^GET\s+(\S+)\s+HTTP/1\.[01]$#i', (string) $requestLine, $m)) {
            $this->rejectHttp($connection, 400, 'Bad Request');

            return;
        }

        $headers = [];
        foreach ($lines as $line) {
            $pos = strpos($line, ':');
            if ($pos === false) {
                continue;
            }
            $headers[strtolower(trim(substr($line, 0, $pos)))] = trim(substr($line, $pos + 1));
        }

        $key = $headers['sec-websocket-key'] ?? '';
        if (stripos($headers['upgrade'] ?? '', 'websocket') === false || $key === '') {
            $this->rejectHttp($connection, 400, 'Bad Request');

            return;
        }

        if (($headers['sec-websocket-version'] ?? '13') !== '13') {
            $this->rejectHttp($connection, 426, 'Upgrade Required', "Sec-WebSocket-Version: 13\r\n");

            return;
        }

        $connection->path = (string) parse_url($m[1], PHP_URL_PATH);
        $connection->headers = $headers;

        $decision = $this->handler->onHandshake($connection);
        if (! ($decision['accept'] ?? false)) {
            $status = (int) ($decision['status'] ?? 404);
            $extra = $status === 401 ? "WWW-Authenticate: Basic realm=\"OCPP\"\r\n" : '';
            $this->rejectHttp($connection, $status, $status === 401 ? 'Unauthorized' : 'Not Found', $extra);

            return;
        }

        $accept = base64_encode(sha1($key.self::GUID, true));
        $response = "HTTP/1.1 101 Switching Protocols\r\n"
            ."Upgrade: websocket\r\n"
            ."Connection: Upgrade\r\n"
            ."Sec-WebSocket-Accept: {$accept}\r\n";
        if (! empty($decision['protocol'])) {
            $response .= 'Sec-WebSocket-Protocol: '.$decision['protocol']."\r\n";
        }
        $response .= "\r\n";

        $connection->writeBuffer .= $response;
        $connection->flush();
        $connection->handshakeDone = true;

        $this->handler->onOpen($connection);
    }

    private function rejectHttp(Connection $connection, int $status, string $text, string $extraHeaders = ''): void
    {
        $connection->writeBuffer .= "HTTP/1.1 {$status} {$text}\r\n{$extraHeaders}Content-Length: 0\r\nConnection: close\r\n\r\n";
        $connection->closing = true;
        $connection->flush();
    }

    private function handleFrame(Connection $connection, Frame $frame): void
    {
        switch ($frame->opcode) {
            case Frame::OP_PING:
                $connection->sendFrame($frame->payload, Frame::OP_PONG);

                return;
            case Frame::OP_PONG:
                return;
            case Frame::OP_CLOSE:
                $code = strlen($frame->payload) >= 2 ? unpack('n', substr($frame->payload, 0, 2))[1] : 1000;
                $connection->close((int) $code);

                return;
            case Frame::OP_TEXT:
            case Frame::OP_BINARY:
                if ($connection->fragmentOpcode !== null) {
                    throw new \RuntimeException('New data frame before previous message completed');
                }
                if (! $frame->fin) {
                    $connection->fragmentOpcode = $frame->opcode;
                    $connection->fragments = $frame->payload;

                    return;
                }
                $this->dispatch($connection, $frame->payload);

                return;
            case Frame::OP_CONTINUATION:
                if ($connection->fragmentOpcode === null) {
                    throw new \RuntimeException('Unexpected continuation frame');
                }
                $connection->fragments .= $frame->payload;
                if (strlen($connection->fragments) > $this->maxMessageBytes) {
                    throw new \RuntimeException('Message too large');
                }
                if ($frame->fin) {
                    $message = $connection->fragments;
                    $connection->fragments = '';
                    $connection->fragmentOpcode = null;
                    $this->dispatch($connection, $message);
                }

                return;
            default:
                throw new \RuntimeException('Unknown opcode '.$frame->opcode);
        }
    }

    private function dispatch(Connection $connection, string $message): void
    {
        try {
            $this->handler->onMessage($connection, $message);
        } catch (\Throwable $e) {
            $this->logger->error('ocpp.ws.handler_failed', [
                'connection' => $connection->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function keepAlive(float $now): void
    {
        foreach ($this->connections as $connection) {
            if (! $connection->handshakeDone) {
                if ($now - $connection->lastActivity > 15) {
                    $connection->closing = true;
                }

                continue;
            }

            if ($now - $connection->lastActivity > $this->idleTimeoutSeconds) {
                $connection->close(1001, 'Idle timeout');

                continue;
            }

            if ($this->pingIntervalSeconds > 0 && $now - $connection->lastPingAt >= $this->pingIntervalSeconds) {
                $connection->lastPingAt = $now;
                $connection->sendFrame('', Frame::OP_PING);
            }
        }
    }

    private function reapClosing(): void
    {
        foreach ($this->connections as $connection) {
            if ($connection->closing && $connection->writeBuffer === '') {
                $this->drop($connection, 'closed');
            }
        }
    }

    private function drop(Connection $connection, string $reason): void
    {
        if (! isset($this->connections[$connection->id])) {
            return;
        }

        unset($this->connections[$connection->id]);
        if (is_resource($connection->stream)) {
            @fclose($connection->stream);
        }

        if ($connection->handshakeDone) {
            try {
                $this->handler->onClose($connection, $reason);
            } catch (\Throwable $e) {
                $this->logger->error('ocpp.ws.close_failed', ['error' => $e->getMessage()]);
            }
        }
    }

    /** @param resource $stream */
    private function find($stream): ?Connection
    {
        foreach ($this->connections as $connection) {
            if ($connection->stream === $stream) {
                return $connection;
            }
        }

        return null;
    }
}
