<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\{Chat, ChatParticipant, GroupSession, GroupSessionParticipant};
use App\Services\LiveKitTokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Group therapy sessions API: catalog, join/leave, LiveKit room tokens for multi-participant calls.
 */
class GroupSessionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $role = $user->role ?? ($user->roles()->value('name') ?? 'user');
        $ageCategory = $request->query('age_category');
        $disorderTag = $request->query('disorder_tag');
        $cacheKey = "group:idx:{$user->id}:{$role}:" . md5((string) $ageCategory . (string) $disorderTag);
        $payload = Cache::remember($cacheKey, 20, function () use ($user, $role, $ageCategory, $disorderTag) {
            $query = GroupSession::with('specialist:id,name')
                ->orderBy('start_at', 'asc');
            if ($ageCategory) {
                $query->where('age_category', $ageCategory);
            }
            if ($disorderTag) {
                $query->where('disorder_tag', $disorderTag);
            }
            if ($role === 'specialist') {
                $query->where('specialist_id', $user->id);
            } else {
                $query->where(function ($q) use ($user) {
                    $q->where('is_public', true)
                        ->orWhereHas('participants', function ($p) use ($user) {
                            $p->where('user_id', $user->id)->whereNull('left_at');
                        });
                });
            }

            $items = $query->limit(200)->get();

            $participantMap = GroupSessionParticipant::whereIn('group_session_id', $items->pluck('id'))
                ->select('group_session_id', DB::raw('count(*) as cnt'))
                ->whereNull('left_at')
                ->groupBy('group_session_id')
                ->pluck('cnt', 'group_session_id');

            $joinedIds = GroupSessionParticipant::where('user_id', $user->id)
                ->whereNull('left_at')
                ->pluck('group_session_id')
                ->toArray();

            $mapped = $items->map(function ($g) use ($participantMap, $joinedIds) {
                return $this->transform($g, in_array($g->id, $joinedIds), (int) ($participantMap[$g->id] ?? 0));
            })->values();

            return ['data' => $mapped];
        });

        return response()->json($payload);
    }

    public function show(Request $request, GroupSession $groupSession)
    {
        $user = $request->user();
        $role = $user->role ?? ($user->roles()->value('name') ?? 'user');
        if ($role !== 'specialist' || $groupSession->specialist_id !== $user->id) {
            $hasAccess = GroupSessionParticipant::where('group_session_id', $groupSession->id)
                ->where('user_id', $user->id)
                ->exists();
            if (!$hasAccess) {
                abort(403, 'group_session_forbidden');
            }
        }
        $cacheKey = "group:show:{$groupSession->id}:{$user->id}";
        $payload = Cache::remember($cacheKey, 20, function () use ($groupSession, $user) {
            $groupSession->loadMissing('specialist:id,name');
            $count = GroupSessionParticipant::where('group_session_id', $groupSession->id)->whereNull('left_at')->count();
            $joined = GroupSessionParticipant::where('group_session_id', $groupSession->id)
                ->where('user_id', $user->id)
                ->whereNull('left_at')
                ->exists();
            $moderators = GroupSessionParticipant::where('group_session_id', $groupSession->id)
                ->where('role', 'moderator')
                ->whereNull('left_at')
                ->with('user:id,name')
                ->get()
                ->map(fn ($m) => ['id' => $m->user_id, 'name' => $m->user?->name])
                ->values();

            return $this->transform($groupSession, $joined, $count, $moderators);
        });

        return response()->json($payload);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $role = $user->role ?? ($user->roles()->value('name') ?? 'user');
        if ($role !== 'specialist') {
            abort(403);
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'topic' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => ['required', Rule::in(['video','voice','chat'])],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date'],
            'max_capacity' => ['nullable', 'integer', 'min:2', 'max:500'],
            'is_public' => ['nullable', 'boolean'],
            'participant_ids' => ['nullable', 'array'],
            'participant_ids.*' => ['integer', 'exists:users,id'],
            'moderator_ids' => ['nullable', 'array'],
            'moderator_ids.*' => ['integer', 'exists:users,id'],
            'age_category' => ['nullable', 'string', 'max:40'],
            'disorder_tag' => ['nullable', 'string', 'max:40'],
        ]);

        $start = $this->parseSchedule($data['start_at'], $request);
        $end = $this->parseSchedule($data['end_at'], $request);
        if ($end->lessThanOrEqualTo($start)) {
            $end = $start->copy()->addMinutes(60);
        }

        $group = GroupSession::create([
            'title' => $data['title'],
            'topic' => $data['topic'] ?? null,
            'description' => $data['description'] ?? null,
            'type' => $data['type'],
            'start_at' => $start,
            'end_at' => $end,
            'status' => 'scheduled',
            'max_capacity' => $data['max_capacity'] ?? 20,
            'is_public' => $data['is_public'] ?? false,
            'specialist_id' => $user->id,
            'created_by' => $user->id,
        ]);

        $chat = $this->ensureChat($group);
        GroupSessionParticipant::firstOrCreate(
            ['group_session_id' => $group->id, 'user_id' => $user->id],
            ['role' => 'specialist', 'joined_at' => now()]
        );
        $participantIds = collect($data['participant_ids'] ?? [])
            ->filter(fn($id) => $id && (int) $id !== (int) $user->id)
            ->unique();
        foreach ($participantIds as $participantId) {
            GroupSessionParticipant::firstOrCreate(
                ['group_session_id' => $group->id, 'user_id' => $participantId],
                ['role' => 'member']
            );
        }
        foreach (collect($data['moderator_ids'] ?? [])->unique() as $moderatorId) {
            GroupSessionParticipant::updateOrCreate(
                ['group_session_id' => $group->id, 'user_id' => $moderatorId],
                ['role' => 'moderator', 'joined_at' => now(), 'left_at' => null]
            );
        }

        $group->loadMissing('specialist:id,name');
        Cache::forget("group:idx:{$user->id}:{$role}");
        return response()->json($this->transform($group, true, 1), 201);
    }

    public function join(Request $request, GroupSession $groupSession)
    {
        $user = $request->user();
        $role = $user->role ?? ($user->roles()->value('name') ?? 'user');
        $count = GroupSessionParticipant::where('group_session_id', $groupSession->id)->whereNull('left_at')->count();
        if ($role !== 'specialist' || $groupSession->specialist_id !== $user->id) {
            $allowed = GroupSessionParticipant::where('group_session_id', $groupSession->id)
                ->where('user_id', $user->id)
                ->exists();
            if (!$allowed && !$groupSession->is_public) {
                abort(403, 'group_session_forbidden');
            }
            if (!$allowed && $groupSession->is_public) {
                if ($count >= (int) ($groupSession->max_capacity ?? 20)) {
                    abort(422, 'group_session_full');
                }
            }
        }
        $chat = $this->ensureChat($groupSession);

        GroupSessionParticipant::updateOrCreate(
            ['group_session_id' => $groupSession->id, 'user_id' => $user->id],
            ['role' => $user->role ?? 'user', 'joined_at' => now(), 'left_at' => null]
        );

        ChatParticipant::firstOrCreate(
            ['chat_id' => $chat->id, 'user_id' => $user->id],
            ['role' => $user->role ?? 'user', 'joined_at' => now()]
        );

        Cache::forget("group:idx:{$user->id}:{$role}");
        Cache::forget("group:show:{$groupSession->id}:{$user->id}");
        return $this->show($request, $groupSession);
    }

    public function leave(Request $request, GroupSession $groupSession)
    {
        $user = $request->user();
        GroupSessionParticipant::where('group_session_id', $groupSession->id)
            ->where('user_id', $user->id)
            ->update(['left_at' => now()]);

        Cache::forget("group:idx:{$user->id}:{$user->role}");
        Cache::forget("group:show:{$groupSession->id}:{$user->id}");
        return $this->show($request, $groupSession);
    }

    public function livekitToken(Request $request, GroupSession $groupSession)
    {
        $user = $request->user();
        $url = LiveKitTokenService::livekitUrl();
        if (!$url) {
            return response()->json(['message' => 'livekit_missing_url'], 500);
        }
        $role = $user->role ?? ($user->roles()->value('name') ?? 'user');
        $room = 'group_' . $groupSession->id;
        $token = LiveKitTokenService::generateToken((string) $user->id, $user->name ?? 'User', $room, $role);
        return response()->json([
            'token' => $token,
            'url' => $url,
            'room' => $room,
        ]);
    }

    private function ensureChat(GroupSession $groupSession): Chat
    {
        if ($groupSession->chat_id) {
            if ($chat = Chat::find($groupSession->chat_id)) {
                return $chat;
            }
        }

        $chat = Chat::create(['subject' => $groupSession->title]);
        $groupSession->chat_id = $chat->id;
        $groupSession->save();
        if ($groupSession->specialist_id) {
            ChatParticipant::firstOrCreate(
                ['chat_id' => $chat->id, 'user_id' => $groupSession->specialist_id],
                ['role' => 'specialist', 'joined_at' => now()]
            );
        }
        return $chat;
    }

    private function transform(GroupSession $g, bool $joined, int $count, $moderators = null): array
    {
        return [
            'id' => $g->id,
            'title' => $g->title,
            'topic' => $g->topic,
            'description' => $g->description,
            'age_category' => $g->age_category,
            'disorder_tag' => $g->disorder_tag,
            'type' => $g->type,
            'start_at' => optional($g->start_at)->toIso8601String(),
            'end_at' => optional($g->end_at)->toIso8601String(),
            'status' => $g->status,
            'max_capacity' => (int) ($g->max_capacity ?? 20),
            'is_public' => (bool) ($g->is_public ?? false),
            'participants_count' => $count,
            'spots_left' => max(0, (int) ($g->max_capacity ?? 20) - $count),
            'specialist_name' => $g->specialist?->name,
            'moderators' => $moderators ?? [],
            'join_url' => $g->join_url,
            'chat_id' => $g->chat_id,
            'joined' => $joined,
        ];
    }

    private function parseSchedule(string $value, Request $request): Carbon
    {
        $tz = $request->input('timezone') ?: ($request->user()->timezone ?? config('app.timezone', 'UTC'));
        return Carbon::parse($value, $tz)->setTimezone('UTC');
    }
}
