<?php

use App\Http\Controllers\Auth\OtpController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\Public\ClinicController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\SearchController;
use Illuminate\Support\Facades\Route;

// Language switcher
Route::get('/lang/{locale}', [LanguageController::class, 'switch'])
    ->whereIn('locale', ['ar', 'en'])
    ->name('lang.switch');

// Public website
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/search', [SearchController::class, 'index'])->name('search');
Route::get('/clinic/{slug}', [ClinicController::class, 'show'])->name('clinic.show');
Route::post('/clinic/{slug}/book', [ClinicController::class, 'book'])->name('clinic.book');

// Customer OTP auth
Route::middleware('guest:web')->group(function () {
    Route::get('/login', [OtpController::class, 'showLogin'])->name('login');
    Route::post('/login/send-otp', [OtpController::class, 'sendOtp'])->name('login.otp');
    Route::post('/login/verify', [OtpController::class, 'verifyOtp'])->name('login.verify');
});
Route::post('/logout', [OtpController::class, 'logout'])->name('logout');
