# Prior-Seasons / Season-List Audit

Audit performed 2026-05-11. Compares [SEASON_LIST.md](SEASON_LIST.md) against the 10 decade-summary posts that back the `/prior-seasons` page.

## 1. Why show lists appeared duplicated on some pages

There were two unrelated causes of visible duplication:

### a. Duplicate decade posts in the WordPress DB

Six decade-summary pages had been imported twice (with identical content but different `_migration_legacy_url` values — one from `/blog/2015/YYYY-YYYY`, one from `/blog/tag/YYYY-YYYY`). Both copies were `post_status=publish`, so blog category/tag archive pages listed them side-by-side as if they were separate posts.

| Decade | Canonical (kept) | Duplicate (drafted) |
|---|---|---|
| 1918-1930 | 1078 | 1174 |
| 1940-1950 | 1076 | 1180 |
| 1990-2000 | 1071 | 1178 |
| 2000-2010 | 1070 | 1181 |
| 2012-2013 | 1067 | 1043 |
| 2012-2013 (extra "shows" stubs) | — | 1159, 1166 |

**Action taken:** Duplicate post IDs were set to `post_status='draft'` (not deleted) AND their `post_name` was suffixed with `-dup-draft` (e.g. `2000-2010-dup-draft`). Each has a `_audit_unpublished_reason` postmeta noting why. To resurrect, restore both the slug and the status.

