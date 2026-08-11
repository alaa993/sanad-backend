<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class SettingsController extends Controller
{
    public function show(Request $request)
    {
        $payload = Cache::remember('settings:public', 120, function () {
            $base = rtrim(config('app.url'), '/');

            return [
                'privacy_policy' => $this->getSetting('privacy_policy'),
                'contact_info' => $this->getSetting('contact_info'),
                'privacy_policy_url' => $this->getSetting('privacy_policy_url') ?: $base . config('sanad.urls.privacy'),
                'delete_account_url' => $this->getSetting('delete_account_url') ?: $base . config('sanad.urls.delete_account'),
                'terms_url' => $this->getSetting('terms_url') ?: $base . config('sanad.urls.terms'),
                'contact_url' => $base . config('sanad.urls.contact'),
                'support_email' => config('sanad.support_email'),
            ];
        });
        return response()->json($payload);
    }

    private function getSetting(string $key): ?string
    {
        return DB::table('site_settings')->where('key', $key)->value('value');
    }
}
