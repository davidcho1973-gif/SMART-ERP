<?php

use App\Http\Controllers\GoogleAuthController;
use App\Livewire\WorkforceApp;
use Illuminate\Support\Facades\Route;

Route::get('/', WorkforceApp::class);

Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect']);
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback']);
