<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Services\SpecialistTransferService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ProcessNoResponseTransfers extends Command
{
    protected $signature = 'sanad:process-no-response-transfers';
    protected $description = 'Transfer pending sessions when specialist did not respond in time';

    public function handle(SpecialistTransferService $transferService): int
    {
        if (!config('appointments.transfer_enabled', true)) {
            return self::SUCCESS;
        }

        $hours = (int) config('appointments.no_response_hours', 24);
        $deadline = Carbon::now()->subHours(max(1, $hours));
        $now = now();

        $candidates = Appointment::query()
            ->where('status', 'pending')
            ->where(function ($q) use ($deadline, $now) {
                $q->where('starts_at', '<=', $now)
                    ->orWhere('created_at', '<=', $deadline);
            })
            ->orderBy('starts_at')
            ->limit(100)
            ->get();

        $transferred = 0;
        $rejected = 0;

        foreach ($candidates as $appointment) {
            if ($transferService->transfer($appointment, 'no_response')) {
                $transferred++;
                continue;
            }

            $appointment->status = 'rejected';
            $appointment->rejection_reason = 'no_specialist_response';
            $appointment->rejection_by = 'system';
            $appointment->closed_at = $now;
            $appointment->save();
            $rejected++;
        }

        $this->info("Transferred: {$transferred}, rejected: {$rejected}");
        return self::SUCCESS;
    }
}
