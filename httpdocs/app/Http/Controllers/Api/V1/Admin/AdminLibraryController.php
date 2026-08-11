<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\LibraryArticle;
use App\Support\Realtime;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AdminLibraryController extends Controller
{
    public function index()
    {
        $payload = Cache::remember('admin:library:list', 30, function () {
            $rows = LibraryArticle::query()
                ->orderByDesc('id')
                ->limit(200)
                ->get(['id', 'title', 'active', 'category_id', 'author_name', 'created_at'])
                ->map(function ($row) {
                    $title = $row->title;
                    if (is_array($title)) {
                        $title = $title['ar'] ?? $title['en'] ?? reset($title);
                    }
                    return [
                        'id' => $row->id,
                        'title' => $title,
                        'status' => $row->active ? 'published' : 'draft',
                        'category_id' => $row->category_id,
                        'author_name' => $row->author_name,
                        'created_at' => optional($row->created_at)->toIso8601String(),
                    ];
                });

            return ['data' => $rows];
        });

        return response()->json($payload);
    }

    public function toggle($id)
    {
        $article = LibraryArticle::find($id);
        if (!$article) {
            return response()->json(['ok' => false], 404);
        }

        $article->active = !$article->active;
        $article->save();

        Cache::forget('admin:library:list');
        Cache::forget('library:categories');
        Cache::forget('library:tags');
        Cache::forget("library:article:{$id}");
        Realtime::libraryUpdated([
            'article_id' => (int) $id,
            'action' => $article->active ? 'published' : 'unpublished',
        ]);

        return response()->json([
            'ok' => true,
            'status' => $article->active ? 'published' : 'draft',
        ]);
    }
}
