<?php

namespace App\Http\Controllers\Api\Auth;

use App\Enums\OtpPurpose;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Services\Auth\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class ResetPasswordController extends Controller
{
    public function __construct(protected OtpService $otpService)
    {
    }

    public function __invoke(ResetPasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::query()->where('email', $validated['email'])->first();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'We could not reset the password at this time.',
            ], 404);
        }

        $isValid = $this->otpService->verify($user, OtpPurpose::PASSWORD_RESET, $validated['otp']);

        if (! $isValid) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired reset code.',
            ], 422);
        }

        $user->forceFill(['password' => Hash::make($validated['password'])])->save();

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully.',
        ]);
    }
}
