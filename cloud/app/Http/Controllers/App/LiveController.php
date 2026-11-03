<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\ChargingSession;
use App\Models\Station;
use App\Models\User;
use App\Services\App\MobileAppService;
use App\Support\Persian;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LiveController extends Controller
{
    public function __construct(
        private readonly MobileAppService $app,
    ) {}

    public function show(Request $request, string $section): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = match ($section) {
            'home' => $this->home($user),
            'map' => $this->map($user),
            'charger' => $this->charger((string) $request->query('station', '')),
            'wallet' => $this->wallet($user),
            'reserve' => $this->reserve((int) $request->query('station', 0)),
            default => [],
        };
        $data['nav'] = $this->nav($user);
        $data['at'] = now()->toIso8601String();

        return response()->json($data)->header('Cache-Control', 'no-store');
    }

    /** @return array{unread: int, active: bool} */
    private function nav(User $user): array
    {
        return [
            'unread' => $user->userNotifications()->whereNull('read_at')->count(),
            'active' => ChargingSession::where('user_id', $user->id)->where('status', 'active')->exists(),
        ];
    }

    private function home(User $user): array
    {
        $home = $this->app->home($user);

        return [
            'stations' => $home['nearbyStations'],
            'balance' => Persian::money((int) round(((float) ($user->wallet?->balance ?? 0)) / 10)),
            'active' => $home['activeCard'],
        ];
    }

    private function map(User $user): array
    {
        $map = $this->app->map($user);

        return [
            'markers' => $map['mapMarkers'],
            'stations' => $map['mapStations'],
            'stats' => $map['mapStats'],
        ];
    }

    private function charger(string $code): array
    {
        $data = $this->app->charger($code ?: null);

        return [
            'connectors' => $data['connectorsJs'],
            'free' => $data['freeCount'],
            'found' => $data['station'] !== null,
        ];
    }

    private function reserve(int $stationId): array
    {
        $station = Station::where('status', 'active')->with(['chargers.connectors'])->find($stationId);
        if (! $station) {
            return ['station' => null];
        }

        return ['station' => [
            'id' => $station->id,
            'stats' => $this->app->reserveStationStats($station),
            'connectors' => $this->app->reserveConnectorsLive($station),
        ]];
    }

    private function wallet(User $user): array
    {
        $user->load('wallet');
        $data = $this->app->wallet($user);

        return [
            'balance' => Persian::money((int) round(((float) ($data['wallet']?->balance ?? 0)) / 10)),
            'balance_toman' => (int) round(((float) ($data['wallet']?->balance ?? 0)) / 10),
            'transactions' => $data['transactionsJs'],
        ];
    }
}
