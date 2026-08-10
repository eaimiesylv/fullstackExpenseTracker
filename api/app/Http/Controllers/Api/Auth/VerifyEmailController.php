<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\VerifyEmailRequest;
use App\Models\EmailVerificationOtp;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class VerifyEmailController extends Controller
{
    public function __invoke(VerifyEmailRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::query()->where('email', $validated['email'])->first();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'We could not verify your email at this time.',
            ], 404);
        }

        if (! is_null($user->email_verified_at)) {
            return response()->json([
                'success' => false,
                'message' => 'Email is already verified.',
            ], 422);
        }

        return DB::transaction(function () use ($user, $validated): JsonResponse {
            $otpRecord = EmailVerificationOtp::query()
                ->where('user_id', $user->id)
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->orderByDesc('created_at')
                ->lockForUpdate()
                ->first();

            if (! $otpRecord) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active verification code found.',
                ], 422);
            }

            if (! Hash::check($validated['otp'], $otpRecord->otp)) {
                $otpRecord->increment('attempts');

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid verification code.',
                ], 422);
            }

            $otpRecord->update([
                'used_at' => now(),
                'attempts' => $otpRecord->attempts + 1,
            ]);

            EmailVerificationOtp::query()
                ->where('user_id', $user->id)
                ->where('id', '!=', $otpRecord->id)
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->update(['used_at' => now()]);

            $user->forceFill(['email_verified_at' => now()])->save();

            return response()->json([
                'success' => true,
                'message' => 'Email verified successfully.',
            ]);
        });
    }
}
