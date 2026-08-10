<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Enums\OtpPurpose;
use App\Models\User;
use App\Services\Auth\OtpService;
use App\Services\Email\AppMailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class RegisterController extends Controller
{
    public function __construct(protected AppMailService $appMailService, protected OtpService $otpService)
    {
    }

    public function __invoke(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::create([
            'fullname' => $validated['fullname'],
            'email' => $validated['email'] ?? null,
            'phone_number' => $validated['phone_number'] ?? null,
            'password' => Hash::make($validated['password']),
        ]);

        if (!empty($user->email)) {
            $otp = $this->otpService->generate($user, OtpPurpose::EMAIL_VERIFICATION);

            $this->appMailService->send('email_verification_otp', $user->email, [
                'user_data' => [
                    'full_name' => $user->fullname,
                    'email'     => $user->email,
                ],

                'subject' => 'Verify your email address',

                'content1' => '
                    <p>Welcome to Gochacha!</p>

                    <p>
                        Thank you for creating an account with us.
                        Please use the verification code below to verify your email address.
                    </p>
                ',

                'content2' => '
                    <div style="margin: 24px 0; text-align: center;">
                        <div style="
                            display: inline-block;
                            padding: 16px 28px;
                            background: #f6f7fb;
                            border: 1px solid #e5e7eb;
                            border-radius: 10px;
                            font-size: 32px;
                            font-weight: 700;
                            letter-spacing: 8px;
                            color: #111827;
                        ">
                            ' . e($otp) . '
                        </div>
                    </div>

                    <p>
                        This verification code will expire in <strong>10 minutes</strong>
                        and can only be used once.
                    </p>

                    <p>
                        If you did not create this account, you can safely ignore this email.
                    </p>
                ',

                'frontend_url' => '',
                'btn_label'    => '',
            ]);


        }

        return response()->json([
            'success' => true,
            'message' => 'Registration successful.',
            'data' => [
                'user' => new UserResource($user),
                
            ],
        ], 201);
    }
}
