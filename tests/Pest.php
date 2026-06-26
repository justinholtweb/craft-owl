<?php

declare(strict_types=1);

/*
 |--------------------------------------------------------------------------
 | Test Case bindings
 |--------------------------------------------------------------------------
 |
 | Unit tests for the recurrence engine are framework-agnostic and need no
 | Craft bootstrap, so they run on plain PHPUnit. Craft-integration (Feature)
 | tests will bind markhuot/craft-pest's TestCase once the companion test
 | site exists.
 |
 */

uses()->group('unit')->in('Unit');

/*
 |--------------------------------------------------------------------------
 | Helpers
 |--------------------------------------------------------------------------
 */

/**
 * Build a DateTimeImmutable in a named timezone — keeps the recurrence tests terse.
 */
function dt(string $datetime, string $tz = 'UTC'): DateTimeImmutable
{
    return new DateTimeImmutable($datetime, new DateTimeZone($tz));
}
