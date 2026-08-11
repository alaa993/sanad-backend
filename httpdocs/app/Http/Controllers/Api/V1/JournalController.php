<?php
namespace App\Http\Controllers\Api\V1; use App\Http\Controllers\Controller; use Illuminate\Http\Request; use App\Models\Journal;
use App\Models\Appointment;
use App\Models\PatientIntake;
use Illuminate\Support\Facades\Cache;
class JournalController extends Controller {
  private function journalAllowed(Request $r): bool
  {
    if (!config('sanad.journal_requires_recovery', true)) {
      return true;
    }
    $intake = PatientIntake::firstWhere('user_id', $r->user()->id);
    if ($intake && $intake->recovery_unlocked) {
      return true;
    }
    $completed = Appointment::where('patient_id', $r->user()->id)->where('status', 'completed')->count();
    $minSessions = (int) config('sanad.recovery_min_completed_sessions', 3);
    $minBenefit = (int) config('sanad.recovery_min_benefit_score', 70);
    if ($completed >= $minSessions && $intake && (int) $intake->benefit_score >= $minBenefit) {
      if ($intake) {
        $intake->recovery_unlocked = true;
        $intake->save();
      }
      return true;
    }
    return false;
  }

  public function index(Request $r){
    if (!$this->journalAllowed($r)) {
      return response()->json(['data' => [], 'locked' => true, 'message' => 'journal_locked_until_recovery'], 403);
    }
    $userId = $r->user()->id;
    $cacheKey = "journal:list:{$userId}";
    $payload = Cache::remember($cacheKey, 20, function () use ($userId) {
      $items=Journal::where(['user_id'=>$userId])->orderByDesc('created_at')->limit(200)->get(['id','entry','created_at']);
      return ['data'=>$items];
    });
    return response()->json($payload);
  }
  public function store(Request $r){
    if (!$this->journalAllowed($r)) {
      return response()->json(['message' => 'journal_locked_until_recovery'], 403);
    }
    $data=$r->validate(['entry'=>'required|string|max:4000']);
    $j=Journal::create(['user_id'=>$r->user()->id,'entry'=>$data['entry'],'created_at'=>now()]);
    Cache::forget("journal:list:{$r->user()->id}");
    return response()->json(['id'=>$j->id,'created_at'=>optional($j->created_at)->toIso8601String()],201);
  }
  public function destroy(Request $r,$id){
    Journal::where(['id'=>$id,'user_id'=>$r->user()->id])->delete();
    Cache::forget("journal:list:{$r->user()->id}");
    return response()->json(['deleted'=>true]);
  }
}
