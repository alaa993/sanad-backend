<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable {
    use HasApiTokens, HasFactory, Notifiable, HasRoles;
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'avatar',
        'phone',
        'locale',
        'timezone',
        'gender',
        'google_id',
        'apple_id',
        'facebook_id',
        'push_enabled',
        'extra',
        'security_question',
    ];
    protected $hidden   = ['password','remember_token','security_answer_hash','google_id','apple_id','facebook_id'];

    public function specialistDocuments()
    {
        return $this->hasMany(SpecialistDocument::class);
    }

    public function isPatientAccount(): bool
    {
        $role = $this->role;
        if (!$role) {
            try {
                $role = $this->getRoleNames()->first();
            } catch (\Throwable $e) {
                $role = 'patient';
            }
        }

        return $role === 'patient' || empty($role);
    }

    public function publicEmail(): ?string
    {
        if ($this->isPatientAccount()) {
            return null;
        }

        $email = $this->email;
        if ($email && str_ends_with($email, '@sanad.local')) {
            return null;
        }

        return $email;
    }

    public function publicPhone(): ?string
    {
        return $this->isPatientAccount() ? null : $this->phone;
    }
}
