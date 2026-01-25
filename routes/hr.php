<?php

/**
 * HRMS Implementation Plan - Section 7.1
 * HR Routes - All Human Resources Management Routes
 */

use App\Http\Controllers\HR\LeaveTypeController;
use App\Http\Controllers\HR\LeaveRequestController;
use App\Http\Controllers\HR\LeaveBalanceController;
use App\Http\Controllers\HR\LeaveCalendarController;
use App\Http\Controllers\HR\DisciplinaryQueryController;
use App\Http\Controllers\HR\StaffSuspensionController;
use App\Http\Controllers\HR\StaffTerminationController;
use App\Http\Controllers\HR\PayHeadController;
use App\Http\Controllers\HR\SalaryProfileController;
use App\Http\Controllers\HR\PayrollBatchController;
use App\Http\Controllers\HR\HrWorkbenchController;
use App\Http\Controllers\HR\EssController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('hr')->name('hr.')->group(function () {

    // ===========================================
    // HR WORKBENCH (Dashboard for HR Manager)
    // ===========================================
    Route::middleware(['permission:hr-workbench.access'])->group(function () {
        Route::get('/workbench', [HrWorkbenchController::class, 'index'])->name('workbench.index');
        Route::get('/workbench/stats', [HrWorkbenchController::class, 'stats'])->name('workbench.stats');
    });

    // ===========================================
    // LEAVE MANAGEMENT
    // ===========================================

    // Leave Types (Admin/HR only)
    Route::middleware(['permission:leave-type.view|leave-type.create'])->group(function () {
        Route::resource('leave-types', LeaveTypeController::class);
    });

    // Leave Requests
    Route::prefix('leave-requests')->name('leave-requests.')->group(function () {
        // HR view all requests
        Route::get('/', [LeaveRequestController::class, 'index'])
            ->middleware('permission:leave-request.view')
            ->name('index');

        // Create request (HR can create for anyone, Staff can create own)
        Route::get('/create', [LeaveRequestController::class, 'create'])
            ->middleware('permission:leave-request.create|leave-request.create-own')
            ->name('create');
        Route::post('/', [LeaveRequestController::class, 'store'])
            ->middleware('permission:leave-request.create|leave-request.create-own')
            ->name('store');

        // Show single request
        Route::get('/{leaveRequest}', [LeaveRequestController::class, 'show'])
            ->middleware('permission:leave-request.view|leave-request.view-own')
            ->name('show');

        // Edit request (only pending, only HR)
        Route::get('/{leaveRequest}/edit', [LeaveRequestController::class, 'edit'])
            ->middleware('permission:leave-request.edit')
            ->name('edit');
        Route::put('/{leaveRequest}', [LeaveRequestController::class, 'update'])
            ->middleware('permission:leave-request.edit')
            ->name('update');

        // Delete (HR only)
        Route::delete('/{leaveRequest}', [LeaveRequestController::class, 'destroy'])
            ->middleware('permission:leave-request.delete')
            ->name('destroy');

        // Approval workflow - Two Level
        // First Level: Supervisor (Unit Head / Dept Head)
        Route::post('/{leaveRequest}/supervisor-approve', [LeaveRequestController::class, 'supervisorApprove'])
            ->middleware('permission:leave-request.supervisor-approve')
            ->name('supervisor-approve');
        // Second Level: HR Manager
        Route::post('/{leaveRequest}/hr-approve', [LeaveRequestController::class, 'hrApprove'])
            ->middleware('permission:leave-request.hr-approve')
            ->name('hr-approve');
        // Legacy approve (auto-detects level)
        Route::post('/{leaveRequest}/approve', [LeaveRequestController::class, 'approve'])
            ->middleware('permission:leave-request.approve|leave-request.supervisor-approve|leave-request.hr-approve')
            ->name('approve');
        Route::post('/{leaveRequest}/reject', [LeaveRequestController::class, 'reject'])
            ->middleware('permission:leave-request.reject|leave-request.supervisor-approve|leave-request.hr-approve')
            ->name('reject');
        Route::post('/{leaveRequest}/cancel', [LeaveRequestController::class, 'cancel'])
            ->name('cancel');
        Route::post('/{leaveRequest}/recall', [LeaveRequestController::class, 'recall'])
            ->middleware('permission:leave-request.recall')
            ->name('recall');
    });

    // Leave Balances
    Route::middleware(['permission:leave-balance.view|leave-balance.manage'])->group(function () {
        Route::get('/leave-balances', [LeaveBalanceController::class, 'index'])->name('leave-balances.index');
        Route::get('/leave-balances/{staff}', [LeaveBalanceController::class, 'show'])->name('leave-balances.show');
        Route::post('/leave-balances/initialize', [LeaveBalanceController::class, 'initializeYear'])
            ->middleware('permission:leave-balance.manage')
            ->name('leave-balances.initialize');
        Route::post('/leave-balances/adjust', [LeaveBalanceController::class, 'adjust'])
            ->middleware('permission:leave-balance.manage')
            ->name('leave-balances.adjust');
    });

    // ===========================================
    // LEAVE CALENDAR (HR Global View)
    // ===========================================
    Route::prefix('leave-calendar')->name('leave-calendar.')->middleware('permission:leave-request.view')->group(function () {
        Route::get('/', [LeaveCalendarController::class, 'index'])->name('index');
        Route::get('/events', [LeaveCalendarController::class, 'events'])->name('events');
        Route::get('/stats', [LeaveCalendarController::class, 'stats'])->name('stats');
        Route::get('/on-leave-today', [LeaveCalendarController::class, 'onLeaveToday'])->name('on-leave-today');
        Route::get('/conflicts', [LeaveCalendarController::class, 'conflicts'])->name('conflicts');
        Route::get('/department-summary', [LeaveCalendarController::class, 'departmentSummary'])->name('department-summary');
        Route::get('/heatmap', [LeaveCalendarController::class, 'heatmap'])->name('heatmap');
    });

    // ===========================================
    // DISCIPLINARY MANAGEMENT
    // ===========================================
    Route::prefix('disciplinary')->name('disciplinary.')->group(function () {
        Route::get('/', [DisciplinaryQueryController::class, 'index'])
            ->middleware('permission:disciplinary.view')
            ->name('index');
        Route::get('/create', [DisciplinaryQueryController::class, 'create'])
            ->middleware('permission:disciplinary.create')
            ->name('create');
        Route::post('/', [DisciplinaryQueryController::class, 'store'])
            ->middleware('permission:disciplinary.create')
            ->name('store');
        Route::get('/{disciplinaryQuery}', [DisciplinaryQueryController::class, 'show'])
            ->middleware('permission:disciplinary.view')
            ->name('show');
        Route::get('/{disciplinaryQuery}/edit', [DisciplinaryQueryController::class, 'edit'])
            ->middleware('permission:disciplinary.edit')
            ->name('edit');
        Route::put('/{disciplinaryQuery}', [DisciplinaryQueryController::class, 'update'])
            ->middleware('permission:disciplinary.edit')
            ->name('update');
        Route::delete('/{disciplinaryQuery}', [DisciplinaryQueryController::class, 'destroy'])
            ->middleware('permission:disciplinary.delete')
            ->name('destroy');

        // Staff response
        Route::post('/{disciplinaryQuery}/respond', [DisciplinaryQueryController::class, 'respond'])
            ->middleware('permission:disciplinary.respond')
            ->name('respond');

        // HR decision
        Route::post('/{disciplinaryQuery}/decide', [DisciplinaryQueryController::class, 'decide'])
            ->middleware('permission:disciplinary.decide')
            ->name('decide');
    });

    // ===========================================
    // SUSPENSION MANAGEMENT
    // ===========================================
    Route::prefix('suspensions')->name('suspensions.')->group(function () {
        Route::get('/', [StaffSuspensionController::class, 'index'])
            ->middleware('permission:suspension.view')
            ->name('index');
        Route::get('/create', [StaffSuspensionController::class, 'create'])
            ->middleware('permission:suspension.create')
            ->name('create');
        Route::post('/', [StaffSuspensionController::class, 'store'])
            ->middleware('permission:suspension.create')
            ->name('store');
        Route::get('/{suspension}', [StaffSuspensionController::class, 'show'])
            ->middleware('permission:suspension.view')
            ->name('show');
        Route::post('/{suspension}/lift', [StaffSuspensionController::class, 'lift'])
            ->middleware('permission:suspension.lift')
            ->name('lift');
    });

    // ===========================================
    // TERMINATION MANAGEMENT
    // ===========================================
    Route::prefix('terminations')->name('terminations.')->group(function () {
        Route::get('/', [StaffTerminationController::class, 'index'])
            ->middleware('permission:termination.view')
            ->name('index');
        Route::get('/create', [StaffTerminationController::class, 'create'])
            ->middleware('permission:termination.create')
            ->name('create');
        Route::post('/', [StaffTerminationController::class, 'store'])
            ->middleware('permission:termination.create')
            ->name('store');
        Route::get('/{termination}', [StaffTerminationController::class, 'show'])
            ->middleware('permission:termination.view')
            ->name('show');
        Route::get('/{termination}/edit', [StaffTerminationController::class, 'edit'])
            ->middleware('permission:termination.edit')
            ->name('edit');
        Route::put('/{termination}', [StaffTerminationController::class, 'update'])
            ->middleware('permission:termination.edit')
            ->name('update');
    });

    // ===========================================
    // PAYROLL MANAGEMENT
    // ===========================================

    // Pay Heads
    Route::middleware(['permission:pay-head.view|pay-head.create'])->group(function () {
        Route::resource('pay-heads', PayHeadController::class);
    });

    // Salary Profiles
    Route::prefix('salary-profiles')->name('salary-profiles.')->group(function () {
        Route::get('/', [SalaryProfileController::class, 'index'])
            ->middleware('permission:salary-profile.view')
            ->name('index');
        Route::get('/create', [SalaryProfileController::class, 'create'])
            ->middleware('permission:salary-profile.create')
            ->name('create');
        Route::post('/', [SalaryProfileController::class, 'store'])
            ->middleware('permission:salary-profile.create')
            ->name('store');
        Route::get('/{salaryProfile}', [SalaryProfileController::class, 'show'])
            ->middleware('permission:salary-profile.view')
            ->name('show');
        Route::get('/{salaryProfile}/edit', [SalaryProfileController::class, 'edit'])
            ->middleware('permission:salary-profile.edit')
            ->name('edit');
        Route::put('/{salaryProfile}', [SalaryProfileController::class, 'update'])
            ->middleware('permission:salary-profile.edit')
            ->name('update');
        Route::delete('/{salaryProfile}', [SalaryProfileController::class, 'destroy'])
            ->middleware('permission:salary-profile.delete')
            ->name('destroy');
        Route::get('/staff/{staff}', [SalaryProfileController::class, 'staffProfiles'])
            ->middleware('permission:salary-profile.view')
            ->name('staff');
    });

    // Payroll Batches
    Route::prefix('payroll')->name('payroll.')->group(function () {
        Route::get('/', [PayrollBatchController::class, 'index'])
            ->middleware('permission:payroll-batch.view')
            ->name('index');
        Route::get('/create', [PayrollBatchController::class, 'create'])
            ->middleware('permission:payroll-batch.create')
            ->name('create');

        // Staff summary for batch creation (must be before {payrollBatch} routes)
        Route::get('/staff-summary', [PayrollBatchController::class, 'staffSummary'])
            ->middleware('permission:payroll-batch.create')
            ->name('staff-summary');
        Route::get('/staff-by-criteria', [PayrollBatchController::class, 'staffByCriteria'])
            ->middleware('permission:payroll-batch.create')
            ->name('staff-by-criteria');
        Route::post('/check-duplicates', [PayrollBatchController::class, 'checkDuplicates'])
            ->middleware('permission:payroll-batch.create')
            ->name('check-duplicates');

        Route::post('/', [PayrollBatchController::class, 'store'])
            ->middleware('permission:payroll-batch.create')
            ->name('store');
        Route::get('/{payrollBatch}', [PayrollBatchController::class, 'show'])
            ->middleware('permission:payroll-batch.view')
            ->name('show');
        Route::get('/{payrollBatch}/edit', [PayrollBatchController::class, 'edit'])
            ->middleware('permission:payroll-batch.edit')
            ->name('edit');
        Route::put('/{payrollBatch}', [PayrollBatchController::class, 'update'])
            ->middleware('permission:payroll-batch.edit')
            ->name('update');
        Route::delete('/{payrollBatch}', [PayrollBatchController::class, 'destroy'])
            ->middleware('permission:payroll-batch.delete')
            ->name('destroy');

        // Workflow
        Route::post('/{payrollBatch}/submit', [PayrollBatchController::class, 'submit'])
            ->middleware('permission:payroll-batch.submit')
            ->name('submit');
        Route::post('/{payrollBatch}/approve', [PayrollBatchController::class, 'approve'])
            ->middleware('permission:payroll-batch.approve')
            ->name('approve');
        Route::post('/{payrollBatch}/reject', [PayrollBatchController::class, 'reject'])
            ->middleware('permission:payroll-batch.reject')
            ->name('reject');
        Route::post('/{payrollBatch}/mark-paid', [PayrollBatchController::class, 'markPaid'])
            ->middleware('permission:payroll-batch.approve')
            ->name('mark-paid');
        Route::post('/{payrollBatch}/revert-draft', [PayrollBatchController::class, 'revertToDraft'])
            ->middleware('permission:payroll-batch.edit')
            ->name('revert-draft');

        // Batch operations
        Route::post('/{payrollBatch}/generate', [PayrollBatchController::class, 'generate'])
            ->middleware('permission:payroll-batch.create')
            ->name('generate');
        Route::get('/{payrollBatch}/payslips', [PayrollBatchController::class, 'payslips'])
            ->middleware('permission:payroll-batch.view')
            ->name('payslips');
        Route::get('/{payrollBatch}/payslip/{payrollItem}/print', [PayrollBatchController::class, 'printPayslip'])
            ->middleware('permission:payroll-batch.view')
            ->name('payslip.print');
        Route::get('/{payrollBatch}/export', [PayrollBatchController::class, 'export'])
            ->middleware('permission:payroll-batch.view')
            ->name('export');
    });

    // ===========================================
    // EMPLOYEE SELF SERVICE (ESS)
    // ===========================================
    Route::middleware(['permission:ess.access'])->prefix('ess')->name('ess.')->group(function () {
        Route::get('/', [EssController::class, 'index'])->name('index');

        // My Leave
        Route::get('/my-leave', [EssController::class, 'myLeave'])->name('my-leave');
        Route::get('/my-leave/request', [EssController::class, 'requestLeave'])->name('my-leave.request');
        Route::post('/my-leave/request', [EssController::class, 'storeLeaveRequest'])->name('my-leave.store');
        Route::post('/my-leave/{leaveRequest}/cancel', [EssController::class, 'cancelLeaveRequest'])->name('my-leave.cancel');

        // My Leave Calendar (Individual)
        Route::get('/my-calendar', [EssController::class, 'myCalendar'])->name('my-calendar');
        Route::get('/my-calendar/events', [EssController::class, 'myCalendarEvents'])->name('my-calendar.events');

        // My Payslips
        Route::get('/my-payslips', [EssController::class, 'myPayslips'])
            ->middleware('permission:ess.view-payslips')
            ->name('my-payslips');
        Route::get('/my-payslips/{payrollItem}/print', [EssController::class, 'printPayslip'])
            ->middleware('permission:ess.view-payslips')
            ->name('my-payslips.print');
        Route::get('/my-payslips/{payrollItem}/download', [EssController::class, 'downloadPayslip'])
            ->middleware('permission:ess.view-payslips')
            ->name('my-payslips.download');

        // My Disciplinary
        Route::get('/my-disciplinary', [EssController::class, 'myDisciplinary'])->name('my-disciplinary');
        Route::get('/my-disciplinary/{disciplinaryQuery}', [EssController::class, 'showDisciplinaryQuery'])->name('my-disciplinary.show');
        Route::post('/my-disciplinary/{disciplinaryQuery}/respond', [EssController::class, 'respondToDisciplinaryQuery'])->name('my-disciplinary.respond');

        // My Profile
        Route::get('/my-profile', [EssController::class, 'myProfile'])->name('my-profile');
        Route::put('/my-profile', [EssController::class, 'updateProfile'])->name('my-profile.update');
        Route::post('/my-profile/password', [EssController::class, 'updatePassword'])->name('my-profile.password');

        // Team Approvals (For Unit Heads / Dept Heads)
        Route::middleware(['permission:leave-request.supervisor-approve'])->prefix('team-approvals')->name('team-approvals.')->group(function () {
            Route::get('/', [EssController::class, 'teamApprovals'])->name('index');
            Route::get('/{leaveRequest}', [EssController::class, 'showTeamLeaveRequest'])->name('show');
            Route::post('/{leaveRequest}/approve', [EssController::class, 'approveTeamLeave'])->name('approve');
            Route::post('/{leaveRequest}/reject', [EssController::class, 'rejectTeamLeave'])->name('reject');
        });

        // Team Calendar (For Unit Heads / Dept Heads)
        Route::middleware(['permission:leave-request.supervisor-approve'])->prefix('team-calendar')->name('team-calendar.')->group(function () {
            Route::get('/', [EssController::class, 'teamCalendar'])->name('index');
            Route::get('/events', [EssController::class, 'teamCalendarEvents'])->name('events');
            Route::get('/on-leave', [EssController::class, 'teamOnLeave'])->name('on-leave');
        });
    });
});
