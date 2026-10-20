<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\ChargingSession;
use App\Models\Station;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\Wallet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function create(): View
    {
        return view('admin.notifications.create', ['audienceCounts' => $this->audienceCounts()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:500'],
            'type' => ['nullable', 'string', 'max:32'],
            'channel' => ['required', 'in:push,sms,email,in_app'],
            'audience' => ['required', 'in:all,active,org,tehran,low_balance'],
            'deep_link' => ['nullable', 'string', 'max:128'],
        ]);

        $users = $this->resolveAudience($data['audience']);
        $sent = 0;

        foreach ($users as $user) {
            UserNotification::create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $user->id,
                'channel' => $data['channel'],
                'type' => $data['type'] ?? 'info',
                'title' => $data['title'],
                'body' => $data['body'],
                'data' => ['deep_link' => $data['deep_link'] ?? null, 'audience' => $data['audience']],
                'sent_at' => now(),
            ]);
            $sent++;
        }

        return redirect()
            ->route('admin.notifications.index')
            ->with('success', "اعلان برای {$sent} کاربر ارسال شد.");
    }

    /** @return array<string, int> */
    private function audienceCounts(): array
    {
        return [
            'all' => User::where('role', UserRole::Customer)->count(),
            'active' => User::where('role', UserRole::Customer)
                ->whereHas('chargingSessions', fn ($q) => $q->where('started_at', '>=', now()->subDays(30)))
                ->count(),
            'org' => User::where('role', UserRole::Customer)->whereNotNull('organization_id')->count(),
            'tehran' => User::where('role', UserRole::Customer)
                ->whereHas('chargingSessions.station', fn ($q) => $q->where('city', 'تهران'))
                ->count(),
            'low_balance' => Wallet::whereHas('user', fn ($q) => $q->where('role', UserRole::Customer))
                ->where('balance', '<', 500_000)
                ->count(),
        ];
    }

    /** @return \Illuminate\Support\Collection<int, User> */
    private function resolveAudience(string $audience)
    {
        $query = User::where('role', UserRole::Customer);

        return match ($audience) {
            'active' => $query->whereHas('chargingSessions', fn ($q) => $q->where('started_at', '>=', now()->subDays(30)))->get(),
            'org' => $query->whereNotNull('organization_id')->get(),
            'tehran' => $query->whereHas('chargingSessions.station', fn ($q) => $q->where('city', 'تهران'))->get(),
            'low_balance' => $query->whereHas('wallet', fn ($q) => $q->where('balance', '<', 500_000))->get(),
            default => $query->get(),
        };
    }
}
