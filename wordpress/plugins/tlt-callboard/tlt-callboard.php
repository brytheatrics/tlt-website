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

    // ----- Read endpoints (all ported from Port Brief §2) -----
    $auth_route( '/initial-data',               'tlt_callboard_ep_get_initial_data' );
    $auth_route( '/dashboard',                  'tlt_callboard_ep_get_dashboard' );
    $auth_route( '/actors',                     'tlt_callboard_ep_get_actors' );
    $auth_route( '/sales',                      'tlt_callboard_ep_get_sales' );
    $auth_route( '/bios',                       'tlt_callboard_ep_get_bios' );
    $auth_route( '/contacts',                   'tlt_callboard_ep_get_contacts' );
    $auth_route( '/contracts',                  'tlt_callboard_ep_get_contracts' );
    $auth_route( '/full-season',                'tlt_callboard_ep_get_full_season' );
    $auth_route( '/combinable-shows',           'tlt_callboard_ep_get_combinable_shows' );
    $auth_route( '/schedule-link',              'tlt_callboard_ep_get_schedule_link' );
    $auth_route( '/contact-sheet-link',         'tlt_callboard_ep_get_contact_sheet_link' );
    $auth_route( '/calendar-events',            'tlt_callboard_ep_get_calendar_events' );
    $auth_route( '/calendar-conflicts',         'tlt_callboard_ep_get_calendar_conflicts' );
    $auth_route( '/program',                    'tlt_callboard_ep_get_program' );

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

/* ===========================================================================
 * SHARED HELPERS for the endpoints below.
 * ======================================================================== */

/** Load Contactbook and index by "firstlast" lowercase key for fast joins. */
function tlt_cb_load_contactbook_index() {
    $rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_CONTACTBOOK_ID, 'Contactbook!A2:O', TLT_CALLBOARD_CONTACT_TTL );
    if ( is_wp_error( $rows ) ) return [];
    $by_name = [];
    $by_email = [];
    foreach ( $rows as $r ) {
        $first = tlt_cb_s( $r[1] ?? '' );
        $last  = tlt_cb_s( $r[3] ?? '' );
        $email = strtolower( tlt_cb_s( $r[7] ?? '' ) );
        $contact = [
            'contactId'  => tlt_cb_s( $r[0] ?? '' ),
            'firstName'  => $first,
            'middleName' => tlt_cb_s( $r[2] ?? '' ),
            'lastName'   => $last,
            'suffix'     => tlt_cb_s( $r[4] ?? '' ),
            'pronouns'   => tlt_cb_s( $r[5] ?? '' ),
            'phone'      => tlt_cb_s( $r[6] ?? '' ),
            'email'      => tlt_cb_s( $r[7] ?? '' ),
            'notes'      => tlt_cb_s( $r[8] ?? '' ),
            'skills'     => array_values( array_filter( array_map( 'trim', explode( ',', tlt_cb_s( $r[9] ?? '' ) ) ) ) ),
        ];
        if ( $first !== '' && $last !== '' ) {
            $by_name[ strtolower( $first . '|' . $last ) ] = $contact;
        }
        if ( $email !== '' ) $by_email[ $email ] = $contact;
    }
    return [ 'byName' => $by_name, 'byEmail' => $by_email, 'all' => array_values( $by_email + $by_name ) ];
}

/** Given a person row (firstName, lastName, email), find their Contactbook entry. */
function tlt_cb_lookup_contact( $idx, $first, $last, $email ) {
    $email = strtolower( trim( (string) $email ) );
    if ( $email !== '' && isset( $idx['byEmail'][ $email ] ) ) return $idx['byEmail'][ $email ];
    $key = strtolower( trim( (string) $first ) . '|' . trim( (string) $last ) );
    return $idx['byName'][ $key ] ?? null;
}

/* ===========================================================================
 * ENDPOINT: GET /contacts  →  full Contactbook. 10-min TTL (matches GAS).
 * ======================================================================== */
