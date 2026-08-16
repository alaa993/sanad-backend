<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use App\Support\UniqueIdentity;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        return app(\App\Http\Controllers\Api\AuthController::class)->me($request);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['nullable', 'string', 'min:2', 'max:120', Rule::unique('users', 'name')->ignore($user->id)],
            'locale' => ['nullable', 'string', 'max:5'],
            'phone' => ['nullable', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($user->id)],
            'gender' => ['nullable', 'string', 'in:male,female,other,prefer_not'],
        ], [
            'name.unique' => 'This name is already registered.',
            'phone.unique' => 'This phone number is already registered.',
        ]);

        if (array_key_exists('locale', $data) && $data['locale'] !== null) {
            $locale = strtolower(trim($data['locale']));
            if (!in_array($locale, ['ar', 'en', 'tr'], true)) {
                return response()->json(['ok' => false, 'message' => 'invalid_locale'], 422);
            }
            $data['locale'] = $locale;
        }

        if (array_key_exists('name', $data) && is_string($data['name'])) {
            $data['name'] = UniqueIdentity::normalizeName($data['name']);
        }
        if (array_key_exists('phone', $data) && is_string($data['phone'])) {
            $data['phone'] = UniqueIdentity::normalizePhone($data['phone']);
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
                'role' => $user->role ?: 'patient',
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
