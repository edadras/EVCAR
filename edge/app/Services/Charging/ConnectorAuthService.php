<?php

namespace App\Services\Charging;

use App\Models\CachedQrToken;
use App\Models\CachedRfidCard;
use App\Models\CachedUser;
use App\Models\ConnectorAuthGrant;
use App\Models\EdgeConnector;

class ConnectorAuthService
{
    private const TTL_MINUTES = 20;

    public function grant(
        CachedUser $user,
        EdgeConnector $connector,
        string $type,
        ?string $sourceUuid = null,
        ?array $meta = null,
    ): ConnectorAuthGrant {
        ConnectorAuthGrant::where('cached_user_uuid', $user->uuid)
            ->where('connector_id', $connector->id)
            ->where('expires_at', '<=', now())
            ->delete();

        return ConnectorAuthGrant::create([
            'cached_user_uuid' => $user->uuid,
            'connector_id' => $connector->id,
            'grant_type' => $type,
            'source_uuid' => $sourceUuid,
            'granted_at' => now(),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
            'meta' => $meta,
        ]);
    }

    public function canStart(CachedUser $user, EdgeConnector $connector): bool
    {
        return ConnectorAuthGrant::query()
            ->where('cached_user_uuid', $user->uuid)
            ->where('connector_id', $connector->id)
            ->where('expires_at', '>', now())
            ->exists();
    }

    /** @return list<array<string, mixed>> */
    public function grantsForUser(CachedUser $user): array
    {
        return ConnectorAuthGrant::query()
            ->where('cached_user_uuid', $user->uuid)
            ->where('expires_at', '>', now())
            ->with('connector.charger')
            ->latest('id')
            ->get()
            ->map(fn (ConnectorAuthGrant $g) => [
                'grant_type' => $g->grant_type,
                'connector_uuid' => $g->connector?->uuid,
                'cloud_connector_uuid' => $g->connector?->cloud_connector_uuid,
                'connector_number' => $g->connector?->connector_number,
                'charge_point_id' => $g->connector?->charger?->charge_point_id,
                'expires_at' => $g->expires_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    public function activeGrant(CachedUser $user, EdgeConnector $connector): ?ConnectorAuthGrant
    {
        return ConnectorAuthGrant::query()
            ->where('cached_user_uuid', $user->uuid)
            ->where('connector_id', $connector->id)
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();
    }

    public function grantFromQr(CachedUser $user, string $connectorUuid, ?string $qrToken = null): ConnectorAuthGrant
    {
        $connector = $this->resolveConnector($connectorUuid);

        if ($qrToken) {
            $qr = CachedQrToken::query()
                ->where('token', $qrToken)
                ->where('status', 'active')
                ->where(function ($q) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->first();

            if (! $qr) {
                throw new \RuntimeException('بارکد QR نامعتبر یا منقضی است.');
            }

            if ($qr->cloud_connector_uuid && $connector->cloud_connector_uuid !== $qr->cloud_connector_uuid) {
                throw new \RuntimeException('این QR متعلق به کانکتور دیگری است.');
            }
        }

        return $this->grant($user, $connector, 'qr', null, ['qr_token' => $qrToken]);
    }

    public function grantFromRfid(CachedUser $user, string $connectorUuid, string $idTag): ConnectorAuthGrant
    {
        $connector = $this->resolveConnector($connectorUuid);

        $card = CachedRfidCard::query()
            ->where('cached_user_uuid', $user->uuid)
            ->where('id_tag', $idTag)
            ->where('status', 'active')
            ->first();

        if (! $card) {
            throw new \RuntimeException('کارت RFID فعال برای این حساب یافت نشد.');
        }

        return $this->grant($user, $connector, 'rfid', $card->uuid, ['id_tag' => $idTag]);
    }

    public function grantFromAnpr(CachedUser $user, EdgeConnector $connector, string $detectionUuid): ConnectorAuthGrant
    {
        return $this->grant($user, $connector, 'anpr', $detectionUuid);
    }

    public function connectorUuidForQr(string $qrToken): string
    {
        $qr = CachedQrToken::query()->where('token', $qrToken)->where('status', 'active')->first();
        if (! $qr?->cloud_connector_uuid) {
            throw new \RuntimeException('بارکد QR نامعتبر یا منقضی است.');
        }

        return $qr->cloud_connector_uuid;
    }

    public function resolveConnector(string $connectorUuid): EdgeConnector
    {
        return EdgeConnector::with('charger')
            ->where(function ($q) use ($connectorUuid) {
                $q->where('uuid', $connectorUuid)
                    ->orWhere('cloud_connector_uuid', $connectorUuid);
            })
            ->firstOrFail();
    }

    public function revokeAllForUser(CachedUser $user, ?int $connectorId = null): int
    {
        $query = ConnectorAuthGrant::query()->where('cached_user_uuid', $user->uuid);
        if ($connectorId !== null) {
            $query->where('connector_id', $connectorId);
        }

        return $query->delete();
    }

    public function denyMessage(): string
    {
        return 'برای شروع شارژ، ابتدا QR کانکتور را اسکن کنید، کارت RFID را بزنید، یا پلاک خودرو توسط دوربین ثبت شود.';
    }
}
