# Future feature — auto-generated calendar

Blake wants a `/calendar/` page that auto-generates from postings in the system, not a separately-edited calendar.

## What feeds it

- **Shows** (mainstage, off_the_shelf, murder_mystery_dinner) — pull from `tlt_show` post type using `show_open_date` and `show_close_date` meta. Each show's run shows as a date range on the calendar.
- **Auditions** — once auditions move to the hub page, each "open audition row" has audition date(s). Those should also appear.
- **Classes / camps** — education events Chris will create using the Designed Page template (Summer Camp, Spring Classes). Need a way to capture start/end dates on those.
- **Special events** — Kaleidoscope, fundraising galas, anything else Chris adds with a date field.

## Implementation thoughts

- A `/calendar/` page that renders month/week view, pulling events from all of the above
- Each event is clickable → links to its source page
- Filter by event type (toggles for "Shows", "Auditions", "Classes", "Special Events")
- Maybe export as iCal / Google Calendar feed for patrons who want to subscribe

## Why this matters

- Single source of truth: dates live on the show / event / audition record, the calendar just renders them. No double-entry.
- If Chris updates a show's close date, the calendar updates automatically.
- Patrons get a single place to see "what's coming up at TLT" across all program types.

## Build priority

Forward-looking. Not blocking launch. Save until core templates are done and content is migrated.
