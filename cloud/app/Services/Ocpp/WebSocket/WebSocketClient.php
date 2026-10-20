<?php

namespace App\Services\Ocpp\WebSocket;

/**
 * کلاینت سادهٔ WebSocket برای شبیه‌ساز شارژر و تست‌ها.
 */
final class WebSocketClient
{
    /** @var resource */
    private $stream;

    private string $buffer = '';

    private function __construct($stream)
    {
        $this->stream = $stream;
    }

    /** Wraps an already upgraded socket (used by tests). */
    public static function fromStream($stream, string $buffered = ''): self
    {
        $client = new self($stream);
        $client->buffer = $buffered;

        return $client;
    }

    /** @param array<string, string> $headers */
    public static function connect(string $url, array $headers = [], float $timeout = 10.0): self
    {
        $parts = parse_url($url);
        $scheme = $parts['scheme'] ?? 'ws';
        $host = $parts['host'] ?? '127.0.0.1';
        $port = $parts['port'] ?? ($scheme === 'wss' ? 443 : 80);
        $path = ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');
        $transport = $scheme === 'wss' ? 'tls' : 'tcp';

        $stream = @stream_socket_client("{$transport}://{$host}:{$port}", $errno, $errstr, $timeout);
        if ($stream === false) {
            throw new \RuntimeException("Connect failed: {$errstr} ({$errno})");
        }

        $key = base64_encode(random_bytes(16));
        $request = "GET {$path} HTTP/1.1\r\nHost: {$host}:{$port}\r\nUpgrade: websocket\r\nConnection: Upgrade\r\n"
            ."Sec-WebSocket-Key: {$key}\r\nSec-WebSocket-Version: 13\r\n";
        foreach ($headers as $name => $value) {
            $request .= "{$name}: {$value}\r\n";
        }
        fwrite($stream, $request."\r\n");

        stream_set_timeout($stream, (int) ceil($timeout));
        $response = '';
        while (! str_contains($response, "\r\n\r\n")) {
            $chunk = fread($stream, 1024);
            if ($chunk === false || $chunk === '') {
                throw new \RuntimeException('Handshake failed: '.trim($response));
            }
            $response .= $chunk;
        }

        [$head, $rest] = explode("\r\n\r\n", $response, 2);
        if (! preg_match('#^HTTP/1\.1 101#', $head)) {
            throw new \RuntimeException('Handshake rejected: '.strtok($head, "\r\n"));
        }

        $expected = base64_encode(sha1($key.'258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
        if (! str_contains($head, $expected)) {
            throw new \RuntimeException('Invalid Sec-WebSocket-Accept');
        }

        $client = new self($stream);
        $client->buffer = $rest;

        return $client;
    }

    public function send(string $text): void
    {
        fwrite($this->stream, Frame::encode($text, Frame::OP_TEXT, true));
    }

    /** Returns the next text message, or null on timeout. */
    public function receive(float $timeout): ?string
    {
        $deadline = microtime(true) + $timeout;

        while (true) {
            while (($frame = Frame::decode($this->buffer, 10_485_760)) !== null) {
                if ($frame->opcode === Frame::OP_PING) {
                    fwrite($this->stream, Frame::encode($frame->payload, Frame::OP_PONG, true));

                    continue;
                }
                if ($frame->opcode === Frame::OP_CLOSE) {
                    throw new \RuntimeException('Connection closed by server');
                }
                if ($frame->opcode === Frame::OP_TEXT || $frame->opcode === Frame::OP_BINARY) {
                    return $frame->payload;
                }
            }

            $remaining = $deadline - microtime(true);
            if ($remaining <= 0) {
                return null;
            }

            $read = [$this->stream];
            $write = $except = null;
            $sec = (int) $remaining;
            if (@stream_select($read, $write, $except, $sec, (int) (($remaining - $sec) * 1_000_000)) < 1) {
                return null;
            }

            $chunk = fread($this->stream, 65536);
            if ($chunk === false || ($chunk === '' && feof($this->stream))) {
                throw new \RuntimeException('Connection closed');
            }
            $this->buffer .= $chunk;
        }
    }

    public function close(): void
    {
        if (is_resource($this->stream)) {
            @fwrite($this->stream, Frame::encode(pack('n', 1000), Frame::OP_CLOSE, true));
            @fclose($this->stream);
        }
    }
}
