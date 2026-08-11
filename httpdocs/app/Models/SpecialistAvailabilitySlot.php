<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Factories\HasFactory;
class SpecialistAvailabilitySlot extends Model {
    use HasFactory;
    protected $fillable = ['specialist_id','weekday','start_time','end_time','repeat_rule','active'];
}
