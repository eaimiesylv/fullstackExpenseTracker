<?php

namespace App\Http\Controllers\Api\Auth;

use App\Enums\OtpPurpose;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\VerifyEmailRequest;
use App\Models\User;
use App\Services\Auth\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\UserResource;

class VerifyEmailController extends Controller
{
    public function __construct(protected OtpService $otpService)
    {
    }

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
            $isValid = $this->otpService->verify($user, OtpPurpose::EMAIL_VERIFICATION, $validated['otp']);

            if (! $isValid) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired verification code.',
                ], 422);
            }

            $user->forceFill(['email_verified_at' => now()])->save();
             $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Email verified successfully.',
                'data' => [
                'user' => new UserResource($user),
                'token' => $token,
                'token_type' => 'Bearer',
            ],
            ]);
        });
    }
}
