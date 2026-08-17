<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Appointment, Chat, ChatParticipant, PatientIntake, SessionRating, SessionTask, User};
use App\Services\LiveKitTokenService;
use App\Services\PushNotificationService;
use App\Support\Realtime;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Patient/specialist session lifecycle API: list, book, accept/update, join, LiveKit tokens, complete, ratings.
 * List responses are version-cached (~20s); overdue status → completed runs outside the cache so hits stay side-effect free.
 */
class SessionController extends Controller
{
    /**
     * Role-scoped appointment list. Bumps via sessions:ver:{userId}; filters are part of the cache key.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $role = $user->role ?? 'patient';
        $filters = $request->validate([
            'status' => ['nullable', 'string'],
            'from'   => ['nullable', 'date'],
            'to'     => ['nullable', 'date'],
            'day'    => ['nullable', 'date'],
            'month'  => ['nullable', 'integer', 'between:1,12'],
            'year'   => ['nullable', 'integer', 'between:2000,2100'],
        ]);
        $filterKey = md5(json_encode($request->query()));
        $ver = (int) Cache::get("sessions:ver:{$user->id}", 1);
        $cacheKey = "sessions:index:{$user->id}:{$role}:v{$ver}:{$filterKey}";

        $scope = Appointment::query()
            ->when($role === 'specialist', fn($q) => $q->where('specialist_id', $user->id))
            ->when($role === 'organization', fn($q) => $q->where('organization_id', $user->id))
            ->when(!in_array($role, ['specialist', 'organization'], true), fn($q) => $q->where('patient_id', $user->id));

        $now = now();
        // Keep status transitions outside the response cache so hits stay side-effect free.
        (clone $scope)
            ->whereIn('status', ['accepted','confirmed','in_progress','started','scheduled','upcoming'])
            ->where('ends_at', '<', $now)
            ->update(['status' => 'completed', 'closed_at' => $now]);

        $payload = Cache::remember($cacheKey, 20, function () use ($user, $role, $filters, $scope, $now) {
            $query = (clone $scope)
                ->with([
                    'specialist:id,name,avatar',
                    'organization:id,name',
                    'patient:id,name',
                ])
                ->select([
                    'id','patient_id','specialist_id','organization_id',
                    'type','status','starts_at','scheduled_at','ends_at',
                    'notes','rejection_reason','rejection_by','specialist_notes',
                    'rating','join_url','chat_id','points_cost',
                    'duration_minutes','extended_minutes',
                ]);

            if (!empty($filters['status'])) {
                $raw = strtolower(trim($filters['status']));
                $list = array_filter(array_map('trim', explode(',', $raw)));
                $list = array_map(function ($s) {
                    if ($s === 'cancelled') return 'canceled';
                    return $s;
                }, $list);
                $allowed = ['pending','accepted','confirmed','in_progress','started','scheduled','upcoming','completed','rejected','canceled'];
                $list = array_values(array_intersect($list, $allowed));
                if (!empty($list)) {
                    $query->whereIn('status', $list);
                }
            }

            if (!empty($filters['day'])) {
                $day = Carbon::parse($filters['day'])->toDateString();
                $query->whereDate('starts_at', $day);
            } else {
                if (!empty($filters['from']) && !empty($filters['to'])) {
                    $from = Carbon::parse($filters['from'])->startOfDay();
                    $to = Carbon::parse($filters['to'])->endOfDay();
                    $query->whereBetween('starts_at', [$from, $to]);
                } elseif (!empty($filters['from'])) {
                    $from = Carbon::parse($filters['from'])->startOfDay();
                    $query->where('starts_at', '>=', $from);
                } elseif (!empty($filters['to'])) {
                    $to = Carbon::parse($filters['to'])->endOfDay();
                    $query->where('starts_at', '<=', $to);
                }
                if (!empty($filters['year'])) {
                    $query->whereYear('starts_at', (int) $filters['year']);
                }
                if (!empty($filters['month'])) {
                    $query->whereMonth('starts_at', (int) $filters['month']);
                }
            }

            $items = $query->orderBy('starts_at', 'asc')
                ->limit(200)
                ->get();

            $ratedIds = [];
            if ($items->isNotEmpty()) {
                $ratedIds = SessionRating::whereIn('appointment_id', $items->pluck('id'))
                    ->where('direction', 'patient_to_specialist')
                    ->pluck('appointment_id')
                    ->flip()
                    ->all();
            }

            $pending = [];
            $accepted = [];
            $completed = [];
            $rejected = [];
            $upcoming = [];
            $history = [];

            foreach ($items as $a) {
                $row = $this->transform($a, $user, false, $ratedIds);
                $status = (string) $a->status;
                if ($status === 'pending') {
                    $pending[] = $row;
                } elseif (in_array($status, ['accepted', 'confirmed', 'in_progress', 'started', 'scheduled', 'upcoming'], true)) {
                    $accepted[] = $row;
                } elseif ($status === 'completed') {
                    $completed[] = $row;
                } elseif (in_array($status, ['rejected', 'canceled', 'cancelled'], true)) {
                    $rejected[] = $row;
                }

                $start = $this->startTime($a);
                if ($start && $start >= $now) {
                    $upcoming[] = $row;
                } else {
                    $history[] = $row;
                }
            }

            return [
                'pending'   => $pending,
                'accepted'  => $accepted,
                'completed' => $completed,
                'rejected'  => $rejected,
                // legacy buckets (keep for backward compatibility)
                'upcoming'  => $upcoming,
                'history'   => $history,
            ];
        });

        return response()->json($payload);
    }

    /**
     * Book a session. Patients must complete intake + pre-session survey; points are reserved from the wallet when cost > 0.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        $role = $user->role ?? 'patient';
        $data = $request->validate([
            'type'            => ['required', 'string', 'max:50'],
            'scheduled_at'    => ['required', 'date'],
            'specialist_id'   => ['nullable', 'exists:users,id'],
            'organization_id' => ['nullable', 'exists:users,id'],
            'notes'           => ['nullable', 'string'],
            'points_cost'     => ['nullable', 'integer', 'min:0'],
            'weekly_recurring' => ['nullable', 'boolean'],
            'recurrence_count' => ['nullable', 'integer', 'min:2', 'max:52'],
        ]);

        if ($role === 'patient') {
            $intake = PatientIntake::where('user_id', $user->id)->first();
            if (!$intake || !$intake->isComplete()) {
                return response()->json([
                    'message' => 'intake_required',
                    'error' => 'Complete patient intake before booking a session',
                ], 428);
            }
            if (!$intake->pre_session_completed_at) {
                return response()->json([
                    'message' => 'pre_session_required',
                    'error' => 'Complete pre-session survey before booking',
                ], 428);
            }
        }

        $start = $this->parseSchedule($data['scheduled_at'], $request);
        if ($start->lte(now()->subMinute())) {
            return response()->json([
                'message' => 'past_datetime',
                'error' => 'Session time must be in the future',
            ], 422);
        }
        $end   = $start->copy()->addMinutes(config('appointments.default_duration_minutes', 60));

        $cost = (int) config('sanad.session_price_points', 100);
        $appointment = null;
        try {
            DB::transaction(function () use ($user, $data, $start, $end, $cost, &$appointment) {
                if ($cost > 0) {
                    $wallet = $this->walletRow('user', $user->id);
                    if (($wallet->points ?? 0) < $cost) {
                        abort(402, 'insufficient_points');
                    }
                    // خصم من المريض عند الحجز (حجز مبلغ الجلسة)
                    DB::table('wallets')->where('id', $wallet->id)->update([
                        'points' => DB::raw('points - '.$cost),
                        'updated_at' => now(),
                    ]);
                }

                $appointment = Appointment::create([
                    'patient_id'      => $user->id,
                    'specialist_id'   => $data['specialist_id'] ?? null,
                    'organization_id' => $data['organization_id'] ?? null,
                    'type'            => $data['type'],
                    'points_cost'     => $cost,
                    'status'          => 'pending',
                    'starts_at'       => $start,
                    'ends_at'         => $end,
                    'scheduled_at'    => $start,
                    'notes'           => $data['notes'] ?? null,
                ]);

                if ($cost > 0) {
                    DB::table('transactions')->insert([
                        'owner_type' => 'user',
                        'owner_id'   => $user->id,
                        'type'       => 'point_debit',
                        'amount'     => 0,
                        'points'     => $cost * -1,
                        'currency'   => 'PTS',
                        'meta'       => json_encode(['appointment_id'=>$appointment->id, 'kind'=>'hold']),
                        'status'     => 'succeeded',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
        } catch (\Throwable $e) {
            if ($e->getMessage() === 'insufficient_points') {
                return response()->json(['ok'=>false,'msg'=>'insufficient_points'], 402);
            }
            return response()->json(['ok'=>false], 500);
        }

        $appointment->loadMissing('patient:id,name', 'specialist:id,name', 'organization:id,name');
        $chat = $this->ensureChatForAppointment($appointment);
        $this->syncChatParticipants($appointment, $chat);

        if (!empty($data['weekly_recurring']) && $appointment->specialist_id) {
            $this->spawnWeeklyRecurrence($appointment, (int) ($data['recurrence_count'] ?? 4));
        }

        $this->invalidateSessionsCacheForAppointment($appointment);
        if ($appointment->specialist_id) {
            app(PushNotificationService::class)->notifyUser(
                (int) $appointment->specialist_id,
                __('New session request'),
                __('A patient booked a new session.'),
                ['type' => 'session', 'session_id' => (string) $appointment->id]
            );
        }
        return response()->json($this->transform($this->autoCloseIfExpired($appointment->fresh([
            'specialist:id,name,avatar',
            'organization:id,name',
            'patient:id,name',
        ])), $user, true), 201);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();
        $appointment = Appointment::with([
                'specialist:id,name,avatar',
                'organization:id,name',
                'patient:id,name',
            ])
            ->findOrFail($id);

        if (!in_array($user->id, array_filter([$appointment->patient_id, $appointment->specialist_id, $appointment->organization_id]))) {
            abort(403);
        }

        return response()->json($this->transform($this->autoCloseIfExpired($appointment), $user, true));
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();
        $role = $user->role ?? 'patient';
        $appointment = Appointment::findOrFail($id);
        $previousStatus = $appointment->status;

        if (!in_array($user->id, array_filter([$appointment->specialist_id, $appointment->organization_id]))) {
            abort(403);
        }

        $data = $request->validate([
            'status'       => ['nullable', Rule::in(['pending','accepted','rejected','canceled','completed'])],
            'notes'        => ['nullable', 'string'],
            'scheduled_at' => ['nullable', 'date'],
            'join_url'     => ['nullable', 'string'],
            'rating'       => ['nullable', 'integer', 'min:1', 'max:5'],
            'specialist_notes' => ['nullable', 'string'],
            'rejection_reason' => ['nullable', 'string', 'max:500'],
        ]);
        unset($data['points_cost']);

        if (isset($data['scheduled_at'])) {
            $start = $this->parseSchedule($data['scheduled_at'], $request);
            if ($start->lte(now()->subMinute())) {
                return response()->json([
                    'message' => 'past_datetime',
                    'error' => 'Session time must be in the future',
                ], 422);
            }
            $data['starts_at'] = $start;
            $data['ends_at'] = $start->copy()->addMinutes(config('appointments.default_duration_minutes', 60));
            $data['scheduled_at'] = $start;
        }

        $appointment->update(array_filter($data, fn($v) => !is_null($v)));
        if (isset($data['status']) && in_array($data['status'], ['rejected','canceled','cancelled'], true)) {
            if (empty($appointment->rejection_by)) {
                $role = $user->role ?? ($user->roles()->value('name') ?? 'user');
                $appointment->rejection_by = $role;
            }
        }
        $appointment->save();
        $appointment = $appointment->fresh([
            'specialist:id,name,avatar',
            'organization:id,name',
            'patient:id,name',
        ]);
        $chat = $this->ensureChatForAppointment($appointment);
        $this->syncChatParticipants($appointment, $chat);

        $this->invalidateSessionsCacheForAppointment($appointment);
        if (isset($data['status']) && $data['status'] !== $previousStatus && $appointment->patient_id) {
            $statusLabel = $data['status'];
            app(PushNotificationService::class)->notifyUser(
                (int) $appointment->patient_id,
                __('Session update'),
                __('Your session status changed to :status.', ['status' => $statusLabel]),
                ['type' => 'session', 'session_id' => (string) $appointment->id]
            );
        }
        if (isset($data['status']) && in_array($data['status'], ['rejected','canceled','cancelled'], true)) {
            $this->maybeRefundOnCancel($appointment, $previousStatus);
        }
        if (isset($data['status']) && $data['status'] !== $previousStatus) {
            Realtime::sessionStatus((int) $appointment->id, (string) $data['status'], [
                'previous' => $previousStatus,
                'by' => $role,
                'patient_id' => $appointment->patient_id,
                'specialist_id' => $appointment->specialist_id,
            ]);
        }
        return response()->json($this->transform($this->autoCloseIfExpired($appointment), $user, true));
    }

    /** Patient marks the session in_progress and fans out session:status via realtime. */
    public function start(Request $request, $id)
    {
        $user = $request->user();
        $appointment = Appointment::findOrFail($id);
        if ($appointment->patient_id !== $user->id) {
            abort(403);
        }
        if (in_array($appointment->status, ['pending','rejected','canceled','cancelled','completed'], true)) {
            return response()->json(['ok' => false, 'status' => $appointment->status], 403);
        }

        $appointment->status = 'in_progress';
        $appointment->save();
        Realtime::sessionStatus((int) $appointment->id, 'in_progress', [
            'by' => 'patient',
            'patient_id' => $appointment->patient_id,
            'specialist_id' => $appointment->specialist_id,
        ]);
        return response()->json(['ok' => true, 'status' => $appointment->status]);
    }

