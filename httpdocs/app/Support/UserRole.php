<?php

namespace App\Support;

use App\Models\User;

final class UserRole
{
    public static function resolve(?User $user): ?string
    {
        if (!$user) {
            return null;
        }
        $spatie = $user->getRoleNames()->first();
        if ($spatie) {
            return (string) $spatie;
        }
        return $user->role;
    }

    public static function isOneOf(?User $user, array $roles): bool
    {
        $role = self::resolve($user);
        return $role !== null && in_array($role, $roles, true);
    }
}
