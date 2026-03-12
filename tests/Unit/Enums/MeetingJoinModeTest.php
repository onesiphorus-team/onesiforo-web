<?php

use App\Enums\MeetingJoinMode;

it('has the correct cases', function () {
    expect(MeetingJoinMode::cases())->toHaveCount(2);
    expect(MeetingJoinMode::Auto->value)->toBe('auto');
    expect(MeetingJoinMode::Manual->value)->toBe('manual');
});

it('has labels', function () {
    expect(MeetingJoinMode::Auto->getLabel())->toBe('Automatico');
    expect(MeetingJoinMode::Manual->getLabel())->toBe('Manuale');
});
