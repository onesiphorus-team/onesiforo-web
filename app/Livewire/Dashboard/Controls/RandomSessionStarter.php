<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard\Controls;

use App\Actions\Sessions\StartRandomPlaybackSessionAction;
use App\Concerns\ChecksOnesiBoxPermission;
use App\Concerns\HandlesOnesiBoxErrors;
use App\Exceptions\NoRandomMediaAvailableException;
use App\Models\OnesiBox;
use App\Models\PlaybackSession;
use App\Services\JwOrgCatalog;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use RuntimeException;

/**
 * Starts a timed session of random JW.org videos or audios, preferring the ones
 * not shown recently on the OnesiBox.
 *
 * @property-read list<array{key: string, name: string}>|null $categories
 * @property-read PlaybackSession|null $activeSession
 * @property-read bool $canControl
 */
class RandomSessionStarter extends Component
{
    use AuthorizesRequests;
    use ChecksOnesiBoxPermission;
    use HandlesOnesiBoxErrors;

    /**
     * @var list<int>
     */
    public const array DURATION_OPTIONS = [30, 60, 120, 180];

    #[Locked]
    public OnesiBox $onesiBox;

    #[Locked]
    public string $mediaType = 'video';

    public int $durationMinutes = 60;

    /**
     * Keys of the categories checked by the caregiver (the checkbox group may send a non-list array).
     *
     * @var array<int, string>
     */
    public array $selectedCategories = [];

    public bool $includeLongItems = false;

    public function mount(): void
    {
        abort_unless(isset(JwOrgCatalog::ROOT_CATEGORIES[$this->mediaType]), 404);

        $this->selectedCategories = array_values(array_diff(
            array_column($this->categories ?? [], 'key'),
            StartRandomPlaybackSessionAction::DEFAULT_EXCLUDED_CATEGORIES,
        ));
    }

    /**
     * Categories the caregiver can pick from (children of the library root).
     *
     * @return list<array{key: string, name: string}>|null Null when JW.org cannot be reached
     */
    #[Computed]
    public function categories(): ?array
    {
        try {
            $root = resolve(JwOrgCatalog::class)->rootCategory($this->mediaType);
        } catch (RuntimeException|ConnectionException) {
            return null;
        }

        return array_map(
            fn (array $category): array => ['key' => $category['key'], 'name' => $category['name']],
            $root['subcategories'],
        );
    }

    #[Computed]
    public function activeSession(): ?PlaybackSession
    {
        return $this->onesiBox->activeSession();
    }

    public function selectAllCategories(): void
    {
        $this->selectedCategories = array_column($this->categories ?? [], 'key');
    }

    public function start(StartRandomPlaybackSessionAction $startRandomSession): void
    {
        $this->authorize('control', $this->onesiBox);

        $this->validate([
            'durationMinutes' => ['required', 'integer', Rule::in(self::DURATION_OPTIONS)],
            'selectedCategories' => ['required', 'array', 'min:1'],
            'selectedCategories.*' => ['string', Rule::in(array_column($this->categories ?? [], 'key'))],
            'includeLongItems' => ['boolean'],
        ], [
            'selectedCategories.required' => 'Seleziona almeno una categoria.',
            'selectedCategories.min' => 'Seleziona almeno una categoria.',
        ]);

        $kindLabel = $this->mediaType === 'audio' ? 'audio' : 'video';

        try {
            $this->executeWithErrorHandling(
                callback: fn (): PlaybackSession => $startRandomSession->execute(
                    $this->onesiBox,
                    $this->mediaType === 'audio' ? 'audio' : 'video',
                    array_values($this->selectedCategories),
                    $this->durationMinutes,
                    $this->includeLongItems,
                ),
                successMessage: "Sessione di {$kindLabel} casuali avviata",
            );
        } catch (NoRandomMediaAvailableException) {
            Flux::toast("Nessun {$kindLabel} disponibile nelle categorie scelte.", variant: 'warning');
        } catch (RuntimeException|ConnectionException) {
            Flux::toast('Impossibile caricare i contenuti da jw.org. Riprova tra poco.', variant: 'danger');
        }

        unset($this->activeSession);
    }

    public function formatDuration(int $minutes): string
    {
        if ($minutes < 60) {
            return "{$minutes} minuti";
        }

        $hours = intdiv($minutes, 60);

        return $hours === 1 ? '1 ora' : "{$hours} ore";
    }

    public function render(): View
    {
        return view('livewire.dashboard.controls.random-session-starter');
    }
}