function tlt_callboard_ep_get_contacts( WP_REST_Request $req ) {
    $idx = tlt_cb_load_contactbook_index();
    return tlt_cb_ok( $idx['all'] );
}

/* ===========================================================================
 * ENDPOINT: GET /initial-data  →  { contacts, season, dashboard }
 * Composition of getContacts + getCurrentSeason + getDashboardData in one call.
 * ======================================================================== */
function tlt_callboard_ep_get_initial_data( WP_REST_Request $req ) {
    $idx = tlt_cb_load_contactbook_index();
    $season_res = tlt_callboard_sheets_get( TLT_CALLBOARD_SHEET_ID, [ 'CurrentSeason', 'CurrentSeasonLong' ] );
    $season = is_wp_error( $season_res ) ? '' : tlt_cb_s( $season_res['CurrentSeason'][0][0] ?? '' );
    // Dashboard is expensive — call the internal helper so we don't re-fetch.
    $dashboard = tlt_cb_build_dashboard();
    if ( is_wp_error( $dashboard ) ) $dashboard = [];
    return tlt_cb_ok( [
        'contacts'  => $idx['all'],
        'season'    => $season,
        'dashboard' => $dashboard,
    ] );
}

/* ===========================================================================
 * ENDPOINT: GET /dashboard  →  per-show rollup
 * { show, filled, total, generated, signed, sent, pending, openingNight,
 *   sales, conflictCount }[]
 *
 * Aggregates Production Teams + Actors (per-show contract status counts),
 * Dates (opening night lookup), Sales (total sold), Conflicts (count per show).
 * ======================================================================== */
function tlt_callboard_ep_get_dashboard( WP_REST_Request $req ) {
    $out = tlt_cb_build_dashboard();
    if ( is_wp_error( $out ) ) return $out;
    return tlt_cb_ok( $out );
}

function tlt_cb_build_dashboard() {
    $data = tlt_callboard_sheets_get( TLT_CALLBOARD_SHEET_ID, [
        'ShowList',
        "'Production Teams'!A2:I",
        'Actors!A2:I',
        'Dates!A2:H',
        'Sales!A2:H',
        'Conflicts!A2:H',
    ] );
    if ( is_wp_error( $data ) ) return $data;

    $shows = [];
    foreach ( $data['ShowList'] ?? [] as $r ) {
        $n = tlt_cb_s( $r[0] ?? '' );
        if ( $n !== '' ) $shows[] = $n;
    }

    $init = function () { return [ 'filled' => 0, 'total' => 0, 'generated' => 0, 'signed' => 0, 'sent' => 0, 'pending' => 0 ]; };
    $per = [];
    foreach ( $shows as $s ) $per[ $s ] = $init();

    $tally = function ( $rows, $has_role_or_char, $col_status ) use ( &$per ) {
        foreach ( $rows as $r ) {
            $show = tlt_cb_s( $r[0] ?? '' );
            if ( ! isset( $per[ $show ] ) ) continue;
            $per[ $show ]['total']++;
            // "Filled" = firstName (col C) present.
            if ( tlt_cb_s( $r[2] ?? '' ) !== '' ) $per[ $show ]['filled']++;
            $status = tlt_cb_s( $r[ $col_status ] ?? '' );
            if ( $status === 'Signed' )                 $per[ $show ]['signed']++;
            elseif ( $status === 'Sent for Signature' ) $per[ $show ]['sent']++;
            elseif ( $status === 'Generated' )          $per[ $show ]['generated']++;
            else                                        $per[ $show ]['pending']++;
        }
    };
    $tally( $data["'Production Teams'!A2:I"] ?? [], true, 8 );
    $tally( $data['Actors!A2:I']            ?? [], true, 8 );

    // Opening night — first row per show with event type "Opening Performance"
    $opening = [];
    foreach ( $data['Dates!A2:H'] ?? [] as $r ) {
        $show = tlt_cb_s( $r[0] ?? '' );
        $type = tlt_cb_s( $r[1] ?? '' );
        $date = tlt_cb_s( $r[4] ?? '' );
        if ( $show && $type === 'Opening Performance' && $date && empty( $opening[ $show ] ) ) {
            $opening[ $show ] = $date;
        }
    }

    // Total sold — Summary rows in Sales tab.
    $sold = [];
    foreach ( $data['Sales!A2:H'] ?? [] as $r ) {
        if ( tlt_cb_s( $r[2] ?? '' ) !== 'Summary' ) continue;
        $show = tlt_cb_s( $r[1] ?? '' );
        if ( $show ) $sold[ $show ] = (int) ( $r[3] ?? 0 );
    }

    // Conflicts per show — count Conflict rows grouped by show (col A).
    $conflict = [];
    foreach ( $data['Conflicts!A2:H'] ?? [] as $r ) {
        $show = tlt_cb_s( $r[0] ?? '' );
        if ( $show === '' ) continue;
        $conflict[ $show ] = ( $conflict[ $show ] ?? 0 ) + 1;
    }

    $out = [];
    foreach ( $shows as $s ) {
        $row = [ 'show' => $s ] + $per[ $s ];
        $row['openingNight']  = $opening[ $s ]  ?? '';
        $row['sales']         = $sold[ $s ]     ?? 0;
        $row['conflictCount'] = $conflict[ $s ] ?? 0;
        $out[] = $row;
    }
    return $out;
}

