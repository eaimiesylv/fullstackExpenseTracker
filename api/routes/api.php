<?php

use App\Http\Controllers\Api\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\LogoutController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\Auth\ResendPasswordResetOtpController;
use App\Http\Controllers\Api\Auth\ResendVerificationOtpController;
use App\Http\Controllers\Api\Auth\ResetPasswordController;
use App\Http\Controllers\Api\Auth\VerifyEmailController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\GroupController;
use App\Http\Controllers\Api\GroupMemberController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\NeedController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

Route::group(['middleware' => ['auth:sanctum']], function () {
    // User search for group members
    Route::get('/users/search', [GroupMemberController::class, 'searchUsers']);

    // Groups
    Route::group(['prefix' => 'groups'], function () {
        Route::get('/permissions', [GroupController::class, 'getPermissions']);
        Route::get('/list', [GroupController::class, 'list']);
        Route::get('/', [GroupController::class, 'index']);
        Route::post('/', [GroupController::class, 'store']);
        Route::get('/{id}', [GroupController::class, 'show']);
        Route::put('/{id}', [GroupController::class, 'update']);
        Route::delete('/{id}', [GroupController::class, 'destroy']);

        // Members
        Route::get('/{groupId}/members', [GroupMemberController::class, 'index']);
        Route::post('/{groupId}/members', [GroupMemberController::class, 'store']);
        Route::put('/{groupId}/members/{memberId}', [GroupMemberController::class, 'update']);
        Route::delete('/{groupId}/members/{memberId}', [GroupMemberController::class, 'destroy']);
    });

    // Categories
    Route::group(['prefix' => 'categories'], function () {
        Route::get('/all', [CategoryController::class, 'all']);
        Route::get('/', [CategoryController::class, 'index']);
        Route::post('/', [CategoryController::class, 'store']);
        Route::put('/{id}', [CategoryController::class, 'update']);
    });

    // Items
    Route::group(['prefix' => 'items'], function () {
        Route::get('/', [ItemController::class, 'index']);
        Route::post('/', [ItemController::class, 'store']);
    });

    // Needs
    Route::group(['prefix' => 'needs'], function () {
        Route::get('/', [NeedController::class, 'index']);
        Route::post('/', [NeedController::class, 'store']);
        Route::get('/{id}', [NeedController::class, 'show']);
        Route::put('/{id}', [NeedController::class, 'update']);
        Route::delete('/{id}', [NeedController::class, 'destroy']);
    });
});
