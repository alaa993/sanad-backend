
<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder; use Illuminate\Support\Facades\DB;
use App\Models\{SpecialistProfile, Organization};

class DashboardsSeeder extends Seeder {
  public function run(): void {
    $user = DB::table('users')->first();
    if(!$user) return;
    SpecialistProfile::firstOrCreate(['user_id'=>$user->id], [
      'specialty'=>'Psychologist','languages'=>['ar','en'],'years_exp'=>5,'accepting_new'=>true,'bio'=>['ar'=>'أخصائي نفسي','en'=>'Psychologist'],'rate_cents'=>4000,'currency'=>'USD'
    ]);
    $org = Organization::firstOrCreate(['name'=>'Sanad Org'], ['about'=>['ar'=>'منظمة افتراضية','en'=>'Virtual Org']]);
    DB::table('organization_user')->updateOrInsert(['organization_id'=>$org->id,'user_id'=>$user->id],['role'=>'manager']);
  }
}
