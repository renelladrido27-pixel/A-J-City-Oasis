<?php

namespace App\Services;

use App\Mail\EmailVerificationCodeMail;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Account verification by a 6-digit code emailed at sign-up. A code (rather
 * than a link) works identically on the website and in the mobile app: the
 * tenant just types it in.
 */
class EmailVerificationService
{
    public const EXPIRES_MINUTES = 15;

    public function __construct(protected MailService $mail) {}

    public function sendCode(User $user): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Stored hashed, like a password — a database leak doesn't reveal usable codes.
        $user->forceFill([
            'email_verification_code' => Hash::make($code),
            'email_verification_expires_at' => now()->addMinutes(self::EXPIRES_MINUTES),
        ])->save();

        $this->mail->send($user->email, new EmailVerificationCodeMail($user, $code));
    }

    /**
     * @return 'verified'|'expired'|'invalid'
     */
    public function verify(User $user, string $code): string
    {
        if ($user->hasVerifiedEmail()) {
            return 'verified';
        }

        if (! $user->email_verification_code || ! $user->email_verification_expires_at?->isFuture()) {
            return 'expired';
        }

        if (! Hash::check(trim($code), $user->email_verification_code)) {
            return 'invalid';
        }

        $user->forceFill([
            'email_verified_at' => now(),
            'email_verification_code' => null,
            'email_verification_expires_at' => null,
        ])->save();

        return 'verified';
    }
}
