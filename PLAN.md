# Owl — Craft CMS 5 Events & Calendar Plugin

> A comprehensive, well-tested, well-documented paid events/calendar plugin for Craft CMS 5
> that competes with Solspace Calendar — better multisite, native Commerce ticketing, and a
> more generous free tier — under the `justinholtweb` namespace.

- **Package:** `justinholtweb/craft-owl`
- **Handle:** `owl`
- **Root namespace:** `justinholtweb\owl`
- **Plugin class:** `justinholtweb\owl\Owl`
- **CP nav icon:** an owl 🦉

---

## 1. Strategic positioning

### Competitor landscape (verified June 2026)

| Product | Craft 5? | Price | Notes |
|---|---|---|---|
| **Solspace Calendar** | ✅ active, market leader | **$99 Lite / $199 Pro** (renew ~50%/yr) | 2,227 installs. Paywalls recurrence, ICS, and front-end submission behind the $199 Pro tier. Recurring **multisite + Matrix bugs** in their issue tracker. |
| **Verbb Events** | ✅ active | free + paid | Ticketing-first (Sessions + Ticket purchasables). The architectural model to study, but more "box office" than "calendar." |
| **Venti / Venti 2** | ⚠️ stale, Craft 5 uncertain | Gumroad | Aging; not actively maintained. |
| **DIY (Entries + date field)** | ✅ | free | The real "free alternative" most devs fall back to. |

**There is no actively-maintained _free_ native calendar plugin for Craft 5.** That is the gap.

### How Owl wins

1. **Unbundle what Solspace paywalls.** Recurrence, exceptions, ICS export/subscribe, and front-end
   event submission ship in Owl's **free** edition. Those are exactly the features Solspace charges
   $199 for and that users complain about being paywalled.
2. **Undercut on price.** Owl **Pro** (Commerce ticketing) at **$149** beats Solspace Pro ($199),
   and Owl **Lite is free** vs. their $99.
3. **Harden the two things they get wrong:** multisite and Matrix/nested-field handling.
4. **Native Commerce ticketing** as a first-class Pro feature — Solspace has none; you'd otherwise
   reach for Verbb. Owl does both calendar and box office.
5. **Maintained, clean major-version migrations** with Rector rulesets — differentiates from Venti.

### Recommended editions & pricing

| | **Owl Lite** (free) | **Owl Pro** ($149, renew $74/yr) |
|---|---|---|
| Event element, unlimited calendars, per-calendar field layouts | ✅ | ✅ |
| Recurrence (RRULE) + exceptions + single-occurrence overrides | ✅ | ✅ |
| Month/week/day/list views, FullCalendar demo templates | ✅ | ✅ |
| Twig query API + GraphQL | ✅ | ✅ |
| ICS export + subscription feeds | ✅ | ✅ |
| Front-end event submission | ✅ | ✅ |
| Multisite | ✅ | ✅ |
| **Commerce ticketing** (Ticket purchasable, ticket types, capacity) | — | ✅ |
| **Registration/RSVP** (non-Commerce attendee capture) | — | ✅ |
| Advanced inventory, multi-location seating, waitlists | — | ✅ |
| Dashboard widgets, CSV/ICS import | — | ✅ |

> Free Lite drives adoption and reviews (Solspace has only 4 reviews — review volume is itself a
> moat). Pro monetizes the Commerce audience. Craft takes a 20% processing fee on Pro sales.
> _Pricing is a recommendation — easy to adjust before launch._

---

## 2. Architecture decisions (the load-bearing ones)

### 2.1 Element model

```
Calendar (config model, has its own field layout + color + ICS settings + ticketing toggle)
  └─ Event  (custom element — the container: title, body, location, RRULE, timezone)
       ├─ Occurrence  (materialized DB rows — concrete start/end instants, fast to query)
       └─ Ticket  (custom PURCHASABLE element — Pro/Commerce only, one per occurrence × ticket type)
```

- **`Event`** extends `craft\base\Element`. First-class element (CP index, slideout editor,
  drafts/revisions, reference tags, relations). Registered via `Elements::EVENT_REGISTER_ELEMENT_TYPES`.
- **`Calendar`** is a config model (like Commerce product types / Solspace calendars) carrying a
  `FieldLayoutBehavior` → **per-calendar field layouts**, color, default site visibility, and a
  "sell tickets" flag. Stored in Project Config.
