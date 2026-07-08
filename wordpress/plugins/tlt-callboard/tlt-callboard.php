<?php
/**
 * Plugin Name: TLT Callboard
 * Description: Fast PHP proxy over the existing Callboard Google Sheets. Replaces
 *              the slow GAS webapp frontend with a WordPress-hosted PWA that reads
 *              Sheets directly via the service account.
 * Version:     0.1.0
 * Author:      TLT
 *
 * PHASE 1 SCOPE — read-only. Every mutation is deferred to Phase 2.
 *
 * ARCHITECTURE
 *   - Auth: password lookup against the Callboard "Theatre" tab, col C. Any row
 *     with a non-empty col C can log in. Session = 32-byte random token stored
 *     in WP transient for 30 days, returned to browser + kept in localStorage.
 *   - Sheets: hand-rolled JWT → OAuth2 token → Sheets API v4 batchGet. No composer.
 *   - Cache: WP transients keyed per range. TTL 60s by default. `getContacts()`
 *     mirrors the current GAS 10-min TTL. Explicit invalidation on write (Phase 2).
 *
 * PREREQ (do once on prod):
 *   1. Drop the service account JSON at /home/<master>/tlt-service-account.json
 *      (outside public_html so it's not web-accessible).
 *   2. Add to wp-config.php:
 *          define( 'TLT_CALLBOARD_SA_JSON',        '/home/master_vdrkzztcte/tlt-service-account.json' );
 *          define( 'TLT_CALLBOARD_SHEET_ID',       '1jMhG2QgyLU_rHQoA2xFALIeAJDNOxXyNHUzMWkT-3ss' );
 *          define( 'TLT_CALLBOARD_CONTACTBOOK_ID', '1qQkqa8_v1Ie3FIPkevH5AUh1DDIY8WTxwL5O-KOf32o' );
 *   3. Share both spreadsheets with the service account's client_email (read-only for Phase 1).
 *   4. Activate this plugin.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ---------------------------------------------------------------------------
 * Config with defaults. Constants in wp-config.php override.
 * ------------------------------------------------------------------------- */
if ( ! defined( 'TLT_CALLBOARD_SHEET_ID' ) )       define( 'TLT_CALLBOARD_SHEET_ID',       '1jMhG2QgyLU_rHQoA2xFALIeAJDNOxXyNHUzMWkT-3ss' );
if ( ! defined( 'TLT_CALLBOARD_CONTACTBOOK_ID' ) ) define( 'TLT_CALLBOARD_CONTACTBOOK_ID', '1qQkqa8_v1Ie3FIPkevH5AUh1DDIY8WTxwL5O-KOf32o' );
// Cloudways home is /home/master/ (the SSH username differs; don't be fooled).
if ( ! defined( 'TLT_CALLBOARD_SA_JSON' ) )        define( 'TLT_CALLBOARD_SA_JSON',        '/home/master/tlt-service-account.json' );

const TLT_CALLBOARD_ROUTE_NS  = 'callboard/v1';
const TLT_CALLBOARD_SESSION_TTL = 30 * DAY_IN_SECONDS;      // login persists per-device
const TLT_CALLBOARD_CACHE_TTL   = 60;                        // read cache
const TLT_CALLBOARD_CONTACT_TTL = 600;                       // mirrors existing GAS 10-min contact cache

/* ---------------------------------------------------------------------------
 * Google service-account auth. Hand-rolled JWT → access token; cached 55 min.
 * ------------------------------------------------------------------------- */
