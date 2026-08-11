<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\ChatController;

Route::middleware(['auth:sanctum'])->prefix('api/v1')->group(function () {
    Route::get('/chats', [ChatController::class, 'index']);
    Route::post('/chats', [ChatController::class, 'store']);
    Route::get('/chats/{chat}/messages', [ChatController::class, 'messages']);
    Route::post('/chats/{chat}/messages', [ChatController::class, 'send']);
});
