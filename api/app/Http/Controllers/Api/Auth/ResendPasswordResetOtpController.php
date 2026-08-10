<?php

namespace App\Http\Controllers\Api\Auth;

use App\Enums\OtpPurpose;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResendPasswordResetOtpRequest;
use App\Models\User;
use App\Services\Auth\OtpService;
use App\Services\Email\AppMailService;
use Illuminate\Http\JsonResponse;

class ResendPasswordResetOtpController extends Controller
{
    public function __construct(protected AppMailService $appMailService, protected OtpService $otpService)
    {
    }

    public function __invoke(ResendPasswordResetOtpRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::query()->where('email', $validated['email'])->first();

        if (! $user) {
            return response()->json([
                'success' => true,
                'message' => 'If an account exists, a new password reset code has been sent.',
            ]);
        }

        $otp = $this->otpService->generate($user, OtpPurpose::PASSWORD_RESET);

        $this->appMailService->send('password_reset_otp', $user->email, [
            'user_data' => [
                'full_name' => $user->fullname,
                'email' => $user->email,
            ],
            'subject' => 'Reset your password',
            'content1' => 'Your new password reset code is below.',
            'content2' => $otp,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'A new password reset code has been sent.',
        ]);
    }
}