    public function cancel(Request $request, $id)
    {
        $user = $request->user();
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $appointment = Appointment::findOrFail($id);
        $previousStatus = $appointment->status;
        if ($appointment->patient_id !== $user->id) {
            abort(403);
        }
        if (in_array($appointment->status, ['completed','rejected','canceled','cancelled'], true)) {
            return response()->json(['ok' => false, 'status' => $appointment->status], 409);
        }
        $appointment->status = 'canceled';
        $appointment->rejection_reason = $data['reason'] ?? null;
        $appointment->rejection_by = 'patient';
        $appointment->save();
        $this->maybeRefundOnCancel($appointment, $previousStatus);
        Realtime::sessionStatus((int) $appointment->id, 'canceled', [
            'previous' => $previousStatus,
            'by' => 'patient',
            'patient_id' => $appointment->patient_id,
            'specialist_id' => $appointment->specialist_id,
        ]);
        if ($appointment->specialist_id) {
            app(PushNotificationService::class)->notifyUser(
                (int) $appointment->specialist_id,
                __('Session canceled'),
                __('A patient canceled the session.'),
                ['type' => 'session', 'session_id' => (string) $appointment->id]
            );
        }
        return response()->json(['ok' => true, 'status' => $appointment->status]);
    }

    private function walletRow($ownerType, $ownerId)
    {
        $w = DB::table('wallets')->where(['owner_type'=>$ownerType,'owner_id'=>$ownerId])->first();
        if(!$w){
            DB::table('wallets')->insert(['owner_type'=>$ownerType,'owner_id'=>$ownerId,'balance'=>0,'points'=>0,'created_at'=>now(),'updated_at'=>now()]);
            $w = DB::table('wallets')->where(['owner_type'=>$ownerType,'owner_id'=>$ownerId])->first();
        }
        return $w;
    }

