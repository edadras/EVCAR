<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Services\Support\AiSupportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** دستیار هوشمند پشتیبانی — مشترک بین وب‌اپ (/app/assistant) و اپ موبایل (/api/v1/mobile/assistant). */
class AssistantController extends Controller
{
    public function __construct(private AiSupportService $ai) {}

    private function channel(Request $request): string
    {
        return $request->is('api/*') ? 'app' : 'web';
    }

    public function page(Request $request): View
    {
        return view('app.assistant', ['initial' => $this->ai->payload($this->ai->conversationFor($request->user(), null, 'web'))]);
    }

    public function show(Request $request): JsonResponse
    {
        return response()->json($this->ai->payload($this->ai->conversationFor($request->user(), $request->query('conversation'), $this->channel($request))));
    }

    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'conversation' => ['nullable', 'string', 'max:64'],
        ]);
        $conversation = $this->ai->conversationFor($request->user(), $data['conversation'] ?? null, $this->channel($request));

        try {
            return response()->json($this->ai->send($request->user(), $conversation, $data['message']));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 429);
        }
    }

    public function escalate(Request $request): JsonResponse
    {
        $data = $request->validate(['conversation' => ['nullable', 'string', 'max:64']]);
        $conversation = $this->ai->conversationFor($request->user(), $data['conversation'] ?? null, $this->channel($request));
        if ($conversation->messages()->count() === 0) {
            return response()->json(['message' => 'ابتدا مشکل خود را بنویسید تا برای کارشناس ثبت شود.'], 422);
        }
        $ticket = $this->ai->escalate($request->user(), $conversation, ['category' => 'other', 'priority' => 'normal']);

        return response()->json(array_merge($this->ai->payload($conversation->fresh()), [
            'message' => 'درخواست شما با شمارهٔ '.$ticket->ticket_number.' برای کارشناس پشتیبانی ثبت شد.',
            'ticket_id' => $ticket->id,
        ]));
    }

    public function reset(Request $request): JsonResponse
    {
        return response()->json($this->ai->payload($this->ai->startNew($request->user(), $this->channel($request))));
    }
}
