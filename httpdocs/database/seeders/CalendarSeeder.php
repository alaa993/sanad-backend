
<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder; use Illuminate\Support\Facades\DB;
use App\Models\{SpecialistAvailabilitySlot, SpecialistBlockedTime, Appointment};

class CalendarSeeder extends Seeder {
    public function run(): void {
        $specialist = DB::table('users')->value('id') ?? 1;
        // Availability: Mon & Wed 10:00-14:00
        SpecialistAvailabilitySlot::firstOrCreate(['specialist_id'=>$specialist,'weekday'=>1,'start_time'=>'10:00','end_time'=>'14:00']);
        SpecialistAvailabilitySlot::firstOrCreate(['specialist_id'=>$specialist,'weekday'=>3,'start_time'=>'10:00','end_time'=>'14:00']);
        // Block an hour next Monday
        SpecialistBlockedTime::firstOrCreate(['specialist_id'=>$specialist,'start_at'=>now()->addDays(3)->setTime(11,0),'end_at'=>now()->addDays(3)->setTime(12,0)],['reason'=>'اجتماع']);
        // Sample appointments
        Appointment::firstOrCreate(['patient_id'=>($specialist+1),'specialist_id'=>$specialist,'starts_at'=>now()->addDays(2)->setTime(10,0),'ends_at'=>now()->addDays(2)->setTime(10,50)],['status'=>'pending','source'=>'patient']);
        Appointment::firstOrCreate(['patient_id'=>($specialist+1),'specialist_id'=>$specialist,'starts_at'=>now()->addDays(5)->setTime(13,0),'ends_at'=>now()->addDays(5)->setTime(13,50)],['status'=>'accepted','source'=>'patient']);
    }
}
