<?php

use App\Http\Controllers\Api\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\LogoutController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\Auth\ResendPasswordResetOtpController;
use App\Http\Controllers\Api\Auth\ResendVerificationOtpController;
use App\Http\Controllers\Api\Auth\ResetPasswordController;
use App\Http\Controllers\Api\Auth\VerifyEmailController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\GroupController;

Route::post('/auth/register', RegisterController::class);
Route::post('/auth/login', LoginController::class);
Route::post('/auth/verify-email', VerifyEmailController::class)->middleware('throttle:10,1');
Route::post('/auth/resend-verification-otp', ResendVerificationOtpController::class)->middleware('throttle:6,1');
Route::post('/auth/forgot-password', ForgotPasswordController::class)->middleware('throttle:6,1');
Route::post('/auth/resend-password-reset-otp', ResendPasswordResetOtpController::class)->middleware('throttle:6,1');
Route::post('/auth/reset-password', ResetPasswordController::class)->middleware('throttle:10,1');
Route::post('/auth/logout', LogoutController::class)->middleware(['auth:sanctum']);

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::group(['prefix' => 'groups', 'middleware' => ['auth:sanctum']], function () {
    Route::post('/', [GroupController::class, 'store']);
    Route::get('/', [GroupController::class, 'index']);
    Route::get('/{id}', [GroupController::class, 'show']);
    Route::put('/{id}', [GroupController::class, 'update']);
    Route::delete('/{id}', [GroupController::class, 'destroy']);
});
