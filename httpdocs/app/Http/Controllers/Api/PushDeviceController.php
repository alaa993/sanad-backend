<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\Request;

class PushDeviceController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['required', 'in:android,ios'],
            'device_id' => ['nullable', 'string', 'max:191'],
        ]);

        DeviceToken::updateOrCreate(
            ['token' => $data['token']],
            [
                'user_id' => $request->user()->id,
                'platform' => $data['platform'],
                'device_id' => $data['device_id'] ?? null,
                'last_seen_at' => now(),
            ]
        );

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:512'],
        ]);

        DeviceToken::where('user_id', $request->user()->id)
            ->where('token', $data['token'])
            ->delete();

        return response()->json(['ok' => true]);
    }

    public function preferences(Request $request)
    {
        return response()->json([
            'push_enabled' => (bool) ($request->user()->push_enabled ?? true),
        ]);
    }

    public function updatePreferences(Request $request)
    {
        $data = $request->validate([
            'push_enabled' => ['required', 'boolean'],
        ]);

        $request->user()->update([
            'push_enabled' => $data['push_enabled'],
        ]);

        return response()->json([
            'push_enabled' => (bool) $data['push_enabled'],
        ]);
    }
}
