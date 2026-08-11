<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\OrgPeriodicReportMail;

class SendOrgPeriodicReports extends Command
{
    protected $signature = 'sanad:send-org-periodic-reports';
    protected $description = 'Send periodic summary reports to organization accounts';

    public function handle(): int
    {
        $orgs = User::query()->where('role', 'organization')->where('status', 'approved')->get();
        $sent = 0;

        foreach ($orgs as $org) {
            try {
                $summary = $this->buildSummary((int) $org->id);
                if (!$summary) {
                    continue;
                }
                $email = $org->email;
                if (!$email) {
                    continue;
                }
                Mail::to($email)->send(new OrgPeriodicReportMail(
                    $org->name ?? 'منظمة',
                    $summary,
                    now()->subMonth()->format('Y-m') . ' — ' . now()->format('Y-m')
                ));
                $sent++;
            } catch (\Throwable $e) {
                Log::warning('org report failed', ['org' => $org->id, 'error' => $e->getMessage()]);
            }
        }

        $this->info("Sent {$sent} org reports.");
        return self::SUCCESS;
    }

    private function buildSummary(int $orgId): ?array
    {
        $from = now()->subMonth();
        $sessions = (int) DB::table('appointments')
            ->where('organization_id', $orgId)
            ->where('created_at', '>=', $from)
            ->count();
        if ($sessions === 0) {
            return null;
        }
        $completed = (int) DB::table('appointments')
            ->where('organization_id', $orgId)
            ->where('status', 'completed')
            ->where('created_at', '>=', $from)
            ->count();
        $beneficiaries = (int) DB::table('appointments')
            ->where('organization_id', $orgId)
            ->where('created_at', '>=', $from)
            ->distinct('patient_id')
            ->count('patient_id');

        return compact('sessions', 'completed', 'beneficiaries');
    }

    private function formatText(?string $name, array $summary): string
    {
        return "تقرير Sanad الدوري — {$name}\n"
            . "الجلسات: {$summary['sessions']}\n"
            . "المكتملة: {$summary['completed']}\n"
            . "المستفيدون: {$summary['beneficiaries']}\n";
    }
}
