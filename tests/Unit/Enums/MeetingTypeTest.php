<?php

declare(strict_types=1);

use App\Enums\MeetingType;

it('has the correct cases', function (): void {
    expect(MeetingType::cases())->toHaveCount(3);
    expect(MeetingType::Midweek->value)->toBe('midweek');
    expect(MeetingType::Weekend->value)->toBe('weekend');
    expect(MeetingType::Adhoc->value)->toBe('adhoc');
});

it('has labels', function (): void {
    expect(MeetingType::Midweek->getLabel())->toBe('Infrasettimanale');
    expect(MeetingType::Weekend->getLabel())->toBe('Fine settimana');
    expect(MeetingType::Adhoc->getLabel())->toBe('Ad-hoc');
});
