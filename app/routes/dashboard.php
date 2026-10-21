<?php

use App\Http\Controllers\Dashboard\AgendaController;
use App\Http\Controllers\Dashboard\AppointmentController;
use App\Http\Controllers\Dashboard\AssistantLogController;
use App\Http\Controllers\Dashboard\BillingSettingsController;
use App\Http\Controllers\Dashboard\DocumentController;
use App\Http\Controllers\Dashboard\LoginController;
use App\Http\Controllers\Dashboard\PatientController;
use App\Http\Controllers\Dashboard\PulseController;
use App\Http\Controllers\Dashboard\RequestController;
use App\Http\Controllers\Dashboard\SettingsController;
use App\Http\Controllers\Dashboard\TodayController;
use App\Http\Controllers\Dashboard\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'show'])->name('login');
    Route::post('login', [LoginController::class, 'store']);
});
Route::post('logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'active'])->prefix('dashboard')->name('dash.')->group(function () {
    Route::get('/', TodayController::class)->name('today');
    Route::get('pulse', PulseController::class)->name('pulse');

    Route::get('agenda', [AgendaController::class, 'index'])->name('agenda');
    Route::get('agenda/events', [AgendaController::class, 'events'])->name('agenda.events');

    Route::post('appointments', [AppointmentController::class, 'store'])->name('appointments.store');
    Route::patch('appointments/{appointment}/status', [AppointmentController::class, 'status'])->name('appointments.status');
    Route::patch('appointments/{appointment}/move', [AppointmentController::class, 'move'])->name('appointments.move');
    Route::patch('appointments/{appointment}/note', [AppointmentController::class, 'note'])->name('appointments.note')->can('medical');

    Route::get('demandes', [RequestController::class, 'index'])->name('requests');
    Route::post('traduction', [RequestController::class, 'translation'])->name('translation');
    Route::post('demandes/{assistantRequest}/{action}', [RequestController::class, 'act'])
        ->whereIn('action', ['confirm', 'decline', 'accept', 'done', 'faq'])->name('requests.act');

    Route::get('patients', [PatientController::class, 'index'])->name('patients');
    Route::post('patients', [PatientController::class, 'store'])->name('patients.store');
    Route::get('patients/search', [PatientController::class, 'search'])->name('patients.search');
    Route::get('patients/{patient}', [PatientController::class, 'show'])->name('patients.show');
    Route::put('patients/{patient}', [PatientController::class, 'update'])->name('patients.update');
    Route::post('patients/{patient}/teeth', [PatientController::class, 'tooth'])->name('patients.tooth')->can('medical');

    Route::get('assistant', [AssistantLogController::class, 'index'])->name('assistant');
    Route::post('assistant/messages/{message}/intent', [AssistantLogController::class, 'correct'])->name('assistant.correct');

    Route::get('factures', [DocumentController::class, 'index'])->name('documents');
    Route::post('factures', [DocumentController::class, 'create'])->name('documents.create');
    Route::get('factures/{document}', [DocumentController::class, 'show'])->name('documents.show');
    Route::get('factures/{document}/modifier', [DocumentController::class, 'edit'])->name('documents.edit');
    Route::put('factures/{document}', [DocumentController::class, 'update'])->name('documents.update');
    Route::delete('factures/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');
    Route::get('factures/{document}/imprimer', [DocumentController::class, 'print'])->name('documents.print');
    Route::post('factures/{document}/paiements', [DocumentController::class, 'pay'])->name('documents.pay');
    Route::post('factures/{document}/annuler', [DocumentController::class, 'cancel'])->name('documents.cancel');
    Route::post('factures/{document}/facturer', [DocumentController::class, 'convert'])->name('documents.convert');

    Route::middleware('can:admin')->group(function () {
        Route::get('facturation', [BillingSettingsController::class, 'edit'])->name('billing');
        Route::put('facturation/en-tete', [BillingSettingsController::class, 'letterhead'])->name('billing.letterhead');
        Route::put('facturation/tarifs', [BillingSettingsController::class, 'acts'])->name('billing.acts');
        Route::get('facturation/apercu', [BillingSettingsController::class, 'preview'])->name('billing.preview');

        Route::get('equipe', [UserController::class, 'index'])->name('users');
        Route::post('equipe', [UserController::class, 'store'])->name('users.store');
        Route::put('equipe/{user}', [UserController::class, 'update'])->name('users.update');
        Route::patch('equipe/{user}/actif', [UserController::class, 'toggle'])->name('users.toggle');
        Route::delete('equipe/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });

    Route::get('cabinet', [SettingsController::class, 'index'])->name('settings');
    Route::put('cabinet/hours', [SettingsController::class, 'hours'])->name('settings.hours');
    Route::post('cabinet/time-offs', [SettingsController::class, 'addTimeOff'])->name('settings.timeoff');
    Route::delete('cabinet/time-offs/{timeOff}', [SettingsController::class, 'removeTimeOff'])->name('settings.timeoff.remove');
    Route::put('cabinet/services', [SettingsController::class, 'services'])->name('settings.services');
});