- **`Occurrence`** = plain rows in `{{%owl_occurrences}}`, **not** elements (avoids per-site row
  explosion in multisite; see 2.3). They carry `eventId`, `startDate`, `endDate`, `allDay`,
  `siteId`-agnostic instant, and a `isException`/`isOverride` flag.
- **`Ticket`** extends `craft\commerce\base\Purchasable` **and** is an element (Pro only). One per
  (occurrence × ticket type). Mirrors Verbb Events + Commerce's product/variant split.

### 2.2 Recurrence: materialize occurrences (chosen over expand-at-query)

Two industry patterns exist:

- **Pattern A — RRULE + expand at query time** (Solspace). Tiny storage, but range queries and
  pagination must expand+merge+sort in PHP; doesn't paginate in SQL; slow with many long rules.
- **Pattern B — Materialized occurrence rows** (Verbb). Range queries/pagination/sorting are plain
  indexed SQL; per-occurrence overrides and ticket inventory are natural; cost is storage + a regen
  job. ✅ **Chosen** — it's the only model that pages cleanly **and** integrates with Commerce.

**Design:**
- Store the canonical **RFC 5545 RRULE** string on the Event (for editing, ICS export, previewing).
- A queue job (`GenerateOccurrencesJob`) expands the RRULE up to a **rolling horizon** (default +2
  years, configurable) into `{{%owl_occurrences}}`, re-running on event save and on a daily cron to
  roll the horizon forward.
- **Exceptions (EXDATE):** stored in `{{%owl_exceptions}}`; excluded from generation.
- **Single-occurrence overrides:** a row that overrides one date's time/title/cancelled/sold-out
  state without detaching the rule.