    private function creditPoints(string $ownerType, int $ownerId, int $points, int $appointmentId): void
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
            'meta'       => json_encode(['appointment_id'=>$appointmentId]),
            'status'     => 'succeeded',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }


    private function invalidateSessionsCacheForAppointment(Appointment $appointment): void
    {
        foreach (array_filter([
            $appointment->patient_id,
            $appointment->specialist_id,
            $appointment->organization_id,
        ]) as $userId) {
            $this->bumpSessionsCacheVersion((int) $userId);
        }
    }

    private function bumpSessionsCacheVersion(int $userId): void
    {
        $key = "sessions:ver:{$userId}";
        if (!Cache::has($key)) {
            Cache::forever($key, 2);
            return;
        }
        Cache::increment($key);
    }

    /**
     * @param  array<int, mixed>|null  $ratedAppointmentIds  appointment_id => truthy (batch lookup)
     */
    private function transform(Appointment $appointment, ?User $viewer = null, bool $ensureChat = true, ?array $ratedAppointmentIds = null): array
    {
        $appointment->loadMissing('patient:id,name', 'specialist:id,name', 'organization:id,name');
        // إنشاء غرفة المحادثة فقط عند الحاجة (تفاصيل الجلسة/الانضمام)
        $chat = null;
        if ($ensureChat) {
            $chat = $this->ensureChatForAppointment($appointment);
            $this->syncChatParticipants($appointment, $chat);
        }
        $viewerRole = $viewer?->role;
        $patientVisible = $viewer && ($appointment->patient_id === $viewer->id || in_array($viewerRole, ['specialist','organization'], true));
        if ($appointment->rating !== null) {
            $surveySubmitted = true;
        } elseif ($ratedAppointmentIds !== null) {
            $surveySubmitted = isset($ratedAppointmentIds[$appointment->id]);
        } else {
            $surveySubmitted = SessionRating::where('appointment_id', $appointment->id)
                ->where('direction', 'patient_to_specialist')->exists();
        }
        return [
            'id'            => $appointment->id,
            'type'          => $appointment->type,
            'status'        => $appointment->status,
            'scheduled_at'  => optional($this->startTime($appointment))->toIso8601String(),
            'ends_at'       => optional($appointment->ends_at)->toIso8601String(),
            'closes_at'     => optional($appointment->ends_at)->toIso8601String(),
            'duration_minutes' => (int) ($appointment->duration_minutes ?? 0),
            'extended_minutes' => (int) ($appointment->extended_minutes ?? 0),
            'notes'         => $appointment->notes,
            'rejection_reason' => $appointment->rejection_reason,
            'rejection_by' => $appointment->rejection_by,
            'specialist_notes' => $appointment->specialist_notes,
            'rating'        => $appointment->rating,
            'survey_submitted' => $surveySubmitted,
            'transferred_at' => optional($appointment->transferred_at)->toIso8601String(),
            'transfer_reason' => $appointment->transfer_reason,
            'recurrence_series_id' => $appointment->recurrence_series_id,
            'occurrence_index' => $appointment->occurrence_index,
            'join_url'      => $appointment->join_url,
            'chat_id'       => $chat?->id ?? $appointment->chat_id,
            'points_cost'   => (int) ($appointment->points_cost ?? 0),
            'specialist'    => $appointment->specialist ? [
                'id'   => $appointment->specialist->id,
                'name' => $appointment->specialist->name,
                'avatar' => $appointment->specialist->avatar ?? null,
            ] : null,
            'organization'  => $appointment->organization ? [
                'id'   => $appointment->organization->id,
                'name' => $appointment->organization->name,
            ] : null,
            'user'          => $appointment->patient ? [
                'id'   => $appointment->patient->id,
                'name' => $patientVisible ? $appointment->patient->name : null,
                'avatar' => $appointment->patient->avatar ?? null,
            ] : null,
        ];
    }

    private function startTime(Appointment $appointment): ?Carbon
    {
        return $appointment->starts_at ?? $appointment->scheduled_at;
    }

    private function ensureChatForAppointment(Appointment $appointment): Chat
    {
        if ($appointment->chat_id) {
            if ($chat = Chat::find($appointment->chat_id)) {
                return $chat;
            }
        }

        $appointment->loadMissing('patient:id,name', 'specialist:id,name');
        $chat = Chat::create([
            'subject' => $this->buildChatSubject($appointment),
        ]);

        $appointment->chat_id = $chat->id;
        $appointment->save();

        return $chat;
    }

    private function syncChatParticipants(Appointment $appointment, Chat $chat): void
    {
        $now = now();
        $participants = [
            $appointment->patient_id      => 'user',
            $appointment->specialist_id   => 'specialist',
        ];
        if ($appointment->organization_id) {
            $participants[$appointment->organization_id] = 'support';
        }

        foreach ($participants as $userId => $role) {
            if (!$userId) {
                continue;
            }
            ChatParticipant::firstOrCreate(
                ['chat_id' => $chat->id, 'user_id' => $userId],
                ['role' => $role, 'joined_at' => $now]
            );
        }
    }

    /**
     * تمديد الجلسة من طرف الأخصائي
     */
    public function extend(Request $request, $id)
    {
        $user = $request->user();
        $appointment = Appointment::findOrFail($id);
        if ($appointment->specialist_id !== $user->id) {
            abort(403);
        }
        $data = $request->validate([
            'minutes' => ['required', 'integer', 'min:5', 'max:180'],
        ]);
        $minutes = (int) $data['minutes'];
        $appointment->extended_minutes = (int) ($appointment->extended_minutes ?? 0) + $minutes;
        $appointment->ends_at = Carbon::parse($appointment->ends_at ?? $appointment->starts_at)->addMinutes($minutes);
        $appointment->save();
        return response()->json([
            'ok' => true,
            'extended_minutes' => $appointment->extended_minutes,
            'ends_at' => optional($appointment->ends_at)->toIso8601String(),
        ]);
    }

    /**
     * Mint a LiveKit JWT for an open session room (session_{id}). Participants only; closed statuses are rejected.
     */
    public function livekitToken(Request $request, $id)
    {
        $user = $request->user();
        $appointment = Appointment::findOrFail($id);
        if (!in_array($user->id, array_filter([$appointment->patient_id, $appointment->specialist_id, $appointment->organization_id]))) {
            abort(403);
        }
        if (in_array($appointment->status, ['pending','rejected','canceled','cancelled','completed'], true)) {
            return response()->json(['message' => 'session_closed', 'status' => $appointment->status], 403);
        }

        $url = LiveKitTokenService::livekitUrl();
        if (!$url) {
            return response()->json(['message' => 'livekit_missing_url'], 500);
        }
        $role = $user->role ?? ($user->roles()->value('name') ?? 'user');
        $room = 'session_' . $appointment->id;
        $token = LiveKitTokenService::generateToken((string) $user->id, $user->name ?? 'User', $room, $role);
        return response()->json([
            'token' => $token,
            'url' => $url,
            'room' => $room,
        ]);
    }

    /**
     * إغلاق الجلسة (من الأخصائي)
     */
    public function complete(Request $request, $id)
    {
        $user = $request->user();
        $appointment = Appointment::findOrFail($id);
        if ($appointment->specialist_id !== $user->id) {
            abort(403);
        }

        $data = $request->validate([
            'diagnosis_notes' => ['nullable', 'string'],
            'patient_feedback' => ['nullable', 'integer', 'min:1', 'max:5'],
        ]);

        $appointment->status = 'completed';
        $appointment->closed_at = now();
        $this->applySessionCompletionFields($appointment, $data);
        $appointment->save();
        Realtime::sessionStatus((int) $appointment->id, 'completed', [
            'by' => 'specialist',
            'patient_id' => $appointment->patient_id,
            'specialist_id' => $appointment->specialist_id,
        ]);

        return response()->json(['ok' => true, 'status' => 'completed']);
    }

    /**
     * استبيان المريض بعد الجلسة
     */
    public function survey(Request $request, $id)
    {
        $user = $request->user();
        $appointment = Appointment::findOrFail($id);
        if ($appointment->patient_id !== $user->id) {
            abort(403);
        }
        if ($appointment->status !== 'completed') {
            return response()->json(['ok' => false, 'message' => 'session_not_completed'], 422);
        }
        if ($appointment->rating !== null || SessionRating::where('appointment_id', $appointment->id)
            ->where('direction', 'patient_to_specialist')->exists()) {
            return response()->json(['ok' => false, 'message' => 'survey_already_submitted'], 422);
        }

        $data = $request->validate([
            'patient_feedback' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string'],
        ]);

        $this->applySessionCompletionFields($appointment, [
            'patient_feedback' => $data['patient_feedback'],
            'survey_comment' => $data['comment'] ?? null,
        ]);
        $appointment->save();

        if ($appointment->specialist_id) {
            SessionRating::updateOrCreate(
                [
                    'appointment_id' => $appointment->id,
                    'rater_id' => $user->id,
                    'direction' => 'patient_to_specialist',
                ],
                [
                    'ratee_id' => $appointment->specialist_id,
                    'score' => $data['patient_feedback'],
                    'comment' => $data['comment'] ?? null,
                ]
            );
        }

        return response()->json(['ok' => true]);
    }

    private function applySessionCompletionFields(Appointment $appointment, array $data): void
    {
        if (array_key_exists('diagnosis_notes', $data) && $data['diagnosis_notes'] !== null) {
            if (Schema::hasColumn('appointments', 'specialist_notes')) {
                $appointment->specialist_notes = $data['diagnosis_notes'];
            } else {
                $this->mergeAppointmentMeta($appointment, ['diagnosis_notes' => $data['diagnosis_notes']]);
            }
        }

        if (array_key_exists('patient_feedback', $data) && $data['patient_feedback'] !== null) {
            if (Schema::hasColumn('appointments', 'rating')) {
                $appointment->rating = (int) $data['patient_feedback'];
            } else {
                $this->mergeAppointmentMeta($appointment, ['patient_feedback' => (int) $data['patient_feedback']]);
            }
        }

        if (!empty($data['survey_comment'])) {
            $this->mergeAppointmentMeta($appointment, ['survey_comment' => $data['survey_comment']]);
        }
    }

    private function mergeAppointmentMeta(Appointment $appointment, array $extras): void
    {
        if (Schema::hasColumn('appointments', 'meta')) {
            $meta = $appointment->meta;
            if (is_string($meta)) {
                $meta = json_decode($meta, true);
            }
            if (!is_array($meta)) {
                $meta = [];
            }
            $appointment->meta = array_merge($meta, $extras);
            return;
        }

        $decoded = json_decode($appointment->notes ?? '', true);
        if (!is_array($decoded)) {
            $decoded = [];
            if (!empty($appointment->notes)) {
                $decoded['text'] = $appointment->notes;
            }
        }
        $appointment->notes = json_encode(array_merge($decoded, $extras));
    }

    /**
     * إضافة مهمة/تمرين من الأخصائي للمريض
     */
    public function addTask(Request $request, $id)
    {
        $user = $request->user();
        $appointment = Appointment::findOrFail($id);
        if ($appointment->specialist_id !== $user->id) {
            abort(403);
        }
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['nullable', Rule::in(['task','question','exercise'])],
            'due_at' => ['nullable', 'date'],
            'create_follow_up' => ['nullable', 'boolean'],
        ]);
        $createFollowUp = $data['create_follow_up'] ?? true;
        $task = SessionTask::create([
            'appointment_id' => $appointment->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'] ?? 'task',
            'status' => 'open',
        ]);
        if ($createFollowUp && $appointment->patient_id) {
            \App\Models\PatientTask::create([
                'user_id' => $appointment->patient_id,
                'appointment_id' => $appointment->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'due_at' => !empty($data['due_at']) ? Carbon::parse($data['due_at']) : null,
                'status' => 'pending',
                'meta' => [
                    'session_task_id' => $task->id,
                    'type' => $data['type'] ?? 'task',
                    'source' => 'session',
                ],
            ]);
        }
        if ($appointment->patient_id) {
            app(PushNotificationService::class)->notifyUser(
                (int) $appointment->patient_id,
                __('New session task'),
                $data['title'],
                ['type' => 'task', 'session_id' => (string) $appointment->id, 'task_id' => (string) $task->id]
            );
        }
        return response()->json(['ok' => true, 'task' => $task], 201);
    }

    /**
     * جلب مهام الجلسة
     */
    public function listTasks(Request $request, $id)
    {
        $user = $request->user();
        $appointment = Appointment::findOrFail($id);
        if (!in_array($user->id, array_filter([$appointment->patient_id, $appointment->specialist_id, $appointment->organization_id]))) {
            abort(403);
        }
        $tasks = SessionTask::where('appointment_id', $appointment->id)->orderByDesc('id')->get();
        return response()->json(['data' => $tasks]);
    }

    /**
     * إكمال المهمة من المريض مع إجابة اختيارية
     */
    public function completeTask(Request $request, $taskId)
    {
        $user = $request->user();
        $task = SessionTask::findOrFail($taskId);
        $appointment = Appointment::findOrFail($task->appointment_id);
        if ($appointment->patient_id !== $user->id) {
            abort(403);
        }
        $data = $request->validate([
            'answer' => ['nullable', 'string'],
        ]);
        $task->status = 'completed';
        $task->patient_answer = $data['answer'] ?? null;
        $task->completed_at = now();
        $task->save();
        return response()->json(['ok' => true, 'task' => $task]);
    }

    /**
     * تقييم المريض للأخصائي
     */
    public function rateSpecialist(Request $request, $id)
    {
        $user = $request->user();
        $appointment = Appointment::findOrFail($id);
        if ($appointment->patient_id !== $user->id) {
            abort(403);
        }
        $data = $request->validate([
            'score' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string'],
        ]);
        SessionRating::updateOrCreate(
            [
                'appointment_id' => $appointment->id,
                'rater_id' => $user->id,
                'direction' => 'patient_to_specialist',
            ],
            [
                'ratee_id' => $appointment->specialist_id,
                'score' => $data['score'],
                'comment' => $data['comment'] ?? null,
            ]
        );
        return response()->json(['ok' => true]);
    }

    /**
     * تقييم الأخصائي للمريض
     */
    public function ratePatient(Request $request, $id)
    {
        $user = $request->user();
        $appointment = Appointment::findOrFail($id);
        if ($appointment->specialist_id !== $user->id) {
            abort(403);
        }
        $data = $request->validate([
            'score' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string'],
        ]);
        SessionRating::updateOrCreate(
            [
                'appointment_id' => $appointment->id,
                'rater_id' => $user->id,
                'direction' => 'specialist_to_patient',
            ],
            [
                'ratee_id' => $appointment->patient_id,
                'score' => $data['score'],
                'comment' => $data['comment'] ?? null,
            ]
        );
        return response()->json(['ok' => true]);
    }

    private function buildChatSubject(Appointment $appointment): string
    {
        $parts = [];
        if ($appointment->patient && $appointment->patient->name) {
            $parts[] = $appointment->patient->name;
        }
        if ($appointment->specialist && $appointment->specialist->name) {
            $parts[] = $appointment->specialist->name;
        }
        if (!empty($parts)) {
            return implode(' × ', $parts);
        }
        return 'Therapy session #' . $appointment->id;
    }

    /**
     * إغلاق تلقائي للجلسات التي تجاوزت وقت الإنهاء ولم تُغلق.
     */
    private function autoCloseIfExpired(Appointment $appointment): Appointment
    {
        $start = $this->startTime($appointment);
        if ($appointment->status === 'pending' && $start && now()->greaterThanOrEqualTo($start)) {
            $appointment->status = 'rejected';
            $appointment->closed_at = now();
            $appointment->save();
            $this->maybeRefundOnCancel($appointment, 'pending');
            return $appointment;
        }
        $end = $appointment->ends_at ?? $appointment->scheduled_at;
        if ($end && now()->greaterThan($end) && !in_array($appointment->status, ['completed','canceled','rejected'])) {
            $appointment->status = 'completed';
            $appointment->closed_at = now();
            $appointment->save();
        }
        return $appointment;
    }

    /**
     * Parse a schedule string using client/user timezone then convert to UTC for storage.
     */
    private function parseSchedule(string $value, Request $request): Carbon
    {
        $tz = $request->input('timezone') ?: ($request->user()->timezone ?? config('app.timezone', 'UTC'));
        return Carbon::parse($value, $tz)->setTimezone('UTC');
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
        $start = $this->startTime($appointment);
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
                    $this->debitPoints('user', $appointment->specialist_id, $shares['spec'], $appointment->id, 'reversal');
                }
                if ($appointment->organization_id && $shares['org'] > 0) {
                    $this->debitPoints('user', $appointment->organization_id, $shares['org'], $appointment->id, 'reversal');
                }
                if ($shares['platform'] > 0) {
                    $this->debitPoints('platform', 0, $shares['platform'], $appointment->id, 'reversal');
                }
            }
            $this->creditPointsWithKind('user', $appointment->patient_id, $cost, $appointment->id, 'refund');
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

    private function spawnWeeklyRecurrence(Appointment $first, int $count): void
    {
        $count = min(max($count, 2), (int) config('appointments.recurrence_max_occurrences', 52));
        $starts = Carbon::parse($first->starts_at);
        $ends = Carbon::parse($first->ends_at);
        $durationMinutes = $starts->diffInMinutes($ends);

        $seriesId = DB::table('appointment_recurrence_series')->insertGetId([
            'patient_id' => $first->patient_id,
            'specialist_id' => $first->specialist_id,
            'frequency' => 'weekly',
            'occurrence_count' => $count,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $first->recurrence_series_id = $seriesId;
        $first->occurrence_index = 1;
        $first->duration_minutes = $durationMinutes;
        $first->save();

        for ($i = 2; $i <= $count; $i++) {
            $occStart = $starts->copy()->addWeeks($i - 1);
            $occEnd = $occStart->copy()->addMinutes($durationMinutes);
            Appointment::create([
                'patient_id' => $first->patient_id,
                'specialist_id' => $first->specialist_id,
                'organization_id' => $first->organization_id,
                'type' => $first->type,
                'points_cost' => $first->points_cost,
                'status' => 'pending',
                'starts_at' => $occStart,
                'ends_at' => $occEnd,
                'scheduled_at' => $occStart,
                'notes' => $first->notes,
                'recurrence_series_id' => $seriesId,
                'occurrence_index' => $i,
                'duration_minutes' => $durationMinutes,
            ]);
        }
    }
}
