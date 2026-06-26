<?php

declare(strict_types=1);

use justinholtweb\owl\recurrence\OccurrenceExpander;
use justinholtweb\owl\recurrence\RecurrenceRule;

/**
 * Convenience: expand a rule over a window and return UTC start strings 'Y-m-d H:i'.
 *
 * @return string[]
 */
function expandStarts(RecurrenceRule $rule, string $from, string $to, array $exdates = []): array
{
    $occurrences = (new OccurrenceExpander())->expand($rule, dt($from), dt($to), $exdates);

    return array_map(fn($o) => $o->start->format('Y-m-d H:i'), $occurrences);
}

it('returns a single non-recurring event that falls inside the window', function() {
    $rule = new RecurrenceRule(dt('2026-07-01 09:00', 'UTC'), dt('2026-07-01 10:00', 'UTC'));

    expect(expandStarts($rule, '2026-06-01 00:00', '2026-08-01 00:00'))
        ->toBe(['2026-07-01 09:00']);
});

it('excludes a single event that falls outside the window', function() {
    $rule = new RecurrenceRule(dt('2026-07-01 09:00', 'UTC'), dt('2026-07-01 10:00', 'UTC'));

    expect(expandStarts($rule, '2026-01-01 00:00', '2026-02-01 00:00'))->toBe([]);
});

it('includes a multi-day event that starts before the window but overlaps into it', function() {
    // Spans midnight boundaries; window only covers the middle day.
    $rule = new RecurrenceRule(dt('2026-07-01 22:00', 'UTC'), dt('2026-07-03 02:00', 'UTC'));

    expect(expandStarts($rule, '2026-07-02 00:00', '2026-07-02 23:59'))
        ->toBe(['2026-07-01 22:00']);
});

it('expands a daily rule bounded by COUNT', function() {
    $rule = new RecurrenceRule(
        dt('2026-07-01 09:00', 'UTC'),
        dt('2026-07-01 10:00', 'UTC'),
        'FREQ=DAILY;COUNT=3',
    );

    expect(expandStarts($rule, '2026-07-01 00:00', '2026-08-01 00:00'))->toBe([
        '2026-07-01 09:00',
        '2026-07-02 09:00',
        '2026-07-03 09:00',
    ]);
});

it('expands a weekly rule on specific weekdays', function() {
    // 2026-07-01 is a Wednesday.
    $rule = new RecurrenceRule(
        dt('2026-07-01 09:00', 'UTC'),
        dt('2026-07-01 10:00', 'UTC'),
        'FREQ=WEEKLY;BYDAY=MO,WE;COUNT=4',
    );

    expect(expandStarts($rule, '2026-07-01 00:00', '2026-08-01 00:00'))->toBe([
        '2026-07-01 09:00', // Wed
        '2026-07-06 09:00', // Mon
        '2026-07-08 09:00', // Wed
        '2026-07-13 09:00', // Mon
    ]);
});

it('honours INTERVAL', function() {
    $rule = new RecurrenceRule(
        dt('2026-07-01 09:00', 'UTC'),
        dt('2026-07-01 10:00', 'UTC'),
        'FREQ=DAILY;INTERVAL=2;COUNT=3',
    );

    expect(expandStarts($rule, '2026-07-01 00:00', '2026-08-01 00:00'))->toBe([
        '2026-07-01 09:00',
        '2026-07-03 09:00',
        '2026-07-05 09:00',
    ]);
});

it('honours UNTIL', function() {
    $rule = new RecurrenceRule(
        dt('2026-07-01 09:00', 'UTC'),
        dt('2026-07-01 10:00', 'UTC'),
        'FREQ=DAILY;UNTIL=20260703T090000Z',
    );

    expect(expandStarts($rule, '2026-07-01 00:00', '2026-08-01 00:00'))->toBe([
        '2026-07-01 09:00',
        '2026-07-02 09:00',
        '2026-07-03 09:00',
    ]);
});

it('clips expansion to the requested window', function() {
    $rule = new RecurrenceRule(
        dt('2026-07-01 09:00', 'UTC'),
        dt('2026-07-01 10:00', 'UTC'),
        'FREQ=DAILY', // infinite
    );

    expect(expandStarts($rule, '2026-07-10 00:00', '2026-07-13 00:00'))->toBe([
        '2026-07-10 09:00',
        '2026-07-11 09:00',
        '2026-07-12 09:00',
    ]);
});

it('removes EXDATE exceptions', function() {
    $rule = new RecurrenceRule(
        dt('2026-07-01 09:00', 'UTC'),
        dt('2026-07-01 10:00', 'UTC'),
        'FREQ=DAILY;COUNT=4',
    );

    $exdates = [dt('2026-07-02 09:00', 'UTC'), dt('2026-07-04 09:00', 'UTC')];

    expect(expandStarts($rule, '2026-07-01 00:00', '2026-08-01 00:00', $exdates))->toBe([
        '2026-07-01 09:00',
        '2026-07-03 09:00',
    ]);
});

it('preserves the all-day flag on occurrences', function() {
    $rule = new RecurrenceRule(
        dt('2026-07-01 00:00', 'UTC'),
        dt('2026-07-02 00:00', 'UTC'),
        'FREQ=WEEKLY;COUNT=2',
        allDay: true,
    );

    $occurrences = (new OccurrenceExpander())->expand($rule, dt('2026-07-01 00:00'), dt('2026-08-01 00:00'));

    expect($occurrences)->toHaveCount(2)
        ->and($occurrences[0]->allDay)->toBeTrue();
});
