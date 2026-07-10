<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::name('api.v1.')->group(function (): void {
    Route::prefix('auth')->name('auth.')->controller(AuthController::class)->group(function (): void {
        Route::post('register', 'register')->name('register');
        Route::post('login', 'login')->name('login');
        Route::post('forgot-password', 'forgotPassword')->name('forgot-password');
        Route::post('reset-password', 'resetPassword')->name('reset-password');
        Route::post('verify-email', 'verifyEmail')->name('verify-email');
        Route::post('verification-code/resend', 'resendVerificationCode')->name('verification-code.resend');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::post('logout', 'logout')->name('logout');
        });
    });
});
