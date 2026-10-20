<?php

namespace App\Services\Sync;

use App\Models\PanelCommandLog;
use App\Services\Ocpp\EdgeCommandExecutor;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CloudCommandPullService
{
    public function __construct(private EdgeCommandExecutor $executor) {}

    /** @return array{pulled:int,ok:int,failed:int} */
    public function pullAndExecute(): array
    {
        $base = rtrim((string) config('services.cloud.api_url', env('CLOUD_API_URL')), '/');
        $token = config('services.cloud.sync_token', env('CLOUD_SYNC_TOKEN'));

        if (! $token) {
            throw new \RuntimeException('CLOUD_SYNC_TOKEN not configured');
        }

        try {
            $response = Http::withToken($token)
                ->timeout(20)
                ->acceptJson()
                ->get("{$base}/api/v1/sync/commands");
        } catch (ConnectionException $e) {
            throw new \RuntimeException('Cloud commands pull failed: '.$e->getMessage());
        }

        if (! $response->successful()) {
            throw new \RuntimeException('Cloud commands pull failed: HTTP '.$response->status());
        }

        $commands = $response->json('data', []);
        if (! is_array($commands)) {
            $commands = [];
        }

        $stats = ['pulled' => count($commands), 'ok' => 0, 'failed' => 0];

        foreach ($commands as $command) {
            if (! is_array($command) || empty($command['uuid'])) {
                continue;
            }

            $uuid = (string) $command['uuid'];
            $commandType = (string) ($command['command_type'] ?? 'unknown');

            $log = PanelCommandLog::create([
                'uuid' => (string) Str::uuid(),
                'source' => 'cloud',
                'cloud_command_uuid' => $uuid,
                'command_type' => $commandType,
                'payload' => $command['payload'] ?? null,
                'status' => 'running',
            ]);

            try {
                $result = $this->executor->execute($command);
                $this->acknowledge($base, $token, $uuid, true, $result);
                $log->update([
                    'status' => 'completed',
                    'result' => $result,
                    'simulated' => (bool) ($result['simulated'] ?? false),
                ]);
                $stats['ok']++;
            } catch (\Throwable $e) {
                Log::warning('edge.command.failed', ['uuid' => $uuid, 'error' => $e->getMessage()]);
                $log->update([
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ]);
                $this->acknowledge($base, $token, $uuid, false, [], $e->getMessage());
                $stats['failed']++;
            }
        }

        return $stats;
    }

    /** @param array<string, mixed> $result */
    private function acknowledge(string $base, string $token, string $uuid, bool $ok, array $result = [], ?string $error = null): void
    {
        try {
            Http::withToken($token)
                ->timeout(15)
                ->acceptJson()
                ->post("{$base}/api/v1/sync/commands/{$uuid}/ack", [
                    'ok' => $ok,
                    'result' => $result,
                    'error' => $error,
                ]);
        } catch (ConnectionException $e) {
            Log::error('edge.command.ack_failed', ['uuid' => $uuid, 'error' => $e->getMessage()]);
        }
    }
}
