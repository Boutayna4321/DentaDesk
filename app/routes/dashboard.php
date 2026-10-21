<?php

use App\Http\Controllers\Dashboard\AgendaController;
use App\Http\Controllers\Dashboard\AppointmentController;
use App\Http\Controllers\Dashboard\AssistantLogController;
use App\Http\Controllers\Dashboard\LoginController;
use App\Http\Controllers\Dashboard\PatientController;
use App\Http\Controllers\Dashboard\RequestController;
use App\Http\Controllers\Dashboard\SettingsController;
use App\Http\Controllers\Dashboard\TodayController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'show'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->middleware('throttle:5,1');
});
Route::post('logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware('auth')->prefix('dashboard')->name('dash.')->group(function () {
    Route::get('/', TodayController::class)->name('today');

    Route::get('agenda', [AgendaController::class, 'index'])->name('agenda');
    Route::get('agenda/events', [AgendaController::class, 'events'])->name('agenda.events');

    Route::post('appointments', [AppointmentController::class, 'store'])->name('appointments.store');
    Route::patch('appointments/{appointment}/status', [AppointmentController::class, 'status'])->name('appointments.status');
    Route::patch('appointments/{appointment}/move', [AppointmentController::class, 'move'])->name('appointments.move');
    Route::patch('appointments/{appointment}/note', [AppointmentController::class, 'note'])->name('appointments.note')->can('medical');

    Route::get('demandes', [RequestController::class, 'index'])->name('requests');
    Route::post('demandes/{assistantRequest}/{action}', [RequestController::class, 'act'])
        ->whereIn('action', ['confirm', 'decline', 'accept', 'done', 'faq'])->name('requests.act');

    Route::get('patients', [PatientController::class, 'index'])->name('patients');
    Route::get('patients/search', [PatientController::class, 'search'])->name('patients.search');
    Route::get('patients/{patient}', [PatientController::class, 'show'])->name('patients.show');
    Route::put('patients/{patient}', [PatientController::class, 'update'])->name('patients.update');
    Route::post('patients/{patient}/teeth', [PatientController::class, 'tooth'])->name('patients.tooth')->can('medical');

    Route::get('assistant', [AssistantLogController::class, 'index'])->name('assistant');
    Route::post('assistant/messages/{message}/intent', [AssistantLogController::class, 'correct'])->name('assistant.correct');

    Route::get('cabinet', [SettingsController::class, 'index'])->name('settings');
    Route::put('cabinet/hours', [SettingsController::class, 'hours'])->name('settings.hours');
    Route::post('cabinet/time-offs', [SettingsController::class, 'addTimeOff'])->name('settings.timeoff');
    Route::delete('cabinet/time-offs/{timeOff}', [SettingsController::class, 'removeTimeOff'])->name('settings.timeoff.remove');
    Route::put('cabinet/services', [SettingsController::class, 'services'])->name('settings.services');
});
