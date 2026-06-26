<?php

declare(strict_types=1);

use justinholtweb\owl\recurrence\OccurrenceExpander;
use justinholtweb\owl\recurrence\RecurrenceRule;

/**
 * DST correctness is the single most common source of calendar bugs. RFC 5545 recurrence is
 * wall-clock local: "every day at 09:00" must stay 09:00 in the event's timezone even as the
 * UTC offset changes across a DST transition. These tests pin that behaviour.
 *
 * US Eastern transitions used:
 *   - Spring forward: 2025-03-09 02:00 -> 03:00  (EST -05:00 becomes EDT -04:00)
 *   - Fall back:      2025-11-02 02:00 -> 01:00  (EDT -04:00 becomes EST -05:00)
 */

function expandEastern(string $rrule, string $from, string $to): array
{
    $rule = new RecurrenceRule(
        new DateTimeImmutable('2025-03-07 09:00', new DateTimeZone('America/New_York')),
        new DateTimeImmutable('2025-03-07 10:00', new DateTimeZone('America/New_York')),
        $rrule,
    );

    return (new OccurrenceExpander())->expand($rule, dt($from), dt($to));
}

it('keeps the local wall-clock time fixed across the spring-forward transition', function() {
    $occurrences = expandEastern('FREQ=DAILY;COUNT=6', '2025-03-01 00:00', '2025-03-20 00:00');

    // Every occurrence must read 09:00 in local time, regardless of the DST shift.
    foreach ($occurrences as $occurrence) {
        expect($occurrence->localStart()->format('H:i'))->toBe('09:00');
    }
});

it('shifts the UTC offset (but not the local time) across spring-forward', function() {
    $occurrences = expandEastern('FREQ=DAILY;COUNT=6', '2025-03-01 00:00', '2025-03-20 00:00');

    $utc = [];
    foreach ($occurrences as $occurrence) {
        $utc[$occurrence->localStart()->format('Y-m-d')] = $occurrence->start->format('Y-m-d H:i');
    }

    // Before the 2025-03-09 transition: 09:00 EST (-05:00) == 14:00 UTC.
    expect($utc['2025-03-08'])->toBe('2025-03-08 14:00');
    // On/after the transition: 09:00 EDT (-04:00) == 13:00 UTC.
    expect($utc['2025-03-09'])->toBe('2025-03-09 13:00')
        ->and($utc['2025-03-10'])->toBe('2025-03-10 13:00');
});

it('keeps the local wall-clock time fixed across the fall-back transition', function() {
    $rule = new RecurrenceRule(
        new DateTimeImmutable('2025-10-31 09:00', new DateTimeZone('America/New_York')),
        new DateTimeImmutable('2025-10-31 10:00', new DateTimeZone('America/New_York')),
        'FREQ=DAILY;COUNT=5',
    );

    $occurrences = (new OccurrenceExpander())->expand($rule, dt('2025-10-25 00:00'), dt('2025-11-10 00:00'));

    $utc = [];
    foreach ($occurrences as $occurrence) {
        expect($occurrence->localStart()->format('H:i'))->toBe('09:00');
        $utc[$occurrence->localStart()->format('Y-m-d')] = $occurrence->start->format('Y-m-d H:i');
    }

    // Before fall-back (2025-11-02): 09:00 EDT (-04:00) == 13:00 UTC.
    expect($utc['2025-11-01'])->toBe('2025-11-01 13:00');
    // After fall-back: 09:00 EST (-05:00) == 14:00 UTC.
    expect($utc['2025-11-03'])->toBe('2025-11-03 14:00');
});

it('keeps a one-hour duration as one wall-clock hour across the transition', function() {
    $occurrences = expandEastern('FREQ=DAILY;COUNT=6', '2025-03-01 00:00', '2025-03-20 00:00');

    // The event is 09:00-10:00 local. End should remain 10:00 local on every day, including
    // the transition day, even though the real elapsed time differs by an hour somewhere.
    foreach ($occurrences as $occurrence) {
        expect($occurrence->localEnd()->format('H:i'))->toBe('10:00');
    }
});
