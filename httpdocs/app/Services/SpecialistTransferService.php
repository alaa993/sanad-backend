<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Chat;
use App\Models\ChatParticipant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reassigns a pending appointment to another specialist when the current one does not respond.
 * Preserves original_specialist_id, logs appointment_transfers when the table exists, and rewires chat participants.
 */
class SpecialistTransferService
{
    /** Pick next specialist (org-scoped when possible), reset status to pending, update chat membership. */
    public function transfer(Appointment $appointment, string $reason = 'no_response'): bool
    {
        if (!$appointment->specialist_id || !$appointment->patient_id) {
            return false;
        }

        $exclude = array_filter([
            (int) $appointment->specialist_id,
            (int) $appointment->original_specialist_id,
        ]);

        $nextId = $this->findNextSpecialist($exclude, $appointment->organization_id);
        if (!$nextId) {
            return false;
        }

        $fromId = (int) $appointment->specialist_id;
        if (!$appointment->original_specialist_id) {
            $appointment->original_specialist_id = $fromId;
        }

        $appointment->specialist_id = $nextId;
        $appointment->transferred_at = now();
        $appointment->transfer_reason = $reason;
        $appointment->status = 'pending';
        $appointment->save();

        if (Schema::hasTable('appointment_transfers')) {
            DB::table('appointment_transfers')->insert([
                'appointment_id' => $appointment->id,
                'from_specialist_id' => $fromId,
                'to_specialist_id' => $nextId,
                'reason' => $reason,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->syncChatSpecialist($appointment, $fromId, $nextId);

        $push = app(PushNotificationService::class);
        $push->notifyUser(
            (int) $appointment->patient_id,
            __('Session transferred'),
            __('Your session was assigned to another specialist'),
            ['type' => 'transfer', 'session_id' => (string) $appointment->id]
        );
        $push->notifyUser(
            $nextId,
            __('New transferred session'),
            __('A session was transferred to you'),
            ['type' => 'transfer', 'session_id' => (string) $appointment->id]
        );
        $push->notifyUser(
            $fromId,
            __('Session transferred away'),
            __('A pending session was reassigned'),
            ['type' => 'transfer', 'session_id' => (string) $appointment->id]
        );

        return true;
    }

    private function findNextSpecialist(array $exclude, ?int $organizationId): ?int
    {
        $query = DB::table('users as u')
            ->join('specialist_profiles as sp', 'sp.user_id', '=', 'u.id')
            ->where('u.role', 'specialist')
            ->where('sp.status', 'approved')
            ->where('sp.accepting_new', true)
            ->when(!empty($exclude), fn ($q) => $q->whereNotIn('u.id', $exclude))
            ->orderBy('u.id');

        if ($organizationId) {
            $orgSpecialists = DB::table('organization_user')
                ->where('organization_id', $organizationId)
                ->pluck('user_id')
                ->all();
            if (!empty($orgSpecialists)) {
                $query->whereIn('u.id', $orgSpecialists);
            }
        }

        $row = $query->first(['u.id']);
        return $row ? (int) $row->id : null;
    }

    public function findPsychiatrist(?int $organizationId = null): ?int
    {
        $query = DB::table('users as u')
            ->join('specialist_profiles as sp', 'sp.user_id', '=', 'u.id')
            ->where('u.role', 'specialist')
            ->where('sp.status', 'approved')
            ->where('sp.accepting_new', true)
            ->where(function ($q) {
                $q->where('sp.specialty', 'like', '%psychiat%')
                    ->orWhere('sp.specialty', 'like', '%Psychiat%')
                    ->orWhere('sp.specialty', 'like', '%طبيب نفس%')
                    ->orWhere('sp.specialty', 'like', '%طب نفس%');
            })
            ->orderBy('u.id');

        if ($organizationId) {
            $orgSpecialists = DB::table('organization_user')
                ->where('organization_id', $organizationId)
                ->pluck('user_id')
                ->all();
            if (!empty($orgSpecialists)) {
                $query->whereIn('u.id', $orgSpecialists);
            }
        }

        $row = $query->first(['u.id']);

        return $row ? (int) $row->id : null;
    }

    public function isPsychiatrist(int $specialistId): bool
    {
        $specialty = DB::table('specialist_profiles')
            ->where('user_id', $specialistId)
            ->value('specialty');

        if (!$specialty) {
            return false;
        }

        $lower = mb_strtolower($specialty);

        return str_contains($lower, 'psychiat')
            || str_contains($specialty, 'طبيب نفس')
            || str_contains($specialty, 'طب نفس');
    }

    private function syncChatSpecialist(Appointment $appointment, int $fromId, int $toId): void
    {
        if (!$appointment->chat_id) {
            return;
        }
        $chat = Chat::find($appointment->chat_id);
        if (!$chat) {
            return;
        }
        ChatParticipant::where('chat_id', $chat->id)
            ->where('user_id', $fromId)
            ->delete();
        ChatParticipant::firstOrCreate(
            ['chat_id' => $chat->id, 'user_id' => $toId],
            ['role' => 'specialist', 'joined_at' => now()]
        );
    }
}
