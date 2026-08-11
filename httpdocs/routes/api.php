<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Password;
use Illuminate\Http\Request;
use App\Http\Controllers\PingController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SocialAuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\LibraryController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PushDeviceController;
use App\Http\Controllers\Api\DirectoryController;
use App\Http\Controllers\Api\V1\ChatController;
use App\Http\Controllers\Api\V1\Patient\PatientIntakeController;
use App\Http\Controllers\Api\V1\Patient\PatientTaskController;
use App\Http\Controllers\Api\V1\Patient\PreSessionSurveyController;
use App\Http\Controllers\Api\V1\Admin\{
    AdminDashboardController,
    AdminUsersController,
    AdminSpecialistsController,
    AdminOrganizationsController,
    AdminAppointmentsController,
    AdminLibraryController,
    AdminVentController,
    AdminDailyTipController,
    AdminProfileController,
    AdminWalletController
};
use App\Http\Controllers\Api\V1\Calendar\{
    AvailabilityController,
    AppointmentsController
};
use App\Http\Controllers\Api\V1\{
    CommunityController,
    ArticleController,
    JournalController,
    GroupSessionController,
    AnonymousMatchController,
    CoachController,
    ProfileController,
    SettingsController
};
use App\Http\Controllers\Api\V1\Specialist\{
    SpecialistDashboardController,
    SpecialistSessionsController,
    SpecialistProfileController,
    SpecialistPatientsController,
    SpecialistDocumentController
};
use App\Http\Controllers\Api\V1\Organization\{
    OrganizationDashboardController,
    OrganizationSpecialistsController,
    OrganizationSessionsController,
    OrganizationReportsController,
    OrganizationBillingController
};

Route::prefix('auth')->middleware('throttle:auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login',    [AuthController::class, 'login']);
    Route::post('phone/request-otp', [AuthController::class, 'phoneRequestOtp']);
    Route::post('phone/login', [AuthController::class, 'phoneLogin']);
    Route::post('google', [SocialAuthController::class, 'google']);
    Route::post('apple', [SocialAuthController::class, 'apple']);
    Route::post('facebook', [SocialAuthController::class, 'facebook']);
    Route::post('security-answer', [AuthController::class, 'saveSecurityAnswer']);
    Route::post('forgot/lookup', [AuthController::class, 'forgotLookup']);
    Route::post('forgot/reset', [AuthController::class, 'resetPasswordWithAnswer']);
    Route::post('forgot-password', function (Request $r) {
        $r->validate(['email' => 'required|email']);
        $status = Password::sendResetLink($r->only('email'));
        return $status === Password::RESET_LINK_SENT
            ? response()->json(['sent' => true])
            : response()->json(['sent' => false], 422);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::delete('account', [AuthController::class, 'deleteAccount']);
    });
});

