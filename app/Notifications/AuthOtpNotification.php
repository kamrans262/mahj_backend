<?php

namespace App\Notifications;

use App\Services\AuthOtpService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AuthOtpNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $code,
        public readonly string $purpose,
        public readonly int $expiresInMinutes,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $isRegistration = $this->purpose === AuthOtpService::REGISTRATION;
        $action = $isRegistration ? 'complete your registration' : 'reset your password';

        return (new MailMessage)
            ->subject($isRegistration ? 'Your Mahj verification code' : 'Your Mahj password reset code')
            ->greeting('Mahj verification')
            ->line("Use this 6-digit code to {$action}:")
            ->line($this->code)
            ->line("This code expires in {$this->expiresInMinutes} minutes.")
            ->line('If you did not request this code, you can ignore this email.');
    }
}
