<?php

declare(strict_types=1);

use justinholtweb\owl\recurrence\RecurrenceRule;

it('reports the start timezone', function() {
    $rule = new RecurrenceRule(dt('2026-07-01 09:00', 'America/New_York'), dt('2026-07-01 10:00', 'America/New_York'));

    expect($rule->timeZone()->getName())->toBe('America/New_York');
});

it('detects whether it is recurring', function() {
    $single = new RecurrenceRule(dt('2026-07-01 09:00'), dt('2026-07-01 10:00'));
    $repeating = new RecurrenceRule(dt('2026-07-01 09:00'), dt('2026-07-01 10:00'), 'FREQ=DAILY');
    $blank = new RecurrenceRule(dt('2026-07-01 09:00'), dt('2026-07-01 10:00'), '   ');

    expect($single->isRecurring())->toBeFalse()
        ->and($repeating->isRecurring())->toBeTrue()
        ->and($blank->isRecurring())->toBeFalse();
});

it('normalises the rrule by stripping the RRULE: prefix and whitespace', function() {
    $rule = new RecurrenceRule(dt('2026-07-01 09:00'), dt('2026-07-01 10:00'), '  RRULE:FREQ=WEEKLY;COUNT=3 ');

    expect($rule->normalizedRrule())->toBe('FREQ=WEEKLY;COUNT=3');
});

it('returns a null normalised rrule for a single event', function() {
    $rule = new RecurrenceRule(dt('2026-07-01 09:00'), dt('2026-07-01 10:00'));

    expect($rule->normalizedRrule())->toBeNull();
});

it('computes the event duration as a calendar interval', function() {
    $rule = new RecurrenceRule(dt('2026-07-01 09:00'), dt('2026-07-01 11:30'));
    $duration = $rule->duration();

    expect($duration->h)->toBe(2)
        ->and($duration->i)->toBe(30);
});

it('rejects an end that is before the start', function() {
    expect(fn() => new RecurrenceRule(dt('2026-07-01 10:00'), dt('2026-07-01 09:00')))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects a start and end in different timezones', function() {
    expect(fn() => new RecurrenceRule(dt('2026-07-01 09:00', 'America/New_York'), dt('2026-07-01 10:00', 'UTC')))
        ->toThrow(InvalidArgumentException::class);
});
