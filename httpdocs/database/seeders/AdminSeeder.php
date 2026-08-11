<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production') && ! env('ADMIN_SEED_PASSWORD')) {
            return;
        }

        $email = env('ADMIN_SEED_EMAIL', 'admin@sanad.local');
        $password = env('ADMIN_SEED_PASSWORD', 'Sanad@123');

        $admin = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Super Admin',
                'password' => Hash::make($password),
                'role' => 'admin',
                'locale' => 'ar',
            ]
        );

        if (method_exists($admin, 'assignRole')) {
            $admin->assignRole('admin');
        }
    }
}
