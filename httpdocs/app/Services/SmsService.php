<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    public function sendOtp(string $phone, string $code, ?string $locale = null): bool
    {
        $locale = $locale ?: app()->getLocale();
        $template = str_starts_with($locale, 'ar')
            ? config('sms.otp.message_ar')
            : config('sms.otp.message_en');
        $message = str_replace(':code', $code, (string) $template);

        return $this->send($phone, $message);
    }

    public function send(string $phone, string $message): bool
    {
        $driver = config('sms.driver', 'log');

        return match ($driver) {
            'http' => $this->sendHttp($phone, $message),
            default => $this->sendLog($phone, $message),
        };
    }

    private function sendLog(string $phone, string $message): bool
    {
        Log::info('sms.sent', ['phone' => $phone, 'message' => $message]);
        return true;
    }

    private function sendHttp(string $phone, string $message): bool
    {
        $url = config('sms.http.url');
        if (!$url) {
            Log::warning('sms.http_missing_url');
            return $this->sendLog($phone, $message);
        }

        $phoneKey = config('sms.http.phone_key', 'phone');
        $messageKey = config('sms.http.message_key', 'message');
        $method = strtolower((string) config('sms.http.method', 'POST'));
        $headers = array_filter(config('sms.http.headers', []));

        $request = Http::withHeaders($headers)->timeout(15);
        $payload = [$phoneKey => $phone, $messageKey => $message];

        $response = $method === 'get'
            ? $request->get($url, $payload)
            : $request->post($url, $payload);

        if (!$response->successful()) {
            Log::warning('sms.http_failed', ['status' => $response->status(), 'body' => $response->body()]);
            return false;
        }

        return true;
    }
}
