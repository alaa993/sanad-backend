<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AnonymousMatchReport;
use App\Models\AnonymousMatchRequest;
use App\Services\AnonymousMatchService;
use Illuminate\Http\Request;

class AnonymousMatchController extends Controller
{
    public function __construct(private AnonymousMatchService $service) {}

    public function status(Request $request)
    {
        return response()->json(['data' => $this->service->status($request->user())]);
    }

    public function join(Request $request)
    {
        $data = $request->validate([
            'gender' => 'required|string|in:male,female,other',
            'match_gender' => 'required|string|in:any,same,male,female',
            'mode' => 'required|string|in:chat,voice',
        ]);
        $result = $this->service->joinQueue($request->user(), $data['gender'], $data['match_gender'], $data['mode']);
        return response()->json(['data' => $result]);
    }

    public function leave(Request $request)
    {
        $this->service->leave($request->user());
        return response()->json(['left' => true]);
    }

    public function end(Request $request, $id)
    {
        $this->service->end($request->user(), (int) $id);
        return response()->json(['ended' => true]);
    }

    public function report(Request $request, $id)
    {
        $data = $request->validate(['reason' => 'nullable|string|max:500']);
        $match = AnonymousMatchRequest::where('id', $id)
            ->where(function ($q) use ($request) {
                $q->where('user_id', $request->user()->id)
                    ->orWhere('partner_id', $request->user()->id);
            })
            ->firstOrFail();
        AnonymousMatchReport::create([
            'match_request_id' => $match->id,
            'reporter_id' => $request->user()->id,
            'reason' => $data['reason'] ?? null,
        ]);
        $this->service->end($request->user(), (int) $id);
        return response()->json(['reported' => true], 201);
    }
}