Route::middleware(['auth:sanctum', \App\Http\Middleware\ForceJson::class, 'throttle:api'])->prefix('v1')->group(function () {
    Route::get('catalog', [\App\Http\Controllers\Api\V1\CatalogController::class, 'index']);
    Route::get('dashboard', [DashboardController::class, 'index']);
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::post('devices', [PushDeviceController::class, 'store']);
    Route::delete('devices', [PushDeviceController::class, 'destroy']);
    Route::get('push-preferences', [PushDeviceController::class, 'preferences']);
    Route::put('push-preferences', [PushDeviceController::class, 'updatePreferences']);
    Route::get('settings', [SettingsController::class, 'show']);
    Route::put('profile', [ProfileController::class, 'update']);
    Route::post('profile/password', [ProfileController::class, 'updatePassword']);

    Route::get('library', [LibraryController::class, 'index']);
    Route::get('library/tags', [LibraryController::class, 'tags']);
    Route::get('library/curated/syria-europe', [LibraryController::class, 'curatedSyriaEurope']);
    Route::get('library/daily-tip', [LibraryController::class, 'dailyTip']);
    Route::get('library/{id}', [LibraryController::class, 'show']);
    Route::post('library/{id}/favorite', [LibraryController::class, 'favorite']);
    Route::delete('library/{id}/favorite', [LibraryController::class, 'unfavorite']);

    Route::get('sessions', [SessionController::class, 'index']);
    Route::post('sessions', [SessionController::class, 'store']);
    Route::get('sessions/{id}', [SessionController::class, 'show']);
    Route::put('sessions/{id}', [SessionController::class, 'update']);
    Route::post('sessions/{id}/start', [SessionController::class, 'start']);
    Route::post('sessions/{id}/cancel', [SessionController::class, 'cancel']);
    Route::post('sessions/{id}/confirm-payment', [\App\Http\Controllers\Api\V1\Billing\CheckoutController::class, 'confirmSessionPayment']);
    // تمديد وإغلاق وتقييم ومهام الجلسة
    Route::post('sessions/{id}/extend', [SessionController::class, 'extend']);
    Route::post('sessions/{id}/complete', [SessionController::class, 'complete']);
    Route::post('sessions/{id}/survey', [SessionController::class, 'survey']);
    Route::post('sessions/{id}/rate-specialist', [SessionController::class, 'rateSpecialist']);
    Route::post('sessions/{id}/rate-patient', [SessionController::class, 'ratePatient']);
    Route::post('sessions/{id}/tasks', [SessionController::class, 'addTask']);
    Route::get('sessions/{id}/tasks', [SessionController::class, 'listTasks']);
    Route::post('sessions/tasks/{taskId}/complete', [SessionController::class, 'completeTask']);
    Route::post('sessions/{id}/livekit-token', [SessionController::class, 'livekitToken']);

    Route::get('specialists', [DirectoryController::class, 'specialists']);
    Route::get('specialists/{id}', [DirectoryController::class, 'show']);
    Route::get('organizations', [DirectoryController::class, 'organizations']);

    Route::get('chats', [ChatController::class, 'index']);
    Route::post('chats', [ChatController::class, 'store']);
    Route::get('chats/{chat}/messages', [ChatController::class, 'messages']);
    Route::post('chats/{chat}/messages', [ChatController::class, 'send']);

    Route::get('group-sessions', [GroupSessionController::class, 'index']);
    Route::post('group-sessions', [GroupSessionController::class, 'store']);
    Route::get('group-sessions/{groupSession}', [GroupSessionController::class, 'show']);
    Route::post('group-sessions/{groupSession}/join', [GroupSessionController::class, 'join']);
    Route::post('group-sessions/{groupSession}/leave', [GroupSessionController::class, 'leave']);
    Route::post('group-sessions/{groupSession}/livekit-token', [GroupSessionController::class, 'livekitToken']);

    Route::get('anonymous/status', [AnonymousMatchController::class, 'status']);
    Route::post('anonymous/join', [AnonymousMatchController::class, 'join']);
    Route::post('anonymous/leave', [AnonymousMatchController::class, 'leave']);
    Route::post('anonymous/{id}/end', [AnonymousMatchController::class, 'end']);
    Route::post('anonymous/{id}/report', [AnonymousMatchController::class, 'report']);

    Route::get('coach/programs', [CoachController::class, 'index']);
    Route::post('coach/programs', [CoachController::class, 'store']);
    Route::get('coach/programs/{id}', [CoachController::class, 'show']);
    Route::post('coach/programs/{id}/checkins', [CoachController::class, 'checkin']);
    Route::post('coach/items/{itemId}/complete', [CoachController::class, 'completeItem']);

    Route::get('patient/intake', [PatientIntakeController::class, 'show']);
    Route::post('patient/intake', [PatientIntakeController::class, 'save']);
    Route::get('patient/pre-session-survey', [PreSessionSurveyController::class, 'show']);
    Route::post('patient/pre-session-survey', [PreSessionSurveyController::class, 'store']);

    // مهام علاجية
    Route::get('patient/tasks', [PatientTaskController::class, 'index']);
    Route::post('patient/tasks', [PatientTaskController::class, 'store']);
    Route::put('patient/tasks/{id}', [PatientTaskController::class, 'update']);

    // إعادة إرسال طلب التفعيل
    Route::post('specialist/resubmit', [\App\Http\Controllers\Api\V1\Specialist\SpecialistProfileController::class, 'resubmit']);
    Route::post('org/resubmit', [OrganizationDashboardController::class, 'resubmit']);
});

