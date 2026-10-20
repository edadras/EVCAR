<?php

namespace App\Services\App;

use App\Models\Connector;
use App\Models\QrToken;
use App\Models\User;

class ChargingAuthorizationService
{
    private const TTL_MINUTES = 20;

    private const SESSION_KEY = 'sc_connector_grants';

    /** @return array{type: string, connector_id: int, connector_uuid: string}|null */
    public function tryGrantFromQrToken(?string $qrToken): ?array
    {
        if (! $qrToken || ! auth()->check()) {
            return null;
        }

        $qr = QrToken::query()
            ->where('token', $qrToken)
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->first();

        if (! $qr?->connector_id) {
            return null;
        }

        $connector = Connector::find($qr->connector_id);
        if (! $connector) {
            return null;
        }

        $this->grant(auth()->user(), $connector, 'qr');

        return [
            'type' => 'qr',
            'connector_id' => $connector->id,
            'connector_uuid' => $connector->uuid,
        ];
    }

    public function grant(User $user, Connector $connector, string $type): void
    {
        $grants = session(self::SESSION_KEY, []);
        $grants[$connector->id] = [
            'type' => $type,
            'connector_id' => $connector->id,
            'connector_uuid' => $connector->uuid,
            'user_id' => $user->id,
            'expires_at' => now()->addMinutes(self::TTL_MINUTES)->timestamp,
        ];
        session([self::SESSION_KEY => $grants]);
    }

    public function canStart(User $user, Connector $connector): bool
    {
        $grant = $this->activeGrant($connector->id);

        return $grant !== null && (int) ($grant['user_id'] ?? 0) === $user->id;
    }

    public function grantTypeFor(Connector $connector): ?string
    {
        return $this->activeGrant($connector->id)['type'] ?? null;
    }

    public function denyMessage(): string
    {
        return 'برای شروع شارژ، QR کانکتور را اسکن کنید، کارت RFID را بزنید، یا پلاک خودرو توسط دوربین ثبت شود.';
    }

    /** @return array<string, mixed>|null */
    public function activeGrant(int $connectorId): ?array
    {
        $grant = session(self::SESSION_KEY, [])[$connectorId] ?? null;
        if (! $grant) {
            return null;
        }
        if (($grant['expires_at'] ?? 0) < time()) {
            return null;
        }

        return $grant;
    }
}
