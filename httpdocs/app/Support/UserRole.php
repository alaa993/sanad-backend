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
        if (!empty($user->role)) {
            return (string) $user->role;
        }
        try {
            $spatie = $user->getRoleNames()->first();
            if ($spatie) {
                return (string) $spatie;
            }
        } catch (\Throwable $e) {
            // users.role is the source of truth for API clients
        }
        return $user->role;
    }

    public static function isOneOf(?User $user, array $roles): bool
    {
        $role = self::resolve($user);
        return $role !== null && in_array($role, $roles, true);
    }
}
