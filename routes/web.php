<?php

use App\Http\Controllers\CommsFileController;
use App\Http\Controllers\ExpenseReceiptController;
use App\Http\Controllers\EquipmentPhotoController;
use App\Http\Controllers\MaterialSlipController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\PayrollExportController;
use App\Http\Controllers\TimesheetExportController;
use App\Livewire\EquipScan;
use App\Livewire\JoinForm;
use App\Livewire\ScanClock;
use App\Livewire\SignupPoster;
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

// Opened when someone scans a piece of equipment's printed QR (check-out / in).
Route::get('/e/{token}', EquipScan::class)->middleware('auth');

// Internal-comms attachment — streamed only to members of the channel.
Route::get('/comms/file/{message}', CommsFileController::class)->middleware('auth');

// Expense receipt image — streamed only to accounting/approver roles.
Route::get('/accounting/receipt/{expense}', ExpenseReceiptController::class)->middleware('auth');

// Materials slip image — streamed only to materials/accounting roles.
Route::get('/accounting/slip/{batch}', MaterialSlipController::class)->middleware('auth');

// Equipment photo — streamed only to equipment roles.
Route::get('/accounting/equip-photo/{photo}', EquipmentPhotoController::class)->middleware('auth');

// Public self-service sign-up opened from a printed site QR (no auth).
Route::get('/join/{token}', JoinForm::class);

// Printable sign-up QR poster for a site (admins print & post it on-site).
Route::get('/join/{token}/poster', SignupPoster::class)->middleware('auth');
