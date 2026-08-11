<?php
namespace App\Http\Controllers\Api\V1\Specialist;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Appointment;
use App\Models\{Chat, ChatParticipant};
use App\Support\Realtime;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SpecialistSessionsController extends Controller
{
    public function index(Request $r)
    {
        $u = $r->user();
        $scope = $r->query('scope', 'pending');
        $ver = (int) Cache::get("sessions:ver:{$u->id}", 1);
        $cacheKey = "spec:sessions:{$u->id}:{$scope}:v{$ver}";

        // Expire stale pending outside cache so cache hits stay pure reads.
        $expired = Appointment::where('specialist_id', $u->id)
            ->where('status', 'pending')
            ->where('starts_at', '<=', now())
            ->get();
        foreach ($expired as $row) {
            $row->status = 'rejected';
            $row->closed_at = now();
            $row->save();
            $this->maybeRefundOnCancel($row, 'pending');
        }

        $payload = Cache::remember($cacheKey, 20, function () use ($u, $scope) {
        $q = Appointment::with(['patient:id,name,avatar', 'organization:id,name'])
            ->where('specialist_id', $u->id);

        if ($scope === 'pending') {
            $q->where('status', 'pending');
        } elseif ($scope === 'accepted') {
            $q->whereIn('status', ['accepted', 'confirmed', 'in_progress', 'started', 'scheduled', 'upcoming']);
        } elseif ($scope === 'rejected') {
            $q->whereIn('status', ['rejected', 'canceled', 'cancelled']);
        } elseif ($scope === 'upcoming') {
            $q->whereIn('status', ['pending', 'accepted', 'confirmed', 'in_progress', 'started', 'scheduled', 'upcoming'])
                ->where(function ($q) {
                    $q->where('starts_at', '>=', now())
                        ->orWhereIn('status', ['in_progress', 'started']);
                });
        } elseif ($scope === 'done' || $scope === 'completed') {
            $q->where('status', 'completed');
        }

        $rows = $q->orderBy('starts_at')->limit(200)->get();
        return [
            'data' => $rows->map(fn($a) => $this->transform($a, false))->values()
        ];
        });
        return response()->json($payload);
    }

    public function accept(Request $r, $id)
    {
        $u = $r->user();
        $a = Appointment::where(['id' => $id, 'specialist_id' => $u->id])->firstOrFail();
        $cost = (int) ($a->points_cost ?? 0);
        if ($cost > 0 && !$this->hasPayout($a->id)) {
            try {
                DB::transaction(function () use ($a, $cost) {
                    if (!$this->hasHold($a->id)) {
                        $wallet = $this->walletRow('user', (int) $a->patient_id);
                        if (($wallet->points ?? 0) < $cost) {
                            abort(402, 'insufficient_points');
                        }
                        DB::table('wallets')->where('id', $wallet->id)->update([
                            'points' => DB::raw('points - '.$cost),
                            'updated_at' => now(),
                        ]);
                        DB::table('transactions')->insert([
                            'owner_type' => 'user',
                            'owner_id'   => (int) $a->patient_id,
                            'type'       => 'point_debit',
                            'amount'     => 0,
                            'points'     => $cost * -1,
                            'currency'   => 'PTS',
                            'meta'       => json_encode(['appointment_id'=>$a->id, 'kind'=>'hold']),
                            'status'     => 'succeeded',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    $shares = $this->sharesFor($a);
                    if ($a->specialist_id && $shares['spec'] > 0) {
                        $this->creditPointsWithKind('user', (int) $a->specialist_id, $shares['spec'], $a->id, 'payout');
                    }
                    if ($a->organization_id && $shares['org'] > 0) {
                        $this->creditPointsWithKind('user', (int) $a->organization_id, $shares['org'], $a->id, 'payout');
                    }
                    if ($shares['platform'] > 0) {
                        $this->creditPointsWithKind('platform', 0, $shares['platform'], $a->id, 'payout');
                    }
                });
            } catch (\Throwable $e) {
                if ($e->getMessage() === 'insufficient_points') {
                    return response()->json(['ok'=>false,'msg'=>'insufficient_points'], 402);
                }
                return response()->json(['ok'=>false], 500);
            }
        }
        $a->status = 'accepted';
        $a->save();
        $this->invalidateSessionsCache($a);
        $this->broadcastStatus($a, 'accepted');
        return response()->json(['ok' => true, 'appointment' => $this->transform($a->fresh(['patient:id,name,avatar', 'organization:id,name']), true)]);
    }

    public function reject(Request $r, $id)
    {
        $u = $r->user();
        $data = $r->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $a = Appointment::where(['id' => $id, 'specialist_id' => $u->id])->firstOrFail();
        $previousStatus = $a->status;
        $a->status = 'rejected';
        $a->rejection_reason = $data['reason'] ?? null;
        $a->rejection_by = 'specialist';
        $a->save();
        $this->maybeRefundOnCancel($a, $previousStatus);
        $this->invalidateSessionsCache($a);
        $this->broadcastStatus($a, 'rejected');
        return response()->json(['ok' => true, 'appointment' => $this->transform($a->fresh(['patient:id,name,avatar', 'organization:id,name']), true)]);
    }

    public function reschedule(Request $r, $id)
    {
        $u = $r->user();
        $a = Appointment::where(['id' => $id, 'specialist_id' => $u->id])->firstOrFail();
        $data = $r->validate([
            'starts_at' => ['required', 'date'],
            'timezone'  => ['nullable', 'string'],
            'join_url'  => ['nullable', 'string'],
            'notes'     => ['nullable', 'string'],
        ]);
        $start = $this->parseSchedule($data['starts_at'], $r);
        if ($start->lte(now()->subMinute())) {
            return response()->json([
                'message' => 'past_datetime',
                'error' => 'Session time must be in the future',
            ], 422);
        }
        $a->starts_at = $start;
        $a->scheduled_at = $start;
        $a->ends_at = $start->copy()->addMinutes(config('appointments.default_duration_minutes', 60));
        if (isset($data['join_url'])) {
            $a->join_url = $data['join_url'];
        }
        if (isset($data['notes'])) {
            $a->notes = $data['notes'];
        }
        // إعادة تأكيد الموعد بعد تغيير الوقت
        $a->status = 'pending';
        $a->save();
        $this->invalidateSessionsCache($a);
        $this->broadcastStatus($a, 'pending');
        return response()->json(['ok' => true, 'appointment' => $this->transform($a->fresh(['patient:id,name,avatar', 'organization:id,name']), true)]);
    }

    public function extend(Request $r, $id)
    {
        $u = $r->user();
        $a = Appointment::where(['id' => $id, 'specialist_id' => $u->id])->firstOrFail();
        $data = $r->validate([
            'minutes' => ['required', 'integer', 'min:5', 'max:180'],
        ]);
        $minutes = (int) $data['minutes'];
        $a->extended_minutes = (int) ($a->extended_minutes ?? 0) + $minutes;
        $a->ends_at = Carbon::parse($a->ends_at ?? $a->starts_at)->addMinutes($minutes);
        $a->save();
        $this->invalidateSessionsCache($a);
        $this->broadcastStatus($a, (string) $a->status, ['extended_minutes' => $minutes]);
        return response()->json(['ok' => true, 'appointment' => $this->transform($a->fresh(['patient:id,name,avatar', 'organization:id,name']), true)]);
    }

    public function complete(Request $r, $id)
    {
        $u = $r->user();
        $a = Appointment::where(['id' => $id, 'specialist_id' => $u->id])->firstOrFail();
        $data = $r->validate([
            'diagnosis_notes' => ['nullable', 'string'],
            'patient_feedback' => ['nullable', 'integer', 'min:1', 'max:5'],
        ]);
        $a->status = 'completed';
        $a->closed_at = now();
        if (!empty($data['diagnosis_notes']) && \Illuminate\Support\Facades\Schema::hasColumn('appointments', 'specialist_notes')) {
            $a->specialist_notes = $data['diagnosis_notes'];
        }
        if (!empty($data['patient_feedback']) && \Illuminate\Support\Facades\Schema::hasColumn('appointments', 'rating')) {
            $a->rating = (int) $data['patient_feedback'];
        }
        $a->save();
        $this->invalidateSessionsCache($a);
        $this->broadcastStatus($a, 'completed');
        return response()->json(['ok' => true, 'appointment' => $this->transform($a->fresh(['patient:id,name,avatar', 'organization:id,name']), true)]);
    }

    private function broadcastStatus(Appointment $a, string $status, array $extra = []): void
    {
        Realtime::sessionStatus((int) $a->id, $status, array_merge($extra, [
            'patient_id' => $a->patient_id,
            'specialist_id' => $a->specialist_id,
            'by' => 'specialist',
        ]));
    }

    private function transform(Appointment $a, bool $ensureChat): array
    {
        $a->loadMissing('patient:id,name,avatar', 'organization:id,name');
        $chat = $ensureChat ? $this->ensureChatForAppointment($a) : null;
        return [
            'id'          => $a->id,
            'status'      => $a->status,
            'type'        => $a->type,
            'scheduled_at'=> optional($a->starts_at ?? $a->scheduled_at)->toIso8601String(),
            'ends_at'     => optional($a->ends_at)->toIso8601String(),
            'join_url'    => $a->join_url,
            'chat_id'     => $chat?->id ?? $a->chat_id,
            'notes'       => $a->notes,
            'points_cost' => (int) ($a->points_cost ?? 0),
            'rejection_reason' => $a->rejection_reason,
            'rejection_by' => $a->rejection_by,
            'patient'     => $a->patient ? [
                'id'    => $a->patient->id,
                'name'  => $a->patient->name,
                'avatar'=> $a->patient->avatar ?? null,
            ] : null,
            'organization'=> $a->organization ? [
                'id'   => $a->organization->id,
                'name' => $a->organization->name,
            ] : null,
            'can' => [
                'accept'     => $a->status === 'pending',
                'reject'     => in_array($a->status, ['pending','accepted']),
                'reschedule' => in_array($a->status, ['pending','accepted']),
                'start'      => in_array($a->status, ['accepted','in_progress']),
            ],
        ];
    }

    private function ensureChatForAppointment(Appointment $a): ?Chat
    {
        if ($a->chat_id && ($chat = Chat::find($a->chat_id))) {
            $this->syncChatParticipants($a, $chat);
            return $chat;
        }
        $a->loadMissing('patient:id,name', 'specialist:id,name');
        $chat = Chat::create(['subject' => $this->buildChatSubject($a)]);
        $a->chat_id = $chat->id;
        $a->save();
        $this->syncChatParticipants($a, $chat);
        return $chat;
    }

    private function syncChatParticipants(Appointment $a, Chat $chat): void
    {
        $now = now();
        $participants = [
            $a->patient_id => 'user',
            $a->specialist_id => 'specialist',
        ];
        if ($a->organization_id) {
            $participants[$a->organization_id] = 'support';
        }
        foreach ($participants as $uid => $role) {
            if (!$uid) {
                continue;
            }
            ChatParticipant::firstOrCreate(
                ['chat_id' => $chat->id, 'user_id' => $uid],
                ['role' => $role, 'joined_at' => $now]
            );
        }
    }

    private function buildChatSubject(Appointment $a): string
    {
        $parts = [];
        if ($a->patient && $a->patient->name) {
            $parts[] = $a->patient->name;
        }
        if ($a->specialist && $a->specialist->name) {
            $parts[] = $a->specialist->name;
        }
        if (!empty($parts)) {
            return implode(' × ', $parts);
        }
        return 'Therapy session #' . $a->id;
    }


    /**
     * Parse a schedule string using client/user timezone then convert to UTC for storage.
     */
    private function parseSchedule(string $value, Request $request): Carbon
    {
        $tz = $request->input('timezone') ?: ($request->user()->timezone ?? config('app.timezone', 'UTC'));
        return Carbon::parse($value, $tz)->setTimezone('UTC');
    }

    private function walletRow(string $ownerType, int $ownerId)
    {
        $w = DB::table('wallets')->where(['owner_type'=>$ownerType,'owner_id'=>$ownerId])->first();
        if(!$w){
            DB::table('wallets')->insert(['owner_type'=>$ownerType,'owner_id'=>$ownerId,'balance'=>0,'points'=>0,'created_at'=>now(),'updated_at'=>now()]);
            $w = DB::table('wallets')->where(['owner_type'=>$ownerType,'owner_id'=>$ownerId])->first();
        }
        return $w;
    }

    private function getPlatformFeePercent(): int
    {
        $raw = DB::table('site_settings')->where('key', 'platform_fee_percent')->value('value');
        $percent = is_numeric($raw) ? (int) $raw : 10;
        if ($percent < 0) $percent = 0;
        if ($percent > 100) $percent = 100;
        return $percent;
    }

    private function sharesFor(Appointment $appointment): array
    {
        $cost = (int) ($appointment->points_cost ?? 0);
        if ($cost <= 0) {
            return ['spec' => 0, 'org' => 0, 'platform' => 0, 'cost' => 0];
        }
        $platformPercent = $this->getPlatformFeePercent();
        $platformShare = (int) round($cost * $platformPercent / 100);
        $orgShare = $appointment->organization_id ? (int) round($cost * 0.2) : 0;
        $specShare = $cost - $platformShare - $orgShare;
        if ($specShare < 0) {
            $platformShare = max(0, $cost - $orgShare);
            $specShare = 0;
        }
        return ['spec' => $specShare, 'org' => $orgShare, 'platform' => $platformShare, 'cost' => $cost];
    }

    private function hasHold(int $appointmentId): bool
    {
        return DB::table('transactions')
            ->where('meta->appointment_id', $appointmentId)
            ->where('meta->kind', 'hold')
            ->exists();
    }

    private function hasPayout(int $appointmentId): bool
    {
        return DB::table('transactions')
            ->where('meta->appointment_id', $appointmentId)
            ->where('meta->kind', 'payout')
            ->exists();
    }

    private function hasRefund(int $appointmentId): bool
    {
        return DB::table('transactions')
            ->where('meta->appointment_id', $appointmentId)
            ->where('meta->kind', 'refund')
            ->exists();
    }

    private function isRefundEligible(Appointment $appointment): bool
    {
        $start = $appointment->starts_at ?? $appointment->scheduled_at;
        if (!$start) {
            return false;
        }
        return now()->diffInSeconds($start, false) >= 3600;
    }

    private function maybeRefundOnCancel(Appointment $appointment, string $previousStatus): void
    {
        $cost = (int) ($appointment->points_cost ?? 0);
        if ($cost <= 0) {
            return;
        }
        if ($this->hasRefund($appointment->id)) {
            return;
        }
        $wasPending = $previousStatus === 'pending';
        if (!$wasPending && !$this->isRefundEligible($appointment)) {
            return;
        }
        DB::transaction(function () use ($appointment, $cost) {
            $shares = $this->sharesFor($appointment);
            if ($this->hasPayout($appointment->id)) {
                if ($appointment->specialist_id && $shares['spec'] > 0) {
                    $this->debitPoints('user', (int) $appointment->specialist_id, $shares['spec'], $appointment->id, 'reversal');
                }
                if ($appointment->organization_id && $shares['org'] > 0) {
                    $this->debitPoints('user', (int) $appointment->organization_id, $shares['org'], $appointment->id, 'reversal');
                }
                if ($shares['platform'] > 0) {
                    $this->debitPoints('platform', 0, $shares['platform'], $appointment->id, 'reversal');
                }
            }
            $this->creditPointsWithKind('user', (int) $appointment->patient_id, $cost, $appointment->id, 'refund');
        });
    }

    private function debitPoints(string $ownerType, int $ownerId, int $points, int $appointmentId, string $kind): void
    {
        $wallet = $this->walletRow($ownerType, $ownerId);
        DB::table('wallets')->where('id', $wallet->id)->update([
            'points' => DB::raw('points - '.$points),
            'updated_at' => now(),
        ]);
        DB::table('transactions')->insert([
            'owner_type' => $ownerType,
            'owner_id'   => $ownerId,
            'type'       => 'point_debit',
            'amount'     => 0,
            'points'     => $points * -1,
            'currency'   => 'PTS',
            'meta'       => json_encode(['appointment_id'=>$appointmentId, 'kind'=>$kind]),
            'status'     => 'succeeded',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function creditPointsWithKind(string $ownerType, int $ownerId, int $points, int $appointmentId, string $kind): void
    {
        $wallet = $this->walletRow($ownerType, $ownerId);
        DB::table('wallets')->where('id', $wallet->id)->update([
            'points' => DB::raw('points + '.$points),
            'updated_at' => now(),
        ]);
        DB::table('transactions')->insert([
            'owner_type' => $ownerType,
            'owner_id'   => $ownerId,
            'type'       => 'point_credit',
            'amount'     => 0,
            'points'     => $points,
            'currency'   => 'PTS',
            'meta'       => json_encode(['appointment_id'=>$appointmentId, 'kind'=>$kind]),
            'status'     => 'succeeded',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function invalidateSessionsCache(Appointment $a): void
    {
        foreach (array_filter([$a->specialist_id, $a->patient_id, $a->organization_id]) as $userId) {
            $key = "sessions:ver:{$userId}";
            if (!Cache::has($key)) {
                Cache::forever($key, 2);
            } else {
                Cache::increment($key);
            }
            Cache::forget("dash:{$userId}:patient");
            Cache::forget("dash:{$userId}:specialist");
            Cache::forget("dash:{$userId}:organization");
            Cache::forget("billing:wallet:{$userId}");
            Cache::forget("billing:tx:{$userId}");
        }
        // Org billing overview may key on organizations.id while organization_id on
        // appointments is the org *user* id — clear both when possible.
        if ($a->organization_id) {
            $orgTableId = DB::table('organization_user')
                ->where('user_id', $a->organization_id)
                ->value('organization_id');
            if ($orgTableId) {
                Cache::forget("org:billing:overview:{$orgTableId}");
            }
        }
    }
}
