<?php

use App\Http\Controllers\Install\InstallController;
use Illuminate\Support\Facades\Route;

Route::prefix('install')->name('install.')->group(function () {
    Route::get('/', [InstallController::class, 'welcome'])->name('welcome');
    Route::get('/checks', [InstallController::class, 'checks'])->name('checks');
    Route::post('/', [InstallController::class, 'store'])->middleware('throttle:5,1')->name('store');
    Route::get('/done', [InstallController::class, 'done'])->name('done');
});
