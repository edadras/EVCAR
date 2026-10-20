<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Services\App\MobileAppService;
use Illuminate\View\View;

class PageController extends Controller
{
    public function __construct(private MobileAppService $app) {}

    private function user()
    {
        return auth()->user();
    }

    public function home(): View
    {
        return view('app.home', $this->app->home($this->user()));
    }

    public function map(): View
    {
        return view('app.map', $this->app->map($this->user()));
    }

    public function wallet(): View
    {
        return view('app.wallet', $this->app->wallet($this->user()));
    }

    public function account(): View
    {
        return view('app.account', $this->app->account($this->user()));
    }

    public function history(): View
    {
        return view('app.history', $this->app->history($this->user()));
    }

    public function charging(): View
    {
        return view('app.charging', $this->app->charging($this->user()));
    }

    public function notifications(): View
    {
        return view('app.notifications', $this->app->notifications($this->user()));
    }

    public function profile(): View
    {
        return view('app.profile', $this->app->account($this->user()));
    }

    public function vehicle(): View
    {
        return view('app.vehicle', $this->app->vehicles($this->user()));
    }

    public function rfid(): View
    {
        return view('app.rfid', $this->app->rfid($this->user()));
    }

    public function reserve(): View
    {
        return view('app.reserve', $this->app->reservations($this->user()));
    }

    public function support(): View
    {
        return view('app.support', $this->app->support($this->user()));
    }

    public function scan(): View
    {
        return view('app.scan', $this->app->scan());
    }

    public function charger(): View
    {
        $auth = app(\App\Services\App\ChargingAuthorizationService::class);
        $qrGrant = $auth->tryGrantFromQrToken(request('qr'));

        return view('app.charger', array_merge(
            $this->app->charger(
                request('station'),
                request('connector') ? (int) request('connector') : null,
            ),
            [
                'qrGrant' => $qrGrant,
                'entryAuth' => $qrGrant ? 'qr' : request('auth'),
            ],
        ));
    }

    public function arrival(): View
    {
        return view('app.arrival');
    }

    public function settings(): View
    {
        $user = $this->user();

        return view('app.settings', [
            'user' => $user,
            'prefs' => $user->meta['app_prefs'] ?? [],
        ]);
    }

    public function offers(): View
    {
        return view('app.offers', ['campaigns' => \App\Models\Campaign::where('status', 'active')->get()]);
    }

    public function plate(): View
    {
        return view('app.plate', $this->app->vehicles($this->user()));
    }

    public function password(): View
    {
        return view('app.password', ['user' => $this->user()]);
    }

    public function privacy(): View
    {
        return view('app.privacy', ['content' => $this->app->legal('privacy')]);
    }

    public function terms(): View
    {
        return view('app.terms', ['content' => $this->app->legal('terms')]);
    }

    public function ticket(): View
    {
        $user = $this->user();
        $ticketId = request('id');
        if ($ticketId) {
            $ticket = \App\Models\SupportTicket::where('user_id', $user->id)->findOrFail($ticketId);

            return view('app.ticket', $this->app->ticketDetail($user, $ticket));
        }

        return view('app.ticket', $this->app->support($user));
    }

    public function ticketNew(): View
    {
        return view('app.ticket-new', $this->app->ticketForm($this->user()));
    }

    public function topupConfirm(): View
    {
        return view('app.topup-confirm', $this->app->wallet($this->user()));
    }

    public function topupResult(): View
    {
        return view('app.topup-result', array_merge(
            $this->app->wallet($this->user()),
            [
                'topupAmount' => request('amount'),
                'paymentRef' => request('payment'),
            ]
        ));
    }
}
