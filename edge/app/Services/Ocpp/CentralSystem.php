<?php

namespace App\Services\Ocpp;

use App\Models\EdgeCharger;
use App\Models\OcppCall;
use App\Models\OcppMessageLog;
use App\Services\Ocpp\WebSocket\Connection;
use App\Services\Ocpp\WebSocket\WebSocketHandler;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * سرور مرکزی OCPP 1.6J (JSON over WebSocket) برای شارژرهای ایستگاه.
 *
 * آدرس اتصال شارژر: ws://<edge>:<port>/ocpp/<ChargePointId>
 * (هر مسیری پذیرفته می‌شود؛ آخرین بخش مسیر شناسهٔ شارژر است — سازگار با SteVe).
 */
class CentralSystem implements WebSocketHandler
{
    private const CALL = 2;

    private const CALL_RESULT = 3;

    private const CALL_ERROR = 4;

    /** @var array<string, Connection> chargePointId => connection */
    private array $connections = [];

    /** @var array<string, array{message_id:string, call_id:int, sent_at:float}> */
    private array $outstanding = [];

    private float $lastHousekeeping = 0.0;

    public function __construct(
        private ChargePointMessageHandler $handler,
        private ChargePointCommander $commander,
    ) {}

    /** Resets state left over from a previous run (crash / restart). */
    public function boot(): void
    {
        // تا شارژر دوباره وصل نشود آفلاین است؛ ابر هم از همین وضعیت مطلع می‌شود.
        foreach (EdgeCharger::where('is_online', true)->pluck('charge_point_id') as $chargePointId) {
            $this->handler->chargerDisconnected((string) $chargePointId);
        }
        EdgeCharger::query()->update(['is_online' => false]);
        OcppCall::where('status', 'sent')->update([
            'status' => 'error',
            'error' => 'OCPP server restarted',
        ]);
    }

    public function onHandshake(Connection $connection): array
    {
        $segments = array_values(array_filter(explode('/', $connection->path), fn ($s) => $s !== ''));
        $chargePointId = rawurldecode((string) end($segments));

        if ($chargePointId === '' || ! preg_match('/^[\w.\-:#*@]{1,64}$/u', $chargePointId)) {
            return ['accept' => false, 'status' => 404];
        }

        $protocols = array_map('trim', explode(',', $connection->headers['sec-websocket-protocol'] ?? ''));
        $protocols = array_filter($protocols);
        if ($protocols !== [] && ! in_array('ocpp1.6', $protocols, true)) {
            Log::warning('ocpp.handshake.unsupported_protocol', ['charge_point' => $chargePointId, 'protocols' => $protocols]);

            return ['accept' => false, 'status' => 400];
        }

        $charger = EdgeCharger::where('charge_point_id', $chargePointId)->first();
        if (! $charger) {
            if (! $this->commander->setting('allow_unknown_chargers', false)) {
                Log::warning('ocpp.handshake.unknown_charger', ['charge_point' => $chargePointId, 'from' => $connection->remoteAddress]);
                $this->log($chargePointId, 'in', 0, 'Handshake', null, 'rejected: unknown charge point');

                return ['accept' => false, 'status' => 404];
            }

            $charger = EdgeCharger::create([
                'uuid' => (string) Str::uuid(),
                'charge_point_id' => $chargePointId,
                'status' => 'inactive',
                'meta' => ['registered_by' => 'ocpp_auto'],
            ]);
        }

        if (! $this->checkBasicAuth($connection, $charger)) {
            Log::warning('ocpp.handshake.auth_failed', ['charge_point' => $chargePointId]);

            return ['accept' => false, 'status' => 401];
        }

        $connection->attributes['charge_point_id'] = $chargePointId;

        return ['accept' => true, 'protocol' => $protocols !== [] ? 'ocpp1.6' : null];
    }

    public function onOpen(Connection $connection): void
    {
        $chargePointId = (string) $connection->attributes['charge_point_id'];

        if (isset($this->connections[$chargePointId]) && $this->connections[$chargePointId] !== $connection) {
            $this->connections[$chargePointId]->close(1000, 'Replaced by new connection');
        }

        $this->connections[$chargePointId] = $connection;
        unset($this->outstanding[$chargePointId]);

        $address = $connection->headers['x-real-ip'] ?? $connection->headers['x-forwarded-for'] ?? $connection->remoteAddress;
        $this->handler->chargerConnected($chargePointId, (string) $address);
        $this->log($chargePointId, 'in', 0, 'Connected', null, (string) $address);
        Log::info('ocpp.connected', ['charge_point' => $chargePointId, 'from' => $address]);
    }

