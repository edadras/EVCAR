<?php

namespace App\Http\Controllers;

use App\Models\Connector;
use App\Models\EvOperator;
use App\Models\ExternalStation;
use App\Models\Station;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class LandingController extends Controller
{
    public function __invoke(): View
    {
        $data = Cache::remember('landing:data:v2', now()->addMinutes(10), function () {
            $own = Station::where('status', '!=', 'inactive')
                ->whereNotNull('latitude')->whereNotNull('longitude')
                ->get(['id', 'name', 'latitude', 'longitude']);

            $ext = ExternalStation::query()
                ->where('is_active', true)
                ->whereHas('operator', fn ($q) => $q->where('is_active', true))
                ->whereNotNull('latitude')->whereNotNull('longitude')
                ->with('operator:id,name,color')
                ->get(['id', 'ev_operator_id', 'latitude', 'longitude']);

            $operators = $ext->pluck('operator')->filter()->unique('id')->values();
            $opIndex = $operators->pluck('id')->flip();

            return [
                'stats' => [
                    'own' => $own->count(),
                    'connectors' => Connector::whereHas('charger.station', fn ($q) => $q->where('status', '!=', 'inactive'))->count(),
                    'map' => $own->count() + $ext->count(),
                    'operators' => EvOperator::where('is_active', true)->count() + 1,
                ],
                'map' => [
                    'own' => $own->map(fn ($s) => [round((float) $s->latitude, 5), round((float) $s->longitude, 5), $s->name])->values()->all(),
                    'ext' => $ext->map(fn ($s) => [round((float) $s->latitude, 5), round((float) $s->longitude, 5), $opIndex[$s->ev_operator_id] ?? 0])->values()->all(),
                    'ops' => $operators->map(fn ($o) => [$o->name, $o->color ?: '#64748b'])->values()->all(),
                ],
            ];
        });

        return view('landing', $data);
    }
}
