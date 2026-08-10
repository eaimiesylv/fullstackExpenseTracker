<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResendEmailOtpRequest;
use App\Models\EmailVerificationOtp;
use App\Models\User;
use App\Services\Email\AppMailService;
use Illuminate\Http\JsonResponse;

class ResendEmailOtpController extends Controller
{
    public function __construct(protected AppMailService $appMailService)
    {
    }

    public function __invoke(ResendEmailOtpRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::query()->where('email', $validated['email'])->first();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'We could not process your request at this time.',
            ], 404);
        }

        if (! is_null($user->email_verified_at)) {
            return response()->json([
                'success' => false,
                'message' => 'Email is already verified.',
            ], 422);
        }

        EmailVerificationOtp::query()
            ->where('user_id', $user->id)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->update(['used_at' => now()]);

        $otp = (string) random_int(100000, 999999);

        EmailVerificationOtp::create([
            'user_id' => $user->id,
            'otp' => \Illuminate\Support\Facades\Hash::make($otp),
            'expires_at' => now()->addMinutes(10),
            'attempts' => 0,
        ]);

        $this->appMailService->send('email_verification_otp', $user->email, [
            'user_data' => [
                'full_name' => $user->fullname,
                'email' => $user->email,
            ],
            'subject' => 'Verify your email address',
            'content1' => 'Your email verification code is:',
            'content2' => $otp,
            'frontend_url' => '',
            'btn_label' => '',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'A new verification code has been sent.',
        ]);
    }
}