    public function onClose(Connection $connection, string $reason): void
    {
        $chargePointId = (string) ($connection->attributes['charge_point_id'] ?? '');
        if ($chargePointId === '' || ($this->connections[$chargePointId] ?? null) !== $connection) {
            return;
        }

        unset($this->connections[$chargePointId]);
        $this->failOutstanding($chargePointId, 'Connection lost');
        $this->handler->chargerDisconnected($chargePointId);
        $this->log($chargePointId, 'in', 0, 'Disconnected', null, $reason);
        Log::info('ocpp.disconnected', ['charge_point' => $chargePointId, 'reason' => $reason]);
    }

    public function onMessage(Connection $connection, string $message): void
    {
        $chargePointId = (string) $connection->attributes['charge_point_id'];
        $frame = json_decode($message, true);

        if (! is_array($frame) || ! array_is_list($frame) || count($frame) < 3 || ! is_int($frame[0])) {
            $this->log($chargePointId, 'in', 0, null, null, $message);
            $id = is_array($frame) && isset($frame[1]) && is_string($frame[1]) ? $frame[1] : '-1';
            $this->sendRaw($connection, $chargePointId, [self::CALL_ERROR, $id, 'FormationViolation', 'Invalid OCPP-J message', (object) []], null);

            return;
        }

        $type = $frame[0];
        $messageId = (string) $frame[1];

        if ($type === self::CALL) {
            $this->handleCall($connection, $chargePointId, $messageId, (string) ($frame[2] ?? ''), $frame[3] ?? []);

            return;
        }

        if ($type === self::CALL_RESULT || $type === self::CALL_ERROR) {
            $this->log($chargePointId, 'in', $type, null, $messageId, $message);
            $this->handleAnswer($chargePointId, $messageId, $type, $frame);

            return;
        }

        $this->sendRaw($connection, $chargePointId, [self::CALL_ERROR, $messageId, 'ProtocolError', 'Unknown message type', (object) []], null);
    }

    public function tick(): void
    {
        $this->expireOutstanding();
        $this->dispatchPendingCalls();

        if (microtime(true) - $this->lastHousekeeping >= 30) {
            $this->lastHousekeeping = microtime(true);
            try {
                $this->handler->housekeeping();
            } catch (\Throwable $e) {
                Log::error('ocpp.housekeeping_failed', ['error' => $e->getMessage()]);
            }
        }
    }

    /** @return list<string> */
    public function connectedChargePoints(): array
    {
        return array_keys($this->connections);
    }

    private function handleCall(Connection $connection, string $chargePointId, string $messageId, string $action, mixed $payload): void
    {
        $this->log($chargePointId, 'in', self::CALL, $action, $messageId, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null);

        if (! is_array($payload)) {
            $this->sendRaw($connection, $chargePointId, [self::CALL_ERROR, $messageId, 'FormationViolation', 'Payload must be an object', (object) []], $action);

            return;
        }

        try {
            $response = $this->handler->handle($chargePointId, $action, $payload);
            $this->sendRaw($connection, $chargePointId, [self::CALL_RESULT, $messageId, $response === [] ? (object) [] : $response], $action);
        } catch (OcppError $e) {
            $this->sendRaw($connection, $chargePointId, [self::CALL_ERROR, $messageId, $e->errorCode, $e->getMessage(), (object) []], $action);
        } catch (\Throwable $e) {
            Log::error('ocpp.handler_error', [
                'charge_point' => $chargePointId,
                'action' => $action,
                'error' => $e->getMessage(),
                'at' => $e->getFile().':'.$e->getLine(),
            ]);
            $this->sendRaw($connection, $chargePointId, [self::CALL_ERROR, $messageId, 'InternalError', 'Internal error', (object) []], $action);
        }
    }

    /** @param list<mixed> $frame */
    private function handleAnswer(string $chargePointId, string $messageId, int $type, array $frame): void
    {
        $pending = $this->outstanding[$chargePointId] ?? null;
        if (! $pending || $pending['message_id'] !== $messageId) {
            Log::info('ocpp.unexpected_answer', ['charge_point' => $chargePointId, 'message_id' => $messageId]);

            return;
        }

        unset($this->outstanding[$chargePointId]);
        $call = OcppCall::find($pending['call_id']);
        if (! $call) {
            return;
        }

        if ($type === self::CALL_RESULT) {
            $response = is_array($frame[2] ?? null) ? $frame[2] : [];
            $call->update(['status' => 'answered', 'response' => $response, 'answered_at' => now()]);
            try {
                $this->handler->onCallResult($call, $response);
            } catch (\Throwable $e) {
                Log::error('ocpp.call_result_handler_failed', ['call' => $call->uuid, 'error' => $e->getMessage()]);
            }

            return;
        }

        $call->update([
            'status' => 'error',
            'error' => substr(trim(($frame[2] ?? 'GenericError').' '.($frame[3] ?? '')), 0, 500),
            'response' => is_array($frame[4] ?? null) ? $frame[4] : null,
            'answered_at' => now(),
        ]);
        $this->handler->onCallFailed($call);
    }

