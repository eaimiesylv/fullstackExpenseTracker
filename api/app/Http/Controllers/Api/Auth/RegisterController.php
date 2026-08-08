<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\Invite;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RegisterController extends Controller
{
    public function __invoke(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        return DB::transaction(function () use ($validated) {
            $invite = null;

            if (! empty($validated['invite_token'])) {
                $invite = Invite::where('token', $validated['invite_token'])
                    ->where('status', 'pending')
                    ->where('expires_at', '>', now())
                    ->first();

                if (! $invite || ! $invite->groupMember || $invite->groupMember->user_id !== null) {
                    return response()->json([
                        'success' => false,
                        'message' => 'The invitation is invalid or has expired.',
                    ], 422);
                }
            }

            $user = User::create([
                'fullname' => $validated['fullname'],
                'email' => $validated['email'] ?? null,
                'phone_number' => $validated['phone_number'] ?? null,
                'password' => Hash::make($validated['password']),
                'status' => 'active',
            ]);

            if ($invite) {
                $invite->groupMember->update(['user_id' => $user->id]);
                $invite->update([
                    'status' => 'accepted',
                    'accepted_at' => now(),
                ]);
            }

            $token = $user->createToken('api-token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Registration successful.',
                'data' => [
                    'user' => new UserResource($user),
                    'token' => $token,
                    'token_type' => 'Bearer',
                ],
            ], 201);
        });
    }
}
