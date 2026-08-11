<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SocialAuthService;
use Illuminate\Http\Request;

/**
 * Google / Apple Sign-In HTTP entry. Verifies provider tokens via SocialAuthService and returns Sanctum session JSON.
 */
class SocialAuthController extends Controller
{
    public function google(Request $request, SocialAuthService $social)
    {
        $data = $request->validate([
            'id_token' => 'required|string',
            'device_name' => 'nullable|string|max:120',
        ]);
        $deviceName = $data['device_name']
            ?? $request->header('X-Device-Id')
            ?? $request->userAgent()
            ?? 'mobile';

        $result = $social->authenticateGoogle($data['id_token'], $deviceName);

        return response()->json([
            'status' => 'success',
            'token' => $result['token'],
            'user' => app(AuthController::class)->publicPayload($result['user']),
        ]);
    }

    public function apple(Request $request, SocialAuthService $social)
    {
        $data = $request->validate([
            'id_token' => 'required|string',
            'name' => 'nullable|string|max:120',
            'device_name' => 'nullable|string|max:120',
        ]);
        $deviceName = $data['device_name']
            ?? $request->header('X-Device-Id')
            ?? $request->userAgent()
            ?? 'mobile';

        $result = $social->authenticateApple($data['id_token'], $data['name'] ?? null, $deviceName);

        return response()->json([
            'status' => 'success',
            'token' => $result['token'],
            'user' => app(AuthController::class)->publicPayload($result['user']),
        ]);
    }

    public function facebook(Request $request, SocialAuthService $social)
    {
        $data = $request->validate([
            'access_token' => 'required|string',
            'device_name' => 'nullable|string|max:120',
        ]);
        $deviceName = $data['device_name']
            ?? $request->header('X-Device-Id')
            ?? $request->userAgent()
            ?? 'mobile';

        $result = $social->authenticateFacebook($data['access_token'], $deviceName);

        return response()->json([
            'status' => 'success',
            'token' => $result['token'],
            'user' => app(AuthController::class)->publicPayload($result['user']),
        ]);
    }
}
