<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Factories\HasFactory;
class Appointment extends Model {
    use HasFactory;
    protected $fillable = [
        'patient_id',
        'specialist_id',
        'organization_id',
        'type',
        'points_cost',
        'chat_id',
        'starts_at',
        'ends_at',
        'scheduled_at',
        'status',
        'source',
        'notes',
        'specialist_notes',
        'rating',
        'rejection_reason',
        'rejection_by',
        'join_url',
        'duration_minutes',
        'extended_minutes',
        'closed_at',
        'reminder_sent_at',
        'original_specialist_id',
        'transferred_at',
        'transfer_reason',
        'recurrence_series_id',
        'occurrence_index',
    ];
    protected $casts = [
        'starts_at'=>'datetime',
        'ends_at'=>'datetime',
        'scheduled_at'=>'datetime',
        'closed_at'=>'datetime',
        'reminder_sent_at'=>'datetime',
        'transferred_at'=>'datetime',
    ];
    public function patient(){ return $this->belongsTo(User::class,'patient_id'); }
    public function specialist(){ return $this->belongsTo(User::class,'specialist_id'); }
    public function organization(){ return $this->belongsTo(User::class,'organization_id'); }
}
