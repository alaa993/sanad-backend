<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AccountDeletionService
{
    public function delete(User $user): void
    {
        if (strcasecmp($user->role ?? '', 'admin') === 0) {
            throw new \RuntimeException('admin_accounts_cannot_be_deleted_via_web');
        }

        $userId = $user->id;

        DB::transaction(function () use ($user) {
            $user->tokens()->delete();
            $user->delete();
        });

        Cache::forget("auth:me:{$userId}");
        Cache::forget("auth:approval:{$userId}:{$user->role}");
        Cache::forget('auth:adminprofile');
    }
}
