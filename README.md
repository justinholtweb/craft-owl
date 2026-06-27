# Owl 🦉

A comprehensive events and calendar plugin for **Craft CMS 5** — recurrence, multisite, ICS
feeds, a frontend query API, GraphQL, and native Commerce ticketing.

Owl is a maintained, fairly-priced alternative to Solspace Calendar: the recurrence, ICS, and
front-end submission features other plugins paywall are included in the **free Lite** edition, and
the **Pro** edition ($149) adds first-class Craft Commerce ticketing.

## Requirements

- Craft CMS 5.6.0 or later
- PHP 8.2 or later
- Craft Commerce 5.0+ (optional — only for the Pro edition's ticketing features)

## Editions

| | Lite (free) | Pro |
| --- | --- | --- |
| Event element, unlimited calendars, per-calendar field layouts | ✓ | ✓ |
| Recurrence (RRULE) + exceptions + single-occurrence overrides | ✓ | ✓ |
| Month/week/day/list views, FullCalendar demo templates | ✓ | ✓ |
| Twig query API + GraphQL | ✓ | ✓ |
| ICS export + subscription feeds | ✓ | ✓ |
| Front-end event submission | ✓ | ✓ |
| Multisite | ✓ | ✓ |
| Commerce ticketing (tickets, ticket types, capacity) | — | ✓ |

## Architecture

Owl stores each event's canonical RRULE and **materialises occurrences** into an indexed table
(generated to a rolling horizon by a queue job), so calendar range queries and pagination are plain,
fast SQL — and per-occurrence ticketing/inventory has a real row to attach to. See
[`PLAN.md`](PLAN.md) for the full design.

The recurrence engine (`src/recurrence`) is deliberately framework-agnostic and unit-tested in
isolation, including DST spring-forward/fall-back correctness.

## Front-end

Query events in Twig:

```twig
{% set upcoming = craft.owl.events
    .startsAfter(now)
    .orderBy('startDate ASC')
    .limit(10)
    .all() %}
```

Feed a JS calendar (FullCalendar) from the JSON endpoint, which returns only the occurrences
overlapping the requested range:

```
GET /owl/events.json?start=2026-07-01&end=2026-08-01&calendar=concerts
```

Subscribe to or download ICS:

```
/owl/calendar/<handle>.ics   # a calendar's subscribable feed
/owl/event/<id>.ics          # a single event
```

## Maintenance

Occurrences are materialised up to a rolling horizon (default 24 months). Run the regenerate
command from a daily cron so open-ended recurrence rules keep extending forward:

```cron
0 3 * * * cd /path/to/project && php craft owl/maintenance/regenerate >> /dev/null 2>&1
```

Add `--queue` to push the work onto Craft's queue instead of running it inline (recommended for
sites with many recurring events).

## Development

PHP and Composer run inside DDEV:

```bash
ddev start
ddev composer install --no-plugins   # --no-plugins: the craft-plugin installer expects a host app
ddev exec vendor/bin/pest --testsuite=Unit   # framework-agnostic unit tests (recurrence, ICS)
```

The recurrence engine and ICS builder are framework-agnostic and covered by the Pest unit suite.

The Craft layer (elements, services, queries, occurrence materialisation, timezone/DST storage) is
covered by an integration harness that runs inside a real Craft install:

```bash
ddev craft owl/test/run   # exits non-zero on failure
```

> `markhuot/craft-pest` is not yet compatible with Craft 5.9's Symfony 7 dependencies, so the
> integration coverage is provided via this console harness rather than Pest feature tests.

## License

This plugin is licensed under the [Craft License](LICENSE.md) — the standard license for
commercial Craft CMS plugins. A commercial license is required for use in a production environment.
© 2026 Justin Holt.
