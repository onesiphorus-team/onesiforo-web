<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Roles;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateUserCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:create-user';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crea un nuovo utente';

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

        $roleOptions = collect(Roles::cases())
            ->mapWithKeys(fn (Roles $role): array => [$role->value => $role->getLabel()])
            ->all();

        /** @var array<int, string> $selectedRoles */
        $selectedRoles = multiselect(
            label: 'Ruoli',
            options: $roleOptions,
            hint: 'Usa spazio per selezionare, invio per confermare. Lascia vuoto per nessun ruolo.',
        );

        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($passwordValue),
        ]);

        foreach ($selectedRoles as $role) {
            $user->assignRole($role);
        }

        $user->sendEmailVerificationNotification();

        $rolesDisplay = count($selectedRoles) > 0
            ? implode(', ', $selectedRoles)
            : 'nessuno';

        $this->info("Utente {$email} creato con successo!");
        $this->line("Ruoli assegnati: {$rolesDisplay}");
        $this->line("Email di verifica inviata a: {$email}");

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
