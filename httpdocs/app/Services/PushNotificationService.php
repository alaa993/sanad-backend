<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    public function notifyUser(int $userId, string $title, string $body, array $data = []): void
    {
        $user = User::find($userId);
        if (!$user || $user->push_enabled === false) {
            return;
        }

        $tokens = DeviceToken::where('user_id', $userId)->get();
        foreach ($tokens as $device) {
            $this->sendToDevice($device->platform, $device->token, $title, $body, $data);
        }
    }

    public function sendToDevice(string $platform, string $token, string $title, string $body, array $data = []): bool
    {
        if ($platform === 'ios') {
            return $this->sendApns($token, $title, $body, $data);
        }

        return $this->sendFcm($token, $title, $body, $data);
    }

    public function sendFcm(string $token, string $title, string $body, array $data = []): bool
    {
        $serverKey = config('services.fcm.server_key');
        if (!$serverKey) {
            Log::info('push.fcm.skipped', ['reason' => 'missing_server_key']);
            return false;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'key ' . $serverKey,
                'Content-Type' => 'application/json',
            ])->post('https://fcm.googleapis.com/fcm/send', [
                'to' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => array_map('strval', $data),
                'priority' => 'high',
            ]);

            if (!$response->successful()) {
                Log::warning('push.fcm.failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('push.fcm.exception', ['message' => $e->getMessage()]);
            return false;
        }
    }

    public function sendApns(string $token, string $title, string $body, array $data = []): bool
    {
        $jwt = $this->apnsJwt();
        if (!$jwt) {
            Log::info('push.apns.skipped', ['reason' => 'missing_credentials']);
            return false;
        }

        $bundleId = config('services.apns.bundle_id');
        $useSandbox = (bool) config('services.apns.sandbox', false);
        $host = $useSandbox ? 'https://api.sandbox.push.apple.com' : 'https://api.push.apple.com';
        $url = $host . '/3/device/' . $token;

        // Flatten custom keys at the top level so iOS can deep-link without nesting issues.
        $payloadBody = [
            'aps' => [
                'alert' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'sound' => 'default',
            ],
            'data' => $data,
        ];
        foreach ($data as $key => $value) {
            if (!array_key_exists($key, $payloadBody)) {
                $payloadBody[$key] = is_scalar($value) ? (string) $value : $value;
            }
        }
        $payload = json_encode($payloadBody);

        try {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'authorization: bearer ' . $jwt,
                    'apns-topic: ' . $bundleId,
                    'apns-push-type: alert',
                    'apns-priority: 10',
                    'content-type: application/json',
                ],
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,
            ]);
            $result = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($status < 200 || $status >= 300) {
                Log::warning('push.apns.failed', ['status' => $status, 'body' => $result]);
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('push.apns.exception', ['message' => $e->getMessage()]);
            return false;
        }
    }

    private function apnsJwt(): ?string
    {
        static $cached;
        if ($cached && $cached['exp'] > time() + 60) {
            return $cached['token'];
        }

        $keyId = config('services.apns.key_id');
        $teamId = config('services.apns.team_id');
        $privateKey = config('services.apns.private_key');
        if (!$keyId || !$teamId || !$privateKey) {
            return null;
        }

        $privateKey = str_replace('\\n', "\n", $privateKey);
        $header = $this->base64UrlEncode(json_encode(['alg' => 'ES256', 'kid' => $keyId]));
        $claims = $this->base64UrlEncode(json_encode([
            'iss' => $teamId,
            'iat' => time(),
        ]));

        $data = $header . '.' . $claims;
        $signature = '';
        $ok = openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        if (!$ok) {
            return null;
        }

        $token = $data . '.' . $this->base64UrlEncode($signature);
        $cached = ['token' => $token, 'exp' => time() + 3000];

        return $token;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
