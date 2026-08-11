<?php
namespace App\Http\Controllers\Api\V1;

use App\Events\CommunityCommentCreated;
use App\Events\CommunityPostCreated;
use App\Events\CommunityPostLiked;
use App\Http\Controllers\Controller;
use App\Models\{Community, CommunityMember, CommunityPost, CommunityPostLike, CommunityPostComment};
use App\Support\OrganizationResolver;
use App\Support\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Community rooms, membership, feed, posts, likes, comments, and Q&A accept-answer.
 * List/feed responses are short-TTL cached; mutations fire Community* events for realtime clients.
 */
class CommunityController extends Controller
{
    public function index(Request $r)
    {
        $user = $r->user();
        $role = UserRole::resolve($user) ?? 'patient';
        $category = $r->query('category');
        $cacheKey = "community:list:{$user->id}:{$role}:" . md5((string) $category);
        $payload = Cache::remember($cacheKey, 20, function () use ($user, $role, $category) {
            $communitiesQuery = Community::withCount('members');
            if ($category) {
                $communitiesQuery->where('category', $category);
            }
            if (in_array($role, ['patient', 'specialist'], true)) {
                $communitiesQuery->whereNull('organization_id');
            } elseif ($role === 'organization') {
                $communitiesQuery->whereNotNull('organization_id');
            }

            $communities = $communitiesQuery->latest()->limit(50)->get();
            $membership = CommunityMember::where('user_id', $user->id)
                ->whereIn('community_id', $communities->pluck('id'))
                ->pluck('community_id')
                ->flip();
            $items = $communities->map(function ($c) use ($membership, $role) {
                return [
                    'id' => $c->id,
                    'slug' => $c->slug,
                    'name' => $c->name,
                    'about' => $c->about,
                    'visibility' => $c->visibility,
                    'kind' => $c->kind ?? 'discussion',
                    'category' => $c->category,
                    'members_count' => $c->members_count,
                    'joined' => $membership->has($c->id),
                    'organization_owned' => $c->organization_id !== null,
                    'capabilities' => $this->communityCapabilities($role, $c, $membership->has($c->id)),
                ];
            });

            return ['data' => $items];
        });

        return response()->json($payload);
    }

