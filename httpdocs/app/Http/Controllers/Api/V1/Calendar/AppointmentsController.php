<?php
namespace App\Http\Controllers\Api\V1\Calendar;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\{Appointment, SpecialistBlockedTime, SpecialistAvailabilitySlot};
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class AppointmentsController extends Controller {

    private function userScopeQuery($u, $scope){
        if($scope==='specialist'){ return Appointment::where('specialist_id',$u->id); }
        return Appointment::where('patient_id',$u->id);
    }

    public function index(Request $r){
        $u = $r->user();
        $scope = $r->query('scope','patient');
        $q = $this->userScopeQuery($u,$scope);
        if($r->filled('from')) $q->where('starts_at','>=',$r->query('from'));
        if($r->filled('to')) $q->where('starts_at','<=',$r->query('to'));
        $cacheKey = "cal:appointments:{$u->id}:{$scope}:" . md5((string) $r->query('from') . '|' . (string) $r->query('to'));
        $payload = Cache::remember($cacheKey, 20, function () use ($q) {
            return ['data'=>$q->orderBy('starts_at')->limit(500)->get()];
        });
        return response()->json($payload);
    }

    public function store(Request $r){
        $u = $r->user();
        $data = $r->validate([
            'specialist_id'=>'required|integer|exists:users,id',
            'starts_at'=>'required|date',
            'ends_at'=>'required|date|after:starts_at',
            'notes'=>'nullable|string'
        ]);
        // conflict check basic
        $conflict = Appointment::where('specialist_id',$data['specialist_id'])
            ->where(function($q) use($data){ $q->whereBetween('starts_at',[$data['starts_at'],$data['ends_at']])
                ->orWhereBetween('ends_at',[$data['starts_at'],$data['ends_at']])
                ->orWhere(function($q2) use($data){ $q2->where('starts_at','<=',$data['starts_at'])->where('ends_at','>=',$data['ends_at']); }); })
            ->whereIn('status',['pending','accepted'])
            ->exists();
        if($conflict){ return response()->json(['error'=>'conflict'],409); }

        $blocked = SpecialistBlockedTime::where('specialist_id',$data['specialist_id'])
            ->where(function($q) use($data){ $q->where('start_at','<=',$data['starts_at'])->where('end_at','>=',$data['starts_at'])
                ->orWhere('start_at','<=',$data['ends_at'])->where('end_at','>=',$data['ends_at']); })->exists();
        if($blocked){ return response()->json(['error'=>'blocked'],422); }

        $appt = Appointment::create([
            'patient_id'=>$u->id, 'specialist_id'=>$data['specialist_id'],
            'starts_at'=>$data['starts_at'], 'ends_at'=>$data['ends_at'],
            'status'=>'pending', 'source'=>'patient', 'notes'=>$data['notes'] ?? null
        ]);
        Cache::forget("cal:appointments:{$u->id}:patient:" . md5(''));
        return response()->json(['id'=>$appt->id,'status'=>$appt->status],201);
    }

    public function storeRecurring(Request $r){
        $u = $r->user();
        $data = $r->validate([
            'specialist_id'=>'required|integer|exists:users,id',
            'starts_at'=>'required|date',
            'ends_at'=>'required|date|after:starts_at',
            'notes'=>'nullable|string',
            'frequency'=>'nullable|in:weekly',
            'occurrence_count'=>'nullable|integer|min:2|max:52',
        ]);
        $count = min(
            (int) ($data['occurrence_count'] ?? 4),
            (int) config('appointments.recurrence_max_occurrences', 52)
        );
        $starts = Carbon::parse($data['starts_at']);
        $ends = Carbon::parse($data['ends_at']);
        $durationMinutes = $starts->diffInMinutes($ends);

        $seriesId = DB::table('appointment_recurrence_series')->insertGetId([
            'patient_id' => $u->id,
            'specialist_id' => $data['specialist_id'],
            'frequency' => $data['frequency'] ?? 'weekly',
            'weekday' => $starts->dayOfWeek,
            'time_of_day' => $starts->format('H:i:s'),
            'starts_at' => $starts,
            'occurrence_count' => $count,
            'notes' => $data['notes'] ?? null,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $created = [];
        for ($i = 0; $i < $count; $i++) {
            $slotStart = $starts->copy()->addWeeks($i);
            $slotEnd = $slotStart->copy()->addMinutes($durationMinutes);
            $appt = Appointment::create([
                'patient_id' => $u->id,
                'specialist_id' => $data['specialist_id'],
                'starts_at' => $slotStart,
                'ends_at' => $slotEnd,
                'scheduled_at' => $slotStart,
                'status' => 'pending',
                'source' => 'patient',
                'notes' => $data['notes'] ?? null,
                'recurrence_series_id' => $seriesId,
                'occurrence_index' => $i + 1,
            ]);
            $created[] = ['id' => $appt->id, 'starts_at' => $slotStart->toIso8601String()];
        }

        return response()->json([
            'series_id' => $seriesId,
            'occurrences' => $created,
        ], 201);
    }

    public function cancel(Request $r, $id){
        $u=$r->user();
        $data = $r->validate(['reason' => ['nullable','string','max:500']]);
        $a = Appointment::findOrFail($id);
        if(!in_array($u->id, [$a->patient_id,$a->specialist_id])) abort(403);
        $a->status = 'canceled';
        $a->rejection_reason = $data['reason'] ?? null;
        $a->rejection_by = $u->id === $a->specialist_id ? 'specialist' : 'patient';
        $a->save();
        Cache::forget("cal:appointments:{$a->patient_id}:patient:" . md5(''));
        Cache::forget("cal:appointments:{$a->specialist_id}:specialist:" . md5(''));
        return response()->json(['ok'=>true]);
    }

    public function accept(Request $r, $id){
        $u=$r->user();
        $a = Appointment::findOrFail($id);
        if($u->id !== $a->specialist_id) abort(403);
        $a->status = 'accepted'; $a->save();
        Cache::forget("cal:appointments:{$a->specialist_id}:specialist:" . md5(''));
        Cache::forget("cal:appointments:{$a->patient_id}:patient:" . md5(''));
        return response()->json(['ok'=>true]);
    }

    public function reject(Request $r, $id){
        $u=$r->user();
        $data = $r->validate(['reason' => ['nullable','string','max:500']]);
        $a = Appointment::findOrFail($id);
        if($u->id !== $a->specialist_id) abort(403);
        $a->status = 'rejected';
        $a->rejection_reason = $data['reason'] ?? null;
        $a->rejection_by = 'specialist';
        $a->save();
        Cache::forget("cal:appointments:{$a->specialist_id}:specialist:" . md5(''));
        Cache::forget("cal:appointments:{$a->patient_id}:patient:" . md5(''));
        return response()->json(['ok'=>true]);
    }

    public function reschedule(Request $r, $id){
        $u=$r->user();
        $a = Appointment::findOrFail($id);
        if(!in_array($u->id, [$a->patient_id,$a->specialist_id])) abort(403);
        $data = $r->validate(['starts_at'=>'required|date','ends_at'=>'required|date|after:starts_at']);
        $a->starts_at = $data['starts_at']; $a->ends_at = $data['ends_at']; $a->status='pending'; $a->save();
        Cache::forget("cal:appointments:{$a->specialist_id}:specialist:" . md5(''));
        Cache::forget("cal:appointments:{$a->patient_id}:patient:" . md5(''));
        return response()->json(['ok'=>true,'status'=>$a->status]);
    }

    public function suggested(Request $r){
        $r->validate(['specialist_id'=>'required|integer','date'=>'required|date']);
        $date = Carbon::parse($r->query('date'))->startOfDay();
        $weekday = $date->dayOfWeek; // 0..6 (Sun..Sat)
        $cacheKey = "cal:suggested:{$r->query('specialist_id')}:{$date->toDateString()}";
        $payload = Cache::remember($cacheKey, 30, function () use ($r, $date, $weekday) {
            $slots = SpecialistAvailabilitySlot::where(['specialist_id'=>$r->query('specialist_id'), 'weekday'=>$weekday, 'active'=>true])->get();
        $duration = (int)config('appointments.default_duration_minutes', 50);

        $result = [];
        foreach($slots as $s){
            $start = Carbon::parse($date->toDateString() . ' ' . $s->start_time);
            $end   = Carbon::parse($date->toDateString() . ' ' . $s->end_time);
            while($start->copy()->addMinutes($duration) <= $end){
                $slotEnd = $start->copy()->addMinutes($duration);
                $conflict = Appointment::where('specialist_id',$r->query('specialist_id'))
                    ->where(function($q) use($start,$slotEnd){ $q->whereBetween('starts_at',[$start,$slotEnd])
                        ->orWhereBetween('ends_at',[$start,$slotEnd])
                        ->orWhere(function($q2) use($start,$slotEnd){ $q2->where('starts_at','<=',$start)->where('ends_at','>=',$slotEnd); }); })
                    ->whereIn('status',['pending','accepted'])->exists();
                if(!$conflict){
                    $result[] = ['starts_at'=>$start->toIso8601String(), 'ends_at'=>$slotEnd->toIso8601String()];
                }
                $start = $start->addMinutes($duration);
            }
        }
            return ['data'=>$result];
        });
        return response()->json($payload);
    }
}
