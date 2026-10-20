<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * ورود staff / توسعه — مشتری در فاز ۳ با OTP ایستگاه (GSM) احراز می‌شود.
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required_without:phone', 'email'],
            'phone' => ['required_without:email', 'string', 'max:20'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:128'],
            'platform' => ['nullable', 'in:web,android,ios'],
        ]);

        $user = isset($data['email'])
            ? User::where('email', $data['email'])->first()
            : User::where('phone', $data['phone'])->first();

        if (! $user || ! $user->password || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'credentials' => ['اطلاعات ورود نادرست است.'],
            ]);
        }

        if ($user->status !== 'active') {
            throw ValidationException::withMessages([
                'credentials' => ['حساب کاربری غیرفعال است.'],
            ]);
        }

        $token = $user->createToken(
            $data['device_name'] ?? 'api',
            ['*'],
            now()->addDays(30)
        );

        $user->update(['last_login_at' => now()]);

        return response()->json([
            'token_type' => 'Bearer',
            'access_token' => $token->plainTextToken,
            'expires_in' => 60 * 60 * 24 * 30,
            'user' => [
                'uuid' => $user->uuid,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role?->value,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['message' => 'خروج انجام شد.']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'uuid' => $user->uuid,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role?->value,
        ]);
    }
}