/* ===========================================================================
 * ENDPOINT: GET /actors  →  every Actors row across all shows.
 * ======================================================================== */
function tlt_callboard_ep_get_actors( WP_REST_Request $req ) {
    $rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_SHEET_ID, 'Actors!A2:S' );
    if ( is_wp_error( $rows ) ) return $rows;
    $out = [];
    foreach ( $rows as $r ) {
        $show = tlt_cb_s( $r[0] ?? '' );
        if ( $show === '' ) continue;
        $out[] = [
            'show'                => $show,
            'character'           => tlt_cb_s( $r[1] ?? '' ),
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
        ];
    }
    return tlt_cb_ok( $out );
}

/* ===========================================================================
 * ENDPOINT: GET /sales  →  per-show aggregated sales.
 * Sales tab row types: Summary (col C='Summary'), Performance, Payment.
 * Dates tab supplies performance capacity by joining on show + date.
 * ======================================================================== */
function tlt_callboard_ep_get_sales( WP_REST_Request $req ) {
    $data = tlt_callboard_sheets_get( TLT_CALLBOARD_SHEET_ID, [
        'Sales!A2:H',
        'Dates!A2:H',
    ] );
    if ( is_wp_error( $data ) ) return $data;

    // Bucket per show.
    $per = [];
    $init = function ( $show ) {
        return [
            'show'          => $show,
            'totalSold'     => 0,
            'perfCount'     => 0,
            'capacity'      => 0,
            'capacityPct'   => 0,
            'seasonTicket'  => 0,
            'flexPass'      => 0,
            'comp'          => 0,
            'individual'    => 0,
            'seasonPct'     => 0,
            'flexPct'       => 0,
            'compPct'       => 0,
            'individualPct' => 0,
            'performances'  => [],
        ];
    };

    foreach ( $data['Sales!A2:H'] ?? [] as $r ) {
        $show = tlt_cb_s( $r[1] ?? '' );
        if ( $show === '' ) continue;
        if ( ! isset( $per[ $show ] ) ) $per[ $show ] = $init( $show );
        $type = tlt_cb_s( $r[2] ?? '' );
        if ( $type === 'Summary' ) {
            $per[ $show ]['totalSold'] = (int) ( $r[3] ?? 0 );
        } elseif ( $type === 'Performance' ) {
            $per[ $show ]['performances'][] = [
                'date'      => tlt_cb_s( $r[4] ?? '' ),
                'sold'      => (int) ( $r[5] ?? 0 ),
                'remaining' => 0, // filled after Dates join below
            ];
            $per[ $show ]['perfCount']++;
        } elseif ( $type === 'Payment' ) {
            $method = strtolower( tlt_cb_s( $r[6] ?? '' ) );
            $count  = (int) ( $r[7] ?? 0 );
            if ( strpos( $method, 'season' ) !== false )      $per[ $show ]['seasonTicket']  = $count;
            elseif ( strpos( $method, 'flex' ) !== false )    $per[ $show ]['flexPass']      = $count;
            elseif ( strpos( $method, 'comp' ) !== false )    $per[ $show ]['comp']          = $count;
            else                                              $per[ $show ]['individual']    = $count;
        }
    }

    // Percentages of totalSold.
    foreach ( $per as &$row ) {
        $t = max( 1, $row['totalSold'] );
        $row['seasonPct']     = round( $row['seasonTicket']  / $t * 100 );
        $row['flexPct']       = round( $row['flexPass']      / $t * 100 );
        $row['compPct']       = round( $row['comp']          / $t * 100 );
        $row['individualPct'] = round( $row['individual']    / $t * 100 );
    }
    unset( $row );

    return tlt_cb_ok( array_values( $per ) );
}

