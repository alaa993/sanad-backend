<?php
namespace App\Http\Controllers\Api\V1\Calendar;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{SpecialistAvailabilitySlot, SpecialistBlockedTime};
use Illuminate\Support\Facades\Cache;

class AvailabilityController extends Controller {

    public function index(Request $r){
        $u = $r->user();
        $cacheKey = "availability:{$u->id}";
        $payload = Cache::remember($cacheKey, 30, function () use ($u) {
            $slots = SpecialistAvailabilitySlot::where('specialist_id',$u->id)->where('active',true)->get();
            $blocks = SpecialistBlockedTime::where('specialist_id',$u->id)->orderByDesc('start_at')->limit(100)->get();
            return ['slots'=>$slots,'blocks'=>$blocks];
        });
        return response()->json($payload);
    }

    public function store(Request $r){
        $u = $r->user();
        $data = $r->validate([
            'weekday'=>'required|integer|min:0|max:6',
            'start_time'=>'required|date_format:H:i',
            'end_time'=>'required|date_format:H:i',
            'repeat_rule'=>'nullable|string'
        ]);
        $slot = SpecialistAvailabilitySlot::create($data + ['specialist_id'=>$u->id, 'active'=>true]);
        Cache::forget("availability:{$u->id}");
        return response()->json(['id'=>$slot->id], 201);
    }

    public function destroy(Request $r, $id){
        $u=$r->user();
        SpecialistAvailabilitySlot::where(['id'=>$id,'specialist_id'=>$u->id])->delete();
        Cache::forget("availability:{$u->id}");
        return response()->json(['deleted'=>true]);
    }

    public function block(Request $r){
        $u = $r->user();
        $data = $r->validate([ 'start_at'=>'required|date', 'end_at'=>'required|date|after:start_at', 'reason'=>'nullable|string' ]);
        $b = SpecialistBlockedTime::create($data + ['specialist_id'=>$u->id]);
        Cache::forget("availability:{$u->id}");
        return response()->json(['id'=>$b->id], 201);
    }

    public function unblock(Request $r, $id){
        $u=$r->user();
        SpecialistBlockedTime::where(['id'=>$id,'specialist_id'=>$u->id])->delete();
        Cache::forget("availability:{$u->id}");
        return response()->json(['deleted'=>true]);
    }
}
