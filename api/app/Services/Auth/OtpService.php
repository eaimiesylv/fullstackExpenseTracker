<?php

namespace App\Services\Auth;

use App\Enums\OtpPurpose;
use App\Models\EmailVerificationOtp;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class OtpService
{
    public function generate(User $user, OtpPurpose|string $purpose, int $expirationMinutes = 10): string
    {
        $purposeValue = $purpose instanceof OtpPurpose ? $purpose->value : $purpose;

        $this->invalidate($user, $purposeValue);

        $otp = (string) random_int(100000, 999999);

        EmailVerificationOtp::create([
            'user_id' => $user->id,
            'purpose' => $purposeValue,
            'otp' => Hash::make($otp),
            'expires_at' => now()->addMinutes($expirationMinutes),
            'attempts' => 0,
        ]);

        return $otp;
    }

    public function verify(User $user, OtpPurpose|string $purpose, string $otp): bool
    {
        $purposeValue = $purpose instanceof OtpPurpose ? $purpose->value : $purpose;

        $otpRecord = EmailVerificationOtp::query()
            ->where('user_id', $user->id)
            ->where('purpose', $purposeValue)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->lockForUpdate()
            ->first();

        if (! $otpRecord) {
            return false;
        }

        if ($otpRecord->attempts >= $this->maxAttempts()) {
            $otpRecord->update(['used_at' => now()]);

            return false;
        }

        if (! Hash::check($otp, $otpRecord->otp)) {
            $otpRecord->increment('attempts');

            return false;
        }

        $otpRecord->update([
            'used_at' => now(),
            'attempts' => $otpRecord->attempts + 1,
        ]);

        EmailVerificationOtp::query()
            ->where('user_id', $user->id)
            ->where('purpose', $purposeValue)
            ->where('id', '!=', $otpRecord->id)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->update(['used_at' => now()]);

        return true;
    }

    public function invalidate(User $user, OtpPurpose|string $purpose): void
    {
        $purposeValue = $purpose instanceof OtpPurpose ? $purpose->value : $purpose;

        EmailVerificationOtp::query()
            ->where('user_id', $user->id)
            ->where('purpose', $purposeValue)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->update(['used_at' => now()]);
    }

    protected function maxAttempts(): int
    {
        return (int) config('auth.otp.max_attempts', 5);
    }
}