- Library: **`rlanvin/php-rrule`** — fast, supports range-bounded iteration (`occurrences(between)`),
  `EXDATE`, infinite-set handling, and `RSet`. (Faster than `simshaun/recurr` for long/infinite
  ranges; recurr only wins on human-readable text, which we'll do ourselves.)
- **DST/timezone:** store an explicit **IANA timezone** + original local wall-clock on the Event.
  RFC 5545 recurrence is wall-clock local ("every day at 09:00" stays 09:00 across DST). Expand in
  the event's tz, then convert to UTC for the occurrence `DATETIME` columns. **Explicitly test
  spring-forward (skipped 02:30) and fall-back (doubled hour).**

### 2.3 Multisite (where Solspace is weak — make this a strength)

- `Event::isLocalized()` → `true`; override `getSupportedSites()` to scope to sites where the
  event's calendar is enabled (return `['siteId' => N, 'enabledByDefault' => bool]` shapes).
- **Dates/RRULE/timezone are NOT translatable** — an event happens at one instant regardless of
  language. Keep them on the **shared canonical-keyed** `{{%owl_events}}` table. Only title/body/
  slug vary per site (Craft 5 stores those in `elements_sites`; there is no `content` table anymore).
- **Occurrences stay as plain DB rows** keyed by canonical event ID → no per-site multiplication.
- In `afterSave()` (called once per supported site), use `$this->propagating` to write shared
  columns only on the canonical pass.
- Timezone is global, never tied to site locale.

### 2.4 Commerce ticketing (Pro)

- **`Ticket` extends `craft\commerce\base\Purchasable`** (not the raw interface — you get
  `getSalePrice()` + unique-SKU validation free). Implement `getPrice()`, `getSku()`,
  `getDescription()`, `getSnapshot()` (freeze event title/date/ticket-type so orders survive event
  edits/deletion), tax/shipping category, `getIsAvailable()`, `populateLineItem()`,
  `afterOrderComplete()` (decrement capacity transactionally).
- **Ticket Types** = reusable config (Adult/Child/VIP) with price + capacity, assignable per event.
- **Capacity:** manage on the Ticket element, gate via `getIsAvailable()`, decrement in
  `afterOrderComplete()` inside a transaction; re-check at `beforeCompleteOrder` to avoid oversell
  under concurrency. (Optionally integrate Commerce 5 location-based inventory later.)
- **Commerce is a _soft_ Pro dependency** — don't hard-require it in `composer.json`; detect at
  runtime and degrade to calendar-only when absent.
- Register via `Purchasables::EVENT_REGISTER_PURCHASABLE_ELEMENT_TYPES`.

### 2.5 Front-end tooling

- **Twig:** `craft.owl.events({...})` via a behavior on `CraftVariable`. Fluent query over the
  occurrence table (native SQL pagination). Params mirror Solspace for easy migration: `calendar`,
  `rangeStart`/`rangeEnd`, `startsAfter`/`endsBefore`, `allDay`, `search`, `relatedTo`, `limit`,
  `orderBy`, plus `.groupedByMonth()/Week()/Day()` helpers and a `loadOccurrences`-style toggle.
- **GraphQL:** `EventInterface` + type + type generator (per-calendar field layouts → contextual
  types); register `events`/`eventCount` queries and schema components.
- **FullCalendar:** a range-param JSON controller action (reads FullCalendar's `start`/`end`,
  returns only occurrences in window — one indexed query). Demo templates installable from settings,
  matching Solspace's one-click demo UX.
- **ICS:** `spatie/icalendar-generator`; emit the **stored RRULE** for recurring events (clients
  expand natively) plus per-event `.ics` download and subscribable feed URLs.

---

## 3. Module / directory layout

```
craft-owl/
├─ composer.json                 # type: craft-plugin, editions: lite/pro, soft commerce dep
├─ CHANGELOG.md  LICENSE.md  README.md
├─ src/
│  ├─ Owl.php                    # main plugin: init(), editions, event wiring, services
│  ├─ models/
│  │  ├─ Settings.php            # horizon, default tz, demo template options
│  │  ├─ Calendar.php            # config model + FieldLayoutBehavior
│  │  └─ TicketType.php
│  ├─ elements/
│  │  ├─ Event.php  + db/EventQuery.php
│  │  ├─ Ticket.php (Purchasable) + db/TicketQuery.php
│  │  └─ conditions/…            # element condition rules
│  ├─ records/                   # ActiveRecords for events, occurrences, exceptions, calendars, tickets
│  ├─ services/
│  │  ├─ Calendars.php  Events.php  Occurrences.php  Recurrence.php (rrule)
│  │  ├─ Ics.php  Tickets.php (Pro)  Registrations.php (Pro)
│  ├─ jobs/GenerateOccurrencesJob.php
│  ├─ controllers/               # cp: EventsController, CalendarsController; site: EventsController (submission), IcsController, ApiController (FullCalendar json)
│  ├─ gql/  variables/  behaviors/  fieldlayoutelements/
│  ├─ migrations/Install.php + content migrations
│  ├─ web/twig/  templates (CP)  + resources (CP JS/CSS)
│  └─ translations/en/
├─ docs/                         # full documentation site (see §6)
└─ tests/                        # Pest (see §5)
```

**DB schema (core tables):**
- `owl_calendars` (id, name, handle, color, fieldLayoutId, hasTickets, settings json, uid)
- `owl_events` (id PK=elementId, calendarId, startDate, endDate, allDay, timezone, rrule, repeating, postDate, …) — **shared, not per-site**
- `owl_occurrences` (id, eventId, startDate, endDate, allDay, isException, isOverride, overrideData json) — indexed on (startDate, endDate, eventId)
- `owl_exceptions` (id, eventId, date)
- `owl_ticket_types`, `owl_tickets` (Pro), `owl_registrations` (Pro)

---

## 4. Build phases (milestones)

**Phase 0 — Scaffolding (½ wk).** `pluginfactory.io` skeleton → rename to `justinholtweb/craft-owl`,
editions, ECS/PHPStan/Rector/Pest wired, GitHub Actions CI matrix (PHP 8.2/8.3 × Craft 5.x), DDEV
test site at `craft-owl.ddev.site`.

**Phase 1 — Calendars + Event element (1.5 wk).** Calendar config model + Project Config, per-calendar
field layouts, Event element, EventQuery, CP index + slideout editor, drafts/revisions, permissions.

**Phase 2 — Recurrence engine (2 wk).** RRULE storage, `Recurrence` service over `rlanvin/php-rrule`,
`GenerateOccurrencesJob` + rolling horizon cron, exceptions, single-occurrence overrides, **DST test
suite**. _Highest-risk phase — schedule first after the element exists._

**Phase 3 — Front-end (1.5 wk).** `craft.owl.events()` Twig API, FullCalendar JSON endpoint, demo
templates (month/week/day/list), ICS export + subscription feeds via spatie.

**Phase 4 — GraphQL + front-end submission (1 wk).** Interface/type/generator, queries, schema
components; front-end event submission controller with validation + spam protection.

**Phase 5 — Multisite hardening (1 wk).** Supported-sites scoping, propagation correctness, the
Matrix/nested-field edge cases Solspace fails, migration-from-Solspace import path.

**Phase 6 — Commerce ticketing, Pro (2 wk).** Ticket purchasable + ticket types, capacity/oversell,
cart/checkout flow, order snapshot, attendee/registration capture, dashboard widgets.

**Phase 7 — Docs, polish, store submission (1.5 wk).** Full docs site, demo content, marketing site
(`craft-owl-website`, matching your existing plugin-site family), Plugin Store submission.

_~12 weeks solo. Phases 0–5 = the free Lite launch; 6 = the paid Pro upsell; could ship Lite first._

---

## 5. Testing strategy

- **Framework:** `markhuot/craft-pest` (Pest on PHPUnit) — the current community standard; Codeception
  is deprecated for Craft 5.
- **Unit:** recurrence expansion (weekly/monthly/by-day/interval/until/count), EXDATE exclusion,
  overrides, and **DST spring-forward/fall-back** (dedicated cases), timezone conversion, ICS output
  validation against the persisted RRULE.
- **Feature:** Event CRUD + occurrence regeneration, EventQuery range/pagination/sorting, multisite
  propagation (shared dates, per-site title/slug), front-end submission, FullCalendar JSON shape,
  GraphQL queries.
- **Commerce (Pro):** add-to-cart, capacity decrement, oversell-under-concurrency guard, order
  snapshot survives event deletion, refund restores capacity.
- **CI:** GitHub Actions — ECS (check), PHPStan, Rector dry-run, Pest across the PHP/Craft matrix.

---

## 6. Documentation

A docs site mirroring your existing `craft-*-website` family (Craft 5 + DDEV + Vite + Tailwind +
Alpine), plus in-repo `docs/`:

- **Getting started:** install, editions, settings, demo templates.
- **Concepts:** calendars, events, occurrences, recurrence, exceptions/overrides, timezones.
- **Templating:** full `craft.owl.events()` reference (params table like Solspace's), grouping
  helpers, pagination, FullCalendar recipe, ICS feeds.
- **GraphQL** reference. **Multisite** guide. **Migrating from Solspace Calendar** (parameter map +
  importer) — a deliberate conversion funnel.
- **Commerce/ticketing (Pro):** ticket types, capacity, checkout, registrations, order data.
- **Extending:** events/hooks, custom field-layout elements, the service API.

---

## 7. Key risks & mitigations

| Risk | Mitigation |
|---|---|
| Recurrence/DST correctness (hardest part) | Materialized model + `rlanvin/php-rrule` + explicit DST test matrix; expand in IANA tz, store UTC. |
| Occurrence table growth (long/infinite rules × multisite) | Occurrences are plain rows (not per-site elements); rolling +2yr horizon, not infinite; cron rolls forward. |
| Multisite propagation bugs (Solspace's weak spot) | Dates on shared table only; `propagating` guard; dedicated multisite + Matrix test suite. |
| Commerce as hard dependency | Soft dependency; runtime detection; Pro gated; calendar-only degrades gracefully. |
| Oversell under concurrency | Transactional decrement + re-check at `beforeCompleteOrder`. |
| Migration churn across Craft versions | Rector rulesets in CI; clean upgrade migrations (differentiator vs Venti). |

---

## 8. Confirmed decisions (2026-06-26)

1. **Editions:** **Free Lite + $149 Pro** (renew ~$74/yr). Lite is genuinely free and includes
   recurrence, ICS, front-end submission, multisite, and GraphQL; Pro adds Commerce ticketing.
2. **v1 scope:** **Full Lite + Pro at launch** — all of Phases 0–6 ship in v1.0.0 (Commerce ticketing
   included from day one). No Lite-only soft launch.
3. **Marketing site:** **Yes — build `craft-owl-website`** alongside, matching the existing
   `craft-*-website` family (Craft 5 + DDEV + Vite + Tailwind + Alpine), including docs and a
   dedicated **"Migrating from Solspace Calendar"** funnel page.
