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

## Development

PHP and Composer run inside DDEV:

```bash
ddev start
ddev composer install --no-plugins   # --no-plugins: the craft-plugin installer expects a host app
ddev exec vendor/bin/pest --testsuite=Unit
```

Integration (Feature) tests run against a companion Craft test site via `markhuot/craft-pest`.

## License

This plugin is licensed under the [Craft License](LICENSE.md) — the standard license for
commercial Craft CMS plugins. A commercial license is required for use in a production environment.
© 2026 Justin Holt.
