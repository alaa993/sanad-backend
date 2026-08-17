<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_SEED_EMAIL', 'admin@sanad.local');
        $password = env('ADMIN_SEED_PASSWORD');
        if (!$password) {
            if (app()->environment('production')) {
                $this->command?->warn('Skipping admin seed: set ADMIN_SEED_PASSWORD.');
                return;
            }
            $password = 'Sanad@123';
        }

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
