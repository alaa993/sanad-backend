<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\DailyTip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AdminDailyTipController extends Controller
{
    public function index()
    {
        $rows = DailyTip::orderByDesc('tip_date')->limit(100)->get();
        return response()->json(['data' => $rows]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'tip_date' => 'required|date',
            'title' => 'required|array',
            'title.ar' => 'required|string|max:500',
            'body' => 'nullable|array',
            'active' => 'boolean',
        ]);
        $tip = DailyTip::updateOrCreate(
            ['tip_date' => $data['tip_date']],
            [
                'title' => $data['title'],
                'body' => $data['body'] ?? null,
                'active' => $data['active'] ?? true,
                'created_by' => $request->user()->id,
            ]
        );
        Cache::forget('library:daily_tip:' . $data['tip_date']);
        Cache::forget('library:daily_tip:' . now()->format('Y-m-d'));

        return response()->json($tip, 201);
    }

    public function update(Request $request, $id)
    {
        $tip = DailyTip::findOrFail($id);
        $data = $request->validate([
            'tip_date' => 'sometimes|date',
            'title' => 'sometimes|array',
            'body' => 'nullable|array',
            'active' => 'boolean',
        ]);
        $tip->fill($data);
        $tip->save();
        Cache::forget('library:daily_tip:' . optional($tip->tip_date)->format('Y-m-d'));
        Cache::forget('library:daily_tip:' . now()->format('Y-m-d'));

        return response()->json($tip);
    }

    public function destroy($id)
    {
        $tip = DailyTip::findOrFail($id);
        $dateKey = optional($tip->tip_date)->format('Y-m-d');
        $tip->delete();
        if ($dateKey) {
            Cache::forget('library:daily_tip:' . $dateKey);
        }
        Cache::forget('library:daily_tip:' . now()->format('Y-m-d'));

        return response()->json(['deleted' => true]);
    }
}
