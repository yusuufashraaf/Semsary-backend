<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class emailOTP extends Notification
{
    use Queueable;
    public User $user;
    /**
     * Create a new notification instance.
     */
    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $otp = $this->user->email_otp; // Assuming you pass the OTP to the notification

        return (new MailMessage)
            ->subject('Your Verification Code - ' . config('app.name'))
            ->greeting('Hello!')
            ->line('You are receiving this email because we received a verification request for your account.')
            ->line('Your verification code is:')
            ->line('## **' . $otp . '**') // This will display the OTP prominently
            ->line('This code will expire in 10 minutes.')
            ->line('If you did not request this verification, please ignore this email.')
            ->salutation('Regards,<br>' . config('app.name'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