function tlt_callboard_google_access_token() {
    $cached = get_transient( 'tlt_cb_google_token' );
    if ( $cached ) return $cached;

    if ( ! is_readable( TLT_CALLBOARD_SA_JSON ) ) {
        return new WP_Error( 'sa_missing', 'Service account JSON not readable at ' . TLT_CALLBOARD_SA_JSON );
    }
    $sa = json_decode( file_get_contents( TLT_CALLBOARD_SA_JSON ), true );
    if ( ! is_array( $sa ) || empty( $sa['client_email'] ) || empty( $sa['private_key'] ) ) {
        return new WP_Error( 'sa_invalid', 'Service account JSON malformed' );
    }

    $now = time();
    $header = [ 'alg' => 'RS256', 'typ' => 'JWT' ];
    $claims = [
        'iss'   => $sa['client_email'],
        'scope' => 'https://www.googleapis.com/auth/spreadsheets.readonly',
        'aud'   => 'https://oauth2.googleapis.com/token',
        'iat'   => $now,
        'exp'   => $now + 3600,
    ];
    $b64u = function ( $s ) { return rtrim( strtr( base64_encode( $s ), '+/', '-_' ), '=' ); };
    $to_sign = $b64u( wp_json_encode( $header ) ) . '.' . $b64u( wp_json_encode( $claims ) );
    $sig = '';
    if ( ! openssl_sign( $to_sign, $sig, $sa['private_key'], OPENSSL_ALGO_SHA256 ) ) {
        return new WP_Error( 'sign_failed', 'Could not sign JWT — check private_key' );
    }
    $jwt = $to_sign . '.' . $b64u( $sig );

    $resp = wp_remote_post( 'https://oauth2.googleapis.com/token', [
        'timeout' => 15,
        'body'    => [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt,
        ],
    ] );
    if ( is_wp_error( $resp ) ) return $resp;
    $data = json_decode( wp_remote_retrieve_body( $resp ), true );
    if ( empty( $data['access_token'] ) ) {
        return new WP_Error( 'token_exchange_failed', 'Google refused JWT: ' . wp_remote_retrieve_body( $resp ) );
    }
    // Cache slightly under Google's 1h expiry.
    set_transient( 'tlt_cb_google_token', $data['access_token'], 55 * MINUTE_IN_SECONDS );
    return $data['access_token'];
}

/* ---------------------------------------------------------------------------
 * Sheets API v4 wrapper — batchGet with per-range transient cache.
 *   $spreadsheet_id : ID of the sheet
 *   $ranges         : ['Theatre!A:D', 'Season!A:N']  — A1 notation
 *   $ttl            : override cache TTL in seconds (default TLT_CALLBOARD_CACHE_TTL)
 *   $force          : bypass cache and refetch
 *
 * Returns [ 'RangeA1' => [ [rowvals], ... ], ... ] or WP_Error.
 * ------------------------------------------------------------------------- */
function tlt_callboard_sheets_get( $spreadsheet_id, $ranges, $ttl = TLT_CALLBOARD_CACHE_TTL, $force = false ) {
    $key = 'tlt_cb_range_' . md5( $spreadsheet_id . '|' . implode( ',', $ranges ) );
    if ( ! $force ) {
        $cached = get_transient( $key );
        if ( is_array( $cached ) ) return $cached;
    }
    $token = tlt_callboard_google_access_token();
    if ( is_wp_error( $token ) ) return $token;

    $qs = [];
    foreach ( $ranges as $r ) $qs[] = 'ranges=' . rawurlencode( $r );
    // Value formatting: UNFORMATTED_VALUE for numbers/dates so we can parse
    // without locale surprises. Frontend formats display-side.
    $qs[] = 'valueRenderOption=UNFORMATTED_VALUE';
    $qs[] = 'dateTimeRenderOption=FORMATTED_STRING';
    $url = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheet_id}/values:batchGet?" . implode( '&', $qs );

    $resp = wp_remote_get( $url, [
        'timeout' => 30,
        'headers' => [ 'Authorization' => 'Bearer ' . $token ],
    ] );
    if ( is_wp_error( $resp ) ) return $resp;
    $code = wp_remote_retrieve_response_code( $resp );
    if ( $code < 200 || $code >= 300 ) {
        return new WP_Error( 'sheets_http', "Sheets API returned $code: " . wp_remote_retrieve_body( $resp ) );
    }
    $data = json_decode( wp_remote_retrieve_body( $resp ), true );
    $out = [];
    foreach ( $data['valueRanges'] ?? [] as $i => $vr ) {
        $out[ $ranges[ $i ] ] = $vr['values'] ?? [];
    }
    set_transient( $key, $out, $ttl );
    return $out;
}

