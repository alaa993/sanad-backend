<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function update(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['nullable', 'string', 'min:2', 'max:120'],
            'locale' => ['nullable', 'string', 'max:5'],
            'phone' => ['nullable', 'string', 'max:20'],
            'gender' => ['nullable', 'string', 'in:male,female,other,prefer_not'],
        ]);

        if (array_key_exists('locale', $data) && $data['locale'] !== null) {
            $locale = strtolower(trim($data['locale']));
            if (!in_array($locale, ['ar', 'en', 'tr'], true)) {
                return response()->json(['ok' => false, 'message' => 'invalid_locale'], 422);
            }
            $data['locale'] = $locale;
        }

        if ($user->isPatientAccount()) {
            unset($data['phone']);
        }

        $user->update(array_filter($data, static fn ($v) => !is_null($v)));
        Cache::forget("auth:me:{$user->id}");

        return response()->json([
            'ok' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->publicEmail(),
                'phone' => $user->publicPhone(),
                'locale' => $user->locale,
                'gender' => $user->gender,
                'role' => $user->getRoleNames()->first() ?? $user->role,
            ],
        ]);
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
}
