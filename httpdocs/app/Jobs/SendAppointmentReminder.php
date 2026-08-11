<?php

namespace App\Jobs;

use App\Models\Appointment;
use App\Services\PushNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendAppointmentReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $appointmentId;

    public function __construct(int $appointmentId)
    {
        $this->appointmentId = $appointmentId;
    }

    public function handle(PushNotificationService $push): void
    {
        $appointment = Appointment::with(['patient:id,name', 'specialist:id,name'])
            ->find($this->appointmentId);

        if (!$appointment || $appointment->reminder_sent_at) {
            return;
        }

        $start = $appointment->starts_at ?? $appointment->scheduled_at;
        $when = optional($start)->format('Y-m-d H:i') ?? '';
        $title = __('Session reminder');

        if ($appointment->patient_id) {
            $specialist = $appointment->specialist->name ?? __('Specialist');
            $push->notifyUser(
                (int) $appointment->patient_id,
                $title,
                __('Your session with :name starts at :time', ['name' => $specialist, 'time' => $when]),
                ['type' => 'session', 'session_id' => (string) $appointment->id]
            );
        }

        if ($appointment->specialist_id) {
            $patient = $appointment->patient->name ?? __('Patient');
            $push->notifyUser(
                (int) $appointment->specialist_id,
                $title,
                __('Your session with :name starts at :time', ['name' => $patient, 'time' => $when]),
                ['type' => 'session', 'session_id' => (string) $appointment->id]
            );
        }

        $appointment->forceFill(['reminder_sent_at' => now()])->save();
    }
}