/* Convenience: fetch a single range and return just the row array. */
function tlt_callboard_sheet_rows( $spreadsheet_id, $range, $ttl = TLT_CALLBOARD_CACHE_TTL, $force = false ) {
    $result = tlt_callboard_sheets_get( $spreadsheet_id, [ $range ], $ttl, $force );
    if ( is_wp_error( $result ) ) return $result;
    return $result[ $range ] ?? [];
}

/* ---------------------------------------------------------------------------
 * Auth. Passwords live in Theatre col C. Any row with non-empty col C can log in.
 * Sessions are 30-day WP transients keyed by a random 32-byte token.
 * ------------------------------------------------------------------------- */

const TLT_CALLBOARD_APPROVER_ROLES = [ 'Managing Artistic Director', 'Associate Artistic Director' ];

function tlt_callboard_login( $password ) {
    $password = trim( (string) $password );
    if ( $password === '' ) return null;

    // Force fresh read of Theatre tab — do NOT hit cache when authenticating,
    // so a password just added takes effect immediately.
    $rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_SHEET_ID, 'Theatre!A2:D200', TLT_CALLBOARD_CACHE_TTL, true );
    if ( is_wp_error( $rows ) ) return $rows;

    foreach ( $rows as $row ) {
        $col_c = trim( (string) ( $row[2] ?? '' ) );
        if ( $col_c === '' || $col_c !== $password ) continue;

        $role = trim( (string) ( $row[0] ?? '' ) );
        $name = trim( (string) ( $row[1] ?? '' ) );
        // Initials for "Ok to send" writes — uppercase first letters of the name.
        $initials = '';
        foreach ( preg_split( '/\s+/', $name ) as $part ) {
            if ( $part !== '' ) $initials .= strtoupper( $part[0] );
        }
        $user = [
            'role'        => $role,
            'name'        => $name,
            'initials'    => $initials,
            'is_approver' => in_array( $role, TLT_CALLBOARD_APPROVER_ROLES, true ),
            'issued_at'   => time(),
        ];
        $token = bin2hex( random_bytes( 24 ) );
        set_transient( 'tlt_cb_sess_' . $token, $user, TLT_CALLBOARD_SESSION_TTL );
        return [ 'token' => $token, 'user' => $user ];
    }
    return null;
}

function tlt_callboard_current_user( WP_REST_Request $req ) {
    $auth = $req->get_header( 'authorization' );
    if ( ! $auth || stripos( $auth, 'Bearer ' ) !== 0 ) return null;
    $token = trim( substr( $auth, 7 ) );
    if ( ! preg_match( '/^[a-f0-9]{48}$/', $token ) ) return null;
    $user = get_transient( 'tlt_cb_sess_' . $token );
    if ( ! is_array( $user ) ) return null;
    // Slide expiration on each use so active users don't get logged out mid-session.
    set_transient( 'tlt_cb_sess_' . $token, $user, TLT_CALLBOARD_SESSION_TTL );
    return $user;
}

function tlt_callboard_require_auth( WP_REST_Request $req ) {
    return tlt_callboard_current_user( $req ) ? true : new WP_Error( 'unauthorized', 'Login required.', [ 'status' => 401 ] );
}

/* ---------------------------------------------------------------------------
 * Small helpers used across endpoints.
 * ------------------------------------------------------------------------- */

/** Trim and coerce sheet cell to string (Sheets returns numeric for numbers). */
function tlt_cb_s( $v ) { return trim( (string) ( $v ?? '' ) ); }

/** Response wrapper that normalizes shape for the frontend. */
function tlt_cb_ok( $data ) { return rest_ensure_response( [ 'ok' => true, 'data' => $data ] ); }

/* ---------------------------------------------------------------------------
 * REST route registration.
 *
 * Phase 1: implemented endpoints below the "FULLY IMPLEMENTED" divider are
 * done. The rest are registered with stubs that return WP_Error('not_implemented')
 * — the frontend will show a clear "not ported yet" when you hit them.
 * Each stub has a comment pointing to the sheet range/shape it should return.
 * ------------------------------------------------------------------------- */
