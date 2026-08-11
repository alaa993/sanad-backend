<?php
namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;

class AdminProfileController extends Controller
{
    public function show(Request $request)
    {
        $u = $request->user();
        $settings = $this->settingsPayload();
        return response()->json([
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'avatar' => $u->avatar,
            'locale' => $u->locale,
            'phone' => $u->phone,
            'stats' => $this->adminStats(),
            'privacy_policy' => $settings['privacy_policy'],
            'contact_info' => $settings['contact_info'],
            'platform_fee_percent' => $settings['platform_fee_percent'],
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['nullable','string','min:2'],
            'locale' => ['nullable','string','max:5'],
            'phone' => ['nullable','string','max:20'],
        ]);
        $user->update(array_filter($data, fn($v) => !is_null($v)));
        Cache::forget('admin:profile:stats');
        return response()->json(['ok' => true]);
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'current_password' => ['required','string'],
            'new_password' => ['required','string','min:6','confirmed'],
        ]);
        if (!Hash::check($data['current_password'], $user->password)) {
            return response()->json(['ok'=>false,'message'=>'invalid_current_password'], 422);
        }
        $user->password = Hash::make($data['new_password']);
        $user->save();
        return response()->json(['ok'=>true]);
    }

    public function uploadAvatar(Request $request)
    {
        $data = $request->validate([
            'avatar' => ['required','image','max:5120'],
        ]);
        $path = $data['avatar']->store('avatars', 'public');
        $url = Storage::disk('public')->url($path);
        $user = $request->user();
        $user->avatar = $url;
        $user->save();
        Cache::forget('admin:profile:stats');
        return response()->json(['url' => $url]);
    }

    public function settings(Request $request)
    {
        return response()->json($this->settingsPayload());
    }

    public function saveSettings(Request $request)
    {
        $data = $request->validate([
            'privacy_policy' => ['nullable','string'],
            'contact_info' => ['nullable','string'],
            'platform_fee_percent' => ['nullable','integer','min:0','max:100'],
        ]);
        $this->putSetting('privacy_policy', $data['privacy_policy'] ?? null);
        $this->putSetting('contact_info', $data['contact_info'] ?? null);
        if (array_key_exists('platform_fee_percent', $data)) {
            $this->putSetting('platform_fee_percent', (string) $data['platform_fee_percent']);
        }
        Cache::forget('admin:settings');
        Cache::forget('settings:public');
        return response()->json(['ok'=>true]);
    }

    private function settingsPayload(): array
    {
        return Cache::remember('admin:settings', 120, function () {
            return [
                'privacy_policy' => $this->getSetting('privacy_policy'),
                'contact_info' => $this->getSetting('contact_info'),
                'platform_fee_percent' => $this->getSetting('platform_fee_percent'),
            ];
        });
    }

    private function getSetting(string $key): ?string
    {
        return DB::table('site_settings')->where('key', $key)->value('value');
    }

    private function putSetting(string $key, ?string $value): void
    {
        DB::table('site_settings')->updateOrInsert(
            ['key' => $key],
            ['value' => $value, 'updated_at' => now(), 'created_at' => now()]
        );
    }

    private function adminStats(): array
    {
        return Cache::remember('admin:profile:stats', 30, function () {
            $pendingSpecs = DB::table('specialist_profiles')->where('status','pending')->count();
            $pendingOrgs = DB::table('organizations')->where('status','pending')->count();
            $totalUsers = DB::table('users')->count();
            $totalSessions = DB::table('appointments')->count();
            return [
                'pending_specialists' => $pendingSpecs,
                'pending_organizations' => $pendingOrgs,
                'total_users' => $totalUsers,
                'total_sessions' => $totalSessions,
            ];
        });
    }
}
