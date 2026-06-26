<?php

declare(strict_types=1);

use justinholtweb\owl\recurrence\OccurrenceExpander;
use justinholtweb\owl\recurrence\RecurrenceRule;

/**
 * Complex RFC 5545 rules. All in UTC so the expected dates are unambiguous — DST behaviour is
 * covered separately in RecurrenceDstTest.
 *
 * @return string[] occurrence start dates 'Y-m-d'
 */
function complexDates(string $rrule, string $start, string $from, string $to): array
{
    $rule = new RecurrenceRule(dt($start, 'UTC'), dt($start, 'UTC')->modify('+1 hour'), $rrule);
    $occurrences = (new OccurrenceExpander())->expand($rule, dt($from), dt($to));

    return array_map(fn($o) => $o->start->format('Y-m-d'), $occurrences);
}

it('expands the last Saturday of each month', function() {
    // 2026-01-31 is the last Saturday of January 2026.
    expect(complexDates('FREQ=MONTHLY;BYDAY=-1SA;COUNT=3', '2026-01-31 09:00', '2026-01-01 00:00', '2026-06-01 00:00'))
        ->toBe(['2026-01-31', '2026-02-28', '2026-03-28']);
});

it('expands a specific day of the month with BYMONTHDAY', function() {
    expect(complexDates('FREQ=MONTHLY;BYMONTHDAY=15;COUNT=3', '2026-01-15 09:00', '2026-01-01 00:00', '2026-06-01 00:00'))
        ->toBe(['2026-01-15', '2026-02-15', '2026-03-15']);
});

it('expands a yearly rule', function() {
    expect(complexDates('FREQ=YEARLY;COUNT=3', '2026-03-10 09:00', '2026-01-01 00:00', '2030-01-01 00:00'))
        ->toBe(['2026-03-10', '2027-03-10', '2028-03-10']);
});

it('expands the first Monday of each month with BYSETPOS', function() {
    // 2026-01-05 is the first Monday of January 2026.
    expect(complexDates('FREQ=MONTHLY;BYDAY=MO;BYSETPOS=1;COUNT=3', '2026-01-05 09:00', '2026-01-01 00:00', '2026-06-01 00:00'))
        ->toBe(['2026-01-05', '2026-02-02', '2026-03-02']);
});

it('expands an every-other-week rule on two weekdays', function() {
    // 2026-07-01 is a Wednesday. Interval 2 weeks, on Mon + Wed.
    expect(complexDates('FREQ=WEEKLY;INTERVAL=2;BYDAY=MO,WE;COUNT=4', '2026-07-01 09:00', '2026-07-01 00:00', '2026-09-01 00:00'))
        ->toBe(['2026-07-01', '2026-07-13', '2026-07-15', '2026-07-27']);
});

it('preserves a multi-hour duration on every occurrence', function() {
    $rule = new RecurrenceRule(dt('2026-07-01 09:00'), dt('2026-07-01 11:30'), 'FREQ=DAILY;COUNT=3');
    $occurrences = (new OccurrenceExpander())->expand($rule, dt('2026-07-01 00:00'), dt('2026-08-01 00:00'));

    foreach ($occurrences as $occurrence) {
        $minutes = intdiv($occurrence->end->getTimestamp() - $occurrence->start->getTimestamp(), 60);
        expect($minutes)->toBe(150);
    }
});

it('preserves a multi-day span on a recurring occurrence', function() {
    // A two-day, eight-hour event: Wed 09:00 -> Fri 17:00, repeating weekly.
    $rule = new RecurrenceRule(dt('2026-07-01 09:00'), dt('2026-07-03 17:00'), 'FREQ=WEEKLY;COUNT=2');
    $occurrences = (new OccurrenceExpander())->expand($rule, dt('2026-07-01 00:00'), dt('2026-08-01 00:00'));

    expect($occurrences)->toHaveCount(2)
        ->and($occurrences[0]->start->format('Y-m-d H:i'))->toBe('2026-07-01 09:00')
        ->and($occurrences[0]->end->format('Y-m-d H:i'))->toBe('2026-07-03 17:00')
        ->and($occurrences[1]->start->format('Y-m-d H:i'))->toBe('2026-07-08 09:00')
        ->and($occurrences[1]->end->format('Y-m-d H:i'))->toBe('2026-07-10 17:00');
});

it('returns no occurrences when the window misses the rule entirely', function() {
    $rule = new RecurrenceRule(dt('2026-07-01 09:00'), dt('2026-07-01 10:00'), 'FREQ=DAILY;COUNT=5');
    $occurrences = (new OccurrenceExpander())->expand($rule, dt('2027-01-01 00:00'), dt('2027-02-01 00:00'));

    expect($occurrences)->toBe([]);
});
