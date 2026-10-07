<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DivisionValidationController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\SchoolAssessmentController;
use App\Http\Controllers\SchoolRegistrationController;
use App\Http\Controllers\Settings\AccountController;
use App\Http\Controllers\SgcAiController;
use App\Http\Controllers\SuperUserController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (! User::query()->where('role', 'super')->exists()) {
        return redirect()->route('setup');
    }

    if (auth()->check()) {
        return redirect()->route(auth()->user()->homeRoute());
    }

    return app(AuthenticatedSessionController::class)->create(request());
})->name('home');

Route::get('dashboard', function () {
    return redirect()->route(request()->user()->homeRoute());
})->middleware('auth')->name('dashboard');

Route::middleware(['auth', 'role:super'])->group(function () {
    Route::get('super', [PortalController::class, 'superOverview'])->name('super.overview');
    Route::get('super/divisions', [PortalController::class, 'superDivisions'])->name('super.divisions');
    Route::get('super/users', [SuperUserController::class, 'index'])->name('super.users');
    Route::post('super/users', [SuperUserController::class, 'store'])->name('super.users.store');
    Route::post('super/reset', [SuperUserController::class, 'reset'])->name('super.reset');
    Route::get('super/cycles', [PortalController::class, 'superCycles'])->name('super.cycles');
});

Route::middleware(['auth', 'role:division'])->group(function () {
    Route::get('division', [PortalController::class, 'divisionOverview'])->name('division.overview');
    Route::get('division/queue', [DivisionValidationController::class, 'queue'])->name('division.queue');
    Route::get('division/queue/{assessment}', [DivisionValidationController::class, 'show'])->name('division.review');
    Route::post('division/movs/{mov}/accept', [DivisionValidationController::class, 'acceptMov'])->name('division.movs.accept');
    Route::post('division/movs/{mov}/return', [DivisionValidationController::class, 'returnMov'])->name('division.movs.return');
    Route::post('division/queue/{assessment}/complete', [DivisionValidationController::class, 'complete'])->name('division.review.complete');
    Route::get('division/schools', [PortalController::class, 'divisionSchools'])->name('division.schools');
    Route::get('division/schools/{code}', [PortalController::class, 'divisionSchool'])->name('division.schools.show');
    Route::get('division/registrations', [SchoolRegistrationController::class, 'index'])->name('division.registrations');
    Route::post('division/registrations/{user}/accept', [SchoolRegistrationController::class, 'accept'])->name('division.registrations.accept');
    Route::get('division/alerts', [PortalController::class, 'divisionAlerts'])->name('division.alerts');
});

Route::middleware(['auth', 'role:school,school_head'])->group(function () {
    Route::get('school', [SchoolAssessmentController::class, 'dashboard'])->name('school.dashboard');
    Route::get('school/assessment', [SchoolAssessmentController::class, 'assessment'])->name('school.assessment');
    Route::post('school/assessment', [SchoolAssessmentController::class, 'encode'])->name('school.assessment.encode');
    Route::get('school/movs', [SchoolAssessmentController::class, 'movs'])->name('school.movs');
    Route::post('school/movs', [SchoolAssessmentController::class, 'upload'])->name('school.movs.upload');
    Route::post('school/movs/reuse', [SchoolAssessmentController::class, 'reuse'])->name('school.movs.reuse');
    Route::get('school/form-data', [SchoolAssessmentController::class, 'formData'])->name('school.form-data');
    Route::post('school/form-data', [SchoolAssessmentController::class, 'saveFormData'])->name('school.form-data.save');
    Route::get('school/templates', [SchoolAssessmentController::class, 'templates'])->name('school.templates');
    Route::get('school/templates/{key}/preview', [SchoolAssessmentController::class, 'previewTemplate'])->name('school.templates.preview');
    Route::get('school/templates/{key}', [SchoolAssessmentController::class, 'downloadTemplate'])->name('school.templates.download');
    Route::post('school/movs/{mov}/remove', [SchoolAssessmentController::class, 'remove'])->name('school.movs.remove');
    Route::post('school/movs/{mov}/request-removal', [SchoolAssessmentController::class, 'requestRemoval'])->name('school.movs.request-removal');
    Route::get('school/movs/{mov}/download', [SchoolAssessmentController::class, 'download'])->name('school.movs.download');
    Route::get('school/submit', [SchoolAssessmentController::class, 'submitPage'])->name('school.submit');
    Route::post('school/submit/qa', [SchoolAssessmentController::class, 'certifyQa'])->name('school.submit.qa');
    Route::post('school/submit', [SchoolAssessmentController::class, 'submit'])->name('school.submit.send');
    Route::post('school/submit/withdraw', [SchoolAssessmentController::class, 'withdraw'])->name('school.submit.withdraw');
    Route::get('school/notifications', [SchoolAssessmentController::class, 'notifications'])->name('school.notifications');
    Route::post('school/ai/chat', [SgcAiController::class, 'chat'])->name('school.ai.chat');
});

Route::middleware(['auth', 'role:school_head'])->group(function () {
    Route::get('school/encoders', [SchoolRegistrationController::class, 'encoders'])->name('school.encoders');
    Route::post('school/encoders/{user}/accept', [SchoolRegistrationController::class, 'acceptEncoder'])->name('school.encoders.accept');
});

Route::middleware(['auth'])->group(function () {
    Route::post('ai/chat', [SgcAiController::class, 'chat'])->name('ai.chat');
    Route::get('movs/{mov}/download', [SchoolAssessmentController::class, 'download'])->name('movs.download');
    Route::get('account', [AccountController::class, 'edit'])->name('account.edit');
    Route::get('configuration', [AccountController::class, 'configuration'])->name('configuration');
    Route::post('configuration', [AccountController::class, 'updateAppearance'])->name('configuration.update');
    Route::post('configuration/reset', [AccountController::class, 'resetAppearance'])->name('configuration.reset');
    Route::get('docs', [AccountController::class, 'docs'])->name('docs');
    Route::get('help', [AccountController::class, 'help'])->name('help');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
