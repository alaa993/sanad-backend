<?php

namespace App\Console\Commands;

use App\Jobs\SendAppointmentReminder;
use App\Models\Appointment;
use Illuminate\Console\Command;

class SendSessionReminders extends Command
{
    protected $signature = 'sanad:send-session-reminders';

    protected $description = 'Dispatch push reminders for sessions starting in ~30 minutes';

    public function handle(): int
    {
        $from = now()->addMinutes(25);
        $to = now()->addMinutes(35);

        $appointments = Appointment::query()
            ->whereNull('reminder_sent_at')
            ->whereBetween('starts_at', [$from, $to])
            ->whereIn('status', [
                'pending', 'accepted', 'confirmed', 'scheduled', 'upcoming',
                'in_progress', 'started',
            ])
            ->pluck('id');

        foreach ($appointments as $id) {
            SendAppointmentReminder::dispatch((int) $id);
        }

        $this->info('Queued ' . $appointments->count() . ' session reminders.');
        return self::SUCCESS;
    }
}