> **Important sub-issue:** Just setting status to draft was not enough. Because both copies shared the same `post_name` (the migration script inserted them directly via SQL, bypassing WordPress's auto-suffix-on-conflict), WP's URL resolver returned 404 on `/1918-1930/`, `/1940-1950/`, `/1990-2000/`, and `/2000-2010/` even with the duplicate set to draft. Renaming the draft slugs to `…-dup-draft` freed up the canonical slugs and those four pages now resolve.

### b. `/prior-seasons` listed "2010-2020" twice

[page-prior-seasons.php](wordpress/themes/tlt/page-prior-seasons.php) ran a main `WP_Query` for decade-summary posts and *then* a second `$extra_q` to also include the `2010-2020` slug — which the main query already returned. So 2010-2020 appeared as two list items.

**Action taken:** Added a one-pass dedupe by title in [page-prior-seasons.php](wordpress/themes/tlt/page-prior-seasons.php) right before the sort.

---

## 2. The 2010-2020 decade page was essentially empty

Post ID 1271 only contained the placeholder text "*From earlier in the decade, brief summaries of show seasons:*" — no actual list. The data needed to rebuild it was already in the DB but spread across three different post types:

- 2010-2011, 2011-2012, 2012-2013 → single-year summary posts (IDs 1069, 1068, 1067) with poster + description per show
- 2013-2014 through 2017-2018 → individual `tlt_show` posts in those season terms
- 2018-2019 → individual `post`-type entries (IDs 1095-1101) — full scraped Squarespace pages with cast, dates, synopsis, review links
- 2019-2020 → individual `post`-type entries (IDs 1079, 1082, 1087-1094) — same level of detail

**Action taken:** Rebuilt post 1271 with all 10 sub-seasons. Each show has a poster image (floated right), title (linked to its own page from 2013-14 onwards), date range, and synopsis pulled from the original scraped content. *Terms of Endearment* and *The Manchurian Candidate* (2019-2020) are marked "Cancelled — COVID-19" inline.

No `[VERIFY]` flags were needed in the end — every show in the decade had real content somewhere in the DB; nothing here is speculative.

---

## 3. SEASON_LIST.md typos that looked like duplicate seasons

`build_season_list.py` derives season-year boundaries from program-PDF filenames. A handful of PDFs had filename typos that the builder turned into spurious single-show "season" sub-headings right under the real one — so the page rendered what looked like the same season twice:

| Spurious heading (before) | Show | Merged into |
|---|---|---|
| `Season 23: 1940-1940` | Tovarich | `Season 23: 1940-1941` |
| `Season 46: 1963-1934` | A Far Country | `Season 46: 1963-1964` |
| `Season 53: 1970-1972` | A Case of Libel | `Season 53: 1970-1971` |
| `Season 65: 1982-1893` | Applause | `Season 65: 1982-1983` |
| `Season 68: 1985-1886` | Lion in Winter | `Season 68: 1985-1986` |

Also fixed in the same pass:
- `Season 41: 1958-1958` → `1958-1959` (single-year typo, no content change)
- **Season 47 / Season 48 split.** `Season 47: 1964-1965` listed 13 shows; the decade-summary page (which is the authoritative source for show→year mapping for old seasons) groups 7 of them under 1965-66. The 6 extra shows (*Love From a Stranger*, *Dear Charles*, *A Shot in the Dark*, *Daughter of Silence*, *Mary Mary*, *The Music Man*) were moved from Season 47 to Season 48 in SEASON_LIST.md.

Underlying root cause is `build_season_list.py` reading the typo'd PDF filenames literally. If the file is regenerated, the typos will return — either fix the source PDFs' filenames or have the builder collapse same-`Season-N` consecutive headings.

---

## 4. Per-decade comparison vs. SEASON_LIST.md

Across decades 1918→2009, every remaining difference between the decade-summary page and SEASON_LIST.md falls into one of these buckets:

- **Punctuation / capitalization** (`WHO'S AFRAID OF VIRGINIA WOOLF` vs `Whos Afraid of Virgnia Woolf`).
- **PDF-filename leakage** in SEASON_LIST.md — e.g. `The Sleeping Prince 7xkg`, `Joseph and the Amazing Technicolor Dreamcoat 2lgn`, `Three Workshop Plays lz5h`. The random suffix is Squarespace's duplicate-slug salting on the PDF upload, not part of the show name. The decade post shows the real titles.
- **Modern-era misnames in PDF filenames** — `2009-2010-Over-the-River-Through-the-Woods.pdf` is *Over the River **and** Through the Woods*; `2002-2003-Hello-Dolly.pdf` is *Hello, Dolly!*; etc. Decade post titles match.

No shows appeared to be in the genuinely wrong year-section on the decade posts. The "wrong year" appearance the user noticed was almost certainly the Season-47/48 split bug above plus the spurious-typo'd subheadings.

---

## 5. Things I deliberately did NOT change

- The 1925 / 1926-27 / 1927-28 / 1928-29 sections of the 1918-1930 post use the same year-section style as Squarespace had them — I didn't reshape these into "1925-1926" / "1926-1927" full-season form because the underlying schedule structure for that era really was per-calendar-year (TLT didn't always run a fall-spring season then). The original phrasing is preserved.
- I did **not** touch the year-specific posts 1067 (2012-2013), 1068 (2011-2012), or 1069 (2010-2011). They aren't linked from `/prior-seasons` but their content is intact and was used to populate the new 2010-2020 page; the user may want to permalink or trash them separately.
- I did **not** delete any duplicate posts, only drafted them. To purge permanently after review:
  ```sql
  DELETE p, pm FROM wp_posts p LEFT JOIN wp_postmeta pm ON pm.post_id=p.ID
  WHERE p.ID IN (1043, 1159, 1166, 1174, 1178, 1180, 1181);
  ```

## 6. Outstanding things to verify by hand

- Show running order within seasons. I sorted by `show_open_date` where available; for seasons without dates I used the order they appear in the season taxonomy. A few 2013-15 productions lack date metadata so their order is a guess — eyeball the rebuilt page and re-arrange any that are visibly out of order.
- The 2013-2014 season currently lists 5 shows. The Squarespace 95th-Season landing page mentioned a 6th — confirm whether anything is missing.
- *Sophie* (2018-2019): the triage note flagged it as "not mainstage so not really sure where it goes." It's currently NOT included in the 2010-2020 page. Decide whether to include side-stage productions.
- Squarespace "smart quote" / non-breaking-space characters in scraped content sometimes render as `�` because the original HTML was Latin-1 inside UTF-8. The rebuilt page inherits these; they'll need a search-and-replace pass (`�` → `'` or `"` depending on context).
