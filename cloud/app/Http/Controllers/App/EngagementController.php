<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\ChargingSession;
use App\Models\Station;
use App\Services\App\CustomerEngagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** رسید جلسه، امتیازدهی، گزارش خرابی، اعلان آزاد شدن و تحلیل هزینه — وب‌اپ و API موبایل. */
class EngagementController extends Controller
{
    public function __construct(private CustomerEngagementService $engagement) {}

    public function receipt(Request $request, string $uuid): View|JsonResponse
    {
        $session = ChargingSession::where('uuid', $uuid)->where('user_id', $request->user()->id)->firstOrFail();
        $receipt = $this->engagement->receipt($request->user(), $session);

        return $this->isApi($request) ? response()->json($receipt) : view('app.session', ['r' => $receipt]);
    }

    public function review(Request $request, string $uuid): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:600'],
            'tags' => ['nullable', 'array', 'max:8'],
            'tags.*' => ['string', 'in:'.implode(',', array_keys(CustomerEngagementService::REVIEW_TAGS))],
        ]);
        $session = ChargingSession::where('uuid', $uuid)->where('user_id', $request->user()->id)->firstOrFail();
        $this->engagement->review($request->user(), $session, $data);
        $msg = 'ممنون از بازخورد شما! امتیاز ثبت شد.';

        return $this->isApi($request)
            ? response()->json(['ok' => true, 'message' => $msg, 'receipt' => $this->engagement->receipt($request->user(), $session->fresh())])
            : redirect()->route('app.session', $uuid)->with('success', $msg);
    }

    public function reviews(string $code): JsonResponse
    {
        return response()->json($this->engagement->stationReviews($this->station($code), 20));
    }

    public function report(Request $request, string $code): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'problem' => ['required', 'in:'.implode(',', array_keys(CustomerEngagementService::PROBLEMS))],
            'description' => ['nullable', 'string', 'max:1500'],
            'connector' => ['nullable', 'string', 'max:20'],
            'session_uuid' => ['nullable', 'string', 'max:64'],
        ]);
        $ticket = $this->engagement->reportProblem($request->user(), $this->station($code), $data, $this->isApi($request) ? 'app' : 'web');
        $msg = 'گزارش شما با شمارهٔ '.$ticket->ticket_number.' ثبت شد. تیم فنی بررسی می‌کند.';

        return $this->isApi($request)
            ? response()->json(['ok' => true, 'message' => $msg, 'ticket' => ['id' => $ticket->id, 'number' => $ticket->ticket_number]])
            : redirect()->route('app.ticket', ['id' => $ticket->id])->with('success', $msg);
    }

    public function watch(Request $request, string $code): JsonResponse
    {
        $result = $this->engagement->toggleWatch($request->user(), $this->station($code));

        return response()->json($result + [
            'ok' => true,
            'message' => $result['watching']
                ? 'به محض آزاد شدن کانکتور به شما خبر می‌دهیم (تا '.CustomerEngagementService::WATCH_HOURS.' ساعت).'
                : 'اعلان آزاد شدن لغو شد.',
        ]);
    }

    public function favorite(Request $request, string $code): JsonResponse
    {
        $this->station($code);
        $user = $request->user();
        $meta = $user->meta ?? [];
        $favs = array_values(array_unique((array) ($meta['favorite_stations'] ?? [])));
        $on = ! in_array($code, $favs, true);
        $meta['favorite_stations'] = $on ? [...$favs, $code] : array_values(array_diff($favs, [$code]));
        $user->update(['meta' => $meta]);

        return response()->json(['ok' => true, 'favorite' => $on, 'message' => $on ? 'به علاقه‌مندی‌ها اضافه شد.' : 'از علاقه‌مندی‌ها حذف شد.']);
    }

    public function insights(Request $request): View|JsonResponse
    {
        $data = $this->engagement->insights($request->user());

        return $this->isApi($request) ? response()->json($data) : view('app.insights', ['ins' => $data]);
    }

    private function station(string $code): Station
    {
        return Station::where('code', $code)->firstOrFail();
    }

    private function isApi(Request $request): bool
    {
        return $request->is('api/*') || $request->expectsJson();
    }
}
