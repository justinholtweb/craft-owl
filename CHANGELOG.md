# Release Notes for Owl

## 5.1.0 - 2026-07-19

### Added
- Framework-agnostic recurrence engine (`RecurrenceRule`, `Occurrence`, `OccurrenceExpander`)
  with RFC 5545 RRULE expansion, EXDATE exceptions, window overlap handling, and DST-correct
  wall-clock semantics.
- Plugin scaffolding: editions (Lite/Pro), Pest unit suite, ECS, PHPStan, Rector, and CI.
- Per-order ticket capacity validation: a single cart can no longer request more tickets than a
  capacity-limited ticket has remaining.

### Fixed
- All-day events could render on the previous day in the ICS feed and the FullCalendar JSON feed
  for timezones east of UTC. All-day occurrences are now emitted as floating calendar dates on the
  event's local date (`DisplayInstant`).
- Adding a ticket that failed to save (e.g. a duplicate SKU) reported "Ticket added" while nothing
  was persisted. The control panel now surfaces the validation error instead.
- The nightly `owl/maintenance/regenerate` command aborted entirely if a single event had an
  invalid recurrence rule, leaving later events' horizons un-rolled. Failures are now logged and
  skipped, and the command exits non-zero to signal them.
- `craft.owl.upcoming()` materialised every occurrence in the window before slicing; the row limit
  is now applied in SQL.

### Changed
- The local DDEV environment is no longer tracked in the repository.