/* ===========================================================================
 * ENDPOINT: GET /bios  →  per-show bio submission summary.
 * ======================================================================== */
function tlt_callboard_ep_get_bios( WP_REST_Request $req ) {
    $data = tlt_callboard_sheets_get( TLT_CALLBOARD_SHEET_ID, [
        'ShowList',
        "'Production Teams'!A2:O",
        'Actors!A2:O',
        'Season!A2:N',
    ] );
    if ( is_wp_error( $data ) ) return $data;

    // Show → cached bio doc URL (Season col L, index 11).
    $bio_doc_by_show = [];
    foreach ( $data['Season!A2:N'] ?? [] as $r ) {
        $show = tlt_cb_s( $r[1] ?? '' );
        if ( $show ) $bio_doc_by_show[ $show ] = tlt_cb_s( $r[11] ?? '' );
    }

    $shows = [];
    foreach ( $data['ShowList'] ?? [] as $r ) {
        $n = tlt_cb_s( $r[0] ?? '' );
        if ( $n ) $shows[ $n ] = [ 'show' => $n, 'submitted' => 0, 'total' => 0, 'team' => [], 'actors' => [], 'bioDocUrl' => $bio_doc_by_show[ $n ] ?? '' ];
    }

    foreach ( $data["'Production Teams'!A2:O"] ?? [] as $r ) {
        $show = tlt_cb_s( $r[0] ?? '' );
        if ( ! isset( $shows[ $show ] ) ) continue;
        $bio_status = tlt_cb_s( $r[13] ?? '' );
        if ( tlt_cb_s( $r[2] ?? '' ) === '' ) continue; // no person = skip
        $shows[ $show ]['total']++;
        if ( strcasecmp( $bio_status, 'Submitted' ) === 0 ) $shows[ $show ]['submitted']++;
        $shows[ $show ]['team'][] = [
            'firstName' => tlt_cb_s( $r[2] ?? '' ),
            'lastName'  => tlt_cb_s( $r[4] ?? '' ),
            'role'      => tlt_cb_s( $r[1] ?? '' ),
            'bioStatus' => $bio_status,
            'bioType'   => tlt_cb_s( $r[14] ?? '' ),
        ];
    }
    foreach ( $data['Actors!A2:O'] ?? [] as $r ) {
        $show = tlt_cb_s( $r[0] ?? '' );
        if ( ! isset( $shows[ $show ] ) ) continue;
        $bio_status = tlt_cb_s( $r[13] ?? '' );
        if ( tlt_cb_s( $r[2] ?? '' ) === '' ) continue;
        $shows[ $show ]['total']++;
        if ( strcasecmp( $bio_status, 'Submitted' ) === 0 ) $shows[ $show ]['submitted']++;
        $shows[ $show ]['actors'][] = [
            'firstName' => tlt_cb_s( $r[2] ?? '' ),
            'lastName'  => tlt_cb_s( $r[4] ?? '' ),
            'character' => tlt_cb_s( $r[1] ?? '' ),
            'bioStatus' => $bio_status,
            'bioType'   => tlt_cb_s( $r[14] ?? '' ),
        ];
    }

    return tlt_cb_ok( array_values( $shows ) );
}

