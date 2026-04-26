<?php

declare(strict_types=1);

use App\Models\Recipient;

describe('full_name accessor', function (): void {
    it('concatenates first and last name', function (): void {
        $recipient = Recipient::factory()->make([
            'first_name' => 'Maria',
            'last_name' => 'Iannascoli',
        ]);

        expect($recipient->full_name)->toBe('Maria Iannascoli');
    });
});

describe('full_address accessor', function (): void {
    it('returns null when every address part is empty', function (): void {
        $recipient = Recipient::factory()->make([
            'street' => null,
            'postal_code' => null,
            'city' => null,
            'province' => null,
        ]);

        expect($recipient->full_address)->toBeNull();
    });

    it('joins all parts with the province in parentheses when complete', function (): void {
        $recipient = Recipient::factory()->make([
            'street' => 'Via Roma 123',
            'postal_code' => '20100',
            'city' => 'Milano',
            'province' => 'MI',
        ]);

        expect($recipient->full_address)->toBe('Via Roma 123, 20100, Milano, (MI)');
    });

    it('skips missing parts but keeps the rest', function (): void {
        $recipient = Recipient::factory()->make([
            'street' => 'Via Roma 123',
            'postal_code' => null,
            'city' => 'Milano',
            'province' => null,
        ]);

        expect($recipient->full_address)->toBe('Via Roma 123, Milano');
    });

    it('omits the province parentheses entirely when province is null', function (): void {
        $recipient = Recipient::factory()->make([
            'street' => 'Via Roma 123',
            'postal_code' => '20100',
            'city' => 'Milano',
            'province' => null,
        ]);

        expect($recipient->full_address)->toBe('Via Roma 123, 20100, Milano');
    });
});
