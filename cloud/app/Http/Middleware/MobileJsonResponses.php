<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\ViewErrorBag;
use Symfony\Component\HttpFoundation\Response;

/**
 * اپ موبایل (Flutter) از همان اکشن‌های وب‌اپ استفاده می‌کند تا منطق کسب‌وکار یکی باشد.
 * این میانه‌افزار یک نشست موقت (حافظه) می‌سازد و پاسخ‌های «redirect + flash» وب را به JSON تبدیل می‌کند:
 *   خطا (withErrors) → 422 {message, errors}     موفق (with success) → 200 {ok, message}
 */
class MobileJsonResponses
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || $user->role !== UserRole::Customer || $user->status !== 'active') {
            return response()->json(['message' => 'دسترسی فقط برای مشتریان فعال.'], 403);
        }

        $request->headers->set('Accept', 'application/json');

        $store = new Store('sc_mobile', new ArraySessionHandler(10));
        $store->start();
        $request->setLaravelSession($store);
        app()->instance('session.store', $store);
        app('redirect')->setSession($store);

        $response = $next($request);

        if (! $response instanceof RedirectResponse) {
            return $response;
        }

        $errors = $store->get('errors');
        if ($errors instanceof ViewErrorBag && $errors->any()) {
            $bag = $errors->getBag('default');

            return new JsonResponse(['message' => $bag->first(), 'errors' => $bag->toArray()], 422);
        }

        $target = $response->getTargetUrl();
        if (! str_starts_with($target, url('/'))) {
            // هدایت به سایت بیرونی (مثلاً صفحهٔ درگاه بانک): اپ آن را در مرورگر باز می‌کند.
            return new JsonResponse(['ok' => true, 'url' => $target, 'redirect' => null, 'params' => []]);
        }

        return new JsonResponse([
            'ok' => true,
            'message' => $store->get('success') ?? $store->get('status'),
            'redirect' => $this->screenFor($response->getTargetUrl()),
            'params' => $this->paramsFor($response->getTargetUrl()),
        ]);
    }

    /** @return array<string, string> */
    private function paramsFor(string $url): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return $query;
    }

    /** نام صفحهٔ مقصد وب‌اپ تا اپ همان مسیر را باز کند (مثلاً charging یا history). */
    private function screenFor(string $url): ?string
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');

        return str_starts_with($path, 'app') ? (trim(substr($path, 3), '/') ?: 'home') : null;
    }
}
