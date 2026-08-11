<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailyTip;
use App\Models\LibraryArticle;
use App\Models\LibraryCategory;
use App\Support\Realtime;
use App\Support\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Patient library: categories/articles, daily tip, tags, curated lists.
 * Reads are cached; write paths emit library:updated via Realtime so clients invalidate.
 */
class LibraryController extends Controller
{
    private const ARTICLE_FIELDS = [
        'id', 'category_id', 'title', 'image', 'type', 'duration',
        'author_name', 'author_title', 'author_avatar', 'video_url', 'thumbnail', 'tags',
    ];

    public function index(Request $request)
    {
        $tag = $request->query('tag');
        $cacheKey = $tag ? "library:categories:tag:{$tag}" : 'library:categories';

        $payload = Cache::remember($cacheKey, 120, function () use ($tag) {
            $cats = LibraryCategory::query()
                ->with(['articles' => function ($q) use ($tag) {
                    $q->where('active', true)
                        ->select(self::ARTICLE_FIELDS);
                    if ($tag) {
                        $q->whereJsonContains('tags', $tag);
                    }
                }])
                ->get(['id', 'title']);

            return $cats;
        });

        return response()->json($payload);
    }

    public function tags()
    {
        $tags = Cache::remember('library:tags', 300, function () {
            return LibraryArticle::query()
                ->where('active', true)
                ->whereNotNull('tags')
                ->pluck('tags')
                ->flatMap(fn ($t) => is_array($t) ? $t : [])
                ->unique()
                ->values()
                ->all();
        });

        return response()->json(['data' => $tags]);
    }

    public function curatedSyriaEurope()
    {
        $tags = config('sanad.curated_library_tags', ['syria', 'europe', 'refugee', 'diaspora']);
        $articles = LibraryArticle::query()
            ->where('active', true)
            ->where(function ($q) use ($tags) {
                foreach ($tags as $tag) {
                    $q->orWhereJsonContains('tags', $tag);
                }
            })
            ->orderByDesc('id')
            ->limit(50)
            ->get(self::ARTICLE_FIELDS);

        return response()->json(['data' => $articles, 'tags' => $tags]);
    }

    public function show(Request $request, $id)
    {
        $cacheKey = "library:article:{$id}";
        $article = Cache::remember($cacheKey, 120, function () use ($id) {
            return LibraryArticle::where('active', true)->findOrFail($id);
        });

        $payload = $article->toArray();
        $user = $request->user();
        if ($user) {
            $payload['favorited'] = \Illuminate\Support\Facades\DB::table('library_favorites')
                ->where('library_article_id', $id)
                ->where('user_id', $user->id)
                ->exists();
        }

        return response()->json($payload);
    }

    public function favorite(Request $request, $id)
    {
        LibraryArticle::where('active', true)->findOrFail($id);
        $userId = $request->user()->id;
        \Illuminate\Support\Facades\DB::table('library_favorites')->updateOrInsert(
            ['library_article_id' => $id, 'user_id' => $userId],
            ['created_at' => now(), 'updated_at' => now()]
        );
        return response()->json(['favorited' => true]);
    }

