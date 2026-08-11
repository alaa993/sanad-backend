<?php
namespace App\Http\Controllers\Api\V1\Reports;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class ReportsController extends Controller {

  private function range(Request $r){
    $from = $r->query('from'); $to = $r->query('to');
    $from = $from ? Carbon::parse($from) : now()->subDays(29)->startOfDay();
    $to   = $to   ? Carbon::parse($to)   : now()->endOfDay();
    return [$from, $to];
  }

  public function overview(Request $r){
    [$from,$to] = $this->range($r);
    $cacheKey = "reports:overview:{$from->toDateString()}:{$to->toDateString()}";
    $payload = Cache::remember($cacheKey, 60, function () use ($from, $to) {
    $users = DB::table('users')->whereBetween('created_at',[$from,$to])->count();
    $sessions_total = DB::table('appointments')->whereBetween('created_at',[$from,$to])->count();
    $sessions_paid = DB::table('appointments')->whereBetween('created_at',[$from,$to])
      ->whereIn('status',['confirmed','accepted','completed'])->count();
    $revenue = 0;
    try{ $revenue = (int) DB::table('transactions')->whereBetween('created_at',[$from,$to])->where('type','charge')->sum('amount'); }catch(\Throwable $e){}
    $avg_rating = null;
    try{
      $avg_rating = DB::table('session_ratings')->whereBetween('created_at',[$from,$to])->avg('score');
      if ($avg_rating === null) {
        $avg_rating = DB::table('appointments')->whereBetween('created_at',[$from,$to])->whereNotNull('rating')->avg('rating');
      }
    }catch(\Throwable $e){ $avg_rating = null; }

    $survey_total = 0;
    $survey_responses = 0;
    try {
      $survey_total = (int) DB::table('appointments')->whereBetween('created_at',[$from,$to])->where('status','completed')->count();
      $survey_responses = (int) DB::table('session_ratings')
        ->where('direction','patient_to_specialist')
        ->whereBetween('created_at',[$from,$to])->count();
    } catch (\Throwable $e) {}

    $response_rate = $survey_total > 0 ? round(($survey_responses / $survey_total) * 100, 1) : null;

    return [
      'period'=>['from'=>$from->toDateString(),'to'=>$to->toDateString()],
      'cards'=>[
        ['key'=>'new_users','value'=>$users],
        ['key'=>'sessions_total','value'=>$sessions_total],
        ['key'=>'sessions_paid','value'=>$sessions_paid],
        ['key'=>'revenue','value'=>$revenue],
        ['key'=>'avg_rating','value'=>$avg_rating !== null ? round((float)$avg_rating, 2) : null],
        ['key'=>'survey_response_rate','value'=>$response_rate],
        ['key'=>'survey_responses','value'=>$survey_responses],
      ]
    ];
    });
    return response()->json($payload);
  }

  public function surveySummary(Request $r){
    [$from,$to] = $this->range($r);
    $cacheKey = "reports:surveys:{$from->toDateString()}:{$to->toDateString()}";
    $payload = Cache::remember($cacheKey, 60, function () use ($from, $to) {
      $completed = (int) DB::table('appointments')
        ->where('status','completed')
        ->whereBetween('closed_at',[$from,$to])->count();
      $responses = (int) DB::table('session_ratings')
        ->where('direction','patient_to_specialist')
        ->whereBetween('created_at',[$from,$to])->count();
      $avg = DB::table('session_ratings')
        ->where('direction','patient_to_specialist')
        ->whereBetween('created_at',[$from,$to])->avg('score');
      $histogram = DB::table('session_ratings')
        ->selectRaw('score, COUNT(*) as count')
        ->where('direction','patient_to_specialist')
        ->whereBetween('created_at',[$from,$to])
        ->groupBy('score')->orderBy('score')->get();
      return [
        'completed_sessions' => $completed,
        'survey_responses' => $responses,
        'response_rate' => $completed > 0 ? round(($responses / $completed) * 100, 1) : null,
        'avg_score' => $avg !== null ? round((float)$avg, 2) : null,
        'score_histogram' => $histogram,
      ];
    });
    return response()->json($payload);
  }

  public function sessionsSeries(Request $r){
    [$from,$to] = $this->range($r);
    $cacheKey = "reports:sessions:{$from->toDateString()}:{$to->toDateString()}";
    $payload = Cache::remember($cacheKey, 60, function () use ($from, $to) {
      $rows = DB::table('appointments')->selectRaw('DATE(created_at) d, COUNT(*) v')
        ->whereBetween('created_at',[$from,$to])->groupBy('d')->orderBy('d')->get();
      return ['data'=>$rows];
    });
    return response()->json($payload);
  }

