<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\ChargingSession;
use App\Services\App\ArrivalService;
use App\Services\App\ConnectivityService;
use App\Services\App\MobileAppPresenter;
use Illuminate\Http\JsonResponse;

class ApiController extends Controller
{
    public function context(ConnectivityService $connectivity): JsonResponse
    {
        $user = auth()->user();

        return response()->json([
            'connectivity' => $connectivity->build($user),
            'backend' => session('sc_backend_kind', 'cloud'),
        ]);
    }

    public function arrival(ArrivalService $arrival): JsonResponse
    {
        return response()->json($arrival->statusForUser(auth()->user()));
    }

    public function confirmArrival(ArrivalService $arrival): JsonResponse
    {
        try {
            $result = $arrival->confirmForUser(auth()->user());
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json($result, ($result['ok'] ?? false) ? 200 : 202);
    }

    public function cancelArrival(ArrivalService $arrival): JsonResponse
    {
        try {
            $result = $arrival->cancelForUser(auth()->user());
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json($result);
    }

    public function chargingStatus(MobileAppPresenter $presenter): JsonResponse
    {
        $session = ChargingSession::with(['charger', 'connector'])
            ->where('user_id', auth()->id())
            ->where('status', 'active')
            ->latest('started_at')
            ->first();

        if (! $session) {
            return response()->json(['active' => false]);
        }

        return response()->json($presenter->sessionLivePayload($session));
    }
}
