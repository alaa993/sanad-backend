<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Syriatel Cash wallet top-up (parallel to MtnPaymentService): init pending request, confirm, credit points.
 * Sandbox confirm can credit without a live merchant callback.
 */
class SyriatelCashService
{
    public function isEnabled(): bool
    {
        return (bool) config('syriatel.enabled', true) || (bool) config('syriatel.sandbox', true);
    }

    /** Persist a pending Syriatel top-up and return reference for Cash payment. */
    public function createTopupRequest(int $userId, int $amount, ?string $phone = null): array
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('invalid_amount');
        }

        $reference = 'SYR-' . $userId . '-' . strtoupper(Str::random(8));
        $expiresAt = now()->addHours(2);

        $id = DB::table('payment_requests')->insertGetId([
            'user_id' => $userId,
            'provider' => 'syriatel',
            'purpose' => 'wallet_topup',
            'amount' => $amount,
            'currency' => config('syriatel.currency', 'SYP'),
            'reference' => $reference,
            'phone' => $phone,
            'status' => 'pending',
            'meta' => json_encode([
                'sandbox' => (bool) config('syriatel.sandbox', true),
                'merchant_code' => config('syriatel.merchant_code'),
            ]),
            'expires_at' => $expiresAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $instructions = str_replace(
            ':reference',
            $reference,
            (string) config('syriatel.instructions_ar')
        );

        return [
            'id' => $id,
            'reference' => $reference,
            'amount' => $amount,
            'currency' => config('syriatel.currency', 'SYP'),
            'ussd' => config('syriatel.ussd_short_code'),
            'instructions' => $instructions,
            'expires_at' => $expiresAt->toIso8601String(),
            'sandbox' => (bool) config('syriatel.sandbox', true),
            'provider' => 'syriatel',
        ];
    }

    /** Confirm Syriatel payment and credit points; idempotent for already-paid references. */
    public function confirmTopup(int $userId, string $reference, ?string $transactionId = null): array
    {
        $request = DB::table('payment_requests')
            ->where('user_id', $userId)
            ->where('reference', $reference)
            ->where('provider', 'syriatel')
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

            DB::table('transactions')->insert([
                'owner_type' => 'user',
                'owner_id' => $userId,
                'type' => 'point_credit',
                'amount' => 0,
                'points' => $credit,
                'currency' => 'PTS',
                'meta' => json_encode([
                    'provider' => 'syriatel',
                    'reference' => $request->reference,
                    'external_ref' => $transactionId,
                    'paid_amount' => $request->amount,
                    'paid_currency' => $request->currency ?? config('syriatel.currency', 'SYP'),
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
        if (config('syriatel.sandbox', true)) {
            return $transactionId !== null && strlen(trim($transactionId)) >= 4;
        }

        $apiUrl = rtrim((string) config('syriatel.api_url'), '/');
        $apiKey = config('syriatel.api_key');
        if (!$apiUrl || !$apiKey || !$transactionId) {
            return false;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Accept' => 'application/json',
            ])->timeout(20)->get($apiUrl . '/payments/' . urlencode($transactionId));

            if (!$response->successful()) {
                Log::warning('syriatel.verify_failed', ['status' => $response->status()]);
                return false;
            }

            $status = strtolower((string) ($response->json('status') ?? ''));
            return in_array($status, ['success', 'successful', 'completed', 'paid'], true);
        } catch (\Throwable $e) {
            Log::warning('syriatel.verify_exception', ['error' => $e->getMessage()]);
            return false;
        }
    }
}