add_action( 'rest_api_init', function () {
    $ns = TLT_CALLBOARD_ROUTE_NS;

    // ----- Public -----
    register_rest_route( $ns, '/login', [
        'methods'             => 'POST',
        'callback'            => 'tlt_callboard_ep_login',
        'permission_callback' => '__return_true',
    ] );

    // ----- Authenticated -----
    $auth_route = function ( $path, $handler ) use ( $ns ) {
        register_rest_route( $ns, $path, [
            'methods'             => 'GET',
            'callback'            => $handler,
            'permission_callback' => 'tlt_callboard_require_auth',
        ] );
    };

    $auth_route( '/whoami',                     'tlt_callboard_ep_whoami' );
    $auth_route( '/logout',                     'tlt_callboard_ep_logout' );

    // ----- FULLY IMPLEMENTED (Phase 1 pilot endpoints) -----
    $auth_route( '/shows',                      'tlt_callboard_ep_get_shows' );
    $auth_route( '/current-season',             'tlt_callboard_ep_get_current_season' );
    $auth_route( '/roles',                      'tlt_callboard_ep_get_roles' );
    $auth_route( '/show-roster',                'tlt_callboard_ep_get_show_roster' );      // ?show=Foo
    $auth_route( '/actors-for-show',            'tlt_callboard_ep_get_actors_for_show' );  // ?show=Foo

    // ----- STUBS (return 501; each has a comment describing the port) -----
    $auth_route( '/initial-data',               'tlt_callboard_ep_todo' );  // getInitialData()
    $auth_route( '/dashboard',                  'tlt_callboard_ep_todo' );  // getDashboardData()
    $auth_route( '/actors',                     'tlt_callboard_ep_todo' );  // getActors()
    $auth_route( '/sales',                      'tlt_callboard_ep_todo' );  // getSalesData()
    $auth_route( '/bios',                       'tlt_callboard_ep_todo' );  // getBiosData()
    $auth_route( '/contacts',                   'tlt_callboard_ep_todo' );  // getContacts()  — TTL 600
    $auth_route( '/contracts',                  'tlt_callboard_ep_todo' );  // getContractsPageData(force)
    $auth_route( '/full-season',                'tlt_callboard_ep_todo' );  // getFullSeasonData()
    $auth_route( '/combinable-shows',           'tlt_callboard_ep_todo' );  // getCombinableShows(...)
    $auth_route( '/schedule-link',              'tlt_callboard_ep_todo' );  // getScheduleLink(show) — cached URL only
    $auth_route( '/contact-sheet-link',         'tlt_callboard_ep_todo' );  // getContactSheetLink(show) — cached URL only
    $auth_route( '/calendar-events',            'tlt_callboard_ep_todo' );  // getSeasonCalendarEvents()
    $auth_route( '/calendar-conflicts',         'tlt_callboard_ep_todo' );  // getSeasonCalendarConflicts()
    $auth_route( '/program',                    'tlt_callboard_ep_todo' );  // getProgramData(show)

    // ----- Approval helper — auth still required, but not implementing the write side yet -----
    $auth_route( '/verify-approval',            'tlt_callboard_ep_verify_approval' ); // ?password=...
} );

/* ---------------------------------------------------------------------------
 * ENDPOINT: POST /login  { password }
 * ------------------------------------------------------------------------- */
function tlt_callboard_ep_login( WP_REST_Request $req ) {
    $body = $req->get_json_params();
    $password = is_array( $body ) ? ( $body['password'] ?? '' ) : '';
    $result = tlt_callboard_login( $password );
    if ( is_wp_error( $result ) ) return $result;
    if ( ! $result ) return new WP_Error( 'bad_password', 'Password not recognized.', [ 'status' => 401 ] );
    return rest_ensure_response( [ 'ok' => true, 'token' => $result['token'], 'user' => $result['user'] ] );
}

function tlt_callboard_ep_whoami( WP_REST_Request $req ) {
    return tlt_cb_ok( tlt_callboard_current_user( $req ) );
}

function tlt_callboard_ep_logout( WP_REST_Request $req ) {
    $auth = $req->get_header( 'authorization' );
    if ( $auth && stripos( $auth, 'Bearer ' ) === 0 ) {
        $token = trim( substr( $auth, 7 ) );
        delete_transient( 'tlt_cb_sess_' . $token );
    }
    return tlt_cb_ok( [ 'logged_out' => true ] );
}

