<?php

declare(strict_types=1);

use justinholtweb\owl\recurrence\Occurrence;

function occ(string $start, string $end, string $tz = 'UTC', bool $allDay = false): Occurrence
{
    return new Occurrence(dt($start, 'UTC'), dt($end, 'UTC'), new DateTimeZone($tz), $allDay);
}

it('converts UTC instants back to local wall-clock time', function() {
    // 13:00 UTC is 09:00 in New York during EDT.
    $occurrence = occ('2025-07-01 13:00', '2025-07-01 14:00', 'America/New_York');

    expect($occurrence->localStart()->format('Y-m-d H:i'))->toBe('2025-07-01 09:00')
        ->and($occurrence->localEnd()->format('H:i'))->toBe('10:00');
});

it('overlaps a window it sits fully inside', function() {
    $occurrence = occ('2026-07-02 09:00', '2026-07-02 10:00');

    expect($occurrence->overlaps(dt('2026-07-01 00:00'), dt('2026-07-03 00:00')))->toBeTrue();
});

it('overlaps a window it straddles', function() {
    $occurrence = occ('2026-07-01 22:00', '2026-07-02 02:00');

    expect($occurrence->overlaps(dt('2026-07-02 00:00'), dt('2026-07-02 12:00')))->toBeTrue();
});

it('does not overlap a window entirely before it', function() {
    $occurrence = occ('2026-07-05 09:00', '2026-07-05 10:00');

    expect($occurrence->overlaps(dt('2026-07-01 00:00'), dt('2026-07-02 00:00')))->toBeFalse();
});

it('treats the window as half-open at both ends', function() {
    $occurrence = occ('2026-07-02 00:00', '2026-07-02 01:00');

    // Occurrence end exactly equals window start -> no overlap.
    expect($occurrence->overlaps(dt('2026-07-02 01:00'), dt('2026-07-03 00:00')))->toBeFalse();

    // Occurrence start exactly equals window end -> no overlap.
    expect($occurrence->overlaps(dt('2026-07-01 00:00'), dt('2026-07-02 00:00')))->toBeFalse();
});

it('retains the all-day flag', function() {
    $occurrence = occ('2026-07-01 00:00', '2026-07-02 00:00', 'UTC', true);

    expect($occurrence->allDay)->toBeTrue();
});
