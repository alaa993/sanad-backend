<?php

use App\Http\Controllers\Web\AccountDeletionController;
use App\Http\Controllers\Web\AdminPanelController;
use App\Http\Controllers\Web\PageController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/privacy', [PageController::class, 'privacy'])->name('privacy');
Route::get('/terms', [PageController::class, 'terms'])->name('terms');
Route::get('/contact', [PageController::class, 'contact'])->name('contact');
Route::post('/contact', [PageController::class, 'submitContact'])->name('contact.submit');
Route::get('/delete-account', [AccountDeletionController::class, 'show'])->name('delete-account');
Route::post('/delete-account', [AccountDeletionController::class, 'destroy'])->name('delete-account.submit');

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [AdminPanelController::class, 'login'])->name('login');
    Route::get('/', [AdminPanelController::class, 'dashboard'])->name('dashboard');
});
