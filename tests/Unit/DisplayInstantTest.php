<?php

declare(strict_types=1);

use justinholtweb\owl\recurrence\DisplayInstant;

/**
 * Guards the fix for the all-day off-by-one bug: occurrences are stored as the UTC instant of the
 * event's local wall-clock time, so an all-day event in a positive-offset timezone lands on the
 * previous UTC date. {@see DisplayInstant} must re-anchor all-day occurrences to the local date.
 */

function stored(string $utc): DateTimeImmutable
{
    return new DateTimeImmutable($utc, new DateTimeZone('UTC'));
}

it('returns a timed occurrence unchanged as its UTC instant', function() {
    $instant = stored('2026-07-19 13:00:00');

    $result = DisplayInstant::forDisplay($instant, new DateTimeZone('America/New_York'), false);

    expect($result->format('Y-m-d H:i:s'))->toBe('2026-07-19 13:00:00')
        ->and($result->getTimezone()->getName())->toBe('UTC');
});

it('re-anchors an all-day occurrence to its local date for a positive-offset timezone', function() {
    // All-day 2026-07-19 in Tokyo (UTC+9) is stored as 2026-07-18 15:00Z.
    $instant = stored('2026-07-18 15:00:00');

    $result = DisplayInstant::forDisplay($instant, new DateTimeZone('Asia/Tokyo'), true);

    // Without the fix this would read 2026-07-18 (the UTC date) — a day early.
    expect($result->format('Y-m-d'))->toBe('2026-07-19')
        ->and($result->format('H:i:s'))->toBe('00:00:00')
        ->and($result->getTimezone()->getName())->toBe('UTC');
});

it('keeps an all-day occurrence on the correct date for a negative-offset timezone', function() {
    // All-day 2026-07-19 in Los Angeles (UTC-7 in July) is stored as 2026-07-19 07:00Z.
    $instant = stored('2026-07-19 07:00:00');

    $result = DisplayInstant::forDisplay($instant, new DateTimeZone('America/Los_Angeles'), true);

    expect($result->format('Y-m-d'))->toBe('2026-07-19');
});

it('re-anchors the all-day end date so a single-day event spans one calendar day', function() {
    // Tokyo all-day 2026-07-19: start stored 07-18 15:00Z, exclusive end stored 07-19 15:00Z.
    $start = DisplayInstant::forDisplay(stored('2026-07-18 15:00:00'), new DateTimeZone('Asia/Tokyo'), true);
    $end = DisplayInstant::forDisplay(stored('2026-07-19 15:00:00'), new DateTimeZone('Asia/Tokyo'), true);

    expect($start->format('Y-m-d'))->toBe('2026-07-19')
        ->and($end->format('Y-m-d'))->toBe('2026-07-20');
});
