<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Services\Auth\CustomerAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthApiController extends Controller
{
    public function __construct(private CustomerAuthService $auth) {}

    public function lookup(Request $request): JsonResponse
    {
        $data = $request->validate(['phone' => ['required', 'string', 'max:20']]);

        return response()->json($this->auth->lookup($data['phone']));
    }

    public function requestOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'purpose' => ['required', 'in:register,login'],
        ]);

        return response()->json($this->auth->requestOtp(
            $data['phone'],
            $data['purpose'],
            $request->ip(),
        ));
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'code' => ['required', 'string', 'size:6'],
            'purpose' => ['nullable', 'in:register,login'],
        ]);

        try {
            return response()->json($this->auth->verifyOtp(
                $data['phone'],
                $data['code'],
                $data['purpose'] ?? 'register',
            ));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'registration_token' => ['required', 'string', 'max:64'],
            'registration_proof' => ['nullable', 'string', 'max:128'],
            'name' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            $result = $this->auth->register(
                $data['phone'],
                $data['password'],
                $data['registration_token'],
                $data['name'] ?? null,
                $data['registration_proof'] ?? null,
            );
            $request->session()->regenerate();

            return response()->json($result);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string'],
        ]);

        try {
            $result = $this->auth->login($data['phone'], $data['password']);
            $request->session()->regenerate();

            return response()->json($result);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
