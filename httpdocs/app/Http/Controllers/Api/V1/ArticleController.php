<?php
namespace App\Http\Controllers\Api\V1; use App\Http\Controllers\Controller; use Illuminate\Http\Request;
use App\Models\{Article, ArticleFavorite};
use Illuminate\Support\Facades\Cache;
class ArticleController extends Controller {
  public function index(Request $r){ $q=Article::where('published',true)->latest(); if($tag=$r->query('tag')) $q->whereJsonContains('tags',$tag);
    $cacheKey = 'articles:list:' . md5((string) $tag);
    $payload = Cache::remember($cacheKey, 60, function () use ($q) {
        $items=$q->limit(100)->get(['id','slug','title','tags','created_at']); return ['data'=>$items];
    });
    return response()->json($payload); }
  public function show(Request $r,$id){ $cacheKey = "articles:show:{$id}";
    $payload = Cache::remember($cacheKey, 120, function () use ($id, $r) {
        $a=Article::findOrFail($id);
        $user = $r->user();
        $canSeeDraft = $user && ($user->role === 'admin' || (int) $a->author_id === (int) $user->id);
        if (!$a->published && !$canSeeDraft) {
            abort(404);
        }
        return ['data'=>$a];
    });
    return response()->json($payload); }
  public function favorite(Request $r,$id){ ArticleFavorite::firstOrCreate(['article_id'=>$id,'user_id'=>$r->user()->id]); return response()->json(['favorited'=>true]); }
  public function unfavorite(Request $r,$id){ ArticleFavorite::where(['article_id'=>$id,'user_id'=>$r->user()->id])->delete(); return response()->json(['favorited'=>false]); }

  public function store(Request $r){
    $user = $r->user();
    if ($user->role === 'patient') {
        abort(403, 'Patients cannot create articles');
    }
    $data = $r->validate([
        'slug' => 'required|string|max:128|unique:articles,slug',
        'title' => 'required|array',
        'body' => 'required|array',
        'tags' => 'nullable|array',
        'published' => 'nullable|boolean',
    ]);
    $published = ($user->role === 'admin') ? (bool) ($data['published'] ?? false) : false;
    $article = Article::create(array_merge($data, [
        'author_id' => $user->id,
        'author_role' => $user->role,
        'published' => $published,
    ]));
    Cache::forget('articles:list:' . md5(''));
    return response()->json(['data'=>$article], 201);
  }

  public function update(Request $r, $id){
    $user = $r->user();
    $article = Article::findOrFail($id);
    $canEdit = $user->role === 'admin' || ($article->author_id && $article->author_id === $user->id);
    if (!$canEdit) {
        abort(403, 'Not allowed to edit this article');
    }
    $data = $r->validate([
        'title' => 'nullable|array',
        'body' => 'nullable|array',
        'tags' => 'nullable|array',
        'published' => 'nullable|boolean',
    ]);
    $article->update(array_filter($data, fn($v) => !is_null($v)));
    if ($user->role !== 'admin') {
        $article->published = false;
        $article->save();
    }
    Cache::forget("articles:show:{$id}");
    Cache::forget('articles:list:' . md5(''));
    return response()->json(['data'=>$article->fresh()]);
  }
}