Route::middleware(['auth:sanctum', 'admin.super'])->prefix('v1/admin')->group(function () {
    Route::get('dashboard', [AdminDashboardController::class, 'index']);
    Route::get('users', [AdminUsersController::class, 'index']);
    Route::get('specialists', [AdminSpecialistsController::class, 'index']);
    Route::post('specialists', [AdminSpecialistsController::class, 'store']);
    Route::get('specialists/{id}/documents', [AdminSpecialistsController::class, 'documents']);
    Route::post('specialists/{id}/review', [AdminSpecialistsController::class, 'review']);
    Route::post('specialists/{id}/approve', [AdminSpecialistsController::class, 'approve']);
    Route::post('specialists/{id}/reject', [AdminSpecialistsController::class, 'reject']);
    Route::get('organizations', [AdminOrganizationsController::class, 'index']);
    Route::get('organizations/{id}', [AdminOrganizationsController::class, 'show']);
    Route::post('organizations/{id}/approve', [AdminOrganizationsController::class, 'approve']);
    Route::post('organizations/{id}/reject', [AdminOrganizationsController::class, 'reject']);
    Route::get('appointments', [AdminAppointmentsController::class, 'index']);
    Route::get('library/posts', [AdminLibraryController::class, 'index']);
    Route::post('library/posts/{id}/toggle', [AdminLibraryController::class, 'toggle']);
    Route::get('daily-tips', [AdminDailyTipController::class, 'index']);
    Route::post('daily-tips', [AdminDailyTipController::class, 'store']);
    Route::put('daily-tips/{id}', [AdminDailyTipController::class, 'update']);
    Route::delete('daily-tips/{id}', [AdminDailyTipController::class, 'destroy']);
    Route::get('vent/reports', [AdminVentController::class, 'reports']);
    Route::post('vent/posts/{id}/hide', [AdminVentController::class, 'hide']);
    Route::get('profile', [AdminProfileController::class, 'show']);
    Route::put('profile', [AdminProfileController::class, 'update']);
    Route::post('profile/password', [AdminProfileController::class, 'updatePassword']);
    Route::post('profile/avatar', [AdminProfileController::class, 'uploadAvatar']);
    Route::get('settings', [AdminProfileController::class, 'settings']);
    Route::put('settings', [AdminProfileController::class, 'saveSettings']);
    Route::post('wallet/coupon', [AdminWalletController::class, 'createCoupon']);
    Route::post('wallet/credit', [AdminWalletController::class, 'credit']);
});

Route::middleware(['auth:sanctum', \App\Http\Middleware\ForceJson::class])->prefix('v1/billing')->group(function () {
    Route::get('plans', [\App\Http\Controllers\Api\V1\Billing\PlansController::class, 'index']);
    Route::post('subscribe', [\App\Http\Controllers\Api\V1\Billing\SubscriptionsController::class, 'subscribe']);
    Route::post('cancel', [\App\Http\Controllers\Api\V1\Billing\SubscriptionsController::class, 'cancel']);
    Route::get('invoices', [\App\Http\Controllers\Api\V1\Billing\InvoicesController::class, 'index']);
    Route::get('transactions', [\App\Http\Controllers\Api\V1\Billing\TransactionsController::class, 'index']);
});

Route::middleware('auth:sanctum')->prefix('v1/wallet')->group(function () {
    Route::get('me', [\App\Http\Controllers\Api\V1\Billing\WalletController::class, 'me']);
    Route::post('topup/intent', [\App\Http\Controllers\Api\V1\Billing\WalletController::class, 'createIntent']);
    Route::post('mtn/init', [\App\Http\Controllers\Api\V1\Billing\WalletController::class, 'mtnInit']);
    Route::post('mtn/confirm', [\App\Http\Controllers\Api\V1\Billing\WalletController::class, 'mtnConfirm']);
    Route::post('syriatel/init', [\App\Http\Controllers\Api\V1\Billing\WalletController::class, 'syriatelInit']);
    Route::post('syriatel/confirm', [\App\Http\Controllers\Api\V1\Billing\WalletController::class, 'syriatelConfirm']);
    Route::post('apply-coupon', [\App\Http\Controllers\Api\V1\Billing\WalletController::class, 'applyCoupon']);
});

Route::get('v1/billing/payment-methods', [\App\Http\Controllers\Api\V1\Billing\WalletController::class, 'paymentMethods']);

Route::post('webhooks/stripe', [\App\Http\Controllers\Api\V1\Billing\StripeWebhookController::class, 'handle']);
Route::post('v1/ios/verify-receipt', [\App\Http\Controllers\Api\V1\Billing\IosReceiptController::class, 'verify'])
    ->middleware('auth:sanctum');

