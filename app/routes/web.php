<?php

use App\Http\Controllers\Api\AssistantController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\SiteController;
use Illuminate\Support\Facades\Route;

Route::get('/', SiteController::class)->name('home');

Route::prefix('api')->middleware('throttle:60,1')->group(function () {
    Route::get('services', [BookingController::class, 'services']);
    Route::get('days', [BookingController::class, 'days']);
    Route::get('slots', [BookingController::class, 'slots']);
    Route::post('appointments', [BookingController::class, 'store'])->middleware('throttle:6,1');
    Route::post('assistant', AssistantController::class)->middleware('throttle:30,1');
});

require __DIR__.'/dashboard.php';
