<?php

use App\Http\Controllers\Api\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\Auth\ResendPasswordResetOtpController;
use App\Http\Controllers\Api\Auth\ResendVerificationOtpController;
use App\Http\Controllers\Api\Auth\ResetPasswordController;
use App\Http\Controllers\Api\Auth\VerifyEmailController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', RegisterController::class);
Route::post('/auth/login', LoginController::class);
Route::post('/auth/verify-email', VerifyEmailController::class)->middleware('throttle:10,1');
Route::post('/auth/resend-verification-otp', ResendVerificationOtpController::class)->middleware('throttle:6,1');
Route::post('/auth/forgot-password', ForgotPasswordController::class)->middleware('throttle:6,1');
Route::post('/auth/resend-password-reset-otp', ResendPasswordResetOtpController::class)->middleware('throttle:6,1');
Route::post('/auth/reset-password', ResetPasswordController::class)->middleware('throttle:10,1');

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
