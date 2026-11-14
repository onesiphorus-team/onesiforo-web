<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard\Controls;

use App\Actions\Sessions\StartQueuedPlaybackSessionAction;
use App\Concerns\ChecksOnesiBoxPermission;
use App\Concerns\HandlesOnesiBoxErrors;
use App\Models\OnesiBox;
use App\Models\PlaybackSession;
use App\Services\JwOrgCatalog;
use App\Services\OnesiBoxCommandServiceInterface;
use App\Support\LastShownLabel;
use Carbon\CarbonInterface;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Client\ConnectionException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use RuntimeException;

/**
 * Lets a caregiver browse the JW.org video or audio library and play an item with one tap,
 * showing for each item when it was last shown on this OnesiBox.
 *
 * Items can also be queued up and then played in order as a temporary playlist. The queue is
 * kept in the caregiver's session, so it survives going back to the menu or reloading the page.
 *
 * @phpstan-import-type JwCategory from JwOrgCatalog
 * @phpstan-import-type JwMediaItem from JwOrgCatalog
 *
 * @property-read JwCategory|null $rootCategory
 * @property-read JwCategory|null $expandedCategoryData
 * @property-read JwCategory|null $openCategoryData
 * @property-read list<array{item: JwMediaItem, last_shown_label: string, is_recent: bool, never_shown: bool}> $visibleItems
 * @property-read array<string, true> $queuedKeys
 * @property-read string $queueDurationLabel
 * @property-read bool $canControl
 */
class JwMediaBrowser extends Component
{
    use AuthorizesRequests;
    use ChecksOnesiBoxPermission;
    use HandlesOnesiBoxErrors;

    #[Locked]
    public OnesiBox $onesiBox;

    #[Locked]
    public string $mediaType = 'video';

    /**
     * Top-level container whose subcategories are expanded inline.
     */
    public ?string $expandedCategory = null;

    /**
     * Category whose media are listed.
     */
    public ?string $openCategory = null;

    public string $search = '';

    /**
     * Media queued up for the temporary playlist, in playing order.
     *
     * @var list<JwMediaItem>
     */
    #[Locked]
    public array $queue = [];

    public function mount(): void
    {
        abort_unless(isset(JwOrgCatalog::ROOT_CATEGORIES[$this->mediaType]), 404);

        $this->queue = session($this->queueSessionKey(), []);
    }

    /**
     * @return array<string, true>
     */
    #[Computed]
    public function queuedKeys(): array
    {
        return array_fill_keys(array_column($this->queue, 'key'), true);
    }

    /**
     * Total duration of the queue, e.g. "12 min" or "1 h 05 min".
     */
    #[Computed]
    public function queueDurationLabel(): string
    {
        $totalMinutes = (int) ceil(array_sum(array_column($this->queue, 'duration_seconds')) / 60);

        if ($totalMinutes < 60) {
            return "{$totalMinutes} min";
        }

        return sprintf('%d h %02d min', intdiv($totalMinutes, 60), $totalMinutes % 60);
    }

    /**
     * @return JwCategory|null Null when JW.org cannot be reached
     */
    #[Computed]
    public function rootCategory(): ?array
    {
        return $this->fetchCategory(fn (JwOrgCatalog $catalog): array => $catalog->rootCategory($this->mediaType));
    }

    /**
     * @return JwCategory|null
     */
    #[Computed]
    public function expandedCategoryData(): ?array
    {
        if ($this->expandedCategory === null) {
            return null;
        }

        return $this->fetchCategory(fn (JwOrgCatalog $catalog): array => $catalog->category($this->expandedCategory));
    }

    /**
     * @return JwCategory|null
     */
    #[Computed]
    public function openCategoryData(): ?array
    {
        if ($this->openCategory === null) {
            return null;
        }

        return $this->fetchCategory(fn (JwOrgCatalog $catalog): array => $catalog->category($this->openCategory));
    }

    /**
     * Media of the open category, filtered by the search text, with their last-shown info.
     *
     * @return list<array{item: JwMediaItem, last_shown_label: string, is_recent: bool, never_shown: bool}>
     */
    #[Computed]
    public function visibleItems(): array
    {
        $category = $this->openCategoryData;

        if ($category === null) {
            return [];
        }

        $lastShownAt = $this->onesiBox->lastShownAtByMediaKey();
        $search = mb_strtolower(trim($this->search));
        $rows = [];
        $seenKeys = [];

        foreach (resolve(JwOrgCatalog::class)->mediaOf($category) as $item) {
            if (isset($seenKeys[$item['key']])) {
                continue;
            }

            if ($search !== '' && ! str_contains(mb_strtolower($item['title']), $search)) {
                continue;
            }

            $seenKeys[$item['key']] = true;
            $itemLastShownAt = $lastShownAt[$item['key']] ?? null;

            $rows[] = [
                'item' => $item,
                'last_shown_label' => LastShownLabel::for($itemLastShownAt),
                'is_recent' => LastShownLabel::isRecent($itemLastShownAt),
                'never_shown' => ! $itemLastShownAt instanceof CarbonInterface,
            ];
        }

        return $rows;
    }

