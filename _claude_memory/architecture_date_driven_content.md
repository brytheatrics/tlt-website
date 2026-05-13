# Architecture — Date-driven content (universal rule)

Every "this needs to change at time X" task on the TLT site is a date field on the content. Required field, not optional.

## Examples

- Show `open_date` / `close_date` → drives hero "Now Playing" vs "Coming Soon", season grid, status badges, prior-season archiving
- Audition `end_date` → auto-archives audition row
- Promotion `start_date` / `end_date` → auto-shows/hides banner
- Season `ticket_sales_end_date` → swaps homepage CTA from Season Tickets to Individual Tickets
- Splash gallery (per show + currently-running test) → auto-shows/hides splash page

## Reasoning

Squarespace mess came partly from no enforcement of stale content. Date-driven content shifts the burden from "Chris remembers to update" to "system enforces correctness." Chris just has to set the date correctly when creating content.

## Implementation rule

When building any form / admin screen for time-bounded content, the end date is **required**. Form does not save without it. Add a sensible default when possible (audition end date = one week before show open; season ticket end = mid-season; etc.).
