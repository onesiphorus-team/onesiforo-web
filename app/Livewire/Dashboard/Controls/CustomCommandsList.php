<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard\Controls;

use App\Concerns\ChecksOnesiBoxPermission;
use App\Concerns\HandlesOnesiBoxErrors;
use App\Models\CustomCommand;
use App\Models\OnesiBox;
use App\Services\OnesiBoxCommandServiceInterface;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Computed;
use Livewire\Component;

class CustomCommandsList extends Component
{
    use AuthorizesRequests;
    use ChecksOnesiBoxPermission;
    use HandlesOnesiBoxErrors;

    public OnesiBox $onesiBox;

    /**
     * @return Collection<int, CustomCommand>
     */
    #[Computed]
    public function commands(): Collection
    {
        return $this->onesiBox->customCommands()
            ->enabled()
            ->ordered()
            ->get();
    }

    public function run(int $commandId, OnesiBoxCommandServiceInterface $commandService): void
    {
        $this->authorize('control', $this->onesiBox);

        if (! $this->canControl()) {
            Flux::toast(__('Non autorizzato'), variant: 'danger');

            return;
        }

        /** @var CustomCommand|null $cmd */
        $cmd = $this->onesiBox->customCommands()->enabled()->find($commandId);

        if ($cmd === null) {
            Flux::toast(__('Comando non trovato'), variant: 'danger');

            return;
        }

        $this->executeWithErrorHandling(
            callback: fn () => $commandService->sendCustomScriptCommand($this->onesiBox, $cmd),
            successMessage: __('Comando inviato: :name', ['name' => $cmd->name]),
        );
    }

    public function render(): View
    {
        return view('livewire.dashboard.controls.custom-commands-list');
    }
}
