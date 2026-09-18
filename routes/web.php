<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DivisionValidationController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\SchoolAssessmentController;
use App\Http\Controllers\SchoolRegistrationController;
use App\Http\Controllers\Settings\AccountController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
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
    Route::get('super/users', [PortalController::class, 'superUsers'])->name('super.users');
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
    Route::get('school/movs/{mov}/download', [SchoolAssessmentController::class, 'download'])->name('school.movs.download');
    Route::get('school/submit', [SchoolAssessmentController::class, 'submitPage'])->name('school.submit');
    Route::post('school/submit/qa', [SchoolAssessmentController::class, 'certifyQa'])->name('school.submit.qa');
    Route::post('school/submit', [SchoolAssessmentController::class, 'submit'])->name('school.submit.send');
    Route::get('school/notifications', [SchoolAssessmentController::class, 'notifications'])->name('school.notifications');
});

Route::middleware(['auth', 'role:school_head'])->group(function () {
    Route::get('school/encoders', [SchoolRegistrationController::class, 'encoders'])->name('school.encoders');
    Route::post('school/encoders/{user}/accept', [SchoolRegistrationController::class, 'acceptEncoder'])->name('school.encoders.accept');
});

Route::middleware(['auth'])->group(function () {
    Route::get('movs/{mov}/download', [SchoolAssessmentController::class, 'download'])->name('movs.download');
    Route::get('account', [AccountController::class, 'edit'])->name('account.edit');
    Route::get('docs', [AccountController::class, 'docs'])->name('docs');
    Route::get('help', [AccountController::class, 'help'])->name('help');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
