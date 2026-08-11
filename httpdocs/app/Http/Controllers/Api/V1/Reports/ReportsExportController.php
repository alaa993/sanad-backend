<?php
namespace App\Http\Controllers\Api\V1\Reports;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Cache;

class ReportsExportController extends Controller {
  public function csv(Request $r) {
    $type = $r->query('type','overview'); // overview|sessions|users|revenue|top_specialists|top_organizations|retention|conversion
    $from = $r->query('from'); $to = $r->query('to');
    $cacheKey = 'reports:export:' . $type . ':' . md5((string) $from . '|' . (string) $to);
    $rows = [];
    // delegate to ReportsController methods by reading DB directly (simple)
    $rows = Cache::remember($cacheKey, 120, function () use ($type, $from, $to) {
    switch($type){
      case 'sessions':
        $rows = DB::table('appointments')->selectRaw('DATE(created_at) d, COUNT(*) v')
          ->when($from && $to, fn($q)=>$q->whereBetween('created_at',[$from,$to]))
          ->groupBy('d')->orderBy('d')->get()->toArray(); break;
      case 'users':
        $rows = DB::table('users')->selectRaw('DATE(created_at) d, COUNT(*) v')
          ->when($from && $to, fn($q)=>$q->whereBetween('created_at',[$from,$to]))
          ->groupBy('d')->orderBy('d')->get()->toArray(); break;
      case 'revenue':
        $rows = DB::table('transactions')->selectRaw('DATE(created_at) d, SUM(amount) v')->where('type','charge')
          ->when($from && $to, fn($q)=>$q->whereBetween('created_at',[$from,$to]))
          ->groupBy('d')->orderBy('d')->get()->toArray(); break;
      case 'top_specialists':
        $rows = DB::table('appointments as a')->join('users as u','u.id','=','a.specialist_id')
          ->when($from && $to, fn($q)=>$q->whereBetween('a.created_at',[$from,$to]))
          ->selectRaw('u.id, u.name, COUNT(*) sessions, AVG(a.rating) avg_rating')
          ->groupBy('u.id','u.name')->orderByDesc('sessions')->limit(50)->get()->toArray(); break;
      case 'top_organizations':
        $rows = DB::table('appointments as a')->join('users as o','o.id','=','a.organization_id')
          ->when($from && $to, fn($q)=>$q->whereBetween('a.created_at',[$from,$to]))
          ->whereNotNull('a.organization_id')
          ->selectRaw('o.id, o.name, COUNT(*) sessions, AVG(a.rating) avg_rating')
          ->groupBy('o.id','o.name')->orderByDesc('sessions')->limit(50)->get()->toArray(); break;
      case 'retention':
        $rows = DB::select(<<<SQL
          with week_sessions as (
            select patient_id as user_id, DATE_FORMAT(created_at,'%x-%v') wk
            from appointments
            where (? is null or created_at >= ?) and (? is null or created_at <= ?)
            group by patient_id, DATE_FORMAT(created_at,'%x-%v')
          ),
          cohorts as (
            select a.wk as week, count(distinct a.user_id) as users,
                   count(distinct case when b.user_id is not null then a.user_id end) as retained
            from week_sessions a
            left join week_sessions b
              on b.user_id=a.user_id
             and b.wk=DATE_FORMAT(DATE_ADD(STR_TO_DATE(concat(a.wk,'-1'),'%x-%v-%w'), interval 7 day),'%x-%v')
            group by a.wk order by a.wk
          )
          select week, users, retained from cohorts
        SQL, [$from,$from,$to,$to]); break;
      case 'conversion':
        $signup = (int) DB::table('users')->when($from && $to, fn($q)=>$q->whereBetween('created_at',[$from,$to]))->count();
        $first  = (int) DB::table('appointments')->when($from && $to, fn($q)=>$q->whereBetween('created_at',[$from,$to]))->distinct('patient_id')->count('patient_id');
        $paid   = (int) DB::table('transactions')->when($from && $to, fn($q)=>$q->whereBetween('created_at',[$from,$to]))->where('type','charge')->distinct('owner_id')->count('owner_id');
        $rows = [['stage'=>'signup','value'=>$signup],['stage'=>'first_session','value'=>$first],['stage'=>'paid_user','value'=>$paid]]; break;
      case 'overview':
      default:
        $newUsers = (int) DB::table('users')->when($from && $to, fn($q)=>$q->whereBetween('created_at',[$from,$to]))->count();
        $sessionsTotal = (int) DB::table('appointments')->when($from && $to, fn($q)=>$q->whereBetween('created_at',[$from,$to]))->count();
        $sessionsPaid  = (int) DB::table('appointments')->when($from && $to, fn($q)=>$q->whereBetween('created_at',[$from,$to]))->whereIn('status',['confirmed','accepted','completed'])->count();
        $revenue = (int) DB::table('transactions')->when($from && $to, fn($q)=>$q->whereBetween('created_at',[$from,$to]))->where('type','charge')->sum('amount');
        $avg = DB::table('session_ratings')->whereBetween('created_at',[$from,$to])->avg('score');
        if ($avg === null) {
          $avg = DB::table('appointments')->whereNotNull('rating')
              ->when($from && $to, fn($q)=>$q->whereBetween('created_at',[$from,$to]))->avg('rating');
        }
        $rows = [['metric','value'],
                 ['new_users',$newUsers],['sessions_total',$sessionsTotal],
                 ['sessions_paid',$sessionsPaid],['revenue',$revenue],['avg_rating',$avg]];
    }
    return $rows;
    });

    $response = new StreamedResponse(function() use ($rows) {
      $out = fopen('php://output', 'w');
      if(empty($rows)){ fputcsv($out, ['empty']); }
      else {
        if(is_array($rows) && isset($rows[0]) && is_array((array)$rows[0])){
          // header
          $first = (array)$rows[0];
          fputcsv($out, array_keys($first));
          foreach($rows as $r){ fputcsv($out, (array)$r); }
        } else {
          foreach($rows as $r){ fputcsv($out, (array)$r); }
        }
      }
      fclose($out);
    });
    $response->headers->set('Content-Type','text/csv');
    $response->headers->set('Content-Disposition','attachment; filename="reports_'.date('Ymd_His').'.csv"');
    return $response;
  }
}
