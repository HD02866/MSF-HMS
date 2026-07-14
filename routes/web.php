<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Modules\CardRoom\Controllers\CardOfficerDashboardController;
use App\Modules\CardRoom\Controllers\DailyRegisterController;
use App\Modules\CardRoom\Controllers\DashboardController;
use App\Modules\CardRoom\Controllers\PatientController;
use App\Modules\CardRoom\Controllers\RecorderDashboardController;
use App\Modules\CardRoom\Controllers\ReportController;
use App\Modules\CardRoom\Controllers\RoomManagementController;
use App\Modules\CardRoom\Controllers\UserManagementController;
use App\Modules\CardRoom\Controllers\VisitController;
use App\Modules\CardRoom\Controllers\VisitRegisterController;
use App\Modules\OPD\Controllers\OpdConsultationController;
use App\Modules\OPD\Controllers\OpdDashboardController;
use App\Modules\OPD\Controllers\OpdQueueController;
use App\Modules\OPD\Controllers\OpdRegisterController;
use App\Modules\OPD\Controllers\OpdReportController;
use App\Modules\OPD\Controllers\LabRequestController;
use App\Modules\OPD\Controllers\ConsultationRequestController;
use App\Modules\OPD\Controllers\ReferralController;
use App\Modules\OPD\Controllers\SickLeaveController;
use App\Modules\OPD\Controllers\HmisReportController;
use App\Modules\ConsultationRequest\Controllers\ConsultationRequestQueueController;
use App\Modules\Lab\Controllers\LabQueueController;
use App\Modules\Lab\Controllers\LabResultController;
use App\Modules\Pharmacy\Controllers\PharmacyDashboardController;
use App\Modules\Pharmacy\Controllers\PharmacyQueueController;
use App\Modules\Pharmacy\Controllers\PrescriptionController;
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
    Route::get('/card-officer/dashboard', CardOfficerDashboardController::class)->name('card-officer.dashboard');
    Route::get('/recorder/dashboard', RecorderDashboardController::class)->name('recorder.dashboard');

    Route::get('/patients/search', [PatientController::class, 'search'])->name('patients.search');
    Route::resource('patients', PatientController::class);

    Route::get('/visits/assign', [VisitController::class, 'create'])->name('visits.assign');
    Route::post('/visits', [VisitController::class, 'store'])->name('visits.store');
    Route::get('/visits/register', [VisitRegisterController::class, 'index'])->name('visits.register');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export/{type}/{period}', [ReportController::class, 'export'])->name('reports.export');

    Route::get('/profile', [UserManagementController::class, 'profile'])->name('profile.edit');
    Route::post('/profile', [UserManagementController::class, 'updateProfile'])->name('profile.update');
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

    // ── OPD Module ────────────────────────────────────────────────────────
    Route::get('/opd/dashboard', OpdDashboardController::class)->name('opd.dashboard');
    Route::post('/opd/queue/{opdQueue}/status', [OpdQueueController::class, 'updateStatus'])->name('opd.queue.status');
    Route::post('/opd/queue/call-next', [OpdQueueController::class, 'callNext'])->name('opd.queue.call-next');
    Route::post('/opd/notifications/mark-read', [OpdQueueController::class, 'markNotificationsRead'])->name('opd.notifications.mark-read');
    // Consultation — static paths before {opdQueue} param
    Route::get('/opd/consultation/{opdQueue}', [OpdConsultationController::class, 'show'])->name('opd.consultation.show');
    Route::get('/opd/consultation/{opdQueue}/history', [OpdConsultationController::class, 'history'])->name('opd.consultation.history');
    Route::post('/opd/consultation/{opdQueue}/notes', [OpdConsultationController::class, 'storeNote'])->name('opd.consultation.notes');
    Route::post('/opd/consultation/{opdQueue}/attachments', [OpdConsultationController::class, 'storeAttachment'])->name('opd.consultation.attachments');
    Route::get('/opd/consultation/{opdQueue}/attachments/{opdAttachment}/download', [OpdConsultationController::class, 'downloadAttachment'])->name('opd.consultation.attachments.download');
    Route::post('/opd/consultation/{opdQueue}/complete', [OpdConsultationController::class, 'complete'])->name('opd.consultation.complete');

    // ── OPD Register ─────────────────────────────────────────────────────
    Route::get('/opd/register', [OpdRegisterController::class, 'index'])->name('opd.register.index');
    Route::get('/opd/register/export/excel', [OpdRegisterController::class, 'exportExcel'])->name('opd.register.export.excel');
    Route::get('/opd/register/export/pdf', [OpdRegisterController::class, 'exportPdf'])->name('opd.register.export.pdf');

    // ── OPD Reports ───────────────────────────────────────────────────────
    Route::get('/opd/reports', [OpdReportController::class, 'index'])->name('opd.reports.index');

    // ── HMIS Reports ────────────────────────────────────────────────────
    Route::get('/opd/hmis-reports', [HmisReportController::class, 'index'])->name('opd.hmis-reports.index');
    Route::get('/opd/hmis-reports/export/excel', [HmisReportController::class, 'exportExcel'])->name('opd.hmis-reports.export.excel');
    Route::get('/opd/hmis-reports/export/pdf', [HmisReportController::class, 'exportPdf'])->name('opd.hmis-reports.export.pdf');

    // ── Laboratory Requests ───────────────────────────────────────────────
    Route::get('/opd/consultation/{opdQueue}/lab', [LabRequestController::class, 'create'])->name('opd.lab.create');
    Route::post('/opd/consultation/{opdQueue}/lab', [LabRequestController::class, 'store'])->name('opd.lab.store');

    // ── Laboratory Queue ──────────────────────────────────────────────────
    Route::get('/lab/queue', [LabQueueController::class, 'index'])->name('lab.queue.index');
    Route::post('/lab/queue/{labQueue}/status', [LabQueueController::class, 'updateStatus'])->name('lab.queue.status');

    // ── Laboratory Results ────────────────────────────────────────────────
    Route::get('/lab/queue/{labQueue}/results', [LabResultController::class, 'create'])->name('lab.results.create');
    Route::post('/lab/queue/{labQueue}/results', [LabResultController::class, 'store'])->name('lab.results.store');

    // ── Pharmacy Module ──────────────────────────────────────────────────
    Route::get('/pharmacy/dashboard', PharmacyDashboardController::class)->name('pharmacy.dashboard');
    Route::get('/pharmacy/queue', [PharmacyQueueController::class, 'index'])->name('pharmacy.queue.index');
    Route::post('/pharmacy/queue/{pharmacyQueue}/status', [PharmacyQueueController::class, 'updateStatus'])->name('pharmacy.queue.status');

    // ── Prescriptions (from OPD consultation) ────────────────────────────
    Route::get('/opd/consultation/{opdQueue}/prescription', [PrescriptionController::class, 'create'])->name('opd.prescription.create');
    Route::post('/opd/consultation/{opdQueue}/prescription', [PrescriptionController::class, 'store'])->name('opd.prescription.store');
    Route::get('/pharmacy/medicines/search', [PrescriptionController::class, 'searchMedicines'])->name('pharmacy.medicines.search');
    Route::post('/pharmacy/medicines/availability', [PrescriptionController::class, 'checkAvailability'])->name('pharmacy.medicines.availability');

    // ── Consultation Requests (from OPD consultation) ─────────────────────
    Route::get('/opd/consultation/{opdQueue}/consultation-request', [ConsultationRequestController::class, 'create'])->name('opd.consultation-request.create');
    Route::post('/opd/consultation/{opdQueue}/consultation-request', [ConsultationRequestController::class, 'store'])->name('opd.consultation-request.store');

    // ── Consultation Request Queue (receiving department) ─────────────────
    Route::get('/consultation-requests/queue', [ConsultationRequestQueueController::class, 'index'])->name('consultation-requests.queue.index');
    Route::post('/consultation-requests/queue/{consultationRequestQueue}/status', [ConsultationRequestQueueController::class, 'updateStatus'])->name('consultation-requests.queue.status');

    // ── Referrals (from OPD consultation) ─────────────────────────────────
    Route::get('/opd/consultation/{opdQueue}/referral', [ReferralController::class, 'create'])->name('opd.referral.create');
    Route::post('/opd/consultation/{opdQueue}/referral', [ReferralController::class, 'store'])->name('opd.referral.store');

    // ── Sick Leave (from OPD consultation) ────────────────────────────────
    Route::get('/opd/consultation/{opdQueue}/sick-leave', [SickLeaveController::class, 'create'])->name('opd.sick-leave.create');
    Route::post('/opd/consultation/{opdQueue}/sick-leave', [SickLeaveController::class, 'store'])->name('opd.sick-leave.store');

    // ── Referrals & Sick Leaves list (Recorder / Admin / Dept Head) ──────
    Route::get('/recorder/referrals-sick-leaves', [ReferralController::class, 'index'])->name('recorder.referrals-sick-leaves');
});
