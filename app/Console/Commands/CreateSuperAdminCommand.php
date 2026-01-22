<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Roles;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateSuperAdminCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:create-super-admin';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crea un nuovo utente super-admin';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = text(
            label: 'Nome completo',
            required: true,
        );

        $email = text(
            label: 'Email',
            required: true,
            validate: function (string $value): ?string {
                $emailError = $this->validateEmail($value);
                if ($emailError) {
                    return $emailError;
                }

                // Check email uniqueness
                if (User::withTrashed()->where('email', $value)->exists()) {
                    return "L'email {$value} è già in uso.";
                }

                return null;
            },
        );

        $passwordValue = password(
            label: 'Password',
            required: true,
        );

        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($passwordValue),
            'email_verified_at' => now(),
        ]);

        $user->assignRole(Roles::SuperAdmin);

        $this->info("Super admin {$email} creato con successo!");

        return Command::SUCCESS;
    }

    private function validateEmail(string $value): ?string
    {
        $validator = Validator::make(
            ['email' => $value],
            ['email' => ['required', 'email']]
        );

        if ($validator->fails()) {
            return 'Email non valida.';
        }

        return null;
    }
}
