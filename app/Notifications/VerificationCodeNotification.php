<?php

namespace App\Notifications;

use App\Enums\VerificationType;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class VerificationCodeNotification extends Notification
{
    use Queueable;

    /**
     * Create a new verification code notification instance.
     */
    public function __construct(
        private readonly string $code,
        private readonly VerificationType $type,
        private readonly Carbon $expiresAt,
    ) {}

    /**
     * Get the notification delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Build the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->subject())
            ->line(__('messages.verification_email_intro'))
            ->line(__('messages.verification_email_code', ['code' => $this->code]))
            ->line(__('messages.verification_email_expires_at', [
                'expires_at' => $this->expiresAt->toDateTimeString(),
            ]));
    }

    /**
     * Get the email subject for the verification type.
     */
    private function subject(): string
    {
        return match ($this->type) {
            VerificationType::PASSWORD_RESET => __('messages.password_reset_email_subject'),
            default => __('messages.verification_email_subject'),
        };
    }
}