/* ---------------------------------------------------------------------------
 * ENDPOINT: GET /shows  →  { data: ["Show A", "Show B", ...] }
 *
 * Source: named range `ShowList` on the Callboard sheet. Named ranges resolve
 * as `<SheetName>!<A1>` — but since the GAS uses `.getValues()` we can just
 * read the range by name via `values.get` when passed as `ShowList`.
 * ------------------------------------------------------------------------- */
function tlt_callboard_ep_get_shows( WP_REST_Request $req ) {
    $rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_SHEET_ID, 'ShowList' );
    if ( is_wp_error( $rows ) ) return $rows;
    $shows = [];
    foreach ( $rows as $row ) {
        $name = tlt_cb_s( $row[0] ?? '' );
        if ( $name !== '' ) $shows[] = $name;
    }
    return tlt_cb_ok( $shows );
}

/* ---------------------------------------------------------------------------
 * ENDPOINT: GET /current-season  →  { data: { season: "26-27", long: "2026-2027" } }
 * ------------------------------------------------------------------------- */
function tlt_callboard_ep_get_current_season( WP_REST_Request $req ) {
    $result = tlt_callboard_sheets_get( TLT_CALLBOARD_SHEET_ID, [ 'CurrentSeason', 'CurrentSeasonLong' ] );
    if ( is_wp_error( $result ) ) return $result;
    return tlt_cb_ok( [
        'season' => tlt_cb_s( $result['CurrentSeason'][0][0] ?? '' ),
        'long'   => tlt_cb_s( $result['CurrentSeasonLong'][0][0] ?? '' ),
    ] );
}

/* ---------------------------------------------------------------------------
 * ENDPOINT: GET /roles  →  { data: ["Director", "Stage Manager", ...] }
 * Deduped from Production Teams col B.
 * ------------------------------------------------------------------------- */
function tlt_callboard_ep_get_roles( WP_REST_Request $req ) {
    $rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_SHEET_ID, "'Production Teams'!B2:B" );
    if ( is_wp_error( $rows ) ) return $rows;
    $seen = [];
    foreach ( $rows as $row ) {
        $role = tlt_cb_s( $row[0] ?? '' );
        if ( $role !== '' ) $seen[ $role ] = true;
    }
    $roles = array_keys( $seen );
    sort( $roles );
    return tlt_cb_ok( $roles );
}

/* ---------------------------------------------------------------------------
 * ENDPOINT: GET /show-roster?show=Foo
 * → per-role rows for the show, joined with Contactbook on email (fallback name).
 * Return shape mirrors getShowRoster() in the original WebApp.js — see the
 * port brief (Section 2) for the full field list. Phase 1 implements the
 * "no Contactbook join" subset first; join added when we need bio + contact info.
 * ------------------------------------------------------------------------- */
function tlt_callboard_ep_get_show_roster( WP_REST_Request $req ) {
    $show = tlt_cb_s( $req->get_param( 'show' ) );
    if ( $show === '' ) return new WP_Error( 'missing_show', 'show query param required', [ 'status' => 400 ] );

    $rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_SHEET_ID, "'Production Teams'!A2:S" );
    if ( is_wp_error( $rows ) ) return $rows;

    // Filter to this show; produce the exact shape the frontend already
    // expects (matches the field names used by Index.html today).
    $out = [];
    foreach ( $rows as $r ) {
        if ( tlt_cb_s( $r[0] ?? '' ) !== $show ) continue;
        $out[] = [
            'role'                => tlt_cb_s( $r[1] ?? '' ),
            'firstName'           => tlt_cb_s( $r[2] ?? '' ),
            'middleName'          => tlt_cb_s( $r[3] ?? '' ),
            'lastName'            => tlt_cb_s( $r[4] ?? '' ),
            'suffix'              => tlt_cb_s( $r[5] ?? '' ),
            'phone'               => tlt_cb_s( $r[6] ?? '' ),
            'email'               => tlt_cb_s( $r[7] ?? '' ),
            'contractStatus'      => tlt_cb_s( $r[8] ?? '' ),
            'contractSentDate'    => tlt_cb_s( $r[9] ?? '' ),
            'contractSignedDate'  => tlt_cb_s( $r[10] ?? '' ),
            'contractLink'        => tlt_cb_s( $r[11] ?? '' ),
            'notes'               => tlt_cb_s( $r[12] ?? '' ),
            'bioStatus'           => tlt_cb_s( $r[13] ?? '' ),
            'bioType'             => tlt_cb_s( $r[14] ?? '' ),
            'okToSend'            => tlt_cb_s( $r[15] ?? '' ),
            'emergencyStatus'     => tlt_cb_s( $r[16] ?? '' ),
            // TODO: join with Contactbook to fill `contact: {…}` field.
        ];
    }
    return tlt_cb_ok( $out );
}

