<?php

declare(strict_types=1);

use justinholtweb\owl\ics\IcsBuilder;

it('builds a VCALENDAR containing a VEVENT', function() {
    $ics = (new IcsBuilder())->build('Concerts', [
        [
            'uid' => 'owl-1-100@example.com',
            'title' => 'Jazz Night',
            'start' => dt('2026-07-01 18:00'),
            'end' => dt('2026-07-01 20:00'),
            'allDay' => false,
            'url' => 'https://example.com/events/jazz-night',
        ],
    ]);

    expect($ics)
        ->toContain('BEGIN:VCALENDAR')
        ->toContain('BEGIN:VEVENT')
        ->toContain('SUMMARY:Jazz Night')
        ->toContain('UID:owl-1-100@example.com')
        ->toContain('END:VEVENT')
        ->toContain('END:VCALENDAR');
});

it('emits one VEVENT per occurrence', function() {
    $events = [];
    foreach ([1, 2, 3] as $day) {
        $events[] = [
            'uid' => "owl-1-{$day}@example.com",
            'title' => 'Stand-up',
            'start' => dt("2026-07-0{$day} 09:00"),
            'end' => dt("2026-07-0{$day} 09:30"),
        ];
    }

    $ics = (new IcsBuilder())->build('Team', $events);

    expect(substr_count($ics, 'BEGIN:VEVENT'))->toBe(3);
});

it('renders all-day occurrences as DATE values', function() {
    $ics = (new IcsBuilder())->build('Holidays', [
        [
            'uid' => 'owl-2-1@example.com',
            'title' => 'Independence Day',
            'start' => dt('2026-07-04 00:00'),
            'end' => dt('2026-07-05 00:00'),
            'allDay' => true,
        ],
    ]);

    expect($ics)->toContain('VALUE=DATE');
});

it('emits all-day dates as floating (no TZID) on the exact date given', function() {
    // The feed hands the builder floating UTC-midnight dates (see DisplayInstant); a DATE value
    // must not carry a TZID (RFC 5545) and must render the date it was given, verbatim.
    $ics = (new IcsBuilder())->build('Holidays', [
        [
            'uid' => 'owl-2-2@example.com',
            'title' => 'All Day',
            'start' => dt('2026-07-19 00:00'),
            'end' => dt('2026-07-20 00:00'),
            'allDay' => true,
        ],
    ]);

    expect($ics)
        ->toContain('DTSTART;VALUE=DATE:20260719')
        ->toContain('DTEND;VALUE=DATE:20260720')
        // A DATE value must be floating — no TZID parameter on the DTSTART/DTEND properties.
        ->not->toContain('DTSTART;TZID')
        ->not->toContain('DTEND;TZID');
});
