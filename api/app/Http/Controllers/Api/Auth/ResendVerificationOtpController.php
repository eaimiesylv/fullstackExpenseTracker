<?php

namespace App\Http\Controllers\Api\Auth;

use App\Enums\OtpPurpose;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResendVerificationOtpRequest;
use App\Models\User;
use App\Services\Auth\OtpService;
use App\Services\Email\AppMailService;
use Illuminate\Http\JsonResponse;

class ResendVerificationOtpController extends Controller
{
    public function __construct(protected AppMailService $appMailService, protected OtpService $otpService)
    {
    }

    public function __invoke(ResendVerificationOtpRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::query()->where('email', $validated['email'])->first();

        if (! $user) {
            return response()->json([
                'success' => true,
                'message' => 'If an account exists, a new verification code has been sent.',
            ]);
        }

        if (! is_null($user->email_verified_at)) {
            return response()->json([
                'success' => false,
                'message' => 'Email is already verified.',
            ], 422);
        }

        $otp = $this->otpService->generate($user, OtpPurpose::EMAIL_VERIFICATION);

        $this->appMailService->send('email_verification_otp', $user->email, [
            'user_data' => [
                'full_name' => $user->fullname,
                'email' => $user->email,
            ],
            'subject' => 'Verify your email address',
            'content1' => 'Your new verification code is below.',
            'content2' => $otp,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'A new verification code has been sent.',
        ]);
    }
}
