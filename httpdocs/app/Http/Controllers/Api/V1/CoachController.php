<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CoachCheckin;
use App\Models\CoachPlanItem;
use App\Models\CoachProgram;
use Illuminate\Http\Request;

class CoachController extends Controller
{
    private function ensurePatient(Request $request)
    {
        if (($request->user()->role ?? null) !== 'patient') {
            abort(403, 'Only patients can use coach');
        }
    }

    public function index(Request $request)
    {
        $this->ensurePatient($request);
        $user = $request->user();
        $programs = CoachProgram::where('user_id', $user->id)
            ->withCount(['items', 'checkins'])
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn ($p) => $this->transformProgram($p));

        return response()->json(['data' => $programs]);
    }

    public function store(Request $request)
    {
        $this->ensurePatient($request);
        $user = $request->user();
        $data = $request->validate([
            'category' => 'required|string|in:vitamins,weight,general',
            'title' => 'required|string|max:255',
            'goals' => 'nullable|array',
            'items' => 'nullable|array',
            'items.*.kind' => 'string|in:vitamin,meal,exercise,tip,water',
            'items.*.title' => 'required_with:items|string|max:255',
            'items.*.schedule' => 'nullable|string|max:32',
        ]);

        $program = CoachProgram::create([
            'user_id' => $user->id,
            'specialist_id' => $user->role === 'specialist' ? $user->id : null,
            'category' => $data['category'],
            'title' => $data['title'],
            'goals' => $data['goals'] ?? null,
            'active' => true,
        ]);

        foreach ($data['items'] ?? [] as $item) {
            CoachPlanItem::create([
                'program_id' => $program->id,
                'kind' => $item['kind'] ?? 'tip',
                'title' => $item['title'],
                'schedule' => $item['schedule'] ?? null,
            ]);
        }

        if (empty($data['items'])) {
            $this->seedDefaults($program);
        }

        return response()->json($this->showProgram($program->fresh()), 201);
    }

    public function show(Request $request, $id)
    {
        $this->ensurePatient($request);
        $program = $this->findOwned($request, (int) $id);

        return response()->json($this->showProgram($program));
    }

    public function checkin(Request $request, $id)
    {
        $this->ensurePatient($request);
        $program = $this->findOwned($request, (int) $id);
        $data = $request->validate([
            'weight_kg' => 'nullable|numeric|min:20|max:400',
            'mood' => 'nullable|string|max:32',
            'note' => 'nullable|string|max:1000',
        ]);

        $checkin = CoachCheckin::create([
            'program_id' => $program->id,
            'weight_kg' => $data['weight_kg'] ?? null,
            'mood' => $data['mood'] ?? null,
            'note' => $data['note'] ?? null,
            'logged_at' => now(),
        ]);

        return response()->json($checkin, 201);
    }

    public function completeItem(Request $request, $itemId)
    {
        $this->ensurePatient($request);
        $item = CoachPlanItem::whereHas('program', fn ($q) => $q->where('user_id', $request->user()->id))
            ->findOrFail($itemId);
        $item->is_done = !$item->is_done;
        $item->done_at = $item->is_done ? now() : null;
        $item->save();

        return response()->json(['id' => $item->id, 'is_done' => $item->is_done]);
    }

    private function findOwned(Request $request, int $id): CoachProgram
    {
        return CoachProgram::where('user_id', $request->user()->id)->findOrFail($id);
    }

    private function showProgram(CoachProgram $program): array
    {
        $program->load(['items', 'checkins' => fn ($q) => $q->latest()->limit(30)]);

        return $this->transformProgram($program) + [
            'items' => $program->items->map(fn ($i) => [
                'id' => $i->id,
                'kind' => $i->kind,
                'title' => $i->title,
                'schedule' => $i->schedule,
                'is_done' => $i->is_done,
                'done_at' => optional($i->done_at)->toIso8601String(),
            ])->values(),
            'checkins' => $program->checkins->map(fn ($c) => [
                'id' => $c->id,
                'weight_kg' => $c->weight_kg,
                'mood' => $c->mood,
                'note' => $c->note,
                'logged_at' => optional($c->logged_at)->toIso8601String(),
            ])->values(),
        ];
    }

    private function transformProgram(CoachProgram $p): array
    {
        return [
            'id' => $p->id,
            'category' => $p->category,
            'title' => $p->title,
            'goals' => $p->goals,
            'active' => $p->active,
            'items_count' => $p->items_count ?? $p->items()->count(),
            'checkins_count' => $p->checkins_count ?? $p->checkins()->count(),
            'created_at' => optional($p->created_at)->toIso8601String(),
        ];
    }

    private function seedDefaults(CoachProgram $program): void
    {
        $defaults = match ($program->category) {
            'vitamins' => [
                ['kind' => 'vitamin', 'title' => 'فيتامين D صباحاً', 'schedule' => 'morning'],
                ['kind' => 'water', 'title' => '8 أكواب ماء', 'schedule' => 'daily'],
                ['kind' => 'tip', 'title' => 'وجبة متوازنة مع البروتين', 'schedule' => 'evening'],
            ],
            'weight' => [
                ['kind' => 'exercise', 'title' => 'مشي 20 دقيقة', 'schedule' => 'daily'],
                ['kind' => 'meal', 'title' => 'تدوين الوجبات', 'schedule' => 'evening'],
                ['kind' => 'tip', 'title' => 'شرب الماء قبل الوجبات', 'schedule' => 'daily'],
            ],
            default => [
                ['kind' => 'tip', 'title' => '5 دقائق تنفس عميق', 'schedule' => 'morning'],
                ['kind' => 'exercise', 'title' => 'تمدد خفيف', 'schedule' => 'evening'],
            ],
        };

        foreach ($defaults as $item) {
            CoachPlanItem::create($item + ['program_id' => $program->id]);
        }
    }
}
