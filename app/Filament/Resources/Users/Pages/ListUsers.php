<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Pages;

use App\Enums\Roles;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Notifications\UserInvitedNotification;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->getInviteAction(),
            CreateAction::make(),
        ];
    }

    protected function getInviteAction(): Action
    {
        /** @var User $currentUser */
        $currentUser = Auth::user();

        return Action::make('invite')
            ->label(__('Invite User'))
            ->icon('heroicon-o-envelope')
            ->color('success')
            ->modalHeading(__('Invite New User'))
            ->modalDescription(__('The user will receive an email with a link to set their password.'))
            ->schema([
                TextInput::make('name')
                    ->label(__('Name'))
                    ->required()
                    ->maxLength(255),

                TextInput::make('email')
                    ->label(__('Email'))
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(
                        table: User::class,
                        modifyRuleUsing: fn (Unique $rule) => $rule->withoutTrashed(),
                    ),

                Select::make('role')
                    ->label(__('Role'))
                    ->options($this->getAvailableRoles())
                    ->required(),
            ])
            ->action(function (array $data): void {
                /** @var User $currentUser */
                $currentUser = Auth::user();

                // Create user with a random temporary password
                $user = User::query()->create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => Hash::make(Str::random(32)),
                ]);

                // Assign the selected role
                $user->assignRole($data['role']);

                // Send the invite notification
                $user->notify(new UserInvitedNotification($currentUser->name));

                // Log the activity
                activity()
                    ->performedOn($user)
                    ->causedBy($currentUser)
                    ->withProperties([
                        'role' => $data['role'],
                        'invited_by' => $currentUser->email,
                    ])
                    ->log('User invited');

                Notification::make()
                    ->title(__('User invited'))
                    ->body(__('The invitation has been sent to :email', ['email' => $data['email']]))
                    ->success()
                    ->send();
            });
    }

    /**
     * @return array<string, string>
     */
    protected function getAvailableRoles(): array
    {
        /** @var User $currentUser */
        $currentUser = Auth::user();

        // Super-admin can assign all roles
        if ($currentUser->hasRole(Roles::SuperAdmin)) {
            return [
                Roles::SuperAdmin->value => Roles::SuperAdmin->getLabel(),
                Roles::Admin->value => Roles::Admin->getLabel(),
                Roles::Caregiver->value => Roles::Caregiver->getLabel(),
            ];
        }

        // Admin can only assign caregiver role
        return [
            Roles::Caregiver->value => Roles::Caregiver->getLabel(),
        ];
    }
}
