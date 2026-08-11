<?php
namespace App\Http\Controllers\Api\V1\Admin;
use App\Http\Controllers\Controller; use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
class AdminAppointmentsController extends Controller {
    public function index(){
        $payload = Cache::remember('admin:appointments:list', 30, function () {
            try {
                $rows = DB::table('appointments')->select('id','status','starts_at','ends_at','specialist_id','patient_id')
                    ->orderBy('starts_at','desc')->limit(200)->get();
            } catch(\Throwable $e){ $rows=[]; }
            return ['data'=>$rows];
        });
        return response()->json($payload);
    }
}