  public function usersSeries(Request $r){
    [$from,$to] = $this->range($r);
    $cacheKey = "reports:users:{$from->toDateString()}:{$to->toDateString()}";
    $payload = Cache::remember($cacheKey, 60, function () use ($from, $to) {
      $rows = DB::table('users')->selectRaw('DATE(created_at) d, COUNT(*) v')
        ->whereBetween('created_at',[$from,$to])->groupBy('d')->orderBy('d')->get();
      return ['data'=>$rows];
    });
    return response()->json($payload);
  }

  public function revenueSeries(Request $r){
    [$from,$to] = $this->range($r);
    $cacheKey = "reports:revenue:{$from->toDateString()}:{$to->toDateString()}";
    $payload = Cache::remember($cacheKey, 60, function () use ($from, $to) {
      $rows = DB::table('transactions')->selectRaw('DATE(created_at) d, SUM(amount) v')
        ->whereBetween('created_at',[$from,$to])
        ->where('type','charge')->groupBy('d')->orderBy('d')->get();
      return ['data'=>$rows];
    });
    return response()->json($payload);
  }

  public function topSpecialists(Request $r){
    [$from,$to] = $this->range($r);
    $cacheKey = "reports:top_spec:{$from->toDateString()}:{$to->toDateString()}";
    $payload = Cache::remember($cacheKey, 60, function () use ($from, $to) {
      $rows = DB::table('appointments as a')
        ->join('users as u','u.id','=','a.specialist_id')
        ->leftJoin('specialist_profiles as sp','sp.user_id','=','a.specialist_id')
        ->whereBetween('a.created_at',[$from,$to])
        ->whereNotNull('a.specialist_id')
        ->selectRaw('u.id, u.name, sp.specialty, COUNT(*) sessions, AVG(a.rating) avg_rating')
        ->groupBy('u.id','u.name','sp.specialty')->orderByDesc('sessions')->limit(10)->get();
      return ['data'=>$rows];
    });
    return response()->json($payload);
  }

  public function topOrganizations(Request $r){
    [$from,$to] = $this->range($r);
    $cacheKey = "reports:top_org:{$from->toDateString()}:{$to->toDateString()}";
    $payload = Cache::remember($cacheKey, 60, function () use ($from, $to) {
      $rows = DB::table('appointments as a')
        ->join('users as o','o.id','=','a.organization_id')
        ->whereBetween('a.created_at',[$from,$to])
        ->whereNotNull('a.organization_id')
        ->selectRaw('o.id, o.name, COUNT(*) sessions, AVG(a.rating) avg_rating')
        ->groupBy('o.id','o.name')->orderByDesc('sessions')->limit(10)->get();
      return ['data'=>$rows];
    });
    return response()->json($payload);
  }

  public function retention(Request $r){
    [$from,$to] = $this->range($r);
    $cacheKey = "reports:retention:{$from->toDateString()}:{$to->toDateString()}";
    $payload = Cache::remember($cacheKey, 120, function () use ($from, $to) {
    $rows = DB::select(<<<SQL
      with week_sessions as (
        select patient_id as user_id, DATE_FORMAT(created_at,'%x-%v') wk
        from appointments where created_at between ? and ? group by patient_id, DATE_FORMAT(created_at,'%x-%v')
      ),
      cohorts as (
        select a.wk as week, count(distinct a.user_id) as users,
               count(distinct case when b.user_id is not null then a.user_id end) as retained
        from week_sessions a
        left join week_sessions b on b.user_id=a.user_id and b.wk=DATE_FORMAT(DATE_ADD(STR_TO_DATE(concat(a.wk,'-1'),'%x-%v-%w'), interval 7 day),'%x-%v')
        group by a.wk order by a.wk
      )
      select week, users, retained from cohorts
    SQL, [$from, $to]);
      return ['data'=>$rows];
    });
    return response()->json($payload);
  }

  public function conversion(Request $r){
    [$from,$to] = $this->range($r);
    $cacheKey = "reports:conversion:{$from->toDateString()}:{$to->toDateString()}";
    $payload = Cache::remember($cacheKey, 60, function () use ($from, $to) {
      $signup = (int) DB::table('users')->whereBetween('created_at',[$from,$to])->count();
      $first  = (int) DB::table('appointments')->whereBetween('created_at',[$from,$to])->distinct('patient_id')->count('patient_id');
      $paid   = (int) DB::table('transactions')->whereBetween('created_at',[$from,$to])->where('type','charge')->distinct('owner_id')->count('owner_id');
      return ['data'=>[
        ['stage'=>'signup','value'=>$signup],
        ['stage'=>'first_session','value'=>$first],
        ['stage'=>'paid_user','value'=>$paid],
      ]];
    });
    return response()->json($payload);
  }
}
