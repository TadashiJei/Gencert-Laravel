<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InstallController;

Route::group(['middleware' => ['web']], function () {
    Route::get('/install', [InstallController::class, 'index'])->name('install.index');
    Route::post('/install/setup', [InstallController::class, 'setup'])->name('install.setup');
});
