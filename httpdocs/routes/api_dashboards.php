
<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Specialist\{
    SpecialistDashboardController,
    SpecialistSessionsController,
    SpecialistProfileController,
    SpecialistPatientsController,
    SpecialistDocumentController
};
use App\Http\Controllers\Api\V1\Organization\{OrganizationDashboardController, OrganizationSpecialistsController, OrganizationSessionsController};

Route::middleware(['auth:sanctum'])->prefix('api/v1')->group(function () {
    // Specialist
    Route::get('/specialist/dashboard', [SpecialistDashboardController::class, 'index']);
    Route::get('/specialist/sessions',  [SpecialistSessionsController::class, 'index']);
    Route::post('/specialist/sessions/{id}/accept', [SpecialistSessionsController::class, 'accept']);
    Route::post('/specialist/sessions/{id}/reject', [SpecialistSessionsController::class, 'reject']);
    Route::post('/specialist/sessions/{id}/reschedule', [SpecialistSessionsController::class, 'reschedule']);
    Route::get('/specialist/profile',   [SpecialistProfileController::class, 'show']);
    Route::put('/specialist/profile',   [SpecialistProfileController::class, 'update']);
    Route::get('/specialist/documents', [SpecialistDocumentController::class, 'index']);
    Route::post('/specialist/documents', [SpecialistDocumentController::class, 'store']);
    Route::delete('/specialist/documents/{id}', [SpecialistDocumentController::class, 'destroy']);
    Route::get('/specialist/patients/{id}/intake', [SpecialistPatientsController::class, 'intake']);
    Route::put('/specialist/patients/{id}/intake', [SpecialistPatientsController::class, 'updateIntake']);
    Route::get('/specialist/patients/{id}/tasks', [SpecialistPatientsController::class, 'tasks']);

    // Organization
    Route::get('/org/dashboard', [OrganizationDashboardController::class, 'index']);
    Route::get('/org/specialists', [OrganizationSpecialistsController::class, 'index']);
    Route::get('/org/sessions', [OrganizationSessionsController::class, 'index']);
});
