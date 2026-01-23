<?php

declare(strict_types=1);

use App\Concerns\HandlesOnesiBoxErrors;
use App\Exceptions\OnesiBoxOfflineException;
use Flux\Flux;
use Livewire\Component;

beforeEach(function (): void {
    // Allow Flux::toast calls during tests without strict verification
    Flux::shouldReceive('toast')->byDefault()->andReturnNull();

    $this->testComponent = new class extends Component
    {
        use HandlesOnesiBoxErrors;

        public bool $commandExecuted = false;

        public function executeTestCommand(): bool
        {
            return $this->executeWithErrorHandling(
                callback: function (): void {
                    $this->commandExecuted = true;
                },
                successMessage: 'Comando eseguito con successo'
            );
        }

        public function executeFailingCommand(): bool
        {
            return $this->executeWithErrorHandling(
                callback: function (): never {
                    throw new OnesiBoxOfflineException('Test offline');
                },
                successMessage: 'Comando eseguito con successo',
                offlineMessage: 'Dispositivo non disponibile'
            );
        }

        public function render(): mixed
        {
            return null;
        }
    };
});

it('executes callback successfully and returns true', function (): void {
    $result = $this->testComponent->executeTestCommand();

    expect($result)->toBeTrue()
        ->and($this->testComponent->commandExecuted)->toBeTrue();
});

it('catches OnesiBoxOfflineException and returns false', function (): void {
    $result = $this->testComponent->executeFailingCommand();

    expect($result)->toBeFalse();
});

it('returns true on success', function (): void {
    $result = $this->testComponent->executeTestCommand();

    expect($result)->toBeTrue();
});

it('returns false on offline exception', function (): void {
    $result = $this->testComponent->executeFailingCommand();

    expect($result)->toBeFalse();
});

it('executes the callback when no exception is thrown', function (): void {
    expect($this->testComponent->commandExecuted)->toBeFalse();

    $this->testComponent->executeTestCommand();

    expect($this->testComponent->commandExecuted)->toBeTrue();
});

it('does not propagate OnesiBoxOfflineException', function (): void {
    // Should not throw - the exception is caught internally
    $result = $this->testComponent->executeFailingCommand();

    expect($result)->toBeFalse();
});
