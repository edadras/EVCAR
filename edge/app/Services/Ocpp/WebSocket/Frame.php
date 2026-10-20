<?php

namespace App\Services\Ocpp\WebSocket;

/**
 * کدگذاری/کدگشایی فریم‌های WebSocket (RFC 6455).
 */
final class Frame
{
    public const OP_CONTINUATION = 0x0;

    public const OP_TEXT = 0x1;

    public const OP_BINARY = 0x2;

    public const OP_CLOSE = 0x8;

    public const OP_PING = 0x9;

    public const OP_PONG = 0xA;

    public function __construct(
        public readonly bool $fin,
        public readonly int $opcode,
        public readonly string $payload,
    ) {}

    public static function encode(string $payload, int $opcode = self::OP_TEXT, bool $mask = false): string
    {
        $length = strlen($payload);
        $header = chr(0x80 | ($opcode & 0x0F));
        $maskBit = $mask ? 0x80 : 0x00;

        if ($length < 126) {
            $header .= chr($maskBit | $length);
        } elseif ($length <= 0xFFFF) {
            $header .= chr($maskBit | 126).pack('n', $length);
        } else {
            $header .= chr($maskBit | 127).pack('J', $length);
        }

        if (! $mask) {
            return $header.$payload;
        }

        $key = random_bytes(4);

        return $header.$key.self::applyMask($payload, $key);
    }

    /**
     * یک فریم کامل را از ابتدای بافر جدا می‌کند؛ اگر داده کافی نباشد null برمی‌گرداند.
     *
     * @throws \RuntimeException برای فریم نامعتبر یا بزرگ‌تر از حد مجاز
     */
    public static function decode(string &$buffer, int $maxPayload): ?self
    {
        $available = strlen($buffer);
        if ($available < 2) {
            return null;
        }

        $b1 = ord($buffer[0]);
        $b2 = ord($buffer[1]);
        $fin = (bool) ($b1 & 0x80);
        $opcode = $b1 & 0x0F;
        $masked = (bool) ($b2 & 0x80);
        $length = $b2 & 0x7F;
        $offset = 2;

        if ($b1 & 0x70) {
            throw new \RuntimeException('RSV bits must be zero');
        }

        if ($length === 126) {
            if ($available < 4) {
                return null;
            }
            $length = unpack('n', substr($buffer, 2, 2))[1];
            $offset = 4;
        } elseif ($length === 127) {
            if ($available < 10) {
                return null;
            }
            $length = unpack('J', substr($buffer, 2, 8))[1];
            $offset = 10;
        }

        if ($length > $maxPayload) {
            throw new \RuntimeException('Frame too large: '.$length);
        }

        if ($opcode >= 0x8 && ($length > 125 || ! $fin)) {
            throw new \RuntimeException('Invalid control frame');
        }

        $key = '';
        if ($masked) {
            if ($available < $offset + 4) {
                return null;
            }
            $key = substr($buffer, $offset, 4);
            $offset += 4;
        }

        if ($available < $offset + $length) {
            return null;
        }

        $payload = substr($buffer, $offset, $length);
        $buffer = (string) substr($buffer, $offset + $length);

        if ($masked) {
            $payload = self::applyMask($payload, $key);
        }

        return new self($fin, $opcode, $payload);
    }

    private static function applyMask(string $payload, string $key): string
    {
        $length = strlen($payload);
        if ($length === 0) {
            return '';
        }

        $mask = str_repeat($key, intdiv($length, 4) + 1);

        return $payload ^ substr($mask, 0, $length);
    }
}
