<?php

use App\Http\Controllers\Api\AssistantController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\SiteController;
use Illuminate\Support\Facades\Route;

Route::get('/', SiteController::class)->name('home');

Route::prefix('api')->middleware('throttle:public-api')->group(function () {
    Route::get('services', [BookingController::class, 'services']);
    Route::get('days', [BookingController::class, 'days']);
    Route::get('slots', [BookingController::class, 'slots']);
    Route::post('appointments', [BookingController::class, 'store'])->middleware('throttle:booking');
    Route::post('assistant', AssistantController::class)->middleware('throttle:assistant');
});

require __DIR__.'/dashboard.php';
