<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Verifies Google/Apple ID tokens and upserts a local user linked by provider subject.
 * Apple JWT is decoded client-side style (iss/aud/exp checks); Google uses tokeninfo.
 */
class SocialAuthService
{
    /** Validate Google id_token then issue or reuse a Sanctum device token. */
    public function authenticateGoogle(string $idToken, string $deviceName): array
    {
        $resp = Http::get('https://oauth2.googleapis.com/tokeninfo', ['id_token' => $idToken]);
        if (!$resp->ok()) {
            abort(422, 'Invalid Google token');
        }
        $payload = $resp->json();
        $sub = $payload['sub'] ?? null;
        $email = $payload['email'] ?? null;
        $name = $payload['name'] ?? 'Google User';
        if (!$sub) {
            abort(422, 'Invalid Google token payload');
        }

        return $this->issueToken('google', $sub, $email, $name, $deviceName);
    }

    /** Validate Apple identity token claims then issue or reuse a Sanctum device token. */
    public function authenticateApple(string $idToken, ?string $name, string $deviceName): array
    {
        $parts = explode('.', $idToken);
        if (count($parts) < 2) {
            abort(422, 'Invalid Apple token');
        }
        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
        if (!$payload || ($payload['iss'] ?? '') !== 'https://appleid.apple.com') {
            abort(422, 'Invalid Apple token issuer');
        }
        if (($payload['exp'] ?? 0) < time()) {
            abort(422, 'Apple token expired');
        }
        $clientId = config('services.apple.client_id');
        if ($clientId && ($payload['aud'] ?? '') !== $clientId) {
            abort(422, 'Apple token audience mismatch');
        }
        $sub = $payload['sub'] ?? null;
        $email = $payload['email'] ?? null;
        if (!$sub) {
            abort(422, 'Invalid Apple token payload');
        }

        return $this->issueToken('apple', $sub, $email, $name ?: 'Apple User', $deviceName);
    }

    public function authenticateFacebook(string $accessToken, string $deviceName): array
    {
        $appId = config('services.facebook.client_id');
        $appSecret = config('services.facebook.client_secret');
        if ($appId && $appSecret) {
            $debug = Http::get('https://graph.facebook.com/debug_token', [
                'input_token' => $accessToken,
                'access_token' => $appId . '|' . $appSecret,
            ]);
            if (!$debug->ok() || !($debug->json('data.is_valid') ?? false)) {
                abort(422, 'Invalid Facebook token');
            }
        }

        $resp = Http::get('https://graph.facebook.com/me', [
            'fields' => 'id,name,email',
            'access_token' => $accessToken,
        ]);
        if (!$resp->ok()) {
            abort(422, 'Invalid Facebook token');
        }
        $payload = $resp->json();
        $sub = $payload['id'] ?? null;
        $email = $payload['email'] ?? null;
        $name = $payload['name'] ?? 'Facebook User';
        if (!$sub) {
            abort(422, 'Invalid Facebook token payload');
        }

        return $this->issueToken('facebook', $sub, $email, $name, $deviceName);
    }

    /**
     * Find user by provider id, else link by email, else create a patient with placeholder email if needed.
     */
    private function issueToken(string $provider, string $providerId, ?string $email, string $name, string $deviceName): array
    {
        $column = match ($provider) {
            'google' => 'google_id',
            'apple' => 'apple_id',
            'facebook' => 'facebook_id',
            default => abort(422, 'Unsupported provider'),
        };

        $user = User::where($column, $providerId)->first();

        if (!$user && $email) {
            $user = User::where('email', $email)->first();
            if ($user) {
                $user->{$column} = $providerId;
                $user->save();
            }
        }

        if (!$user) {
            $safeEmail = $email ?: ($provider . '_' . $providerId . '@sanad.local');
            if (User::where('email', $safeEmail)->exists()) {
                $safeEmail = $provider . '_' . Str::uuid() . '@sanad.local';
            }
            $user = User::create([
                'name' => $name,
                'email' => $safeEmail,
                'password' => Hash::make(Str::random(32)),
                'role' => 'patient',
                $column => $providerId,
            ]);
            $user->assignRole('patient');
        }

        $user->tokens()->where('name', $deviceName)->delete();
        $token = $user->createToken($deviceName)->plainTextToken;

        return ['user' => $user, 'token' => $token];
    }
}
