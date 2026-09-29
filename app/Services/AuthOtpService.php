<?php

namespace App\Services;

use App\Models\AuthOtp;
use App\Notifications\AuthOtpNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthOtpService
{
    public const REGISTRATION = 'registration';
    public const PASSWORD_RESET = 'password_reset';
    public const OTP_TTL_MINUTES = 10;
    public const RESET_TOKEN_TTL_MINUTES = 15;
    public const MAX_ATTEMPTS = 5;

    public function send(string $email, string $purpose): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        AuthOtp::query()->updateOrCreate(
            ['email' => $email, 'purpose' => $purpose],
            [
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addMinutes(self::OTP_TTL_MINUTES),
                'attempts' => 0,
                'last_sent_at' => now(),
                'reset_token_hash' => null,
                'reset_token_expires_at' => null,
            ],
        );

        Notification::route('mail', $email)->notify(
            new AuthOtpNotification($code, $purpose, self::OTP_TTL_MINUTES),
        );
    }

    public function verify(string $email, string $purpose, string $code): AuthOtp
    {
        $otp = AuthOtp::query()
            ->where('email', $email)
            ->where('purpose', $purpose)
            ->first();

        if ($otp === null || $otp->code_hash === null || $otp->expires_at === null) {
            $this->invalidCode();
        }

        if ($otp->expires_at->isPast()) {
            $otp->delete();
            $this->invalidCode('The verification code has expired. Request a new code.');
        }

        if ($otp->attempts >= self::MAX_ATTEMPTS) {
            $this->invalidCode('Too many incorrect attempts. Request a new code.');
        }

        $otp->increment('attempts');

        if (! Hash::check($code, $otp->code_hash)) {
            $this->invalidCode();
        }

        return $otp->refresh();
    }

    public function issueResetToken(AuthOtp $otp): string
    {
        $plainToken = Str::random(64);

        $otp->forceFill([
            'code_hash' => null,
            'reset_token_hash' => Hash::make($plainToken),
            'reset_token_expires_at' => now()->addMinutes(self::RESET_TOKEN_TTL_MINUTES),
        ])->save();

        return $plainToken;
    }

    public function consumeResetToken(string $email, string $plainToken): AuthOtp
    {
        $otp = AuthOtp::query()
            ->where('email', $email)
            ->where('purpose', self::PASSWORD_RESET)
            ->first();

        if (
            $otp === null ||
            $otp->reset_token_hash === null ||
            $otp->reset_token_expires_at === null ||
            $otp->reset_token_expires_at->isPast() ||
            ! Hash::check($plainToken, $otp->reset_token_hash)
        ) {
            throw ValidationException::withMessages([
                'reset_token' => ['The password reset session is invalid or has expired.'],
            ]);
        }

        return $otp;
    }

    public function clear(string $email, string $purpose): void
    {
        AuthOtp::query()
            ->where('email', $email)
            ->where('purpose', $purpose)
            ->delete();
    }

    private function invalidCode(string $message = 'The verification code is invalid.'): never
    {
        throw ValidationException::withMessages([
            'otp' => [$message],
        ]);
    }
}
