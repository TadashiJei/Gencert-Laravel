<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CertificateController;
use App\Http\Controllers\Api\V1\TemplateController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// API Version 1
Route::prefix('v1')->group(function () {
    // Authentication routes
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('tokens', [AuthController::class, 'createToken']);
        Route::delete('tokens', [AuthController::class, 'destroyToken']);
        Route::get('tokens', [AuthController::class, 'listTokens']);
        Route::delete('tokens/{token}', [AuthController::class, 'revokeToken']);
        Route::delete('tokens', [AuthController::class, 'revokeAllTokens']);
    });

    // Protected routes
    Route::middleware(['auth.api', 'api.rate.limit:60,1'])->group(function () {
        // Templates
        Route::get('templates', [TemplateController::class, 'index'])->middleware('auth.api:view-templates');
        Route::post('templates', [TemplateController::class, 'store'])->middleware('auth.api:create-templates');
        Route::get('templates/{template}', [TemplateController::class, 'show'])->middleware('auth.api:view-templates');
        Route::put('templates/{template}', [TemplateController::class, 'update'])->middleware('auth.api:edit-templates');
        Route::delete('templates/{template}', [TemplateController::class, 'destroy'])->middleware('auth.api:delete-templates');

        // Certificates
        Route::get('certificates', [CertificateController::class, 'index'])->middleware('auth.api:view-certificates');
        Route::post('certificates', [CertificateController::class, 'store'])->middleware('auth.api:create-certificates');
        Route::get('certificates/{certificate}', [CertificateController::class, 'show'])->middleware('auth.api:view-certificates');
        Route::delete('certificates/{certificate}', [CertificateController::class, 'destroy'])->middleware('auth.api:delete-certificates');
        Route::get('certificates/{certificate}/download', [CertificateController::class, 'download'])->middleware('auth.api:download-certificates');
    });
});
