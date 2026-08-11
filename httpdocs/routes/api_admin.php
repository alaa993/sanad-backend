
<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Admin\{
  AdminDashboardController, AdminUsersController, AdminSpecialistsController, AdminOrganizationsController,
  AdminAppointmentsController, AdminLibraryController
};

Route::middleware(['auth:sanctum','admin.super'])->prefix('api/v1/admin')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index']);
    Route::get('/users', [AdminUsersController::class, 'index']);
    Route::get('/specialists', [AdminSpecialistsController::class, 'index']);
    Route::post('/specialists', [AdminSpecialistsController::class, 'store']);
    Route::get('/specialists/{id}/documents', [AdminSpecialistsController::class, 'documents']);
    Route::post('/specialists/{id}/review', [AdminSpecialistsController::class, 'review']);
    Route::get('/organizations', [AdminOrganizationsController::class, 'index']);
    Route::get('/appointments', [AdminAppointmentsController::class, 'index']);
    Route::get('/library/posts', [AdminLibraryController::class, 'index']);
    Route::post('/library/posts/{id}/toggle', [AdminLibraryController::class, 'toggle']);

    // approvals
    Route::post('/specialists/{id}/approve', [AdminSpecialistsController::class, 'approve']);
    Route::post('/specialists/{id}/reject',  [AdminSpecialistsController::class, 'reject']);
    Route::post('/organizations/{id}/approve', [AdminOrganizationsController::class, 'approve']);
    Route::post('/organizations/{id}/reject',  [AdminOrganizationsController::class, 'reject']);
});
