<?php

namespace App\Services;

use App\Models\AnonymousMatchRequest;
use App\Models\Chat;
use App\Models\ChatParticipant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Anonymous patient matchmaking: queue by gender preferences, pair into a private Chat with aliases.
 * Waiting requests expire after 15 minutes; only patients may join.
 */
class AnonymousMatchService
{
    /** Enqueue the user (cancels prior waiting) and pair immediately if a compatible partner is waiting. */
    public function joinQueue(User $user, string $gender, string $matchGender, string $mode): array
    {
        if ($user->role !== 'patient') {
            abort(403, 'Only patients can use anonymous matching');
        }

        $this->cancelWaiting($user->id);

        $request = AnonymousMatchRequest::create([
            'user_id' => $user->id,
            'gender' => $gender,
            'match_gender' => $matchGender,
            'mode' => $mode,
            'status' => 'waiting',
            'alias_self' => $this->alias(),
            'expires_at' => now()->addMinutes(15),
        ]);

        $partner = $this->findPartner($request);
        if ($partner) {
            return $this->pair($request, $partner);
        }

        return $this->transform($request->fresh());
    }

    public function status(User $user): ?array
    {
        $row = AnonymousMatchRequest::where('user_id', $user->id)
            ->whereIn('status', ['waiting', 'matched'])
            ->latest()
            ->first();

        if (!$row) {
            return null;
        }

        if ($row->status === 'waiting' && $row->expires_at && $row->expires_at->isPast()) {
            $row->update(['status' => 'expired', 'ended_at' => now()]);
            return null;
        }

        if ($row->status === 'waiting') {
            $partner = $this->findPartner($row);
            if ($partner) {
                return $this->pair($row, $partner);
            }
        }

        return $this->transform($row);
    }

    public function leave(User $user): void
    {
        AnonymousMatchRequest::where('user_id', $user->id)
            ->where('status', 'waiting')
            ->update(['status' => 'cancelled', 'ended_at' => now()]);
    }

    public function end(User $user, int $matchId): void
    {
        $row = AnonymousMatchRequest::where('id', $matchId)
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)->orWhere('partner_id', $user->id);
            })
            ->where('status', 'matched')
            ->firstOrFail();

        DB::transaction(function () use ($row) {
            $now = now();
            AnonymousMatchRequest::where('id', $row->id)
                ->orWhere(function ($q) use ($row) {
                    $q->where('user_id', $row->partner_id)
                        ->where('partner_id', $row->user_id)
                        ->where('status', 'matched');
                })
                ->update(['status' => 'ended', 'ended_at' => $now]);
        });
    }

    private function cancelWaiting(int $userId): void
    {
        AnonymousMatchRequest::where('user_id', $userId)
            ->where('status', 'waiting')
            ->update(['status' => 'cancelled', 'ended_at' => now()]);
    }

    private function findPartner(AnonymousMatchRequest $request): ?AnonymousMatchRequest
    {
        return AnonymousMatchRequest::where('status', 'waiting')
            ->where('id', '!=', $request->id)
            ->where('user_id', '!=', $request->user_id)
            ->where('mode', $request->mode)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderBy('created_at')
            ->get()
            ->first(function ($candidate) use ($request) {
                return $this->gendersCompatible(
                    $request->gender,
                    $request->match_gender,
                    $candidate->gender,
                    $candidate->match_gender
                );
            });
    }

    private function gendersCompatible(string $gA, string $prefA, string $gB, string $prefB): bool
    {
        return $this->prefAllows($prefA, $gA, $gB) && $this->prefAllows($prefB, $gB, $gA);
    }

    private function prefAllows(string $pref, string $selfGender, string $otherGender): bool
    {
        if ($pref === 'any') {
            return true;
        }
        if ($pref === 'same') {
            return $selfGender === $otherGender;
        }

        return $otherGender === $pref;
    }

    /** Create a private Chat with aliased participants and mark both requests matched. */
    private function pair(AnonymousMatchRequest $a, AnonymousMatchRequest $b): array
    {
        return DB::transaction(function () use ($a, $b) {
            $chat = Chat::create(['subject' => 'محادثة مجهولة']);
            ChatParticipant::create(['chat_id' => $chat->id, 'user_id' => $a->user_id, 'role' => 'patient']);
            ChatParticipant::create(['chat_id' => $chat->id, 'user_id' => $b->user_id, 'role' => 'patient']);

            $now = now();
            $aliasB = $b->alias_self ?: $this->alias();

            $a->update([
                'status' => 'matched',
                'partner_id' => $b->user_id,
                'chat_id' => $chat->id,
                'alias_partner' => $aliasB,
                'matched_at' => $now,
            ]);

            $b->update([
                'status' => 'matched',
                'partner_id' => $a->user_id,
                'chat_id' => $chat->id,
                'alias_partner' => $a->alias_self,
                'matched_at' => $now,
            ]);

            if ($a->user_id) {
                User::where('id', $a->user_id)->update(['gender' => $a->gender]);
            }
            if ($b->user_id) {
                User::where('id', $b->user_id)->update(['gender' => $b->gender]);
            }

            return $this->transform($a->fresh());
        });
    }

    private function alias(): string
    {
        return 'صديق #' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    public function transform(?AnonymousMatchRequest $row): ?array
    {
        if (!$row) {
            return null;
        }

        return [
            'id' => $row->id,
            'status' => $row->status,
            'mode' => $row->mode,
            'gender' => $row->gender,
            'match_gender' => $row->match_gender,
            'chat_id' => $row->chat_id,
            'alias_self' => $row->alias_self,
            'alias_partner' => $row->alias_partner,
            'matched_at' => optional($row->matched_at)->toIso8601String(),
            'expires_at' => optional($row->expires_at)->toIso8601String(),
        ];
    }
}
