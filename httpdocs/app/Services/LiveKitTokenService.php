<?php
namespace App\Services;

/**
 * Builds HS256 LiveKit access tokens from LIVEKIT_API_KEY/SECRET (no LiveKit PHP SDK).
 * Grants publish + subscribe in the named room; role is stored in token metadata for the client UI.
 */
class LiveKitTokenService
{
    public static function livekitUrl(): ?string
    {
        return env('LIVEKIT_URL');
    }

    /** Issue a ~2h roomJoin JWT for identity/name in the given room. */
    public static function generateToken(string $identity, string $name, string $room, string $role): string
    {
        $apiKey = env('LIVEKIT_API_KEY');
        $apiSecret = env('LIVEKIT_API_SECRET');
        if (!$apiKey || !$apiSecret) {
            throw new \RuntimeException('livekit_missing_credentials');
        }

        $now = time();
        $payload = [
            'iss' => $apiKey,
            'sub' => $identity,
            'name' => $name,
            'nbf' => $now - 5,
            'exp' => $now + (2 * 60 * 60),
            'metadata' => json_encode(['role' => $role]),
            'video' => [
                'room' => $room,
                'roomJoin' => true,
                'canPublish' => true,
                'canSubscribe' => true,
                'canPublishData' => true,
            ],
        ];

        $header = ['typ' => 'JWT', 'alg' => 'HS256'];
        $base64Header = self::base64UrlEncode(json_encode($header));
        $base64Payload = self::base64UrlEncode(json_encode($payload));
        $signature = hash_hmac('sha256', $base64Header.'.'.$base64Payload, $apiSecret, true);
        $base64Signature = self::base64UrlEncode($signature);
        return $base64Header.'.'.$base64Payload.'.'.$base64Signature;
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