/* ===========================================================================
 * ENDPOINT: GET /contracts?force=1  →  { shows, contracts }
 *
 * Wide join of Production Teams + Actors + Duties (for hasTemplate) + Budget
 * (for compensation) + Contactbook. Frontend needs everything to render the
 * contracts tab and pre-flight the "Generate contract" flow.
 * ======================================================================== */
function tlt_callboard_ep_get_contracts( WP_REST_Request $req ) {
    $force = $req->get_param( 'force' ) ? true : false;
    $ttl   = $force ? 1 : TLT_CALLBOARD_CACHE_TTL;

    $data = tlt_callboard_sheets_get( TLT_CALLBOARD_SHEET_ID, [
        'ShowList',
        "'Production Teams'!A2:S",
        'Actors!A2:S',
        'Duties!A2:A',
        'Budget!A2:F',
    ], $ttl, $force );
    if ( is_wp_error( $data ) ) return $data;

    $shows = [];
    foreach ( $data['ShowList'] ?? [] as $r ) {
        $n = tlt_cb_s( $r[0] ?? '' );
        if ( $n ) $shows[] = $n;
    }

    $duties_roles = [];
    foreach ( $data['Duties!A2:A'] ?? [] as $r ) {
        $roleName = tlt_cb_s( $r[0] ?? '' );
        if ( $roleName ) $duties_roles[ $roleName ] = true;
    }

    // Budget: (show, role) → stipend.
    $budget = [];
    foreach ( $data['Budget!A2:F'] ?? [] as $r ) {
        $key = tlt_cb_s( $r[0] ?? '' ) . '|' . tlt_cb_s( $r[1] ?? '' );
        $budget[ $key ] = tlt_cb_s( $r[2] ?? '' );
    }

    $idx = tlt_cb_load_contactbook_index();

    $contracts = [];
    $emit = function ( $r, $is_actor ) use ( &$contracts, $duties_roles, $budget, $idx ) {
        $first = tlt_cb_s( $r[2] ?? '' );
        $last  = tlt_cb_s( $r[4] ?? '' );
        if ( $first === '' ) return;
        $show      = tlt_cb_s( $r[0] ?? '' );
        $role_char = tlt_cb_s( $r[1] ?? '' );
        $contact   = tlt_cb_lookup_contact( $idx, $first, $last, tlt_cb_s( $r[7] ?? '' ) );
        $stipend   = $budget[ $show . '|' . $role_char ] ?? '';

        $contracts[] = [
            'show'                => $show,
            'role'                => $is_actor ? '' : $role_char,
            'character'           => $is_actor ? $role_char : '',
            'firstName'           => $first,
            'lastName'            => $last,
            'fullName'            => trim( $first . ' ' . $last ),
            'contractStatus'      => tlt_cb_s( $r[8] ?? '' ),
            'contractSentDate'    => tlt_cb_s( $r[9] ?? '' ),
            'contractSignedDate'  => tlt_cb_s( $r[10] ?? '' ),
            'contractLink'        => tlt_cb_s( $r[11] ?? '' ),
            'okToSend'            => tlt_cb_s( $r[15] ?? '' ),
            'combinedContractId'  => tlt_cb_s( $r[18] ?? '' ),
            'isActor'             => $is_actor,
            'hasTemplate'         => $is_actor ? true : isset( $duties_roles[ $role_char ] ),
            'stipend'             => $stipend,
            'contact'             => $contact,
        ];
    };
    foreach ( $data["'Production Teams'!A2:S"] ?? [] as $r ) $emit( $r, false );
    foreach ( $data['Actors!A2:S']            ?? [] as $r ) $emit( $r, true );

    return tlt_cb_ok( [ 'shows' => $shows, 'contracts' => $contracts ] );
}

