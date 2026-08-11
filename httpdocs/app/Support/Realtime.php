<?php

namespace App\Support;

use App\Jobs\BroadcastRealtimeEvent;

/**
 * Laravel → Socket.IO bridge helpers. Prefer queued emit unless $immediate (e.g. session status during a call).
 * Room naming convention: session_{id}, chat_{id}, community_{id}, user_{id}.
 */
class Realtime
{
    /** Dispatch BroadcastRealtimeEvent (HTTP internal emit preferred; ElephantIO fallback). */
    public static function emit(string $event, array $payload, bool $immediate = false): void
    {
        if ($immediate) {
            (new BroadcastRealtimeEvent($event, $payload))->handle();
            return;
        }

        BroadcastRealtimeEvent::dispatch($event, $payload);
    }

    /**
     * Emit session:status to session_{id} and notify:event to patient/specialist user rooms.
     * Defaults to immediate so mobile clients see join/accept without waiting on the queue.
     */
    public static function sessionStatus(int $sessionId, string $status, array $meta = [], bool $immediate = true): void
    {
        self::emit('session:status', [
            'room' => 'session_'.$sessionId,
            'sessionId' => $sessionId,
            'status' => $status,
            'meta' => $meta,
            'patientId' => $meta['patient_id'] ?? $meta['patientId'] ?? null,
            'specialistId' => $meta['specialist_id'] ?? $meta['specialistId'] ?? null,
        ], $immediate);

        foreach (['patient_id', 'patientId', 'specialist_id', 'specialistId', 'user_id', 'userId'] as $key) {
            $uid = $meta[$key] ?? null;
            if (!$uid) {
                continue;
            }
            self::emit('notify:event', [
                'targetUserId' => (int) $uid,
                'type' => 'session:status',
                'data' => array_merge($meta, [
                    'sessionId' => $sessionId,
                    'status' => $status,
                ]),
            ], $immediate);
        }
    }

    public static function libraryUpdated(array $meta = [], bool $immediate = true): void
    {
        self::emit('library:updated', array_merge([
            'at' => now()->toIso8601String(),
        ], $meta), $immediate);
    }
}
