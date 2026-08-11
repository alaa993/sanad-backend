<?php

namespace App\Services;

use App\Models\Community;
use App\Models\CommunityMember;
use Illuminate\Support\Facades\DB;

/**
 * Ensures each organization has a support community and org members are enrolled as CommunityMembers.
 */
class OrgSupportRoomService
{
    /** Resolve the caller's org (manager/specialist) and return its support community. */
    public function resolveForUser(int $userId): Community
    {
        $orgId = DB::table('organization_user')
            ->where('user_id', $userId)
            ->whereIn('role', ['manager', 'specialist'])
            ->value('organization_id');

        if (!$orgId) {
            abort(404, 'no_org');
        }

        return $this->findOrCreate((int) $orgId);
    }

    /** Find org support community by organization_id or create slug org-support-{id} and seed memberships. */
    public function findOrCreate(int $organizationId): Community
    {
        $existing = Community::where('organization_id', $organizationId)->first();
        if ($existing) {
            $this->ensureMemberships($existing);

            return $existing;
        }

        $org = DB::table('organizations')->where('id', $organizationId)->first();
        $orgName = $org->name ?? 'Organization';
        $slug = 'org-support-' . $organizationId;

        $managerId = DB::table('organization_user')
            ->where('organization_id', $organizationId)
            ->where('role', 'manager')
            ->value('user_id');

        $community = Community::create([
            'slug' => $slug,
            'name' => [
                'ar' => 'غرفة دعم ' . $orgName,
                'en' => $orgName . ' Support Room',
                'tr' => $orgName . ' Destek Odası',
            ],
            'about' => [
                'ar' => 'مساحة خاصة لأعضاء المنظمة',
                'en' => 'Private space for organization members',
            ],
            'visibility' => 'private',
            'kind' => 'discussion',
            'organization_id' => $organizationId,
            'owner_id' => $managerId ?? 1,
        ]);

        $this->ensureMemberships($community);

        return $community;
    }

    private function ensureMemberships(Community $community): void
    {
        if (!$community->organization_id) {
            return;
        }

        $memberIds = DB::table('organization_user')
            ->where('organization_id', $community->organization_id)
            ->pluck('user_id');

        foreach ($memberIds as $userId) {
            CommunityMember::firstOrCreate(
                ['community_id' => $community->id, 'user_id' => $userId],
                ['role' => 'member']
            );
        }
    }
}
