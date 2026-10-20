<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OperationalAlert;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AlertController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status', 'open');

        return view('admin.alerts', [
            'alerts' => OperationalAlert::with('station')
                ->when($status !== 'all', fn ($q) => $q->where('status', $status))
                ->orderByRaw("CASE severity WHEN 'critical' THEN 0 WHEN 'warning' THEN 1 ELSE 2 END")
                ->latest('last_seen_at')
                ->paginate(30)
                ->withQueryString(),
            'status' => $status,
            'counts' => [
                'open' => OperationalAlert::where('status', 'open')->count(),
                'critical' => OperationalAlert::where('status', 'open')->where('severity', 'critical')->count(),
                'today' => OperationalAlert::whereDate('opened_at', today())->count(),
            ],
        ]);
    }

    public function resolve(OperationalAlert $alert): RedirectResponse
    {
        $alert->update(['status' => 'resolved', 'resolved_at' => now()]);

        return back()->with('success', 'هشدار بسته شد.');
    }
}
