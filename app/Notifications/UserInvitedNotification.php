<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Password;

class UserInvitedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected string $invitedBy,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        /** @var \Illuminate\Auth\Passwords\PasswordBroker $broker */
        $broker = Password::broker();
        $token = $broker->createToken($notifiable);
        $url = url(route('password.reset', [
            'token' => $token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        return (new MailMessage)
            ->subject(__('You have been invited to :app', ['app' => config('app.name')]))
            ->greeting(__('Hello :name!', ['name' => $notifiable->name]))
            ->line(__('You have been invited to join :app by :inviter.', ['app' => config('app.name'), 'inviter' => $this->invitedBy]))
            ->line(__('Click the button below to set your password and access the system.'))
            ->action(__('Set Password'), $url)
            ->line(__('This link will expire in :minutes minutes.', ['minutes' => config('auth.passwords.users.expire')]))
            ->line(__('If you did not expect this invitation, you can ignore this email.'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        return [
            'invited_by' => $this->invitedBy,
        ];
    }
}
