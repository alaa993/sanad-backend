<?php
namespace App\Jobs;
use Illuminate\Bus\Queueable; use Illuminate\Contracts\Queue\ShouldQueue; use Illuminate\Foundation\Bus\Dispatchable; use Illuminate\Queue\InteractsWithQueue; use Illuminate\Queue\SerializesModels;
use App\Models\Appointment; use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
class SweepExpiredPendingAppointments implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public function handle(): void {
        $hours = (int)config('appointments.pending_expire_hours', 48);
        $cut = Carbon::now()->subHours($hours);
        $rows = Appointment::where('status','pending')->where('created_at','<',$cut)->get();
        foreach ($rows as $a) {
            $a->status = 'canceled';
            $a->save();
            $this->refundHold($a);
        }
    }

    private function refundHold(Appointment $appointment): void
    {
        $cost = (int) ($appointment->points_cost ?? 0);
        if ($cost <= 0) return;
        $hasRefund = DB::table('transactions')
            ->where('meta->appointment_id', $appointment->id)
            ->where('meta->kind', 'refund')
            ->exists();
        if ($hasRefund) return;
        DB::transaction(function () use ($appointment, $cost) {
            $wallet = DB::table('wallets')->where(['owner_type'=>'user','owner_id'=>$appointment->patient_id])->first();
            if (!$wallet) {
                DB::table('wallets')->insert(['owner_type'=>'user','owner_id'=>$appointment->patient_id,'balance'=>0,'points'=>0,'created_at'=>now(),'updated_at'=>now()]);
                $wallet = DB::table('wallets')->where(['owner_type'=>'user','owner_id'=>$appointment->patient_id])->first();
            }
            DB::table('wallets')->where('id', $wallet->id)->update([
                'points' => DB::raw('points + '.$cost),
                'updated_at' => now(),
            ]);
            DB::table('transactions')->insert([
                'owner_type' => 'user',
                'owner_id'   => (int) $appointment->patient_id,
                'type'       => 'point_credit',
                'amount'     => 0,
                'points'     => $cost,
                'currency'   => 'PTS',
                'meta'       => json_encode(['appointment_id'=>$appointment->id, 'kind'=>'refund']),
                'status'     => 'succeeded',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }
}
