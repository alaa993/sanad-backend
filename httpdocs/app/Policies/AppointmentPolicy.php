<?php
namespace App\Policies;
use App\Models\{User, Appointment};
class AppointmentPolicy {
    public function view(User $u, Appointment $a){ return in_array($u->id, [$a->patient_id,$a->specialist_id]); }
    public function update(User $u, Appointment $a){ return $u->id === $a->specialist_id; }
    public function cancel(User $u, Appointment $a){ return in_array($u->id, [$a->patient_id,$a->specialist_id]); }
}