/* ---------------------------------------------------------------------------
 * ENDPOINT: GET /actors-for-show?show=Foo
 * ------------------------------------------------------------------------- */
function tlt_callboard_ep_get_actors_for_show( WP_REST_Request $req ) {
    $show = tlt_cb_s( $req->get_param( 'show' ) );
    if ( $show === '' ) return new WP_Error( 'missing_show', 'show query param required', [ 'status' => 400 ] );

    $rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_SHEET_ID, "Actors!A2:S" );
    if ( is_wp_error( $rows ) ) return $rows;
    $out = [];
    foreach ( $rows as $r ) {
        if ( tlt_cb_s( $r[0] ?? '' ) !== $show ) continue;
        $out[] = [
            'character'         => tlt_cb_s( $r[1] ?? '' ),
            'firstName'         => tlt_cb_s( $r[2] ?? '' ),
            'middleName'        => tlt_cb_s( $r[3] ?? '' ),
            'lastName'          => tlt_cb_s( $r[4] ?? '' ),
            'suffix'            => tlt_cb_s( $r[5] ?? '' ),
            'phone'             => tlt_cb_s( $r[6] ?? '' ),
            'email'             => tlt_cb_s( $r[7] ?? '' ),
            'contractStatus'    => tlt_cb_s( $r[8] ?? '' ),
        ];
    }
    return tlt_cb_ok( $out );
}

/* ---------------------------------------------------------------------------
 * ENDPOINT: GET /verify-approval?password=...  →  { data: { ok, role, initials } }
 * Same rule as the current GAS: only rows where col A is Managing/Associate
 * Artistic Director can approve. Used by Ok-to-send flow.
 * ------------------------------------------------------------------------- */
function tlt_callboard_ep_verify_approval( WP_REST_Request $req ) {
    $password = tlt_cb_s( $req->get_param( 'password' ) );
    $rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_SHEET_ID, 'Theatre!A2:D200' );
    if ( is_wp_error( $rows ) ) return $rows;
    foreach ( $rows as $r ) {
        $role = tlt_cb_s( $r[0] ?? '' );
        $name = tlt_cb_s( $r[1] ?? '' );
        $pw   = tlt_cb_s( $r[2] ?? '' );
        if ( $pw === '' || $pw !== $password ) continue;
        if ( ! in_array( $role, TLT_CALLBOARD_APPROVER_ROLES, true ) ) continue;
        $initials = '';
        foreach ( preg_split( '/\s+/', $name ) as $part ) if ( $part !== '' ) $initials .= strtoupper( $part[0] );
        return tlt_cb_ok( [ 'ok' => true, 'role' => $role, 'initials' => $initials ] );
    }
    return tlt_cb_ok( [ 'ok' => false ] );
}

/* ---------------------------------------------------------------------------
 * STUB — every not-yet-ported endpoint returns 501 with a message pointing
 * to the port brief for its intended shape.
 * ------------------------------------------------------------------------- */
function tlt_callboard_ep_todo( WP_REST_Request $req ) {
    return new WP_Error(
        'not_implemented',
        'Endpoint not yet ported. See Port Brief Section 2 for the intended shape.',
        [ 'status' => 501, 'route' => $req->get_route() ]
    );
}