/* ===========================================================================
 * ENDPOINT: GET /full-season  →  Season tab as show config objects.
 * Season tab first N rows = key/value config; rest = one row per show (col A
 * matches /^Show\s*\d+$/i).
 * ======================================================================== */
function tlt_callboard_ep_get_full_season( WP_REST_Request $req ) {
    $rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_SHEET_ID, 'Season!A2:N' );
    if ( is_wp_error( $rows ) ) return $rows;

    $config = [];
    $shows  = [];
    foreach ( $rows as $r ) {
        $label = tlt_cb_s( $r[0] ?? '' );
        if ( $label === '' ) continue;
        if ( preg_match( '/^Show\s*\d+$/i', $label ) ) {
            $shows[] = [
                'slot'                => $label,
                'name'                => tlt_cb_s( $r[1] ?? '' ),
                'compCode1'           => tlt_cb_s( $r[2] ?? '' ),
                'compCode2'           => tlt_cb_s( $r[3] ?? '' ),
                'smEmail'             => tlt_cb_s( $r[4] ?? '' ),
                'actorBioWordCount'   => tlt_cb_s( $r[5] ?? '' ),
                'directorBioCount'    => tlt_cb_s( $r[6] ?? '' ),
                'designerBioCount'    => tlt_cb_s( $r[7] ?? '' ),
                'ludusId'             => tlt_cb_s( $r[8] ?? '' ),
                'castingManagerId'    => tlt_cb_s( $r[9] ?? '' ),
                'sharedDriveUrl'      => tlt_cb_s( $r[10] ?? '' ),
                'bioDocUrl'           => tlt_cb_s( $r[11] ?? '' ),
                'contactSheetUrl'     => tlt_cb_s( $r[12] ?? '' ),
                'techScheduleUrl'     => tlt_cb_s( $r[13] ?? '' ),
            ];
        } else {
            $config[ $label ] = tlt_cb_s( $r[1] ?? '' );
        }
    }
    return tlt_cb_ok( [ 'config' => $config, 'shows' => $shows ] );
}

/* ===========================================================================
 * ENDPOINT: GET /combinable-shows?show=Foo&role=Director&firstName=X&lastName=Y
 * → shows OTHER than the current one where the same person has the same role.
 * ======================================================================== */
function tlt_callboard_ep_get_combinable_shows( WP_REST_Request $req ) {
    $current = tlt_cb_s( $req->get_param( 'show' ) );
    $role    = tlt_cb_s( $req->get_param( 'role' ) );
    $first   = tlt_cb_s( $req->get_param( 'firstName' ) );
    $last    = tlt_cb_s( $req->get_param( 'lastName' ) );
    if ( ! $current || ! $role || ! $first || ! $last ) {
        return new WP_Error( 'missing_params', 'show/role/firstName/lastName all required', [ 'status' => 400 ] );
    }
    $rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_SHEET_ID, "'Production Teams'!A2:E" );
    if ( is_wp_error( $rows ) ) return $rows;

    $out = [];
    foreach ( $rows as $r ) {
        $show = tlt_cb_s( $r[0] ?? '' );
        if ( $show === '' || $show === $current ) continue;
        if ( tlt_cb_s( $r[1] ?? '' ) !== $role )  continue;
        if ( tlt_cb_s( $r[2] ?? '' ) !== $first ) continue;
        if ( tlt_cb_s( $r[4] ?? '' ) !== $last )  continue;
        $out[] = $show;
    }
    // Dedupe while preserving order.
    $seen = []; $unique = [];
    foreach ( $out as $s ) if ( ! isset( $seen[ $s ] ) ) { $seen[ $s ] = true; $unique[] = $s; }
    return tlt_cb_ok( $unique );
}

