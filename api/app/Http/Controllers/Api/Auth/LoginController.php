<?php

namespace App\Http\Controllers\Api\Auth;

use App\Enums\OtpPurpose;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Auth\OtpService;
use App\Services\Email\AppMailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function __construct(protected AppMailService $appMailService, protected OtpService $otpService)
    {
    }

    public function __invoke(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::query()->where('email', $validated['email'])->first();

        if (! $user || ! Auth::guard('web')->attempt(['email' => $validated['email'], 'password' => $validated['password']])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials.',
            ], 401);
        }

        if (is_null($user->email_verified_at)) {
            $otp = $this->otpService->generate($user, OtpPurpose::EMAIL_VERIFICATION);

            $this->appMailService->send('email_verification_otp', $user->email, [
                'user_data' => [
                    'full_name' => $user->fullname,
                    'email' => $user->email,
                ],
                'subject' => 'Verify your account',
                'content1' => 'Please verify your account using the OTP sent to your email.',
                'content2' => $otp,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Verify your account using the OTP sent to ' . $user->email . '.',
            ], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'data' => [
                'user' => new UserResource($user),
                'token' => $token,
                'token_type' => 'Bearer',
            ],
        ]);
    }
}
