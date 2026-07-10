<?php

namespace App\Services;

use App\Enums\VerificationType;
use App\Models\User;
use App\Models\VerificationCode;
use App\Notifications\VerificationCodeNotification;
use InvalidArgumentException;

class VerificationNotificationService
{
    /**
     * Create a new verification notification service instance.
     */
    public function __construct() {}

    /**
     * Send the verification code through the channel required by its type.
     */
    public function send(User $user, VerificationCode $verificationCode): void
    {
        if ($this->shouldSendEmail($verificationCode->type)) {
            $this->sendEmail($user, $verificationCode);

            return;
        }

        throw new InvalidArgumentException('Unsupported verification notification channel.');
    }

    /**
     * Send the verification code by email.
     */
    public function sendEmail(User $user, VerificationCode $verificationCode): void
    {
        $user->notify(new VerificationCodeNotification(
            code: $verificationCode->code,
            type: $verificationCode->type,
            expiresAt: $verificationCode->expires_at,
        ));
    }

    /**
     * Determine whether the given verification type should be delivered by email.
     */
    private function shouldSendEmail(VerificationType $type): bool
    {
        return in_array($type, [
            VerificationType::EMAIL,
            VerificationType::PASSWORD_RESET,
            VerificationType::CHANGE_EMAIL,
        ], true);
    }
}
