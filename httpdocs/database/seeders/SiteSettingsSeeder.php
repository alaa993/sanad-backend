<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SiteSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $base = rtrim(config('app.url'), '/');

        $privacyIntro = "سياسة الخصوصية الكاملة متاحة على: {$base}/privacy\n\n";
        $privacyIntro .= "لحذف حسابك: {$base}/delete-account";

        $contact = "البريد: " . config('sanad.support_email') . "\n";
        $contact .= "الموقع: {$base}";

        $rows = [
            ['key' => 'privacy_policy', 'value' => $privacyIntro],
            ['key' => 'contact_info', 'value' => $contact],
            ['key' => 'platform_fee_percent', 'value' => '15'],
            ['key' => 'privacy_policy_url', 'value' => $base . config('sanad.urls.privacy')],
            ['key' => 'delete_account_url', 'value' => $base . config('sanad.urls.delete_account')],
            ['key' => 'terms_url', 'value' => $base . config('sanad.urls.terms')],
        ];

        foreach ($rows as $row) {
            DB::table('site_settings')->updateOrInsert(
                ['key' => $row['key']],
                ['value' => $row['value'], 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }
}