/* ===========================================================================
 * ENDPOINT: GET /schedule-link?show=Foo  →  { url, cached }
 * Phase 1 read-only: returns the cached URL from Season col N (index 13).
 * If empty, tells the frontend to open the old GAS webapp to generate.
 * ======================================================================== */
function tlt_callboard_ep_get_schedule_link( WP_REST_Request $req ) {
    return tlt_cb_get_season_link( $req, 13, 'techScheduleUrl' );
}

/* ===========================================================================
 * ENDPOINT: GET /contact-sheet-link?show=Foo  →  { url, cached }
 * Same pattern — Season col M (index 12).
 * ======================================================================== */
function tlt_callboard_ep_get_contact_sheet_link( WP_REST_Request $req ) {
    return tlt_cb_get_season_link( $req, 12, 'contactSheetUrl' );
}

function tlt_cb_get_season_link( $req, $col_index, $label ) {
    $show = tlt_cb_s( $req->get_param( 'show' ) );
    if ( $show === '' ) return new WP_Error( 'missing_show', 'show query param required', [ 'status' => 400 ] );
    $rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_SHEET_ID, 'Season!A2:N' );
    if ( is_wp_error( $rows ) ) return $rows;
    foreach ( $rows as $r ) {
        if ( tlt_cb_s( $r[1] ?? '' ) !== $show ) continue;
        $url = tlt_cb_s( $r[ $col_index ] ?? '' );
        return tlt_cb_ok( [
            'url'         => $url,
            'cached'      => $url !== '',
            'label'       => $label,
            'generateHint'=> $url === '' ? 'Not yet generated. Use the old Callboard to generate for the first time.' : null,
        ] );
    }
    return tlt_cb_ok( [ 'url' => '', 'cached' => false, 'label' => $label, 'generateHint' => 'Show not found in Season tab.' ] );
}

/* ===========================================================================
 * ENDPOINT: GET /calendar-events  →  flat list of every Dates row.
 * ======================================================================== */
function tlt_callboard_ep_get_calendar_events( WP_REST_Request $req ) {
    $rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_SHEET_ID, 'Dates!A2:H' );
    if ( is_wp_error( $rows ) ) return $rows;
    $performance_types = [ 'Performance', 'Opening Performance', 'Closing Performance' ];
    $out = [];
    foreach ( $rows as $r ) {
        $show = tlt_cb_s( $r[0] ?? '' );
        $type = tlt_cb_s( $r[1] ?? '' );
        $date = tlt_cb_s( $r[4] ?? '' );
        if ( $show === '' || $date === '' ) continue;
        $out[] = [
            'show'          => $show,
            'eventType'     => $type,
            'notes'         => tlt_cb_s( $r[2] ?? '' ),
            'date'          => $date,
            'time'          => tlt_cb_s( $r[5] ?? '' ),
            'endTime'       => tlt_cb_s( $r[7] ?? '' ),
            'isPerformance' => in_array( $type, $performance_types, true ),
        ];
    }
    return tlt_cb_ok( $out );
}

/* ===========================================================================
 * ENDPOINT: GET /calendar-conflicts
 * → { "YYYY-MM-DD": [ { show, firstName, lastName, role, eventType, notes }, ... ] }
 * ======================================================================== */
