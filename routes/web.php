<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\EmailTrackingController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\AuditLogController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth'])->group(function () {
    Route::resource('templates', TemplateController::class);
    Route::resource('certificates', CertificateController::class)->except(['edit', 'update']);
    Route::post('certificates/bulk', [CertificateController::class, 'bulkGenerate'])->name('certificates.bulk');
    Route::post('certificates/preview', [CertificateController::class, 'preview'])->name('certificates.preview');
    Route::get('certificates/{certificate}/download', [CertificateController::class, 'download'])->name('certificates.download');
    Route::get('analytics', [AnalyticsController::class, 'index'])->name('analytics');
    
    // Email tracking routes
    Route::post('certificates/{certificate}/resend-email', [EmailTrackingController::class, 'resend'])
        ->name('certificates.resend-email');
    Route::get('certificates/{certificate}/email-stats', [EmailTrackingController::class, 'stats'])
        ->name('certificates.email-stats');
});

// Email tracking routes (public)
Route::get('email/track/open/{tracking_id}', [EmailTrackingController::class, 'trackOpen'])
    ->name('email.track.open')
    ->middleware('signed');

Route::get('email/track/click/{tracking_id}', [EmailTrackingController::class, 'trackClick'])
    ->name('email.track.click')
    ->middleware('signed');

// Admin routes
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    
    // User management
    Route::resource('users', UserController::class);
    Route::post('users/{user}/impersonate', [UserController::class, 'impersonate'])->name('users.impersonate');
    Route::post('users/stop-impersonating', [UserController::class, 'stopImpersonating'])->name('users.stop-impersonating');
    
    // Settings
    Route::resource('settings', SettingController::class)->except(['edit', 'show']);
    Route::post('settings', [SettingController::class, 'update'])->name('settings.update');
    
    // Audit logs
    Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    Route::get('audit-logs/{auditLog}', [AuditLogController::class, 'show'])->name('audit-logs.show');
    Route::get('audit-logs/export', [AuditLogController::class, 'export'])->name('audit-logs.export');
});

require __DIR__.'/auth.php';