    public function unfavorite(Request $request, $id)
    {
        \Illuminate\Support\Facades\DB::table('library_favorites')
            ->where('library_article_id', $id)
            ->where('user_id', $request->user()->id)
            ->delete();
        return response()->json(['favorited' => false]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if (!UserRole::isOneOf($user, ['admin', 'specialist', 'organization'])) {
            abort(403, 'Forbidden');
        }
        $role = UserRole::resolve($user);

        $data = $request->validate([
            'category_id' => 'nullable|integer|exists:library_categories,id',
            'title' => 'required|array',
            'title.ar' => 'required|string|max:500',
            'body' => 'nullable|array',
            'type' => 'nullable|string|in:article,video',
            'video_url' => 'nullable|string|max:2048',
            'thumbnail' => 'nullable|string|max:2048',
            'image' => 'nullable|string|max:2048',
            'duration' => 'nullable|string|max:32',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:64',
            'active' => 'sometimes|boolean',
            'published' => 'sometimes|boolean',
        ]);

        $categoryId = $data['category_id'] ?? LibraryCategory::query()->value('id');
        if (!$categoryId) {
            $categoryId = LibraryCategory::create([
                'title' => ['ar' => 'عام', 'en' => 'General', 'tr' => 'Genel'],
            ])->id;
        }

        $user = $request->user();
        $active = array_key_exists('active', $data)
            ? (bool) $data['active']
            : (bool) ($data['published'] ?? true);

        $article = LibraryArticle::create([
            'category_id' => $categoryId,
            'title' => $data['title'],
            'body' => $data['body'] ?? ['ar' => ''],
            'type' => $data['type'] ?? (!empty($data['video_url']) ? 'video' : 'article'),
            'video_url' => $data['video_url'] ?? null,
            'thumbnail' => $data['thumbnail'] ?? null,
            'image' => $data['image'] ?? null,
            'duration' => $data['duration'] ?? null,
            'tags' => $data['tags'] ?? null,
            'active' => $active,
            'author_name' => $user->name,
            'author_title' => $role === 'specialist' ? 'أخصائي' : ($role === 'admin' ? 'إدارة سند' : null),
        ]);

        Cache::forget('library:categories');
        Cache::forget('library:tags');
        Cache::forget('admin:library:list');
        $this->flushTagCaches();
        Realtime::libraryUpdated(['article_id' => $article->id, 'action' => 'created']);

        return response()->json($article, 201);
    }

    private function flushTagCaches(): void
    {
        $tags = LibraryArticle::query()
            ->whereNotNull('tags')
            ->pluck('tags')
            ->flatMap(fn ($t) => is_array($t) ? $t : [])
            ->unique();
        foreach ($tags as $tag) {
            Cache::forget("library:categories:tag:{$tag}");
        }
    }

    public function dailyTip(Request $request)
    {
        $dayKey = now()->format('Y-m-d');
        $locale = $request->header('Accept-Language', 'ar');
        $lang = str_starts_with($locale, 'en') ? 'en' : (str_starts_with($locale, 'tr') ? 'tr' : 'ar');

        $payload = Cache::remember("library:daily_tip:{$dayKey}", 3600, function () use ($dayKey, $lang) {
            $tip = DailyTip::where('tip_date', $dayKey)->where('active', true)->first();
            if ($tip) {
                $title = $tip->title[$lang] ?? $tip->title['ar'] ?? 'نصيحة اليوم';
                $bodyRaw = $tip->body[$lang] ?? $tip->body['ar'] ?? '';
                return [
                    'title' => $title,
                    'body' => $bodyRaw,
                    'article_id' => null,
                    'source' => 'managed',
                ];
            }

            $article = LibraryArticle::query()
                ->where('active', true)
                ->inRandomOrder()
                ->first(['id', 'title', 'body', 'author_name']);
            if (!$article) {
                return [
                    'title' => 'نصيحة اليوم',
                    'body' => 'خذ لحظة للتنفس العميق — أنت تستحق الراحة.',
                    'article_id' => null,
                    'source' => 'fallback',
                ];
            }
            $title = is_array($article->title)
                ? ($article->title[$lang] ?? $article->title['ar'] ?? 'نصيحة اليوم')
                : $article->title;
            $body = is_array($article->body)
                ? ($article->body[$lang] ?? $article->body['ar'] ?? '')
                : $article->body;
            if (is_string($body) && strlen($body) > 220) {
                $body = mb_substr($body, 0, 220) . '…';
            }

            return [
                'title' => $title,
                'body' => $body,
                'article_id' => $article->id,
                'author_name' => $article->author_name,
                'source' => 'library',
            ];
        });

        return response()->json($payload);
    }
}
