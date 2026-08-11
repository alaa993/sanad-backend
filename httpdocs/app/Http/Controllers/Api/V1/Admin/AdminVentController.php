<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\VentPost;
use App\Models\VentReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AdminVentController extends Controller
{
    public function reports()
    {
        $rows = VentReport::with(['post:id,alias,body,hidden_at', 'user:id,name'])
            ->where('status', 'open')
            ->latest()
            ->limit(200)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'reason' => $r->reason,
                'status' => $r->status,
                'created_at' => optional($r->created_at)->toIso8601String(),
                'post' => $r->post ? [
                    'id' => $r->post->id,
                    'alias' => $r->post->alias,
                    'body' => mb_substr($r->post->body ?? '', 0, 200),
                    'hidden_at' => optional($r->post->hidden_at)->toIso8601String(),
                ] : null,
                'reporter' => $r->user ? ['id' => $r->user->id, 'name' => $r->user->name] : null,
            ]);

        return response()->json(['data' => $rows]);
    }

    public function hide(Request $request, $id)
    {
        $post = VentPost::findOrFail($id);
        $post->hidden_at = now();
        $post->save();
        Cache::forget('vent:latest');
        VentReport::where('vent_post_id', $post->id)->where('status', 'open')
            ->update(['status' => 'resolved']);

        return response()->json(['ok' => true, 'hidden_at' => $post->hidden_at->toIso8601String()]);
    }
}
