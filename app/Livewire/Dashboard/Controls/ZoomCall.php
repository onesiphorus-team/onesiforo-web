<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard\Controls;

use App\Actions\Meetings\JoinCongregationMeetingAction;
use App\Concerns\ChecksOnesiBoxPermission;
use App\Concerns\HandlesOnesiBoxErrors;
use App\Models\Congregation;
use App\Models\OnesiBox;
use App\Models\ZoomMeeting;
use App\Services\OnesiBoxCommandServiceInterface;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Connects the box to a Zoom meeting: its congregation meeting or one of the meetings
 * saved for the box with one tap, or any meeting by pasting its link (optionally saving
 * it for next time).
 *
 * @property-read Congregation|null $congregation
 * @property-read Collection<int, ZoomMeeting> $savedMeetings
 * @property-read bool $canControl
 */
class ZoomCall extends Component
{
    use AuthorizesRequests;
    use ChecksOnesiBoxPermission;
    use HandlesOnesiBoxErrors;

    #[Locked]
    public OnesiBox $onesiBox;

    #[Validate([
        'zoomUrl' => [
            'required',
            'url',
            'regex:/^https:\/\/[a-z0-9]+\.zoom\.us\/j\/[0-9]+(\?pwd=.+)?$/i',
        ],
    ], message: [
        'zoomUrl.required' => 'Il link Zoom è obbligatorio.',
        'zoomUrl.url' => 'Inserisci un URL valido.',
        'zoomUrl.regex' => 'Il link deve essere un URL Zoom valido (es: https://us05web.zoom.us/j/123456789?pwd=...)',
    ])]
    public string $zoomUrl = '';

    public bool $saveMeeting = false;

    public string $meetingName = '';

    /**
     * The congregation of the recipient, when it has a Zoom meeting to join.
     */
    #[Computed]
    public function congregation(): ?Congregation
    {
        $congregation = $this->onesiBox->recipient?->congregation;

        return $congregation !== null && filled($congregation->zoom_url) ? $congregation : null;
    }

    /**
     * @return Collection<int, ZoomMeeting>
     */
    #[Computed]
    public function savedMeetings(): Collection
    {
        return $this->onesiBox->zoomMeetings()->mostRecentlyUsedFirst()->get();
    }

    public function joinCongregationMeeting(JoinCongregationMeetingAction $joinCongregationMeeting): void
    {
        $this->authorize('control', $this->onesiBox);

        if ($this->congregation === null) {
            return;
        }

        $this->joinAndClose(
            fn (): bool => $joinCongregationMeeting->execute($this->onesiBox),
            "Collegamento all'adunanza in corso...",
        );
    }

    public function joinSavedMeeting(int $meetingId, OnesiBoxCommandServiceInterface $commandService): void
    {
        $this->authorize('control', $this->onesiBox);

        $meeting = $this->onesiBox->zoomMeetings()->findOrFail($meetingId);

        $this->joinAndClose(
            function () use ($commandService, $meeting): void {
                $commandService->sendZoomUrlCommand($this->onesiBox, $meeting->zoom_url, $this->getParticipantName());
                $meeting->update(['last_used_at' => now()]);
            },
            "Collegamento a «{$meeting->name}» in corso...",
        );
    }

    /**
     * Join the meeting of the pasted link, saving it for next time when asked.
     */
    public function startCall(OnesiBoxCommandServiceInterface $commandService): void
    {
        $this->authorize('control', $this->onesiBox);

        $this->validate();

        if ($this->saveMeeting) {
            $this->validate(
                ['meetingName' => ['required', 'string', 'max:100']],
                ['meetingName.required' => 'Dai un nome alla riunione, ad esempio «Adunanza Loreto».'],
            );
        }

        $isJoining = $this->joinAndClose(
            fn () => $commandService->sendZoomUrlCommand($this->onesiBox, $this->zoomUrl, $this->getParticipantName()),
            'Connessione alla riunione Zoom in corso...',
        );

        if ($isJoining && $this->saveMeeting) {
            $this->onesiBox->zoomMeetings()->updateOrCreate(
                ['zoom_url' => $this->zoomUrl],
                ['name' => trim($this->meetingName), 'last_used_at' => now()],
            );
        }

        if ($isJoining) {
            $this->reset('zoomUrl', 'saveMeeting', 'meetingName');
        }
    }

    public function deleteSavedMeeting(int $meetingId): void
    {
        $this->authorize('control', $this->onesiBox);

        $this->onesiBox->zoomMeetings()->findOrFail($meetingId)->delete();

        unset($this->savedMeetings);
    }

    public function getParticipantName(): string
    {
        $recipient = $this->onesiBox->recipient;

        return $recipient !== null ? $recipient->full_name : $this->onesiBox->name;
    }

    public function render(): View
    {
        return view('livewire.dashboard.controls.zoom-call');
    }

    /**
     * Send the join command and, when it went out, close the sheet so the caregiver sees
     * the call starting on the box status card.
     */
    private function joinAndClose(Closure $join, string $successMessage): bool
    {
        $isJoining = $this->executeWithErrorHandling(callback: $join, successMessage: $successMessage);

        if ($isJoining) {
            $this->dispatch('close-quick-play');
        }

        return $isJoining;
    }
}
