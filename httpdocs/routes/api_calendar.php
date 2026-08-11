
<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Calendar\{AvailabilityController, AppointmentsController};

Route::middleware(['auth:sanctum'])->prefix('api/v1')->group(function () {

    // Availability (specialist)
    Route::get('/cal/availability', [AvailabilityController::class, 'index']);
    Route::post('/cal/availability', [AvailabilityController::class, 'store']);
    Route::delete('/cal/availability/{id}', [AvailabilityController::class, 'destroy']);

    Route::post('/cal/block', [AvailabilityController::class, 'block']);
    Route::delete('/cal/block/{id}', [AvailabilityController::class, 'unblock']);

    // Appointments
    Route::get('/cal/appointments', [AppointmentsController::class, 'index']);
    Route::post('/cal/appointments', [AppointmentsController::class, 'store']);
    Route::post('/cal/appointments/{id}/cancel', [AppointmentsController::class, 'cancel']);
    Route::post('/cal/appointments/{id}/accept', [AppointmentsController::class, 'accept']);
    Route::post('/cal/appointments/{id}/reject', [AppointmentsController::class, 'reject']);
    Route::post('/cal/appointments/{id}/reschedule', [AppointmentsController::class, 'reschedule']);

    // Suggested slots
    Route::get('/cal/suggested-slots', [AppointmentsController::class, 'suggested']);
});
