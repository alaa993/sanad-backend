<?php
namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class AdminWalletController extends Controller
{
    public function createCoupon(Request $request)
    {
        $data = $request->validate([
            'code' => ['required','string','max:64'],
            'points' => ['required','integer','min:1'],
            'expires_at' => ['nullable','date'],
        ]);
        $exists = DB::table('coupons')->where('code', $data['code'])->exists();
        if ($exists) {
            return response()->json(['ok'=>false,'msg'=>'code_exists'], 422);
        }
        $payload = [
            'code' => $data['code'],
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if (Schema::hasColumn('coupons', 'points')) {
            $payload['points'] = $data['points'];
        } elseif (Schema::hasColumn('coupons', 'amount_off')) {
            $payload['amount_off'] = $data['points'];
        }
        if (Schema::hasColumn('coupons', 'type')) {
            $payload['type'] = 'points';
        }
        if (!empty($data['expires_at'])) {
            $payload['expires_at'] = Carbon::parse($data['expires_at']);
        }
        DB::table('coupons')->insert($payload);
        return response()->json(['ok'=>true]);
    }

    public function credit(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required','integer','exists:users,id'],
            'points' => ['required','integer','min:1'],
        ]);
        $userId = $data['user_id'];
        $points = (int) $data['points'];
        DB::transaction(function() use ($userId, $points) {
            $wallet = $this->walletRow('user', $userId);
            DB::table('wallets')->where('id', $wallet->id)->update([
                'points' => DB::raw('points + '.$points),
                'updated_at' => now(),
            ]);
            DB::table('transactions')->insert([
                'owner_type' => 'user',
                'owner_id'   => $userId,
                'type'       => 'point_credit',
                'amount'     => 0,
                'points'     => $points,
                'currency'   => 'PTS',
                'meta'       => json_encode(['source'=>'admin_manual']),
                'status'     => 'succeeded',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
        Cache::forget("billing:wallet:{$userId}");
        Cache::forget("billing:tx:{$userId}");
        return response()->json(['ok'=>true]);
    }

    private function walletRow($ownerType, $ownerId)
    {
        $w = DB::table('wallets')->where(['owner_type'=>$ownerType,'owner_id'=>$ownerId])->first();
        if(!$w){
            DB::table('wallets')->insert([
                'owner_type'=>$ownerType,
                'owner_id'=>$ownerId,
                'balance'=>0,
                'points'=>0,
                'created_at'=>now(),
                'updated_at'=>now(),
            ]);
            $w = DB::table('wallets')->where(['owner_type'=>$ownerType,'owner_id'=>$ownerId])->first();
        }
        return $w;
    }
}
