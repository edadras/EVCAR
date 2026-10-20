<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\EdgeCharger;
use App\Models\EdgeConnector;
use App\Services\Panel\PanelSetupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SetupApiController extends Controller
{
    public function __construct(private PanelSetupService $setup) {}

    public function show(): JsonResponse
    {
        return response()->json($this->setup->fullState());
    }

    public function updateStation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'local_hostname' => ['nullable', 'string', 'max:120'],
            'wifi_ssid' => ['nullable', 'string', 'max:64'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'offline_mode_enabled' => ['nullable', 'boolean'],
        ]);

        $this->setup->updateStation($data);

        return response()->json(['ok' => true, 'message' => 'اطلاعات ایستگاه ذخیره شد.']);
    }

    public function updateCloud(Request $request): JsonResponse
    {
        $data = $request->validate([
            'api_url' => ['nullable', 'string', 'max:255'],
            'sync_token' => ['nullable', 'string', 'max:255'],
        ]);

        $result = $this->setup->updateCloud($data);

        return response()->json(['ok' => true, ...$result]);
    }

    public function updateOcpp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'api_url' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'max:120'],
            'password' => ['nullable', 'string', 'max:120'],
        ]);

        $this->setup->updateOcpp($data);

        return response()->json(['ok' => true, 'message' => 'تنظیمات OCPP ذخیره شد.']);
    }

    public function testOcpp(): JsonResponse
    {
        $result = $this->setup->testOcpp();

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function updateAnpr(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plpr_url' => ['nullable', 'string', 'max:255'],
            'min_confidence' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'default_camera' => ['nullable', 'string', 'max:32'],
            'auto_push_cloud' => ['nullable', 'boolean'],
            'cameras' => ['nullable', 'array'],
            'cameras.*.id' => ['nullable', 'string', 'max:32'],
            'cameras.*.name' => ['nullable', 'string', 'max:120'],
            'cameras.*.rtsp_url' => ['nullable', 'string', 'max:500'],
            'cameras.*.role' => ['nullable', 'string', 'max:32'],
            'cameras.*.enabled' => ['nullable', 'boolean'],
            'cameras.*.connector_id' => ['nullable', 'integer'],
        ]);

        $this->setup->updateAnpr($data);

        return response()->json(['ok' => true, 'message' => 'تنظیمات پلاک‌خوان ذخیره شد.']);
    }

    public function testAnprHealth(): JsonResponse
    {
        $result = $this->setup->testAnprHealth();

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function startAnprCamera(Request $request): JsonResponse
    {
        $data = $request->validate([
            'source' => ['required', 'string', 'max:500'],
        ]);

        $result = $this->setup->startAnprCamera($data['source']);

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function testAnprRecognize(Request $request): JsonResponse
    {
        $request->validate(['image' => ['required', 'file', 'image', 'max:8192']]);

        $result = $this->setup->testAnprRecognize($request->file('image'));

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function updateGsm(Request $request): JsonResponse
    {
        $data = $request->validate([
            'port' => ['nullable', 'string', 'max:64'],
            'baud_rate' => ['nullable', 'integer', 'min:9600', 'max:921600'],
            'pin_code' => ['nullable', 'string', 'max:8'],
            'sms_center' => ['nullable', 'string', 'max:20'],
            'sender_id' => ['nullable', 'string', 'max:20'],
            'is_enabled' => ['nullable', 'boolean'],
        ]);

        $this->setup->updateGsm($data);

        return response()->json(['ok' => true, 'message' => 'تنظیمات GSM ذخیره شد.']);
    }

    public function testGsmOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:15'],
        ]);

        try {
            $result = $this->setup->testGsmOtp($data['phone']);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        return response()->json($result);
    }

    public function syncDevices(): JsonResponse
    {
        try {
            $result = $this->setup->syncDevices();
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        return response()->json($result);
    }

    public function updateCharger(Request $request, EdgeCharger $charger): JsonResponse
    {
        $data = $request->validate([
            'charge_point_id' => ['nullable', 'string', 'max:64'],
            'max_power_kw' => ['nullable', 'integer', 'min:1', 'max:500'],
            'status' => ['nullable', 'string', 'max:20'],
        ]);

        $updated = $this->setup->updateCharger($charger, $data);

        return response()->json(['ok' => true, 'charger' => $updated]);
    }

    public function updateConnector(Request $request, EdgeConnector $connector): JsonResponse
    {
        $data = $request->validate([
            'max_power_kw' => ['nullable', 'integer', 'min:1', 'max:500'],
            'operational_status' => ['nullable', 'string', 'max:20'],
            'ocpp_status' => ['nullable', 'string', 'max:32'],
        ]);

        $updated = $this->setup->updateConnector($connector, $data);

        return response()->json(['ok' => true, 'connector' => $updated]);
    }
}
