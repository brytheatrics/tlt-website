# TLT Callboard — Phase 1 Scaffolding

Fast PHP proxy over the existing Callboard Google Sheets. Replaces the slow
GAS webapp with a WordPress-hosted PWA that reads Sheets directly via a
service account. Ships with authentication + 5 pilot endpoints + a placeholder
frontend that proves login → API → data round-trip.

The stubs for the other 13 endpoints are registered and return HTTP 501 with
a pointer to the Port Brief. Fill them in one at a time — each is ~20 lines
of "select rows, shape into the expected JSON."

---

## Directory layout

```
wordpress/plugins/tlt-callboard/
├── tlt-callboard.php    # Plugin main — auth, Sheets API wrapper, endpoints
└── README.md            # This file

deploy/callboard-frontend/
└── index.html           # Placeholder PWA shell — login + smoke test
```

The frontend gets deployed to `public_html/callboard/` (served at
`https://tacomalittletheatre.com/callboard/`).

---

## One-time setup on production

### 1. Upload the service account JSON (outside `public_html`)

The service account already exists at
`C:\Users\blake\dev\tlt-callboard\TLT Ludus Sync\tlt-bio-app-562a4e56ec80.json`
(and its client_email already has access to both spreadsheets, since the
Python syncs use it).

SCP it to Cloudways *outside* the web root so it's not fetchable:

```bash
scp -i C:/Users/blake/.ssh/tlt_cloudways \
    "C:/Users/blake/dev/tlt-callboard/TLT Ludus Sync/tlt-bio-app-562a4e56ec80.json" \
    master_vdrkzztcte@64.23.180.12:/home/master_vdrkzztcte/tlt-service-account.json
```

Confirm it's readable by the web user:

```bash
ssh -i C:/Users/blake/.ssh/tlt_cloudways master_vdrkzztcte@64.23.180.12 \
    "ls -l /home/master_vdrkzztcte/tlt-service-account.json"
```

### 2. Verify the service account has access to the sheets

Both should already be shared with the service account's `client_email`
(Python syncs read/write them daily). If a permission error surfaces later:
open each sheet → Share → paste the client_email → give Editor access
(Viewer is enough for Phase 1 read-only, but Editor lets Phase 2 write
without another permission dance).

### 3. Add wp-config.php constants (optional — defaults work)

Defaults in the plugin already point at the current spreadsheet IDs and the
`/home/master_vdrkzztcte/tlt-service-account.json` path. Override only if
you need to point at test sheets or move the JSON.

```php
define( 'TLT_CALLBOARD_SA_JSON',        '/home/master_vdrkzztcte/tlt-service-account.json' );
define( 'TLT_CALLBOARD_SHEET_ID',       '1jMhG2QgyLU_rHQoA2xFALIeAJDNOxXyNHUzMWkT-3ss' );
define( 'TLT_CALLBOARD_CONTACTBOOK_ID', '1qQkqa8_v1Ie3FIPkevH5AUh1DDIY8WTxwL5O-KOf32o' );
```

### 4. Deploy the plugin + frontend

From the repo root:

```bash
SSH_KEY="C:/Users/blake/.ssh/tlt_cloudways"
SSH_USER="master_vdrkzztcte"
SSH_HOST="64.23.180.12"
APP="dtvxxevyxd"
DEST="applications/${APP}/public_html"

# Plugin
tar -czf - -C wordpress/plugins tlt-callboard \
  | ssh -i "$SSH_KEY" "${SSH_USER}@${SSH_HOST}" "cd ${DEST}/wp-content/plugins && tar -xzf -"

# Frontend
ssh -i "$SSH_KEY" "${SSH_USER}@${SSH_HOST}" "mkdir -p ${DEST}/callboard"
scp -i "$SSH_KEY" deploy/callboard-frontend/index.html \
    "${SSH_USER}@${SSH_HOST}:${DEST}/callboard/index.html"
```

### 5. Activate + smoke test

```bash
ssh -i "$SSH_KEY" "${SSH_USER}@${SSH_HOST}" \
    "cd applications/${APP}/public_html && wp plugin activate tlt-callboard"
```

Then visit `https://tacomalittletheatre.com/callboard/` and log in with any
password that has a matching value in the Callboard sheet → Theatre tab, col C.

---

## Adding new endpoints (the pattern)

Each remaining endpoint is roughly:

```php
function tlt_callboard_ep_get_sales( WP_REST_Request $req ) {
    // 1. Fetch the ranges from Sheets.
    $data = tlt_callboard_sheets_get( TLT_CALLBOARD_SHEET_ID, [
        'Sales!A2:H',
        'Dates!A2:H',
    ] );
    if ( is_wp_error( $data ) ) return $data;

    // 2. Transform into the shape Index.html expects. See Port Brief Section 2
    //    for the exact fields each frontend caller uses.
    $rows_by_show = [];
    foreach ( $data['Sales!A2:H'] as $row ) {
        $show = tlt_cb_s( $row[1] ?? '' );
        if ( ! $show ) continue;
        // ... aggregate summary/performance/payment rows per show ...
    }

    // 3. Return with tlt_cb_ok() for the standard envelope.
    return tlt_cb_ok( array_values( $rows_by_show ) );
}
```

Then swap `tlt_callboard_ep_todo` for your new function in the
`rest_api_init` hook.

---

## Frontend port (from Index.html to /callboard/)

Almost all of Index.html copy-pastes. The only mechanical change:

```javascript
// OLD
google.script.run
    .withSuccessHandler(onSuccess)
    .withFailureHandler(onError)
    .getShowRoster(show);

// NEW
api('/show-roster?show=' + encodeURIComponent(show))
    .then(({ data }) => onSuccess(data))
    .catch(onError);
```

The `api()` helper in the placeholder `index.html` already handles auth
tokens, Content-Type, and error normalization. Reuse it.

---

## Caching + edit-to-view latency

- Default TTL: **60 seconds** (constant `TLT_CALLBOARD_CACHE_TTL`).
- Contacts TTL: **10 minutes** (matches existing GAS behavior).
- Sessions: 30 days per token, refreshed on every use.
- Google access tokens: 55 minutes cached.

To bust the cache instantly after editing a sheet, hit any endpoint with
`?force=1` — currently only enforced in `login()`. Extend the pattern to
other endpoints once you know which need it.

---

## Phase 2 (deferred, not touched by this scaffolding)

- All mutations (`saveContact`, `addRole`, `generateContractFromWebapp`, etc.)
- Contract generation via Docs API v1 `documents.batchUpdate`
- Bio compilation via Docs API
- OpenSign integration
- Program (playbill) editor writes

`ContractOrganizer` (separate GAS on `contracts@`) and `TLTBioApp` (separate
GAS webapp) both continue to run untouched. `Ludus` and `CastingManager`
Python syncs also unchanged — they talk to Sheets directly via the same
service account.
