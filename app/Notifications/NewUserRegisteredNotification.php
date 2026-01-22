<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewUserRegisteredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected User $newUser,
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
        return (new MailMessage)
            ->subject(__('New user registered on :app', ['app' => config('app.name')]))
            ->greeting(__('Hello :name!', ['name' => $notifiable->name]))
            ->line(__('A new user has registered on :app.', ['app' => config('app.name')]))
            ->line(__('**Name:** :name', ['name' => $this->newUser->name]))
            ->line(__('**Email:** :email', ['email' => $this->newUser->email]))
            ->line(__('**Registered at:** :date', ['date' => $this->newUser->created_at?->format('d/m/Y H:i')]))
            ->action(__('Manage Users'), url(route('filament.admin.resources.users.index')))
            ->line(__('Please assign the appropriate role to this user.'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        return [
            'new_user_id' => $this->newUser->id,
            'new_user_name' => $this->newUser->name,
            'new_user_email' => $this->newUser->email,
        ];
    }
}