Route::middleware(['auth:sanctum', \App\Http\Middleware\ForceJson::class, 'throttle:api'])->prefix('v1')->group(function () {
    Route::get('cal/availability', [AvailabilityController::class, 'index']);
    Route::post('cal/availability', [AvailabilityController::class, 'store']);
    Route::delete('cal/availability/{id}', [AvailabilityController::class, 'destroy']);

    Route::post('cal/block', [AvailabilityController::class, 'block']);
    Route::delete('cal/block/{id}', [AvailabilityController::class, 'unblock']);

    Route::get('cal/appointments', [AppointmentsController::class, 'index']);
    Route::post('cal/appointments', [AppointmentsController::class, 'store']);
    Route::post('cal/appointments/recurring', [AppointmentsController::class, 'storeRecurring']);
    Route::post('cal/appointments/{id}/cancel', [AppointmentsController::class, 'cancel']);
    Route::post('cal/appointments/{id}/accept', [AppointmentsController::class, 'accept']);
    Route::post('cal/appointments/{id}/reject', [AppointmentsController::class, 'reject']);
    Route::post('cal/appointments/{id}/reschedule', [AppointmentsController::class, 'reschedule']);
    Route::get('cal/suggested-slots', [AppointmentsController::class, 'suggested']);
});

Route::middleware(['auth:sanctum', \App\Http\Middleware\ForceJson::class, 'throttle:api'])->prefix('v1')->group(function () {
    Route::get('community', [CommunityController::class, 'index']);
    Route::post('community', [CommunityController::class, 'store']);
    Route::get('community/{id}', [CommunityController::class, 'show']);
    Route::post('community/{id}/join', [CommunityController::class, 'join']);
    Route::post('community/{id}/leave', [CommunityController::class, 'leave']);
    Route::get('community/{id}/feed', [CommunityController::class, 'feed']);
    Route::post('community/{id}/post', [CommunityController::class, 'post']);
    Route::post('community/{id}/post/{post}/like', [CommunityController::class, 'like']);
    Route::post('community/{id}/post/{post}/comment', [CommunityController::class, 'comment']);
    Route::post('community/{community}/question/{question}/accept/{answer}', [CommunityController::class, 'acceptAnswer']);
    Route::post('community/media', [\App\Http\Controllers\Api\V1\CommunityMediaController::class, 'store']);
    Route::post('library/articles', [LibraryController::class, 'store']);
    Route::post('library/media', [\App\Http\Controllers\Api\V1\LibraryMediaController::class, 'store']);
    // فضفضة مع سند
    Route::get('vent', [\App\Http\Controllers\Api\V1\VentController::class, 'index']);
    Route::post('vent', [\App\Http\Controllers\Api\V1\VentController::class, 'store']);
    Route::post('vent/{id}/react', [\App\Http\Controllers\Api\V1\VentController::class, 'react']);
    Route::post('vent/{id}/report', [\App\Http\Controllers\Api\V1\VentController::class, 'report']);
    Route::post('vent/chat', [\App\Http\Controllers\Api\V1\VentController::class, 'chat']);

    Route::get('articles', [ArticleController::class, 'index']);
    Route::get('articles/{id}', [ArticleController::class, 'show']);
    Route::post('articles/{id}', [ArticleController::class, 'favorite']);
    Route::delete('articles/{id}', [ArticleController::class, 'unfavorite']);
    Route::post('articles', [ArticleController::class, 'store']);
    Route::put('articles/{id}', [ArticleController::class, 'update']);

    Route::get('journal', [JournalController::class, 'index']);
    Route::post('journal', [JournalController::class, 'store']);
    Route::delete('journal/{id}', [JournalController::class, 'destroy']);
});

