<?php

namespace App\Services\Anpr;

use App\Services\Station\StationConfigResolver;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

class PlprLiteClient
{
    public function __construct(private StationConfigResolver $stationConfig) {}

    public function isHealthy(): bool
    {
        try {
            $response = Http::timeout(3)->get($this->baseUrl().'/health');

            return $response->successful();
        } catch (ConnectionException|\Throwable) {
            return false;
        }
    }

    /**
     * @return array{ok:bool,data:?array<string,mixed>,error:?string,http_status:?int}
     */
    public function recognizeImage(UploadedFile|string $file): array
    {
        try {
            $request = Http::timeout(30)->acceptJson();
            if ($file instanceof UploadedFile) {
                $request = $request->attach(
                    'file',
                    file_get_contents($file->getRealPath()),
                    $file->getClientOriginalName() ?: 'upload.jpg',
                );
            } else {
                $request = $request->attach('file', file_get_contents($file), basename($file));
            }

            $response = $request->post($this->baseUrl().'/api/recognize');

            if ($response->status() === 400) {
                return [
                    'ok' => false,
                    'data' => null,
                    'error' => (string) ($response->json('error') ?? 'فایل تصویر نامعتبر است.'),
                    'http_status' => 400,
                ];
            }

            if (! $response->successful()) {
                return [
                    'ok' => false,
                    'data' => null,
                    'error' => 'PLPR خطا داد: HTTP '.$response->status(),
                    'http_status' => $response->status(),
                ];
            }

            $data = $response->json();
            if (! is_array($data)) {
                return ['ok' => false, 'data' => null, 'error' => 'پاسخ PLPR نامعتبر است.', 'http_status' => $response->status()];
            }

            return ['ok' => true, 'data' => $data, 'error' => null, 'http_status' => $response->status()];
        } catch (ConnectionException $e) {
            return [
                'ok' => false,
                'data' => null,
                'error' => 'اتصال به PLPR برقرار نشد ('.$this->baseUrl().'). در Docker از host.docker.internal:8502 استفاده کنید.',
                'http_status' => null,
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'data' => null, 'error' => $e->getMessage(), 'http_status' => null];
        }
    }

    public function startCamera(string $source = '0'): bool
    {
        try {
            $response = Http::timeout(10)
                ->asForm()
                ->post($this->baseUrl().'/api/camera/start', [
                    'source' => $source,
                ]);

            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    public function streamUrl(): string
    {
        return $this->baseUrl().'/api/stream.mjpg';
    }

    private function baseUrl(): string
    {
        return $this->stationConfig->plprUrl();
    }
}
