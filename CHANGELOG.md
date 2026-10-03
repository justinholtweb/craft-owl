# Release Notes for Owl

## 5.2.1 - 2026-10-03

### Security

- **The ICS feeds can be filtered.** `Ics::EVENT_DEFINE_ICS_ROWS` lets a site or a host bundle
  remove occurrences before a calendar or event feed is built — the JSON feed already had
  `EVENT_DEFINE_FEED_ITEMS`, but the ICS feeds, also anonymous, had nothing, so an event hidden
  from some visitors still appeared in every calendar app subscribed to its calendar. A single
  event's feed answers 404 when every occurrence is filtered out, rather than an empty feed with
  the event's title in it.
- **`events.json` answers for at most 400 days and 2,000 occurrences.** It accepted any range —
  a request with none returned two years, and one for a decade returned every occurrence on the
  site, from an anonymous endpoint.
- **A calendar's colour must be a colour.** It was any string, drawn as an inline style in the
  control panel.

### Fixed

- **The events index showed no event names.** `defineDefaultTableAttributes()` omitted `title`, so
  out of the box the screen listed a calendar name and two dates per row and never said which event
  each row was. `title` is available in the column picker, but the default has to include it.

## 5.2.0 - 2026-07-25

### Added
- A mount seam, so Owl can run as an internal module of a host bundle plugin instead of as
  its own installed plugin: a `mountedUnderShowtime` flag plus a `bootFeatures()` /
  `bootChrome()` split, where the host takes over the control-panel nav, settings screen and
  permission heading, and everything else — element types, project-config handlers, CP and
  site routes, GraphQL, Commerce ticketing — boots identically. Standalone behavior is
  unchanged; the flag defaults to false.
- `Owl::permissionDefinitions()` exposes Owl's permissions so a host bundle can list them
  under a single combined heading instead of one heading per bundled plugin. The permission
  keys are unchanged, so existing user groups keep working. Installed standalone, Owl
  registers its own "Owl" heading exactly as before.
- Owl now refuses to install on a site where a host bundle that already includes it is
  installed. Both copies would register the Event element type and share the `owl_*` tables,
  and uninstalling either would then drop the other's data.
- `FeedController::EVENT_DEFINE_FEED_ITEMS` — other code can now contribute items to the
  calendar feed, so a host bundle (or any plugin) can show its own dated records alongside
  events. Handlers append FullCalendar-shaped items to `FeedItemsEvent::$items`.
  **The feed endpoint is anonymous**, so a handler adding anything non-public must check
  permissions itself; that's documented on the event and the constant.
- `Tickets::registrationsForEmail()` — every ticket someone has bought, newest order first.
  Owl has no attendee table: a registration *is* a completed Commerce order containing a
  ticket line item, so this reads the order tables directly and returns the line item's
  frozen snapshot, which still names the event after the event has been renamed or deleted.
  Matches on the order's email rather than its customer, since guest checkout is the norm
  for tickets. Returns an empty list without Commerce.

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
