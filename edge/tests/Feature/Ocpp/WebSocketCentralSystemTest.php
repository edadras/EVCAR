<?php

namespace Tests\Feature\Ocpp;

use App\Models\OcppCall;
use App\Services\Ocpp\CentralSystem;
use App\Services\Ocpp\OcppCallQueue;
use App\Services\Ocpp\WebSocket\WebSocketClient;
use App\Services\Ocpp\WebSocket\WebSocketServer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Log\NullLogger;
use Tests\TestCase;

class WebSocketCentralSystemTest extends TestCase
{
    use OcppFixtures;
    use RefreshDatabase;

    private WebSocketServer $server;

    private CentralSystem $central;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedStation(online: false);

        $this->central = app(CentralSystem::class);
        $this->server = new WebSocketServer('127.0.0.1', 0, $this->central, new NullLogger);
        $this->server->listen();
    }

    public function test_charger_connects_boots_and_answers_commands(): void
    {
        $client = $this->connect('/ocpp/CP-T1');
        $this->assertTrue($this->charger->fresh()->is_online);

        $boot = $this->ocppCall($client, 'BootNotification', ['chargePointVendor' => 'V', 'chargePointModel' => 'M']);
        $this->assertSame(3, $boot[0]);
        $this->assertSame('Accepted', $boot[2]['status']);

        $status = $this->ocppCall($client, 'StatusNotification', ['connectorId' => 1, 'errorCode' => 'NoError', 'status' => 'Available']);
        $this->assertSame([], $status[2]);
        $this->assertSame('{}', json_encode((object) $status[2]));

        // Central System → Charger
        OcppCall::query()->delete();
        $call = app(OcppCallQueue::class)->enqueue('CP-T1', 'Reset', ['type' => 'Soft']);
        do {
            $incoming = $this->receive($client);
            if ($incoming[0] === 2 && $incoming[2] !== 'Reset') {
                $client->send(json_encode([3, $incoming[1], ['status' => 'Accepted']]));
            }
        } while ($incoming[0] !== 2 || $incoming[2] !== 'Reset');
        $this->assertSame(2, $incoming[0]);
        $this->assertSame('Reset', $incoming[2]);
        $client->send(json_encode([3, $incoming[1], ['status' => 'Accepted']]));
        $this->pump();

        $call->refresh();
        $this->assertSame('answered', $call->status);
        $this->assertSame(['status' => 'Accepted'], $call->response);

        $client->close();
        $this->pump();
        $this->assertFalse($this->charger->fresh()->is_online);
    }

    public function test_malformed_message_returns_call_error(): void
    {
        $client = $this->connect('/ocpp/CP-T1');
        $client->send('not json');

        $answer = $this->receive($client);
        $this->assertSame(4, $answer[0]);
        $this->assertSame('FormationViolation', $answer[2]);
        $client->close();
    }

    public function test_unknown_charge_point_is_rejected_at_handshake(): void
    {
        $this->expectExceptionMessage('Handshake rejected');
        $this->connect('/ocpp/UNKNOWN-CP');
    }

    public function test_charger_password_is_enforced(): void
    {
        $this->charger->update(['meta' => ['ocpp_password' => 's3cret']]);

        try {
            $this->connect('/ocpp/CP-T1');
            $this->fail('Connection without credentials should be rejected');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('401', $e->getMessage());
        }

        $client = $this->connect('/ocpp/CP-T1', ['Authorization' => 'Basic '.base64_encode('CP-T1:s3cret')]);
        $this->assertTrue($this->charger->fresh()->is_online);
        $client->close();
    }

    public function test_pending_call_for_disconnected_charger_fails_after_timeout(): void
    {
        config(['safircharge.ocpp.call_timeout' => 1]);
        $call = app(OcppCallQueue::class)->enqueue('CP-T1', 'Reset', ['type' => 'Soft']);
        OcppCall::whereKey($call->id)->update(['created_at' => now()->subSeconds(5)]);

        $this->central->tick();

        $this->assertSame('error', $call->fresh()->status);
    }

    /** @param array<string, string> $headers */
    private function connect(string $path, array $headers = []): WebSocketClient
    {
        return $this->handshake($this->server->port(), $path, $headers);
    }

    /** @param array<string, string> $headers */
    private function handshake(int $port, string $path, array $headers): WebSocketClient
    {
        // کلاینت و سرور در یک پروسه‌اند: درخواست ارتقا را خام می‌فرستیم، سرور را pump
        // می‌کنیم و سپس پاسخ 101 را می‌خوانیم.
        $socket = stream_socket_client("tcp://127.0.0.1:{$port}", $errno, $errstr, 2);
        $key = base64_encode(random_bytes(16));
        $request = "GET {$path} HTTP/1.1\r\nHost: 127.0.0.1\r\nUpgrade: websocket\r\nConnection: Upgrade\r\n"
            ."Sec-WebSocket-Key: {$key}\r\nSec-WebSocket-Version: 13\r\nSec-WebSocket-Protocol: ocpp1.6\r\n";
        foreach ($headers as $name => $value) {
            $request .= "{$name}: {$value}\r\n";
        }
        fwrite($socket, $request."\r\n");
        $this->pump(5);

        stream_set_timeout($socket, 1);
        $response = (string) fread($socket, 4096);
        if (! str_starts_with($response, 'HTTP/1.1 101')) {
            throw new \RuntimeException('Handshake rejected: '.strtok($response, "\r\n"));
        }

        $expected = base64_encode(sha1($key.'258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
        $this->assertStringContainsString($expected, $response);
        $this->assertStringContainsString('Sec-WebSocket-Protocol: ocpp1.6', $response);

        $client = WebSocketClient::fromStream($socket, (string) substr($response, strpos($response, "\r\n\r\n") + 4));
        $this->pump();

        return $client;
    }

    /** @return list<mixed> */
    private function ocppCall(WebSocketClient $client, string $action, array $payload): array
    {
        $id = bin2hex(random_bytes(4));
        $client->send(json_encode([2, $id, $action, (object) $payload]));

        // مانند شارژر واقعی: درخواست‌های سرور (مثلاً ChangeConfiguration پس از Boot) را پاسخ می‌دهیم.
        for ($i = 0; $i < 10; $i++) {
            $answer = $this->receive($client);
            if ($answer[0] === 2) {
                $client->send(json_encode([3, $answer[1], ['status' => 'Accepted']]));

                continue;
            }
            $this->assertSame($id, $answer[1]);

            return $answer;
        }

        $this->fail('No answer for '.$action);
    }

    /** @return list<mixed> */
    private function receive(WebSocketClient $client): array
    {
        for ($i = 0; $i < 20; $i++) {
            $this->pump();
            $message = $client->receive(0.05);
            if ($message !== null) {
                return json_decode($message, true);
            }
        }

        $this->fail('No message received from central system');
    }

    private function pump(int $rounds = 3): void
    {
        for ($i = 0; $i < $rounds; $i++) {
            $this->server->runOnce(0.02);
            $this->central->tick();
        }
    }
}
