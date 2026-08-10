<?php

use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\Auth\ResendEmailOtpController;
use App\Http\Controllers\Api\Auth\VerifyEmailController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', RegisterController::class);
Route::post('/auth/verify-email', VerifyEmailController::class)->middleware('throttle:10,1');
Route::post('/auth/resend-email-otp', ResendEmailOtpController::class)->middleware('throttle:6,1');

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
