
<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\{CommunityController, ArticleController, JournalController};
Route::middleware(['auth:sanctum'])->prefix('api/v1')->group(function () {
    Route::get('/community', [CommunityController::class, 'index']);
    Route::post('/community', [CommunityController::class, 'store'])->middleware('can:manage-community');
    Route::get('/community/{id}', [CommunityController::class, 'show']);
    Route::post('/community/{id}/join', [CommunityController::class, 'join']);
    Route::post('/community/{id}/leave', [CommunityController::class, 'leave']);
    Route::get('/community/{id}/feed', [CommunityController::class, 'feed']);
    Route::post('/community/{id}/post', [CommunityController::class, 'post']);
    Route::get('/articles', [ArticleController::class, 'index']);
    Route::get('/articles/{id}', [ArticleController::class, 'show']);
    Route::post('/articles/{id}', [ArticleController::class, 'favorite']);
    Route::delete('/articles/{id}', [ArticleController::class, 'unfavorite']);
    Route::get('/journal', [JournalController::class, 'index']);
    Route::post('/journal', [JournalController::class, 'store']);
    Route::delete('/journal/{id}', [JournalController::class, 'destroy']);
});