    public function store(Request $r)
    {
        $user = $r->user();
        if (!UserRole::isOneOf($user, ['admin', 'organization'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        $role = UserRole::resolve($user);
        $data = $r->validate([
            'slug' => 'required|string|max:64|unique:communities,slug',
            'name' => 'required|array',
            'about' => 'nullable|array',
            'visibility' => 'in:public,private',
            'kind' => 'nullable|string|in:discussion,qa',
            'category' => 'nullable|string|max:60',
        ]);
        $orgId = null;
        if ($role === 'organization') {
            $orgId = OrganizationResolver::resolveOrgId($r->user());
        }
        $c = Community::create($data + [
            'owner_id' => $r->user()->id,
            'organization_id' => $orgId,
        ]);
        CommunityMember::firstOrCreate(['community_id' => $c->id, 'user_id' => $r->user()->id], ['role' => 'owner']);
        $this->forgetCommunityListCache($r->user());

        return response()->json(['id' => $c->id], 201);
    }

    public function show(Request $r, $id)
    {
        $cacheKey = "community:show:{$id}:{$r->user()->id}";
        $payload = Cache::remember($cacheKey, 20, function () use ($r, $id) {
            $c = Community::withCount('members')->findOrFail($id);
            $joined = CommunityMember::where(['community_id' => $c->id, 'user_id' => $r->user()->id])->exists();

            return ['data' => [
                'id' => $c->id,
                'slug' => $c->slug,
                'name' => $c->name,
                'about' => $c->about,
                'visibility' => $c->visibility,
                'kind' => $c->kind ?? 'discussion',
                'members_count' => $c->members_count,
                    'joined' => $joined,
                    'organization_owned' => $c->organization_id !== null,
                ]];
        });

        return response()->json($payload);
    }

    public function join(Request $r, $id)
    {
        $community = Community::findOrFail($id);
        if ($community->visibility === 'private') {
            $allowed = CommunityMember::where(['community_id' => $id, 'user_id' => $r->user()->id])->exists()
                || (int) $community->owner_id === (int) $r->user()->id
                || UserRole::isOneOf($r->user(), ['admin']);
            if (!$allowed) {
                return response()->json(['message' => 'invite_required'], 403);
            }
        }

        CommunityMember::firstOrCreate(['community_id' => $id, 'user_id' => $r->user()->id], ['role' => 'member']);
        $count = CommunityMember::where('community_id', $id)->count();
        $this->forgetCommunityListCache($r->user());
        Cache::forget("community:show:{$id}:{$r->user()->id}");

        return response()->json(['joined' => true, 'members_count' => $count]);
    }

    public function leave(Request $r, $id)
    {
        CommunityMember::where(['community_id' => $id, 'user_id' => $r->user()->id])->delete();
        $count = CommunityMember::where('community_id', $id)->count();
        $this->forgetCommunityListCache($r->user());
        Cache::forget("community:show:{$id}:{$r->user()->id}");

        return response()->json(['joined' => false, 'members_count' => $count]);
    }

    public function feed(Request $r, $id)
    {
        $community = Community::findOrFail($id);
        if ($community->visibility === 'private' && !CommunityMember::where(['community_id' => $community->id, 'user_id' => $r->user()->id])->exists()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        $cacheKey = "community:feed:{$id}:{$r->user()->id}";
        $payload = Cache::remember($cacheKey, 15, function () use ($id, $r, $community) {
            $isQa = ($community->kind ?? 'discussion') === 'qa';
            $postsQuery = CommunityPost::where('community_id', $id)
                ->with(['author:id,name', 'comments' => function ($q) {
                    $q->with('author:id,name')->orderBy('created_at', 'desc')->limit(5);
                }])
                ->latest()
                ->limit(50);

            if ($isQa) {
                $postsQuery->where(function ($q) {
                    $q->where('post_kind', 'question')->orWhereNull('post_kind')->orWhere('post_kind', 'post');
                });
            }

            $posts = $postsQuery->get();
            $questionIds = $posts->pluck('id');
            $answersByQuestion = collect();
            if ($isQa && $questionIds->isNotEmpty()) {
                $answers = CommunityPost::where('community_id', $id)
                    ->where('post_kind', 'answer')
                    ->whereIn('question_id', $questionIds)
                    ->with(['author:id,name'])
                    ->orderByDesc('accepted_at')
                    ->orderBy('created_at')
                    ->get();
                $answersByQuestion = $answers->groupBy('question_id');
            }

            $liked = CommunityPostLike::where('user_id', $r->user()->id)
                ->whereIn('post_id', $posts->pluck('id'))
                ->pluck('post_id')->flip();
            $counts = CommunityPostLike::selectRaw('post_id, COUNT(*) as cnt')
                ->whereIn('post_id', $posts->pluck('id'))
                ->groupBy('post_id')->pluck('cnt', 'post_id');

            $mapped = $posts->map(function ($p) use ($liked, $counts, $isQa, $answersByQuestion, $r) {
                $item = $this->mapPost($p, $liked, $counts);
                if ($isQa) {
                    $answers = $answersByQuestion->get($p->id, collect());
                    $item['answers'] = $answers->map(function ($a) use ($r) {
                        return $this->mapPost($a, collect(), collect());
                    })->values();
                    $item['answers_count'] = $answers->count();
                    $item['accepted_answer_id'] = $answers->firstWhere('accepted_at', '!=', null)?->id;
                }

                return $item;
            });

            return ['data' => $mapped, 'kind' => $community->kind ?? 'discussion'];
        });

        return response()->json($payload);
    }

    /**
     * Create a post/question/answer. Non-private communities auto-join on participate; fires CommunityPostCreated.
     */
    public function post(Request $r, $id)
    {
        $community = Community::findOrFail($id);
        if ($deny = $this->ensureCanParticipate($community, $r->user())) {
            return $deny;
        }
        $role = UserRole::resolve($r->user()) ?? ($r->user()->role ?? 'patient');
        if ($role === 'organization' && $community->organization_id === null) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        $isQa = ($community->kind ?? 'discussion') === 'qa';

        $data = $r->validate([
            'body' => 'nullable|string|max:4000',
            'media_url' => 'nullable|string|max:2048',
            'type' => 'nullable|string|in:personal,awareness,official',
            'post_kind' => 'nullable|string|in:post,question,answer',
            'question_id' => 'nullable|integer|exists:community_posts,id',
        ]);
        if (empty(trim($data['body'] ?? '')) && empty($data['media_url'] ?? null)) {
            return response()->json(['message' => 'body or media_url required'], 422);
        }

        $role = $r->user()->role;
        $type = $data['type'] ?? null;
        if ($role === 'patient') {
            $type = 'personal';
        } elseif (!$type) {
            $type = 'awareness';
        }

        $postKind = $data['post_kind'] ?? 'post';
        if ($isQa) {
            if ($postKind === 'answer') {
                if (empty($data['question_id'])) {
                    return response()->json(['message' => 'question_id required for answers'], 422);
                }
                $question = CommunityPost::where('community_id', $id)
                    ->where('id', $data['question_id'])
                    ->whereIn('post_kind', ['question', 'post'])
                    ->firstOrFail();
            } else {
                $postKind = 'question';
            }
        } else {
            $postKind = 'post';
        }

        if ($community->visibility !== 'private') {
            CommunityMember::firstOrCreate(['community_id' => $id, 'user_id' => $r->user()->id], ['role' => 'member']);
        }
        $p = CommunityPost::create([
            'community_id' => $id,
            'author_id' => $r->user()->id,
            'body' => $data['body'],
            'media_url' => $data['media_url'] ?? null,
            'type' => $type,
            'post_kind' => $postKind,
            'question_id' => $postKind === 'answer' ? ($data['question_id'] ?? null) : null,
        ]);
        Cache::forget("community:feed:{$id}:{$r->user()->id}");
        $postPayload = $this->formatPost($p->fresh(['author:id,name', 'comments.author:id,name']));
        event(new CommunityPostCreated($id, [
            'communityId' => $id,
            'post' => $postPayload,
        ]));

        return response()->json(['id' => $p->id, 'post_kind' => $postKind], 201);
    }

    public function acceptAnswer(Request $r, $communityId, $questionId, $answerId)
    {
        $question = CommunityPost::where(['community_id' => $communityId, 'id' => $questionId])
            ->whereIn('post_kind', ['question', 'post'])
            ->firstOrFail();
        if ($question->author_id !== $r->user()->id && !UserRole::isOneOf($r->user(), ['admin', 'organization'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        $answer = CommunityPost::where([
            'community_id' => $communityId,
            'id' => $answerId,
            'question_id' => $questionId,
            'post_kind' => 'answer',
        ])->firstOrFail();

        CommunityPost::where('question_id', $questionId)->where('post_kind', 'answer')
            ->update(['accepted_at' => null]);
        $answer->accepted_at = now();
        $answer->save();
        Cache::forget("community:feed:{$communityId}:{$r->user()->id}");

        return response()->json(['accepted' => true, 'answer_id' => $answer->id]);
    }

    /** Toggle like on a post and broadcast CommunityPostLiked with the new count. */
    public function like(Request $r, $communityId, $postId)
    {
        $community = Community::findOrFail($communityId);
        if ($deny = $this->ensureCanParticipate($community, $r->user())) {
            return $deny;
        }
        $post = CommunityPost::where(['community_id' => $communityId, 'id' => $postId])->firstOrFail();
        if ($community->visibility !== 'private') {
            CommunityMember::firstOrCreate(['community_id' => $communityId, 'user_id' => $r->user()->id], ['role' => 'member']);
        }
        $like = CommunityPostLike::where(['post_id' => $post->id, 'user_id' => $r->user()->id])->first();
        if ($like) {
            $like->delete();
            $liked = false;
        } else {
            CommunityPostLike::create(['post_id' => $post->id, 'user_id' => $r->user()->id]);
            $liked = true;
        }
        $count = CommunityPostLike::where('post_id', $post->id)->count();
        Cache::forget("community:feed:{$communityId}:{$r->user()->id}");
        event(new CommunityPostLiked($communityId, [
            'communityId' => $communityId,
            'postId' => $postId,
            'likesCount' => $count,
            'liked' => $liked,
            'userId' => $r->user()->id,
        ]));

        return response()->json(['liked' => $liked, 'likes_count' => $count]);
    }

    public function comment(Request $r, $communityId, $postId)
    {
        $community = Community::findOrFail($communityId);
        if ($deny = $this->ensureCanParticipate($community, $r->user())) {
            return $deny;
        }
        $post = CommunityPost::where(['community_id' => $communityId, 'id' => $postId])->firstOrFail();
        if ($community->visibility !== 'private') {
            CommunityMember::firstOrCreate(['community_id' => $communityId, 'user_id' => $r->user()->id], ['role' => 'member']);
        }
        $data = $r->validate(['body' => 'required|string|max:2000']);
        $comment = CommunityPostComment::create([
            'post_id' => $post->id,
            'user_id' => $r->user()->id,
            'body' => $data['body'],
        ]);
        Cache::forget("community:feed:{$communityId}:{$r->user()->id}");
        $formatted = $this->formatComment($comment->fresh(['author:id,name']));
        event(new CommunityCommentCreated($communityId, [
            'communityId' => $communityId,
            'postId' => $postId,
            'comment' => $formatted,
        ]));

        return response()->json($formatted, 201);
    }

    private function mapPost(CommunityPost $p, $liked, $counts): array
    {
        return [
            'id' => $p->id,
            'body' => $p->body,
            'media_url' => $this->absoluteMediaUrl($p->media_url),
            'type' => $p->type ?? 'personal',
            'post_kind' => $p->post_kind ?? 'post',
            'question_id' => $p->question_id,
            'accepted_at' => optional($p->accepted_at)->toIso8601String(),
            'author' => ['id' => $p->author->id ?? 0, 'name' => $p->author->name ?? '—'],
            'created_at' => optional($p->created_at)->toIso8601String(),
            'likes_count' => $counts[$p->id] ?? 0,
            'liked' => $liked->has($p->id),
            'comments' => $p->comments ? $p->comments->map(fn ($c) => $this->formatComment($c)) : [],
        ];
    }

    private function formatPost(CommunityPost $post): array
    {
        $post->loadMissing(['author:id,name', 'comments.author:id,name']);
        $comments = $post->comments->sortByDesc('created_at')->values()->map(fn ($c) => $this->formatComment($c));

        return [
            'id' => $post->id,
            'body' => $post->body,
            'media_url' => $this->absoluteMediaUrl($post->media_url),
            'type' => $post->type ?? 'personal',
            'post_kind' => $post->post_kind ?? 'post',
            'question_id' => $post->question_id,
            'accepted_at' => optional($post->accepted_at)->toIso8601String(),
            'author' => ['id' => $post->author->id ?? 0, 'name' => $post->author->name ?? '—'],
            'created_at' => optional($post->created_at)->toIso8601String(),
            'likes_count' => CommunityPostLike::where('post_id', $post->id)->count(),
            'liked' => false,
            'comments' => $comments,
        ];
    }

    private function formatComment(CommunityPostComment $comment): array
    {
        $comment->loadMissing('author:id,name');

        return [
            'id' => $comment->id,
            'body' => $comment->body,
            'author' => ['id' => $comment->author->id ?? 0, 'name' => $comment->author->name ?? '—'],
            'created_at' => optional($comment->created_at)->toIso8601String(),
        ];
    }

    private function ensureCanParticipate(Community $community, $user)
    {
        if ($community->visibility !== 'private') {
            return null;
        }
        $isMember = CommunityMember::where([
            'community_id' => $community->id,
            'user_id' => $user->id,
        ])->exists();
        $isOwner = (int) $community->owner_id === (int) $user->id;
        $isAdmin = UserRole::isOneOf($user, ['admin']);
        if (!$isMember && !$isOwner && !$isAdmin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return null;
    }

    private function communityCapabilities(string $role, Community $community, bool $joined): array
    {
        $canPost = $joined;
        if ($role === 'organization') {
            $canPost = $joined && $community->organization_id !== null;
        }
        return [
            'can_create' => in_array($role, ['admin', 'organization'], true),
            'can_join' => $role !== 'organization' || $community->organization_id !== null,
            'can_post' => $canPost,
            'can_answer_qa' => in_array($role, ['specialist', 'admin', 'organization'], true),
            'shows_vent' => $role === 'patient',
            'shows_anonymous' => $role === 'patient',
            'shows_coach' => $role === 'patient',
        ];
    }

    private function forgetCommunityListCache($user): void
    {
        $role = UserRole::resolve($user) ?? ($user->role ?? 'patient');
        Cache::forget("community:list:{$user->id}:{$role}");
        Cache::forget("community:list:{$user->id}");
    }

    private function absoluteMediaUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        return url($path);
    }
}
