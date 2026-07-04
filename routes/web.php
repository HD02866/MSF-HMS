<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Modules\CardRoom\Controllers\DailyRegisterController;
use App\Modules\CardRoom\Controllers\DashboardController;
use App\Modules\CardRoom\Controllers\PatientController;
use App\Modules\CardRoom\Controllers\RecorderDashboardController;
use App\Modules\CardRoom\Controllers\ReportController;
use App\Modules\CardRoom\Controllers\RoomManagementController;
use App\Modules\CardRoom\Controllers\UserManagementController;
use App\Modules\CardRoom\Controllers\VisitController;
use App\Modules\CardRoom\Controllers\VisitRegisterController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/recorder/dashboard', RecorderDashboardController::class)->name('recorder.dashboard');

    Route::get('/patients/search', [PatientController::class, 'search'])->name('patients.search');
    Route::resource('patients', PatientController::class);

    Route::get('/visits/assign', [VisitController::class, 'create'])->name('visits.assign');
    Route::post('/visits', [VisitController::class, 'store'])->name('visits.store');
    Route::get('/visits/register', [VisitRegisterController::class, 'index'])->name('visits.register');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export/{type}/{period}', [ReportController::class, 'export'])->name('reports.export');

    Route::get('/profile', [UserManagementController::class, 'profile'])->name('profile.edit');
    Route::patch('/profile', [UserManagementController::class, 'updateProfile'])->name('profile.update');
    Route::put('/profile/password', [UserManagementController::class, 'updatePassword'])->name('profile.password');

    // Daily Register — viewable by Admin, Recorder, Card Officer, Department Head
    // Create/Edit/Delete restricted to Admin and Recorder via Policy
    // NOTE: Static sub-paths (export/*, patient-info/*) MUST come before {dailyRegister} param routes
    Route::get('/daily-register', [DailyRegisterController::class, 'index'])->name('daily-register.index');
    Route::post('/daily-register', [DailyRegisterController::class, 'store'])->name('daily-register.store');
    Route::get('/daily-register/export/excel', [DailyRegisterController::class, 'exportExcel'])->name('daily-register.export.excel');
    Route::get('/daily-register/export/pdf', [DailyRegisterController::class, 'exportPdf'])->name('daily-register.export.pdf');
    Route::get('/daily-register/patient-info/{patient}', [DailyRegisterController::class, 'patientInfo'])->name('daily-register.patient-info');
    Route::get('/daily-register/patient-search', [DailyRegisterController::class, 'patientSearch'])->name('daily-register.patient-search');
    Route::put('/daily-register/{dailyRegister}', [DailyRegisterController::class, 'update'])->name('daily-register.update');
    Route::delete('/daily-register/{dailyRegister}', [DailyRegisterController::class, 'destroy'])->name('daily-register.destroy');

    Route::middleware('role:Admin')->group(function () {
        Route::resource('users', UserManagementController::class)->except(['show']);
        Route::resource('rooms', RoomManagementController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->names('rooms');
    });
});
