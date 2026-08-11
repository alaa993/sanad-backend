<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * يربط مستخدم الدور organization بمنظمة فعلية في قاعدة البيانات.
 */
final class OrganizationResolver
{
    public static function resolveOrgId(?User $user): ?int
    {
        if (!$user || $user->role !== 'organization') {
            return null;
        }

        $orgId = DB::table('organization_user')
            ->where('user_id', $user->id)
            ->orderByRaw("CASE WHEN role = 'manager' THEN 0 ELSE 1 END")
            ->value('organization_id');

        if ($orgId) {
            return (int) $orgId;
        }

        return self::provisionForUser($user);
    }

    public static function resolveOrgIdFromRequest(\Illuminate\Http\Request $request): ?int
    {
        return self::resolveOrgId($request->user());
    }

    /**
     * إنشاء منظمة + ربط مدير عند التسجيل أو للحسابات القديمة بلا ربط.
     */
    public static function provisionForUser(User $user): int
    {
        $existing = DB::table('organization_user')
            ->where('user_id', $user->id)
            ->value('organization_id');
        if ($existing) {
            return (int) $existing;
        }

        $orgId = DB::table('organizations')->insertGetId([
            'name' => $user->name ?: 'Organization',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('organization_user')->insert([
            'organization_id' => $orgId,
            'user_id' => $user->id,
            'role' => 'manager',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        self::clearUserCaches($user->id, $orgId);

        return (int) $orgId;
    }

    public static function clearUserCaches(int $userId, ?int $orgId = null): void
    {
        Cache::forget("auth:orgprofile:{$userId}");
        Cache::forget("auth:orgstatus:{$userId}");
        Cache::forget("auth:approval:{$userId}:organization");
        if ($orgId) {
            Cache::forget("org:dash:{$orgId}");
        }
    }
}