    private function dispatchPendingCalls(): void
    {
        $calls = OcppCall::where('status', 'pending')->orderBy('id')->limit(100)->get();
        $timeout = (int) $this->commander->setting('call_timeout', 30);

        foreach ($calls as $call) {
            $chargePointId = $call->charge_point_id;
            $connection = $this->connections[$chargePointId] ?? null;

            if (! $connection) {
                if ($call->created_at && $call->created_at->lt(now()->subSeconds($timeout))) {
                    $this->finishCall($call, 'error', 'Charger not connected');
                } elseif (! EdgeCharger::where('charge_point_id', $chargePointId)->exists()) {
                    $this->finishCall($call, 'error', 'Unknown charge point');
                }

                continue;
            }

            if (isset($this->outstanding[$chargePointId])) {
                continue; // OCPP: فقط یک CALL باز در هر جهت
            }

            $messageId = (string) Str::uuid();
            $claimed = OcppCall::where('id', $call->id)->where('status', 'pending')->update([
                'status' => 'sent',
                'message_id' => $messageId,
                'sent_at' => now(),
            ]);
            if ($claimed !== 1) {
                continue;
            }

            $this->outstanding[$chargePointId] = [
                'message_id' => $messageId,
                'call_id' => $call->id,
                'sent_at' => microtime(true),
            ];
            $payload = $call->payload ?: (object) [];
            $this->sendRaw($connection, $chargePointId, [self::CALL, $messageId, $call->action, $payload], $call->action);
        }
    }

    private function expireOutstanding(): void
    {
        $timeout = (int) $this->commander->setting('call_timeout', 30);
        foreach ($this->outstanding as $chargePointId => $pending) {
            if (microtime(true) - $pending['sent_at'] >= $timeout) {
                unset($this->outstanding[$chargePointId]);
                if ($call = OcppCall::find($pending['call_id'])) {
                    $this->finishCall($call, 'timeout', 'No answer within '.$timeout.'s');
                }
            }
        }
    }

    private function failOutstanding(string $chargePointId, string $reason): void
    {
        $pending = $this->outstanding[$chargePointId] ?? null;
        unset($this->outstanding[$chargePointId]);
        if ($pending && ($call = OcppCall::find($pending['call_id']))) {
            $this->finishCall($call, 'error', $reason);
        }
    }

    private function finishCall(OcppCall $call, string $status, string $error): void
    {
        $call->update(['status' => $status, 'error' => $error, 'answered_at' => now()]);
        try {
            $this->handler->onCallFailed($call);
        } catch (\Throwable $e) {
            Log::error('ocpp.call_failed_handler', ['call' => $call->uuid, 'error' => $e->getMessage()]);
        }
    }

    /** @param list<mixed> $frame */
    private function sendRaw(Connection $connection, string $chargePointId, array $frame, ?string $action): void
    {
        $json = json_encode($frame, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
        if ($json === false) {
            return;
        }

        $connection->sendText($json);
        $this->log($chargePointId, 'out', (int) $frame[0], $action, (string) $frame[1], $json);
    }

    private function checkBasicAuth(Connection $connection, EdgeCharger $charger): bool
    {
        $password = (string) ($charger->meta['ocpp_password'] ?? '');
        $required = $password !== '' || (bool) $this->commander->setting('require_basic_auth', false);
        if (! $required) {
            return true;
        }

        $header = $connection->headers['authorization'] ?? '';
        if (! str_starts_with($header, 'Basic ')) {
            return false;
        }

        $decoded = (string) base64_decode(substr($header, 6), true);
        $pos = strrpos($decoded, ':');
        if ($pos === false) {
            return false;
        }

        return $password !== ''
            && hash_equals($charger->charge_point_id, substr($decoded, 0, $pos))
            && hash_equals($password, substr($decoded, $pos + 1));
    }

    private function log(string $chargePointId, string $direction, int $type, ?string $action, ?string $messageId, ?string $payload): void
    {
        if (! $this->commander->setting('log_messages', true)) {
            return;
        }

        try {
            OcppMessageLog::create([
                'charge_point_id' => substr($chargePointId, 0, 64),
                'direction' => $direction,
                'message_type' => $type,
                'action' => $action,
                'message_id' => $messageId !== null ? substr($messageId, 0, 64) : null,
                'payload' => $payload !== null ? substr($payload, 0, 65000) : null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('ocpp.log_failed', ['error' => $e->getMessage()]);
        }
    }
}
