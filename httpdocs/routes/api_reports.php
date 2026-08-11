
<?php
use Illuminate\Support\Facades\Route;
Route::middleware(['auth:sanctum'])->group(function(){
  // Core
  Route::get('/api/v1/reports/overview', [\App\Http\Controllers\Api\V1\Reports\ReportsController::class,'overview']);
  Route::get('/api/v1/reports/timeseries/sessions', [\App\Http\Controllers\Api\V1\Reports\ReportsController::class,'sessionsSeries']);
  Route::get('/api/v1/reports/timeseries/users', [\App\Http\Controllers\Api\V1\Reports\ReportsController::class,'usersSeries']);
  Route::get('/api/v1/reports/timeseries/revenue', [\App\Http\Controllers\Api\V1\Reports\ReportsController::class,'revenueSeries']);
  Route::get('/api/v1/reports/top/specialists', [\App\Http\Controllers\Api\V1\Reports\ReportsController::class,'topSpecialists']);
  Route::get('/api/v1/reports/top/organizations', [\App\Http\Controllers\Api\V1\Reports\ReportsController::class,'topOrganizations']);
  Route::get('/api/v1/reports/retention', [\App\Http\Controllers\Api\V1\Reports\ReportsController::class,'retention']);
  Route::get('/api/v1/reports/conversion', [\App\Http\Controllers\Api\V1\Reports\ReportsController::class,'conversion']);
  // Exports
  Route::get('/api/v1/reports/export/csv', [\App\Http\Controllers\Api\V1\Reports\ReportsExportController::class,'csv']);
});
