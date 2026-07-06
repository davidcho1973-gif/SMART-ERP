<?php

use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\PayrollExportController;
use App\Http\Controllers\TimesheetExportController;
use App\Livewire\ScanClock;
use App\Livewire\WorkforceApp;
use Illuminate\Support\Facades\Route;

Route::get('/', WorkforceApp::class);

// Daily attendance timesheet → .xlsx download
Route::get('/export/timesheet', TimesheetExportController::class);

// Bi-weekly payroll register → .xlsx download (period + recipient chosen in the UI)
Route::get('/export/payroll', PayrollExportController::class);

Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect']);
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback']);

// Opened when a worker scans a posted crew/site QR with their phone.
Route::get('/scan/{team}', ScanClock::class)->middleware('auth');
