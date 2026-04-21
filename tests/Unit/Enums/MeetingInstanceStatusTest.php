<?php

declare(strict_types=1);

use App\Enums\MeetingInstanceStatus;

it('has the correct cases', function (): void {
    expect(MeetingInstanceStatus::cases())->toHaveCount(5);
    expect(MeetingInstanceStatus::Scheduled->value)->toBe('scheduled');
    expect(MeetingInstanceStatus::Notified->value)->toBe('notified');
    expect(MeetingInstanceStatus::InProgress->value)->toBe('in_progress');
    expect(MeetingInstanceStatus::Completed->value)->toBe('completed');
    expect(MeetingInstanceStatus::Cancelled->value)->toBe('cancelled');
});

it('identifies terminal statuses', function (): void {
    expect(MeetingInstanceStatus::Completed->isTerminal())->toBeTrue();
    expect(MeetingInstanceStatus::Cancelled->isTerminal())->toBeTrue();
    expect(MeetingInstanceStatus::Scheduled->isTerminal())->toBeFalse();
    expect(MeetingInstanceStatus::Notified->isTerminal())->toBeFalse();
    expect(MeetingInstanceStatus::InProgress->isTerminal())->toBeFalse();
});
