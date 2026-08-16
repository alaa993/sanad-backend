<?php

namespace App\Support;

use App\Models\User;

final class UniqueIdentity
{
    public static function normalizeName(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');

        return $name === '' ? null : $name;
    }

    public static function normalizePhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return $digits === '' ? null : $digits;
    }

    public static function nameExists(string $name, ?int $ignoreUserId = null): bool
    {
        $name = self::normalizeName($name);
        if ($name === null) {
            return false;
        }
        $query = User::query()->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($name, 'UTF-8')]);
        if ($ignoreUserId) {
            $query->where('id', '!=', $ignoreUserId);
        }

        return $query->exists();
    }

    public static function emailExists(string $email, ?int $ignoreUserId = null): bool
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return false;
        }
        $query = User::query()->whereRaw('LOWER(email) = ?', [$email]);
        if ($ignoreUserId) {
            $query->where('id', '!=', $ignoreUserId);
        }

        return $query->exists();
    }

    public static function phoneExists(string $phone, ?int $ignoreUserId = null): bool
    {
        $normalized = self::normalizePhone($phone);
        if ($normalized === null) {
            return false;
        }
        $query = User::query()->where(function ($inner) use ($normalized, $phone) {
            $inner->where('phone', $normalized)->orWhere('phone', trim($phone));
        });
        if ($ignoreUserId) {
            $query->where('id', '!=', $ignoreUserId);
        }

        return $query->exists();
    }
}