function tlt_callboard_ep_get_calendar_conflicts( WP_REST_Request $req ) {
    // Conflicts tab schema: A=show, B=firstName, C=lastName, D=role, E=date,
    // F=eventType, G=notes  (adjust if the real schema differs — see quirks).
    $rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_SHEET_ID, 'Conflicts!A2:H' );
    if ( is_wp_error( $rows ) ) return $rows;
    $by_date = [];
    foreach ( $rows as $r ) {
        $date = tlt_cb_s( $r[4] ?? '' );
        if ( $date === '' ) continue;
        $by_date[ $date ][] = [
            'show'      => tlt_cb_s( $r[0] ?? '' ),
            'firstName' => tlt_cb_s( $r[1] ?? '' ),
            'lastName'  => tlt_cb_s( $r[2] ?? '' ),
            'role'      => tlt_cb_s( $r[3] ?? '' ),
            'eventType' => tlt_cb_s( $r[5] ?? '' ),
            'notes'     => tlt_cb_s( $r[6] ?? '' ),
        ];
    }
    return tlt_cb_ok( $by_date );
}

/* ===========================================================================
 * ENDPOINT: GET /program?show=Foo
 * → { info, staff, cast, team } — everything the Programs tab needs.
 * ======================================================================== */
function tlt_callboard_ep_get_program( WP_REST_Request $req ) {
    $show = tlt_cb_s( $req->get_param( 'show' ) );
    if ( $show === '' ) return new WP_Error( 'missing_show', 'show query param required', [ 'status' => 400 ] );

    $data = tlt_callboard_sheets_get( TLT_CALLBOARD_SHEET_ID, [
        'Season!A2:N',
        'Theatre!A2:D200',
        "'Production Teams'!A2:S",
        'Actors!A2:S',
        'Dates!A2:H',
    ] );
    if ( is_wp_error( $data ) ) return $data;

    // Info: Season row for this show + related season config.
    $info = null;
    foreach ( $data['Season!A2:N'] ?? [] as $r ) {
        if ( tlt_cb_s( $r[1] ?? '' ) === $show ) {
            $info = [
                'name'            => $show,
                'sharedDriveUrl'  => tlt_cb_s( $r[10] ?? '' ),
                'bioDocUrl'       => tlt_cb_s( $r[11] ?? '' ),
                'contactSheetUrl' => tlt_cb_s( $r[12] ?? '' ),
                'techScheduleUrl' => tlt_cb_s( $r[13] ?? '' ),
            ];
            break;
        }
    }

    // Staff: Theatre tab rows with display order (col D not blank).
    $staff = [];
    foreach ( $data['Theatre!A2:D200'] ?? [] as $r ) {
        $order = tlt_cb_s( $r[3] ?? '' );
        if ( $order === '' ) continue;
        $staff[] = [
            'role'  => tlt_cb_s( $r[0] ?? '' ),
            'name'  => tlt_cb_s( $r[1] ?? '' ),
            'order' => (int) $order,
        ];
    }
    usort( $staff, function ( $a, $b ) { return $a['order'] <=> $b['order']; } );

    // Team: this show's Production Teams entries.
    $team = [];
    foreach ( $data["'Production Teams'!A2:S"] ?? [] as $r ) {
        if ( tlt_cb_s( $r[0] ?? '' ) !== $show ) continue;
        if ( tlt_cb_s( $r[2] ?? '' ) === '' ) continue;
        $team[] = [
            'role'      => tlt_cb_s( $r[1] ?? '' ),
            'firstName' => tlt_cb_s( $r[2] ?? '' ),
            'lastName'  => tlt_cb_s( $r[4] ?? '' ),
        ];
    }

    // Cast: this show's Actors entries.
    $cast = [];
    foreach ( $data['Actors!A2:S'] ?? [] as $r ) {
        if ( tlt_cb_s( $r[0] ?? '' ) !== $show ) continue;
        if ( tlt_cb_s( $r[2] ?? '' ) === '' ) continue;
        $cast[] = [
            'character' => tlt_cb_s( $r[1] ?? '' ),
            'firstName' => tlt_cb_s( $r[2] ?? '' ),
            'lastName'  => tlt_cb_s( $r[4] ?? '' ),
        ];
    }

    return tlt_cb_ok( [
        'info'  => $info,
        'staff' => $staff,
        'team'  => $team,
        'cast'  => $cast,
    ] );
}