Route::middleware(['auth:sanctum', \App\Http\Middleware\ForceJson::class, 'role.approved', 'throttle:api'])->prefix('v1')->group(function () {
    Route::get('specialist/dashboard', [SpecialistDashboardController::class, 'index']);
    Route::get('specialist/sessions', [SpecialistSessionsController::class, 'index']);
    Route::post('specialist/sessions/{id}/accept', [SpecialistSessionsController::class, 'accept']);
    Route::post('specialist/sessions/{id}/reject', [SpecialistSessionsController::class, 'reject']);
    Route::post('specialist/sessions/{id}/reschedule', [SpecialistSessionsController::class, 'reschedule']);
    Route::post('specialist/sessions/{id}/extend', [SpecialistSessionsController::class, 'extend']);
    Route::post('specialist/sessions/{id}/complete', [SpecialistSessionsController::class, 'complete']);
    Route::get('specialist/profile', [SpecialistProfileController::class, 'show']);
    Route::put('specialist/profile', [SpecialistProfileController::class, 'update']);
    Route::post('specialist/profile/avatar', [SpecialistProfileController::class, 'uploadAvatar']);
    Route::get('specialist/documents', [SpecialistDocumentController::class, 'index']);
    Route::post('specialist/documents', [SpecialistDocumentController::class, 'store']);
    Route::delete('specialist/documents/{id}', [SpecialistDocumentController::class, 'destroy']);
    Route::get('specialist/patients', [SpecialistPatientsController::class, 'index']);
    Route::get('specialist/patients/{id}/intake', [SpecialistPatientsController::class, 'intake']);
    Route::put('specialist/patients/{id}/intake', [SpecialistPatientsController::class, 'updateIntake']);
    Route::get('specialist/patients/{id}/tasks', [SpecialistPatientsController::class, 'tasks']);
    Route::get('specialist/patients/{id}/sessions', [SpecialistPatientsController::class, 'sessions']);
    Route::post('specialist/patients/{id}/acknowledge-physician-referral', [SpecialistPatientsController::class, 'acknowledgePhysicianReferral']);
    Route::post('specialist/patients/{id}/recommend-external-physician', [SpecialistPatientsController::class, 'recommendExternalPhysician']);
    Route::post('specialist/patients/{id}/tasks/templates', [SpecialistPatientsController::class, 'applyTaskTemplates']);

    Route::get('org/dashboard', [OrganizationDashboardController::class, 'index']);
    Route::get('org/support-room', [OrganizationDashboardController::class, 'supportRoom']);
    Route::get('org/specialists', [OrganizationSpecialistsController::class, 'index']);
    Route::get('org/specialists/{id}', [OrganizationSpecialistsController::class, 'show']);
    Route::get('org/sessions', [OrganizationSessionsController::class, 'index']);
    Route::get('org/reports/summary', [OrganizationReportsController::class, 'summary']);
    Route::get('org/billing/overview', [OrganizationBillingController::class, 'overview']);
    Route::get('org/beneficiaries', [\App\Http\Controllers\Api\V1\Organization\OrganizationBeneficiariesController::class, 'index']);
    Route::post('org/beneficiaries', [\App\Http\Controllers\Api\V1\Organization\OrganizationBeneficiariesController::class, 'store']);
    Route::get('org/beneficiaries/{id}', [\App\Http\Controllers\Api\V1\Organization\OrganizationBeneficiariesController::class, 'show']);
    Route::post('org/beneficiaries/{id}/assign-specialist', [\App\Http\Controllers\Api\V1\Organization\OrganizationBeneficiariesController::class, 'assignSpecialist']);
});

Route::middleware(['auth:sanctum', 'throttle:api'])->prefix('v1')->group(function () {
    Route::get('reports/overview', [\App\Http\Controllers\Api\V1\Reports\ReportsController::class, 'overview']);
    Route::get('reports/surveys/summary', [\App\Http\Controllers\Api\V1\Reports\ReportsController::class, 'surveySummary']);
    Route::get('reports/timeseries/sessions', [\App\Http\Controllers\Api\V1\Reports\ReportsController::class, 'sessionsSeries']);
    Route::get('reports/timeseries/users', [\App\Http\Controllers\Api\V1\Reports\ReportsController::class, 'usersSeries']);
    Route::get('reports/timeseries/revenue', [\App\Http\Controllers\Api\V1\Reports\ReportsController::class, 'revenueSeries']);
    Route::get('reports/top/specialists', [\App\Http\Controllers\Api\V1\Reports\ReportsController::class, 'topSpecialists']);
    Route::get('reports/top/organizations', [\App\Http\Controllers\Api\V1\Reports\ReportsController::class, 'topOrganizations']);
    Route::get('reports/retention', [\App\Http\Controllers\Api\V1\Reports\ReportsController::class, 'retention']);
    Route::get('reports/conversion', [\App\Http\Controllers\Api\V1\Reports\ReportsController::class, 'conversion']);
    Route::get('reports/export/csv', [\App\Http\Controllers\Api\V1\Reports\ReportsExportController::class, 'csv']);
});

Route::middleware(['force.json', 'set.locale'])->group(function () {
    Route::get('ping', [PingController::class, 'ping']);
    Route::get('bootstrap', [PingController::class, 'bootstrap']);
});
