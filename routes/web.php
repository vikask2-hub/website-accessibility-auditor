<?php

use App\Http\Controllers\AccessibilityAuditController;
use Illuminate\Support\Facades\Route;

Route::redirect('/portfolio', 'https://tech4projects.online/')->name('portfolio');
Route::redirect('/', '/accessibility-auditor');

Route::controller(AccessibilityAuditController::class)->prefix('accessibility-auditor')->name('accessibility-auditor.')->group(function (): void {
    Route::get('/', 'index')->name('index');
    Route::post('/audits', 'store')->middleware('throttle:8,1')->name('audits.store');
    Route::post('/audits/{audit}/rerun', 'rerun')->middleware('throttle:8,1')->name('audits.rerun');
    Route::delete('/audits/{audit}', 'destroy')->name('audits.destroy');
});
