<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * MTN Cash wallet top-up: create pending payment_requests, optionally call provider APIs, confirm and credit points.
 * Sandbox mode accepts confirmation without a live provider callback.
 */
class MtnPaymentService
{
    public function isEnabled(): bool
    {
        return (bool) config('mtn.enabled', false) || (bool) config('mtn.sandbox', true);
    }

    /** Persist a pending top-up request and return reference/expiry for the client UI. */
    public function createTopupRequest(int $userId, int $amount, ?string $phone = null): array
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('invalid_amount');
        }

        $reference = 'SANAD-' . $userId . '-' . strtoupper(Str::random(8));
        $expiresAt = now()->addHours(2);

        $id = DB::table('payment_requests')->insertGetId([
            'user_id' => $userId,
            'provider' => 'mtn',
            'purpose' => 'wallet_topup',
            'amount' => $amount,
            'currency' => config('mtn.currency', 'SYP'),
            'reference' => $reference,
            'phone' => $phone,
            'status' => 'pending',
            'meta' => json_encode(['sandbox' => (bool) config('mtn.sandbox', true)]),
            'expires_at' => $expiresAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $instructions = str_replace(
            ':reference',
            $reference,
            (string) config('mtn.instructions_ar')
        );

        return [
            'id' => $id,
            'reference' => $reference,
            'amount' => $amount,
            'currency' => config('mtn.currency', 'SYP'),
            'ussd' => config('mtn.ussd_short_code'),
            'instructions' => $instructions,
            'expires_at' => $expiresAt->toIso8601String(),
            'sandbox' => (bool) config('mtn.sandbox', true),
        ];
    }

    /** Mark pending request paid (sandbox or provider verify) and credit wallet points once. */
    public function confirmTopup(int $userId, string $reference, ?string $transactionId = null): array
    {
        $request = DB::table('payment_requests')
            ->where('user_id', $userId)
            ->where('reference', $reference)
            ->where('status', 'pending')
            ->first();

        if (!$request) {
            throw new \RuntimeException('payment_not_found');
        }

        if ($request->expires_at && now()->greaterThan($request->expires_at)) {
            DB::table('payment_requests')->where('id', $request->id)->update([
                'status' => 'expired',
                'updated_at' => now(),
            ]);
            throw new \RuntimeException('payment_expired');
        }

        $verified = $this->verifyWithProvider($request, $transactionId);
        if (!$verified) {
            throw new \RuntimeException('payment_not_verified');
        }

        DB::transaction(function () use ($request, $transactionId, $userId) {
            DB::table('payment_requests')->where('id', $request->id)->update([
                'status' => 'completed',
                'external_ref' => $transactionId,
                'updated_at' => now(),
            ]);

            $wallet = DB::table('wallets')->where([
                'owner_type' => 'user',
                'owner_id' => $userId,
            ])->first();

            $credit = (int) $request->amount;
            if (!$wallet) {
                DB::table('wallets')->insert([
                    'owner_type' => 'user',
                    'owner_id' => $userId,
                    'balance' => 0,
                    'points' => $credit,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('wallets')->where('id', $wallet->id)->update([
                    'points' => DB::raw('points + ' . $credit),
                    'updated_at' => now(),
                ]);
            }

            // Sessions spend `points` — credit the spendable ledger, not unused cash balance.
            DB::table('transactions')->insert([
                'owner_type' => 'user',
                'owner_id' => $userId,
                'type' => 'point_credit',
                'amount' => 0,
                'points' => $credit,
                'currency' => 'PTS',
                'meta' => json_encode([
                    'provider' => 'mtn',
                    'reference' => $request->reference,
                    'external_ref' => $transactionId,
                    'paid_amount' => $request->amount,
                    'paid_currency' => $request->currency ?? config('mtn.currency', 'SYP'),
                ]),
                'status' => 'succeeded',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $wallet = DB::table('wallets')->where(['owner_type' => 'user', 'owner_id' => $userId])->first();

        return [
            'ok' => true,
            'balance' => (int) ($wallet->balance ?? 0),
            'points' => (int) ($wallet->points ?? 0),
        ];
    }

    private function verifyWithProvider(object $request, ?string $transactionId): bool
    {
        if (config('mtn.sandbox', true)) {
            return $transactionId !== null && strlen($transactionId) >= 4;
        }

        $apiUrl = rtrim((string) config('mtn.api_url'), '/');
        $key = config('mtn.subscription_key');
        if (!$apiUrl || !$key || !$transactionId) {
            return false;
        }

        try {
            $response = Http::withHeaders([
                'Ocp-Apim-Subscription-Key' => $key,
                'Authorization' => 'Bearer ' . $this->accessToken(),
                'X-Target-Environment' => 'sandbox',
            ])->timeout(20)->get($apiUrl . '/collection/v1_0/requesttopay/' . urlencode($transactionId));

            if (!$response->successful()) {
                Log::warning('mtn.verify_failed', ['status' => $response->status()]);
                return false;
            }

            $status = $response->json('status');
            return in_array($status, ['SUCCESSFUL', 'successful', 'COMPLETED'], true);
        } catch (\Throwable $e) {
            Log::warning('mtn.verify_exception', ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function accessToken(): string
    {
        $apiUrl = rtrim((string) config('mtn.api_url'), '/');
        $user = config('mtn.api_user');
        $key = config('mtn.api_key');
        $subKey = config('mtn.subscription_key');

        $response = Http::withHeaders([
            'Ocp-Apim-Subscription-Key' => $subKey,
        ])->withBasicAuth((string) $user, (string) $key)
            ->asForm()
            ->post($apiUrl . '/collection/token/');

        return (string) ($response->json('access_token') ?? '');
    }
}