    /**
     * Expand a container inline, or open the list of media of a leaf category.
     */
    public function selectCategory(string $categoryKey): void
    {
        $subcategory = $this->findSubcategory($this->rootCategory, $categoryKey);

        if ($subcategory === null) {
            return;
        }

        if ($subcategory['is_container']) {
            $this->expandedCategory = $this->expandedCategory === $categoryKey ? null : $categoryKey;

            return;
        }

        $this->showCategory($categoryKey);
    }

    /**
     * Open the list of media of a subcategory of the expanded container.
     */
    public function openSubcategory(string $categoryKey): void
    {
        if ($this->findSubcategory($this->expandedCategoryData, $categoryKey) === null) {
            return;
        }

        $this->showCategory($categoryKey);
    }

    public function closeCategory(): void
    {
        $this->openCategory = null;
        $this->search = '';
    }

    public function play(string $mediaKey, OnesiBoxCommandServiceInterface $commandService): void
    {
        $this->authorize('control', $this->onesiBox);

        $item = $this->findOpenCategoryItem($mediaKey);

        if ($item === null) {
            return;
        }

        $this->executeWithErrorHandling(
            callback: fn () => $commandService->sendMediaCommand($this->onesiBox, $item['url'], $item['media_type']),
            successMessage: "«{$item['title']}» inviato al box",
        );
    }

    /**
     * Add an item of the open category to the queue, or take it out if it is already queued.
     */
    public function toggleQueued(string $mediaKey): void
    {
        $this->authorize('control', $this->onesiBox);

        if (isset($this->queuedKeys[$mediaKey])) {
            $this->removeFromQueue($mediaKey);

            return;
        }

        $item = $this->findOpenCategoryItem($mediaKey);

        if ($item === null) {
            return;
        }

        if (count($this->queue) >= StartQueuedPlaybackSessionAction::MAX_ITEMS) {
            Flux::toast('La playlist può contenere al massimo '.StartQueuedPlaybackSessionAction::MAX_ITEMS.' contenuti.', variant: 'warning');

            return;
        }

        $this->saveQueue([...$this->queue, $item]);
    }

    public function removeFromQueue(string $mediaKey): void
    {
        $this->authorize('control', $this->onesiBox);

        $this->saveQueue(array_values(array_filter(
            $this->queue,
            fn (array $item): bool => $item['key'] !== $mediaKey,
        )));
    }

    public function clearQueue(): void
    {
        $this->authorize('control', $this->onesiBox);

        $this->saveQueue([]);
    }

    /**
     * Play the queued items in order as a playlist session.
     */
    public function startQueue(StartQueuedPlaybackSessionAction $startQueuedSession): void
    {
        $this->authorize('control', $this->onesiBox);

        if ($this->queue === []) {
            return;
        }

        $itemCount = count($this->queue);

        $isStarted = $this->executeWithErrorHandling(
            callback: fn (): PlaybackSession => $startQueuedSession->execute(
                $this->onesiBox,
                $this->mediaType === 'audio' ? 'audio' : 'video',
                $this->queue,
            ),
            successMessage: $itemCount === 1 ? 'Playlist avviata: 1 contenuto' : "Playlist avviata: {$itemCount} contenuti",
        );

        if ($isStarted) {
            $this->saveQueue([]);
        }
    }

    public function render(): View
    {
        return view('livewire.dashboard.controls.jw-media-browser');
    }

    /**
     * @return JwMediaItem|null
     */
    private function findOpenCategoryItem(string $mediaKey): ?array
    {
        if ($this->openCategoryData === null) {
            return null;
        }

        return collect(resolve(JwOrgCatalog::class)->mediaOf($this->openCategoryData))->firstWhere('key', $mediaKey);
    }

    /**
     * @param  list<JwMediaItem>  $queue
     */
    private function saveQueue(array $queue): void
    {
        $this->queue = $queue;
        session()->put($this->queueSessionKey(), $queue);
        unset($this->queuedKeys, $this->queueDurationLabel);
    }

    private function queueSessionKey(): string
    {
        return "jw-media-queue.{$this->onesiBox->id}.{$this->mediaType}";
    }

    private function showCategory(string $categoryKey): void
    {
        $this->openCategory = $categoryKey;
        $this->search = '';
    }

    /**
     * @param  JwCategory|null  $category
     * @return array{key: string, name: string, is_container: bool, media: list<JwMediaItem>}|null
     */
    private function findSubcategory(?array $category, string $categoryKey): ?array
    {
        if ($category === null) {
            return null;
        }

        return collect($category['subcategories'])->firstWhere('key', $categoryKey);
    }

    /**
     * @param  callable(JwOrgCatalog): JwCategory  $fetch
     * @return JwCategory|null
     */
    private function fetchCategory(callable $fetch): ?array
    {
        try {
            return $fetch(resolve(JwOrgCatalog::class));
        } catch (RuntimeException|ConnectionException) {
            return null;
        }
    }
}
