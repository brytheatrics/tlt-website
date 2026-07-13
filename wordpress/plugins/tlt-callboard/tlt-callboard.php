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

// Drive folder + template used by /contact-sheet-generate. Both must be shared
// as Editor with the SA email. Values match the GAS ContactSheet.gs constants.
if ( ! defined( 'TLT_CALLBOARD_CS_FOLDER_ID' ) )   define( 'TLT_CALLBOARD_CS_FOLDER_ID',   '18CAXsUPT2WZgGBDbP-SGZeYbI0W-LSC_' );
if ( ! defined( 'TLT_CALLBOARD_CS_TEMPLATE_ID' ) ) define( 'TLT_CALLBOARD_CS_TEMPLATE_ID', '1vFJOkb8GI4SVhjdNIELlhZ8K2BjpK9cJtkfEBVGnz7s' );

// Tech schedule generator (from GAS TechScheduleGenerator.js constants).
if ( ! defined( 'TLT_CALLBOARD_TS_FOLDER_ID' ) )   define( 'TLT_CALLBOARD_TS_FOLDER_ID',   '1eAk4aNXBdbBVG6pJt4GDd9rf3Qg37UJT' );
if ( ! defined( 'TLT_CALLBOARD_TS_TEMPLATE_ID' ) ) define( 'TLT_CALLBOARD_TS_TEMPLATE_ID', '138nn2ZR_VKywXYakTWOchtNUzbwv_uebt7VQA5SKuEw' );

// Bios doc compilation (GAS BiosManager.js).
if ( ! defined( 'TLT_CALLBOARD_BIOS_FOLDER_ID' ) ) define( 'TLT_CALLBOARD_BIOS_FOLDER_ID', '1_hUkdeqSFZJtI49MPg52p22GmQnZ58Pq' );

// Contract generation (GAS ContractGenerator.js).
if ( ! defined( 'TLT_CALLBOARD_CONTRACT_ROOT_FOLDER_ID' ) ) define( 'TLT_CALLBOARD_CONTRACT_ROOT_FOLDER_ID', '1azafGrlfByl7kgVtUYBr3JhzVPO34pxZ' );
if ( ! defined( 'TLT_CALLBOARD_DUTIES_DOC_ID' ) )          define( 'TLT_CALLBOARD_DUTIES_DOC_ID',          '1kEDGRgKmpyzxnop36L77AQXOGaeVYFbnScBh1R_KNLI' );
if ( ! defined( 'TLT_CALLBOARD_TPL_GENERAL' ) )   define( 'TLT_CALLBOARD_TPL_GENERAL',   '1tfXC6fk7MiqJXMPUYoFPrShe380pV266psAzfGXq0V0' );
if ( ! defined( 'TLT_CALLBOARD_TPL_DIRECTOR' ) )  define( 'TLT_CALLBOARD_TPL_DIRECTOR',  '11M2io31fUcaKyIyfaivxA2Yqm0hdbs2ae2WgKzP_KiQ' );
if ( ! defined( 'TLT_CALLBOARD_TPL_ACTOR' ) )     define( 'TLT_CALLBOARD_TPL_ACTOR',     '1SD-bwwuwUMHulsOY1IIhjaK8xNGkek0HMOe8xrScmxw' );
if ( ! defined( 'TLT_CALLBOARD_TPL_OPERATOR' ) )  define( 'TLT_CALLBOARD_TPL_OPERATOR',  '1bdL4jz0GM1gQ1haXQ8uYFvXvQMmhsmc_z2KXR_DJvpQ' );
if ( ! defined( 'TLT_CALLBOARD_HANDBOOK_URL' ) )  define( 'TLT_CALLBOARD_HANDBOOK_URL',  'https://docs.google.com/document/d/1uVtm_ZC06MJel5WOW9bY0DSjMqETA6jWBTIbF9HXguk/preview' );

// Emergency Info (Phase B/C — Medical + WATCH release form).
// Templates auto-bootstrap into TEMPLATE_PARENT_FOLDER; IDs cached in wp_options.
// Season folder ID is read live from the Season tab (matches GAS behavior).
if ( ! defined( 'TLT_CALLBOARD_EMERGENCY_TAB' ) )                define( 'TLT_CALLBOARD_EMERGENCY_TAB',                'Emergency Info' );
if ( ! defined( 'TLT_CALLBOARD_EMERGENCY_WATCH_SHARE_EMAIL' ) )  define( 'TLT_CALLBOARD_EMERGENCY_WATCH_SHARE_EMAIL',  'chris@tacomalittletheatre.com' );
if ( ! defined( 'TLT_CALLBOARD_EMERGENCY_TEMPLATE_PARENT_FOLDER' ) ) define( 'TLT_CALLBOARD_EMERGENCY_TEMPLATE_PARENT_FOLDER', TLT_CALLBOARD_CS_FOLDER_ID );

// External API integrations. These MUST be defined in wp-config.php on the
// production server — the plugin returns a clear config-missing error at
// request time when unset. Never commit these values.
//   TLT_CALLBOARD_OPENSIGN_KEY   — OpenSign REST API token (x-api-token header)
//   TLT_CALLBOARD_RESEND_KEY     — Resend Bearer token for outbound email
//   TLT_CALLBOARD_MAIL_FROM      — "Name <email@…>" — default "Tacoma Little Theatre <contracts@tacomalittletheatre.com>"
//   TLT_CALLBOARD_MAIL_REPLY_TO  — reply-to header. Defaults to tlt@…
//   TLT_CALLBOARD_MAIL_BCC       — bcc address on outbound messages
if ( ! defined( 'TLT_CALLBOARD_OPENSIGN_URL' ) ) define( 'TLT_CALLBOARD_OPENSIGN_URL', 'https://app.opensignlabs.com/api/v1' );
if ( ! defined( 'TLT_CALLBOARD_RESEND_URL' ) )   define( 'TLT_CALLBOARD_RESEND_URL',   'https://api.resend.com/emails' );
if ( ! defined( 'TLT_CALLBOARD_MAIL_FROM' ) )    define( 'TLT_CALLBOARD_MAIL_FROM',    'Tacoma Little Theatre <contracts@tacomalittletheatre.com>' );
if ( ! defined( 'TLT_CALLBOARD_MAIL_REPLY_TO' ) ) define( 'TLT_CALLBOARD_MAIL_REPLY_TO', 'tlt@tacomalittletheatre.com' );
if ( ! defined( 'TLT_CALLBOARD_MAIL_BCC' ) )     define( 'TLT_CALLBOARD_MAIL_BCC',     'contracts@tacomalittletheatre.com' );

const TLT_CALLBOARD_ROUTE_NS  = 'callboard/v1';
const TLT_CALLBOARD_SESSION_TTL = 30 * DAY_IN_SECONDS;      // login persists per-device
const TLT_CALLBOARD_CACHE_TTL   = 60;                        // read cache
const TLT_CALLBOARD_CONTACT_TTL = 600;                       // mirrors existing GAS 10-min contact cache

/* ---------------------------------------------------------------------------
 * Google service-account auth. Hand-rolled JWT → access token; cached 55 min.
 * ------------------------------------------------------------------------- */
function tlt_callboard_google_access_token() {
    // Token cache key is versioned per (scope set, impersonation subject).
    // Bump the suffix any time either changes so old cached tokens are ignored.
    $impersonate = defined( 'TLT_CALLBOARD_SA_IMPERSONATE' ) ? TLT_CALLBOARD_SA_IMPERSONATE : '';
    $cache_key = 'tlt_cb_google_token_v3_' . md5( $impersonate );
    $cached = get_transient( $cache_key );
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
    // Sheets (read/write shows/actors/contacts), Drive (copy/list/trash contact
    // sheet + tech schedule + bio + contract docs), Docs (build those docs via
    // documents.batchUpdate). The SA must be shared as Editor on any target
    // Drive folder + template file, OR (better) domain-wide delegation is
    // configured and TLT_CALLBOARD_SA_IMPERSONATE names the user to
    // impersonate — files then get owned by that real user and use their
    // (paid) workspace quota instead of the SA's zero-quota storage.
    $claims = [
        'iss'   => $sa['client_email'],
        'scope' => implode( ' ', [
            'https://www.googleapis.com/auth/spreadsheets',
            'https://www.googleapis.com/auth/drive',
            'https://www.googleapis.com/auth/documents',
        ] ),
        'aud'   => 'https://oauth2.googleapis.com/token',
        'iat'   => $now,
        'exp'   => $now + 3600,
    ];
    if ( $impersonate !== '' ) $claims['sub'] = $impersonate;
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
    set_transient( $cache_key, $data['access_token'], 55 * MINUTE_IN_SECONDS );
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
    // Version-scoped cache key. Bumping the version orphans every prior cache
    // entry without touching WP sessions / other transients. See tlt_cb_bump_cache().
    $v = (int) get_option( 'tlt_cb_cache_version', 1 );
    $key = 'tlt_cb_range_v' . $v . '_' . md5( $spreadsheet_id . '|' . implode( ',', $ranges ) );
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

/**
 * Bump the cache version — instantly invalidates every read-cache key without
 * touching sessions, Google tokens, or unrelated site transients. Safe to call
 * from any mutation or the manual purge endpoint.
 */
function tlt_cb_bump_cache() {
    $v = (int) get_option( 'tlt_cb_cache_version', 1 );
    update_option( 'tlt_cb_cache_version', $v + 1, false );
}

/* Convenience: fetch a single range and return just the row array. */
function tlt_callboard_sheet_rows( $spreadsheet_id, $range, $ttl = TLT_CALLBOARD_CACHE_TTL, $force = false ) {
    $result = tlt_callboard_sheets_get( $spreadsheet_id, [ $range ], $ttl, $force );
    if ( is_wp_error( $result ) ) return $result;
    return $result[ $range ] ?? [];
}

/**
 * Write to a single cell/range in Sheets. Uses values.update (USER_ENTERED so
 * dates + numbers behave sensibly). Purges any range-cache transients that
 * overlap the touched range so subsequent reads see the fresh value.
 *
 * @param string $spreadsheet_id
 * @param string $range   A1 notation, e.g. "'Production Teams'!P42"
 * @param array  $values  2D array of rows; single cell = [[ 'BRY' ]]
 * @return array|WP_Error  API response body on success
 */
function tlt_callboard_sheets_write( $spreadsheet_id, $range, $values ) {
    $token = tlt_callboard_google_access_token();
    if ( is_wp_error( $token ) ) return $token;

    $url = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheet_id}/values/"
        . rawurlencode( $range ) . '?valueInputOption=USER_ENTERED';

    $resp = wp_remote_request( $url, [
        'method'  => 'PUT',
        'timeout' => 30,
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'  => 'application/json',
        ],
        'body'    => wp_json_encode( [ 'values' => $values ] ),
    ] );
    if ( is_wp_error( $resp ) ) return $resp;
    $code = wp_remote_retrieve_response_code( $resp );
    if ( $code < 200 || $code >= 300 ) {
        return new WP_Error( 'sheets_write_http', "Sheets write returned $code: " . wp_remote_retrieve_body( $resp ) );
    }

    // Invalidate every range cache safely — bumps a version number so
    // sessions/tokens/other transients are untouched.
    tlt_cb_bump_cache();
    return json_decode( wp_remote_retrieve_body( $resp ), true );
}

/* ---------------------------------------------------------------------------
 * Drive API v3 helpers.
 *
 * The SA needs Editor access to any folder we copy into and Reader access to
 * any template file. Errors bubble up as WP_Errors so endpoint handlers can
 * return them straight to the client.
 * ------------------------------------------------------------------------- */

/**
 * Copy a Drive file (template) into a target folder with a new name.
 *
 * @param string $template_id Source file ID
 * @param string $folder_id   Destination folder ID
 * @param string $new_name    Name for the copy
 * @return array|WP_Error     Decoded file resource ({ id, name, ... })
 */
function tlt_cb_drive_copy( $template_id, $folder_id, $new_name ) {
    $token = tlt_callboard_google_access_token();
    if ( is_wp_error( $token ) ) return $token;
    // Google Drive silently ignores `parents:` in the copy body when the target
    // folder is in a different user's My Drive from the impersonating account
    // (files land in the impersonating account's root instead). We ask for the
    // file's `parents` back so we can explicitly move it if needed.
    $url  = 'https://www.googleapis.com/drive/v3/files/' . rawurlencode( $template_id )
          . '/copy?supportsAllDrives=true&fields=id,name,webViewLink,parents';
    $resp = wp_remote_post( $url, [
        'timeout' => 30,
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'  => 'application/json',
        ],
        'body' => wp_json_encode( [
            'name'    => $new_name,
            'parents' => [ $folder_id ],
        ] ),
    ] );
    if ( is_wp_error( $resp ) ) return $resp;
    $code = wp_remote_retrieve_response_code( $resp );
    $body = wp_remote_retrieve_body( $resp );
    if ( $code < 200 || $code >= 300 ) {
        return new WP_Error( 'drive_copy_http', "Drive copy returned $code: $body" );
    }
    $data = json_decode( $body, true );

    // If Drive dropped the file somewhere other than $folder_id (e.g. into the
    // impersonating account's root), move it explicitly via addParents/removeParents.
    $current_parents = ! empty( $data['parents'] ) && is_array( $data['parents'] ) ? $data['parents'] : [];
    if ( ! empty( $data['id'] ) && ! in_array( $folder_id, $current_parents, true ) ) {
        $move_url = 'https://www.googleapis.com/drive/v3/files/' . rawurlencode( $data['id'] )
                  . '?addParents=' . rawurlencode( $folder_id )
                  . '&removeParents=' . rawurlencode( implode( ',', $current_parents ) )
                  . '&supportsAllDrives=true&fields=id,name,webViewLink,parents';
        $mv = wp_remote_request( $move_url, [
            'method'  => 'PATCH',
            'timeout' => 15,
            'headers' => [ 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json' ],
            'body'    => wp_json_encode( new stdClass() ),
        ] );
        if ( ! is_wp_error( $mv ) ) {
            $mv_code = wp_remote_retrieve_response_code( $mv );
            if ( $mv_code >= 200 && $mv_code < 300 ) {
                $moved = json_decode( wp_remote_retrieve_body( $mv ), true );
                if ( is_array( $moved ) ) $data = $moved + $data;
            }
        }
    }
    return $data;
}

/**
 * Find files in a folder by exact name. Returns array of {id, name} or empty.
 *
 * @param string $folder_id Parent folder ID
 * @param string $name      Exact filename (untrashed only)
 * @return array|WP_Error
 */
function tlt_cb_drive_find_in_folder( $folder_id, $name ) {
    $token = tlt_callboard_google_access_token();
    if ( is_wp_error( $token ) ) return $token;
    // Escape single quotes in the name for the q= expression.
    $escaped_name = str_replace( "'", "\\'", $name );
    $q = "name = '{$escaped_name}' and '{$folder_id}' in parents and trashed = false";
    $url  = 'https://www.googleapis.com/drive/v3/files?'
          . 'q=' . rawurlencode( $q )
          . '&fields=' . rawurlencode( 'files(id,name)' )
          . '&supportsAllDrives=true&includeItemsFromAllDrives=true';
    $resp = wp_remote_get( $url, [
        'timeout' => 15,
        'headers' => [ 'Authorization' => 'Bearer ' . $token ],
    ] );
    if ( is_wp_error( $resp ) ) return $resp;
    $code = wp_remote_retrieve_response_code( $resp );
    $body = wp_remote_retrieve_body( $resp );
    if ( $code < 200 || $code >= 300 ) {
        return new WP_Error( 'drive_list_http', "Drive list returned $code: $body" );
    }
    $data = json_decode( $body, true );
    return isset( $data['files'] ) && is_array( $data['files'] ) ? $data['files'] : [];
}

/**
 * Move a file to Drive trash. Non-destructive; user can un-trash from Drive UI.
 *
 * @param string $file_id
 * @return true|WP_Error
 */
/**
 * Rename a Drive file (PATCH files.update with just a name). Doesn't require
 * ownership, only edit access — useful as a fallback when trash fails 403.
 */
function tlt_cb_drive_rename( $file_id, $new_name ) {
    $token = tlt_callboard_google_access_token();
    if ( is_wp_error( $token ) ) return $token;
    $url = 'https://www.googleapis.com/drive/v3/files/' . rawurlencode( $file_id ) . '?supportsAllDrives=true';
    $resp = wp_remote_request( $url, [
        'method'  => 'PATCH',
        'timeout' => 15,
        'headers' => [ 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json' ],
        'body'    => wp_json_encode( [ 'name' => $new_name ] ),
    ] );
    if ( is_wp_error( $resp ) ) return $resp;
    $code = wp_remote_retrieve_response_code( $resp );
    if ( $code < 200 || $code >= 300 ) {
        return new WP_Error( 'drive_rename_http', 'Drive rename returned ' . $code . ': ' . wp_remote_retrieve_body( $resp ) );
    }
    return true;
}

/**
 * Get a Drive file's current parents. Returns array of folder IDs (or []).
 */
function tlt_cb_drive_get_parents( $file_id ) {
    $token = tlt_callboard_google_access_token();
    if ( is_wp_error( $token ) ) return [];
    $resp = wp_remote_get(
        'https://www.googleapis.com/drive/v3/files/' . rawurlencode( $file_id ) . '?fields=parents&supportsAllDrives=true',
        [ 'timeout' => 15, 'headers' => [ 'Authorization' => 'Bearer ' . $token ] ]
    );
    if ( is_wp_error( $resp ) ) return [];
    $data = json_decode( wp_remote_retrieve_body( $resp ), true );
    return ! empty( $data['parents'] ) && is_array( $data['parents'] ) ? $data['parents'] : [];
}

/**
 * Add a parent folder to a Drive file. Idempotent — Drive silently no-ops if
 * the parent is already there. Returns true or WP_Error.
 */
function tlt_cb_drive_add_parent( $file_id, $parent_id ) {
    $token = tlt_callboard_google_access_token();
    if ( is_wp_error( $token ) ) return $token;
    $url = 'https://www.googleapis.com/drive/v3/files/' . rawurlencode( $file_id )
         . '?addParents=' . rawurlencode( $parent_id )
         . '&supportsAllDrives=true';
    $resp = wp_remote_request( $url, [
        'method'  => 'PATCH',
        'timeout' => 15,
        'headers' => [ 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json' ],
        'body'    => '{}',
    ] );
    if ( is_wp_error( $resp ) ) return $resp;
    $code = wp_remote_retrieve_response_code( $resp );
    if ( $code < 200 || $code >= 300 ) {
        return new WP_Error( 'drive_add_parent_http', 'Drive add-parent returned ' . $code . ': ' . wp_remote_retrieve_body( $resp ) );
    }
    return true;
}

function tlt_cb_drive_trash( $file_id ) {
    $token = tlt_callboard_google_access_token();
    if ( is_wp_error( $token ) ) return $token;
    $url = 'https://www.googleapis.com/drive/v3/files/' . rawurlencode( $file_id )
         . '?supportsAllDrives=true';
    $resp = wp_remote_request( $url, [
        'method'  => 'PATCH',
        'timeout' => 15,
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'  => 'application/json',
        ],
        'body' => wp_json_encode( [ 'trashed' => true ] ),
    ] );
    if ( is_wp_error( $resp ) ) return $resp;
    $code = wp_remote_retrieve_response_code( $resp );
    if ( $code < 200 || $code >= 300 ) {
        return new WP_Error( 'drive_trash_http', 'Drive trash returned ' . $code . ': ' . wp_remote_retrieve_body( $resp ) );
    }
    return true;
}

/**
 * Build the canonical open-in-tab URL for a Google Doc.
 */
function tlt_cb_doc_url( $doc_id ) {
    return 'https://docs.google.com/document/d/' . $doc_id . '/edit';
}

/* ---------------------------------------------------------------------------
 * Docs API v1 wrapper — documents.batchUpdate. All the doc-building endpoints
 * feed lists of requests into this.
 * ------------------------------------------------------------------------- */

/**
 * @param string $doc_id   Target document ID
 * @param array  $requests List of batchUpdate Request objects
 * @return array|WP_Error  Decoded response body
 */
function tlt_cb_docs_batch_update( $doc_id, array $requests ) {
    if ( empty( $requests ) ) return [];
    $token = tlt_callboard_google_access_token();
    if ( is_wp_error( $token ) ) return $token;
    $url  = 'https://docs.googleapis.com/v1/documents/' . rawurlencode( $doc_id ) . ':batchUpdate';
    $resp = wp_remote_post( $url, [
        'timeout' => 60,
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'  => 'application/json',
        ],
        'body' => wp_json_encode( [ 'requests' => $requests ] ),
    ] );
    if ( is_wp_error( $resp ) ) return $resp;
    $code = wp_remote_retrieve_response_code( $resp );
    $body = wp_remote_retrieve_body( $resp );
    if ( $code < 200 || $code >= 300 ) {
        return new WP_Error( 'docs_batch_http', "Docs batchUpdate returned $code: $body" );
    }
    return json_decode( $body, true );
}

/**
 * Fetch a document (or subset via ?fields=) — used to discover current end
 * index before appending, or to inspect header structure.
 *
 * @param string $doc_id
 * @param string $fields Optional Google fields mask (e.g. "body(content(endIndex))")
 * @return array|WP_Error
 */
function tlt_cb_docs_get( $doc_id, $fields = '' ) {
    $token = tlt_callboard_google_access_token();
    if ( is_wp_error( $token ) ) return $token;
    $url = 'https://docs.googleapis.com/v1/documents/' . rawurlencode( $doc_id );
    if ( $fields !== '' ) $url .= '?fields=' . rawurlencode( $fields );
    $resp = wp_remote_get( $url, [
        'timeout' => 15,
        'headers' => [ 'Authorization' => 'Bearer ' . $token ],
    ] );
    if ( is_wp_error( $resp ) ) return $resp;
    $code = wp_remote_retrieve_response_code( $resp );
    $body = wp_remote_retrieve_body( $resp );
    if ( $code < 200 || $code >= 300 ) {
        return new WP_Error( 'docs_get_http', "Docs get returned $code: $body" );
    }
    return json_decode( $body, true );
}

/**
 * Find the 1-based row number in a tab where cols match given values.
 * Returns 0 if no match. Used to translate (show, role, firstName) tuples
 * into a specific A1 row address for a targeted write.
 *
 * @param array $rows  Sheet rows starting at row 2 (headers stripped by caller).
 * @param array $match Map of col-index → expected value (case-sensitive).
 * @param int   $offset How many rows are above `$rows` in the sheet (typically 1 = header).
 */
function tlt_cb_find_row( $rows, array $match, $offset = 1 ) {
    foreach ( $rows as $i => $r ) {
        $all_ok = true;
        foreach ( $match as $col => $expected ) {
            if ( tlt_cb_s( $r[ $col ] ?? '' ) !== tlt_cb_s( $expected ) ) { $all_ok = false; break; }
        }
        if ( $all_ok ) return $i + 1 + $offset;
    }
    return 0;
}

/* ---------------------------------------------------------------------------
 * Auth. Passwords live in Theatre col C. Any row with non-empty col C can log in.
 * Sessions are 30-day WP transients keyed by a random 32-byte token.
 * ------------------------------------------------------------------------- */

// Approver roles for Ok-to-Send checks. "Associate Producing Director" (APD)
// replaced "Associate Artistic Director" (AAD) in the old callboard — keep
// the old label recognised as a courtesy in case any Theatre rows still use it.
// Technical Director included so Blake can test end-to-end; remove after cutover.
const TLT_CALLBOARD_APPROVER_ROLES = [
    'Managing Artistic Director',
    'Associate Producing Director',
    'Associate Artistic Director',
    'Technical Director',
];

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

/**
 * Normalize a Sheets date cell to ISO YYYY-MM-DD.
 * Sheets returns FORMATTED_STRING values which follow the sheet's locale —
 * usually M/D/YYYY on US locales. Convert so downstream JS parsing works.
 * Empty / unparseable input → empty string (safe for stringly-typed callers).
 */
function tlt_cb_iso_date( $v ) {
    $v = trim( (string) ( $v ?? '' ) );
    if ( $v === '' ) return '';
    // Already ISO — passthrough.
    if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ) return $v;
    // Strip a trailing time so "8/28/2026 7:30 PM" also parses.
    $head = preg_split( '/\s+/', $v, 2 )[0];
    $ts = strtotime( $head );
    if ( ! $ts ) return $v;   // best-effort — leave as-is if we can't parse
    return date( 'Y-m-d', $ts );
}

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

    // ----- Public bio submission API (token-authed via URL param) -----
    // Called by the /bio/ frontend and the legacy Netlify frontend.
    register_rest_route( $ns, '/bio-contact', [
        'methods'             => 'GET',
        'callback'            => 'tlt_callboard_ep_bio_contact',
        'permission_callback' => '__return_true',
    ] );
    register_rest_route( $ns, '/bio-submit', [
        'methods'             => 'POST',
        'callback'            => 'tlt_callboard_ep_bio_submit',
        'permission_callback' => '__return_true',
    ] );
    register_rest_route( $ns, '/bio-update-contact', [
        'methods'             => 'POST',
        'callback'            => 'tlt_callboard_ep_bio_update_contact',
        'permission_callback' => '__return_true',
    ] );
    register_rest_route( $ns, '/bio-save-conflicts', [
        'methods'             => 'POST',
        'callback'            => 'tlt_callboard_ep_bio_save_conflicts',
        'permission_callback' => '__return_true',
    ] );
    // Emergency Info — Medical + WATCH release forms. Public, token-authed.
    register_rest_route( $ns, '/bio-emergency', [
        'methods'             => 'GET',
        'callback'            => 'tlt_callboard_ep_bio_emergency',
        'permission_callback' => '__return_true',
    ] );
    register_rest_route( $ns, '/bio-emergency-submit', [
        'methods'             => 'POST',
        'callback'            => 'tlt_callboard_ep_bio_emergency_submit',
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
    $auth_route( '/schedule-link',              'tlt_callboard_ep_get_schedule_link_v2' );
    $auth_route( '/contact-sheet-link',         'tlt_callboard_ep_get_contact_sheet_link' );
    $auth_route( '/calendar-events',            'tlt_callboard_ep_get_calendar_events' );
    $auth_route( '/calendar-conflicts',         'tlt_callboard_ep_get_calendar_conflicts' );
    $auth_route( '/program',                    'tlt_callboard_ep_get_program' );

    // ----- Approval helper -----
    $auth_route( '/verify-approval',            'tlt_callboard_ep_verify_approval' ); // ?password=...

    // ---------- Phase 2 mutations ----------
    $post_route = function ( $path, $handler ) use ( $ns ) {
        register_rest_route( $ns, $path, [
            'methods'             => 'POST',
            'callback'            => $handler,
            'permission_callback' => 'tlt_callboard_require_auth',
        ] );
    };
    $post_route( '/set-ok-to-send',       'tlt_callboard_ep_set_ok_to_send' );
    $post_route( '/save-contact',         'tlt_callboard_ep_save_contact' );
    $post_route( '/delete-contact',       'tlt_callboard_ep_delete_contact' );
    $post_route( '/sync-contactbook',     'tlt_callboard_ep_sync_contactbook' );
    // Phase 2a-4/5/6
    $post_route( '/add-role',             'tlt_callboard_ep_add_role' );
    $post_route( '/update-person',        'tlt_callboard_ep_update_person' );
    $post_route( '/delete-role',          'tlt_callboard_ep_delete_role' );
    $post_route( '/remove-person',        'tlt_callboard_ep_remove_person' );
    $post_route( '/add-actor',            'tlt_callboard_ep_add_actor' );
    $post_route( '/remove-actor',         'tlt_callboard_ep_remove_actor' );
    $post_route( '/import-actors',        'tlt_callboard_ep_import_actors' );
    $post_route( '/save-program-fields',  'tlt_callboard_ep_save_program_fields' );
    // Contact sheet generation (Docs API port of ContactSheetGenerator.gs)
    $post_route( '/contact-sheet-generate',    'tlt_callboard_ep_contact_sheet_generate' );
    $post_route( '/contact-sheet-regenerate',  'tlt_callboard_ep_contact_sheet_regenerate' );
    $post_route( '/contact-sheet-add-to-show', 'tlt_callboard_ep_contact_sheet_add_to_show' );
    $post_route( '/tech-schedule-add-to-show', 'tlt_callboard_ep_tech_schedule_add_to_show' );
    // Tech schedule
    $post_route( '/tech-schedule-generate',   'tlt_callboard_ep_tech_schedule_generate' );
    // Bios
    $post_route( '/bios-doc-compile',         'tlt_callboard_ep_bios_doc_compile' );
    $post_route( '/bios-send-requests',       'tlt_callboard_ep_bios_send_requests' );
    $post_route( '/bios-resend',              'tlt_callboard_ep_bios_resend' );
    // Program export
    $post_route( '/program-export',           'tlt_callboard_ep_program_export' );
    // Contracts
    $post_route( '/contract-generate',          'tlt_callboard_ep_contract_generate' );
    $post_route( '/contract-generate-combined', 'tlt_callboard_ep_contract_generate_combined' );
    $post_route( '/contract-send',              'tlt_callboard_ep_contract_send' );
    $post_route( '/contract-send-combined',     'tlt_callboard_ep_contract_send_combined' );
    $post_route( '/contract-resend',            'tlt_callboard_ep_contract_resend' );
    $post_route( '/contract-delete',            'tlt_callboard_ep_contract_delete' );
    $post_route( '/purge-cache',          'tlt_callboard_ep_purge_cache' );
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
            'emergencyInfoStatus' => tlt_cb_s( $r[16] ?? '' ),
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
            'character'           => tlt_cb_s( $r[1] ?? '' ),
            'firstName'           => tlt_cb_s( $r[2] ?? '' ),
            'middleName'          => tlt_cb_s( $r[3] ?? '' ),
            'lastName'            => tlt_cb_s( $r[4] ?? '' ),
            'suffix'              => tlt_cb_s( $r[5] ?? '' ),
            'phone'               => tlt_cb_s( $r[6] ?? '' ),
            'email'               => tlt_cb_s( $r[7] ?? '' ),
            'contractStatus'      => tlt_cb_s( $r[8] ?? '' ),
            'bioStatus'           => tlt_cb_s( $r[13] ?? '' ),
            'bioType'             => tlt_cb_s( $r[14] ?? '' ),
            'emergencyInfoStatus' => tlt_cb_s( $r[16] ?? '' ),
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
    // Force-refresh — the calling flow is user-triggered (they've just typed a
    // password) so we want the freshest possible Theatre data, not a 60s-old
    // cache. Cheap because it's one small range.
    $password = tlt_cb_s( $req->get_param( 'password' ) );
    $rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_SHEET_ID, 'Theatre!A2:D200', TLT_CALLBOARD_CACHE_TTL, true );
    if ( is_wp_error( $rows ) ) return $rows;
    foreach ( $rows as $r ) {
        $role = tlt_cb_s( $r[0] ?? '' );
        $name = tlt_cb_s( $r[1] ?? '' );
        $pw   = tlt_cb_s( $r[2] ?? '' );
        if ( $pw === '' || $pw !== $password ) continue;
        if ( ! in_array( $role, TLT_CALLBOARD_APPROVER_ROLES, true ) ) continue;
        $initials = '';
        foreach ( preg_split( '/\s+/', $name ) as $part ) if ( $part !== '' ) $initials .= strtoupper( $part[0] );
        // Frontend checks result.valid — return BOTH `valid` and legacy `ok`
        // so nothing breaks either way.
        return tlt_cb_ok( [
            'valid'    => true,
            'ok'       => true,
            'role'     => $role,
            'initials' => $initials,
        ] );
    }
    return tlt_cb_ok( [ 'valid' => false, 'ok' => false ] );
}

/* ===========================================================================
 * SHARED HELPERS for the endpoints below.
 * ======================================================================== */

/** Load Contactbook and index by "firstlast" lowercase key for fast joins. */
function tlt_cb_load_contactbook_index() {
    // Range widened to col P for the "Alt Email" column. Existing rows without
    // that column just return empty strings — no breakage if the header hasn't
    // been added yet.
    $rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_CONTACTBOOK_ID, 'Contactbook!A2:P', TLT_CALLBOARD_CONTACT_TTL );
    if ( is_wp_error( $rows ) ) return [];
    $by_name = [];
    $by_email = [];
    $all      = [];   // one entry per sheet row, in row order — used by /contacts.
    foreach ( $rows as $r ) {
        $first = tlt_cb_s( $r[1] ?? '' );
        $last  = tlt_cb_s( $r[3] ?? '' );
        $email = strtolower( tlt_cb_s( $r[7] ?? '' ) );
        $contact = [
            'contactId'     => tlt_cb_s( $r[0] ?? '' ),
            'firstName'     => $first,
            'middleName'    => tlt_cb_s( $r[2] ?? '' ),
            'lastName'      => $last,
            'suffix'        => tlt_cb_s( $r[4] ?? '' ),
            'pronouns'      => tlt_cb_s( $r[5] ?? '' ),
            'phone'         => tlt_cb_s( $r[6] ?? '' ),
            'email'         => tlt_cb_s( $r[7] ?? '' ),
            'notes'         => tlt_cb_s( $r[8] ?? '' ),
            'skills'        => array_values( array_filter( array_map( 'trim', explode( ',', tlt_cb_s( $r[9] ?? '' ) ) ) ) ),
            'bioToken'      => tlt_cb_s( $r[10] ?? '' ),
            'tokenSentDate' => tlt_cb_s( $r[11] ?? '' ),
            'lastBioLogin'  => tlt_cb_s( $r[12] ?? '' ),
            'altEmail'      => tlt_cb_s( $r[15] ?? '' ),   // col P — set for people with two contexts (staff + actor, etc.)
        ];
        if ( $first !== '' && $last !== '' ) {
            $by_name[ strtolower( $first . '|' . $last ) ] = $contact;
        }
        if ( $email !== '' ) $by_email[ $email ] = $contact;
        // Alt email indexes to the SAME contact so name-and-alt-email lookups
        // hit as if it were the primary.
        $alt_lc = strtolower( tlt_cb_s( $r[15] ?? '' ) );
        if ( $alt_lc !== '' ) $by_email[ $alt_lc ] = $contact;
        // Only add to `all` once per sheet row — skip completely-empty rows.
        if ( $first !== '' || $last !== '' || $email !== '' ) $all[] = $contact;
    }
    return [ 'byName' => $by_name, 'byEmail' => $by_email, 'all' => $all ];
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

    // Performance count from Dates tab (not Sales — Ludus template-inflates
    // to 11 rows per show regardless of the actual run length).
    $perf_count = [];
    $performance_types = [ 'Performance', 'Opening Performance', 'Closing Performance' ];
    foreach ( $data['Dates!A2:H'] ?? [] as $r ) {
        $show = tlt_cb_s( $r[0] ?? '' );
        $type = tlt_cb_s( $r[1] ?? '' );
        if ( $show === '' || ! in_array( $type, $performance_types, true ) ) continue;
        $perf_count[ $show ] = ( $perf_count[ $show ] ?? 0 ) + 1;
    }

    $out = [];
    foreach ( $shows as $s ) {
        $row = [ 'show' => $s ] + $per[ $s ];
        $row['openingNight']  = $opening[ $s ]  ?? '';
        $total_sold = $sold[ $s ] ?? 0;
        $perf_n     = $perf_count[ $s ] ?? 0;
        $capacity   = $perf_n * 215;
        // Renderer expects show.sales to be an OBJECT (destructured as `s`).
        // Only expose it when we actually have performance rows — an empty
        // `null` hides the ticket-sales panel on shows with no sales yet.
        $row['sales'] = $perf_n > 0 ? [
            'totalSold'   => $total_sold,
            'perfCount'   => $perf_n,
            'capacity'    => $capacity,
            'capacityPct' => $capacity > 0 ? (int) round( $total_sold / $capacity * 100 ) : 0,
        ] : null;
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
            'bioStatus'           => tlt_cb_s( $r[13] ?? '' ),
            'bioType'             => tlt_cb_s( $r[14] ?? '' ),
            'emergencyInfoStatus' => tlt_cb_s( $r[16] ?? '' ),
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

    // Real per-show performance count comes from the Dates tab (scheduled
    // performances), not Sales (Ludus template-inflates to 11 rows per show
    // regardless of the actual run length).
    $dates_perf_count = [];
    $performance_types = [ 'Performance', 'Opening Performance', 'Closing Performance' ];
    foreach ( $data['Dates!A2:H'] ?? [] as $r ) {
        $show = tlt_cb_s( $r[0] ?? '' );
        $type = tlt_cb_s( $r[1] ?? '' );
        if ( $show === '' || ! in_array( $type, $performance_types, true ) ) continue;
        $dates_perf_count[ $show ] = ( $dates_perf_count[ $show ] ?? 0 ) + 1;
    }

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
            $sold_n = (int) ( $r[5] ?? 0 );
            $per[ $show ]['performances'][] = [
                'date'      => tlt_cb_s( $r[4] ?? '' ),
                'sold'      => $sold_n,
                'remaining' => max( 0, 215 - $sold_n ),
            ];
        } elseif ( $type === 'Payment' ) {
            $method = strtolower( tlt_cb_s( $r[6] ?? '' ) );
            $count  = (int) ( $r[7] ?? 0 );
            // Ludus emits multiple Payment rows per bucket (Season Ticket - Cash,
            // Season Ticket - Credit, Season Ticket - Check, etc.). Accumulate,
            // don't overwrite. Order matters: "Season Ticket - Comp" contains
            // both "season" and "comp"; we want it to count as Season Ticket.
            if ( strpos( $method, 'season' ) !== false )      $per[ $show ]['seasonTicket']  += $count;
            elseif ( strpos( $method, 'flex' ) !== false )    $per[ $show ]['flexPass']      += $count;
            elseif ( strpos( $method, 'comp' ) !== false )    $per[ $show ]['comp']          += $count;
            else                                              $per[ $show ]['individual']    += $count;
        }
    }

    // Percentages of totalSold + capacity from Dates-tab performance count × 215.
    foreach ( $per as &$row ) {
        $t = max( 1, $row['totalSold'] );
        $row['seasonPct']     = (int) round( $row['seasonTicket']  / $t * 100 );
        $row['flexPct']       = (int) round( $row['flexPass']      / $t * 100 );
        $row['compPct']       = (int) round( $row['comp']          / $t * 100 );
        $row['individualPct'] = (int) round( $row['individual']    / $t * 100 );
        $row['perfCount']     = $dates_perf_count[ $row['show'] ] ?? 0;
        $row['capacity']      = $row['perfCount'] * 215;
        $row['capacityPct']   = $row['capacity'] > 0
            ? (int) round( $row['totalSold'] / $row['capacity'] * 100 )
            : 0;
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
        "'Production Teams'!A2:Q",
        'Actors!A2:Q',
        'Season!A2:N',
    ] );
    if ( is_wp_error( $data ) ) return $data;

    // Contactbook join — used to look up each person's Token Sent Date, which
    // drives the linkSent flag the frontend reads for "Sent" vs "Not Sent".
    $cb_idx = tlt_cb_load_contactbook_index();
    $link_sent = function ( $first, $last, $email ) use ( $cb_idx ) {
        $c = tlt_cb_lookup_contact( $cb_idx, $first, $last, $email );
        return $c && ! empty( $c['tokenSentDate'] );
    };

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

    foreach ( $data["'Production Teams'!A2:Q"] ?? [] as $r ) {
        $show = tlt_cb_s( $r[0] ?? '' );
        if ( ! isset( $shows[ $show ] ) ) continue;
        if ( tlt_cb_s( $r[2] ?? '' ) === '' ) continue; // no person = skip
        $bio_status = tlt_cb_s( $r[13] ?? '' );
        $first = tlt_cb_s( $r[2] ?? '' );
        $last  = tlt_cb_s( $r[4] ?? '' );
        $email = tlt_cb_s( $r[7] ?? '' );
        $shows[ $show ]['total']++;
        if ( strcasecmp( $bio_status, 'Submitted' ) === 0 ) $shows[ $show ]['submitted']++;
        $shows[ $show ]['team'][] = [
            'firstName'        => $first,
            'lastName'         => $last,
            'role'             => tlt_cb_s( $r[1] ?? '' ),
            'character'        => '',
            'bioStatus'        => $bio_status,
            'bioType'          => tlt_cb_s( $r[14] ?? '' ),
            'emergencyInfoStatus' => tlt_cb_s( $r[16] ?? '' ),
            'linkSent'         => $link_sent( $first, $last, $email ),
        ];
    }
    foreach ( $data['Actors!A2:Q'] ?? [] as $r ) {
        $show = tlt_cb_s( $r[0] ?? '' );
        if ( ! isset( $shows[ $show ] ) ) continue;
        if ( tlt_cb_s( $r[2] ?? '' ) === '' ) continue;
        $bio_status = tlt_cb_s( $r[13] ?? '' );
        $first = tlt_cb_s( $r[2] ?? '' );
        $last  = tlt_cb_s( $r[4] ?? '' );
        $email = tlt_cb_s( $r[7] ?? '' );
        $shows[ $show ]['total']++;
        if ( strcasecmp( $bio_status, 'Submitted' ) === 0 ) $shows[ $show ]['submitted']++;
        $shows[ $show ]['actors'][] = [
            'firstName'        => $first,
            'lastName'         => $last,
            'character'        => tlt_cb_s( $r[1] ?? '' ),
            'role'             => '',
            'bioStatus'        => $bio_status,
            'bioType'          => tlt_cb_s( $r[14] ?? '' ),
            'emergencyInfoStatus' => tlt_cb_s( $r[16] ?? '' ),
            'linkSent'         => $link_sent( $first, $last, $email ),
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

    // Two frontend callers hit this endpoint with different shape expectations:
    //   getContractsPageData → wants { shows, contracts } (drives the page shell
    //     including the show-filter dropdown).
    //   getContractsData     → wants just the contracts array (post-mutation
    //     refresh — the shell is already rendered).
    // We return the wrapped shape unless ?shape=array is set. port_callboard.py
    // routes getContractsData with ?shape=array; getContractsPageData without.
    $shape = tlt_cb_s( $req->get_param( 'shape' ) );
    if ( $shape === 'array' ) return tlt_cb_ok( $contracts );
    return tlt_cb_ok( [ 'shows' => $shows, 'contracts' => $contracts ] );
}

/* ===========================================================================
 * ENDPOINT: GET /full-season
 *
 * Returns an ARRAY of per-show blocks — one per show in ShowList — with the
 * shape the Season tab (renderFullSeason) actually consumes:
 *   [ { show, total, filled, openingNight, roster: [ …rows… ] }, … ]
 *
 * "Roster" here is the same shape as /show-roster (Production Teams rows for
 * that show). filled = count where firstName is non-empty. openingNight is
 * the first Opening Performance date from Dates tab.
 * ======================================================================== */
function tlt_callboard_ep_get_full_season( WP_REST_Request $req ) {
    $data = tlt_callboard_sheets_get( TLT_CALLBOARD_SHEET_ID, [
        'ShowList',
        "'Production Teams'!A2:S",
        'Dates!A2:H',
    ] );
    if ( is_wp_error( $data ) ) return $data;

    $shows = [];
    foreach ( $data['ShowList'] ?? [] as $r ) {
        $n = tlt_cb_s( $r[0] ?? '' );
        if ( $n !== '' ) $shows[] = $n;
    }

    // Roster rows per show, matching /show-roster shape.
    $roster_by_show = [];
    foreach ( $shows as $s ) $roster_by_show[ $s ] = [];
    foreach ( $data["'Production Teams'!A2:S"] ?? [] as $r ) {
        $show = tlt_cb_s( $r[0] ?? '' );
        if ( ! isset( $roster_by_show[ $show ] ) ) continue;
        $roster_by_show[ $show ][] = [
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
            'emergencyInfoStatus' => tlt_cb_s( $r[16] ?? '' ),
        ];
    }

    // Opening night per show (first Opening Performance row).
    $opening = [];
    foreach ( $data['Dates!A2:H'] ?? [] as $r ) {
        $show = tlt_cb_s( $r[0] ?? '' );
        $type = tlt_cb_s( $r[1] ?? '' );
        $date = tlt_cb_s( $r[4] ?? '' );
        if ( $show && $type === 'Opening Performance' && $date && empty( $opening[ $show ] ) ) {
            $opening[ $show ] = $date;
        }
    }

    // Assemble the array in ShowList order.
    $out = [];
    foreach ( $shows as $s ) {
        $roster = $roster_by_show[ $s ];
        $filled = 0;
        foreach ( $roster as $row ) if ( $row['firstName'] !== '' ) $filled++;
        $out[] = [
            'show'         => $s,
            'total'        => count( $roster ),
            'filled'       => $filled,
            'openingNight' => $opening[ $s ] ?? '',
            'roster'       => $roster,
        ];
    }
    return tlt_cb_ok( $out );
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
    // GAS returns [ { show, contractStatus, combinedContractId }, ... ] — the
    // frontend's openCombineModal uses each item's contractStatus to label
    // "already sent — will be replaced" or "already signed — cannot replace",
    // and its combinedContractId to detect existing groups. Return the same
    // shape.
    $rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_SHEET_ID, "'Production Teams'!A2:S" );
    if ( is_wp_error( $rows ) ) return $rows;

    $out = [];
    $first_lc = strtolower( $first );
    $last_lc  = strtolower( $last );
    foreach ( $rows as $r ) {
        $show = tlt_cb_s( $r[0] ?? '' );
        if ( $show === '' || $show === $current ) continue;
        if ( tlt_cb_s( $r[1] ?? '' ) !== $role )  continue;
        if ( strtolower( tlt_cb_s( $r[2] ?? '' ) ) !== $first_lc ) continue;
        if ( strtolower( tlt_cb_s( $r[4] ?? '' ) ) !== $last_lc  ) continue;
        $out[] = [
            'show'               => $show,
            'contractStatus'     => tlt_cb_s( $r[8] ?? '' ) ?: 'Not Started',
            'combinedContractId' => tlt_cb_s( $r[18] ?? '' ),
        ];
    }
    // Dedupe by show while preserving order.
    $seen = []; $unique = [];
    foreach ( $out as $item ) if ( ! isset( $seen[ $item['show'] ] ) ) { $seen[ $item['show'] ] = true; $unique[] = $item; }
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
 * ENDPOINT: GET /contact-sheet-link?show=Foo  →  { url, exists, source }
 *
 * Distinct from /schedule-link because contact sheets now support "does one
 * already exist" checks — the frontend uses this to decide between showing
 * the View/Regenerate modal (exists) vs auto-generating (missing).
 *
 * source is one of:
 *   'cache' — URL was in Season col M
 *   'drive' — Season col M was empty but a matching doc lives in the folder
 *             (URL gets back-filled to col M as a side-effect)
 *   'none'  — no existing doc anywhere
 * ======================================================================== */
function tlt_callboard_ep_get_contact_sheet_link( WP_REST_Request $req ) {
    $show = tlt_cb_s( $req->get_param( 'show' ) );
    if ( $show === '' ) return new WP_Error( 'missing_show', 'show query param required', [ 'status' => 400 ] );

    $season_rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_SHEET_ID, 'Season!A1:N' );
    if ( is_wp_error( $season_rows ) ) return $season_rows;

    $season_long    = '';
    $cached_url     = '';
    $season_row_num = 0;
    foreach ( $season_rows as $i => $r ) {
        $label = tlt_cb_s( $r[0] ?? '' );
        if ( $label === 'Current Season Long' ) $season_long = tlt_cb_s( $r[1] ?? '' );
        if ( tlt_cb_s( $r[1] ?? '' ) === $show ) {
            $cached_url     = tlt_cb_s( $r[12] ?? '' );
            $season_row_num = $i + 1;
        }
    }

    if ( $cached_url !== '' ) {
        return tlt_cb_ok( [ 'url' => $cached_url, 'exists' => true, 'source' => 'cache' ] );
    }

    // Drive scan fallback — same as GAS getOrGenerateContactSheet() when the
    // Season cache is empty but a doc happens to exist in the folder already.
    // We back-fill the cache so the next check hits the fast path.
    if ( $season_long !== '' ) {
        $doc_name = tlt_cb_contact_sheet_doc_name( $show, $season_long );
        $files    = tlt_cb_drive_find_in_folder( TLT_CALLBOARD_CS_FOLDER_ID, $doc_name );
        if ( is_wp_error( $files ) ) return $files;
        if ( ! empty( $files ) ) {
            $url = tlt_cb_doc_url( $files[0]['id'] );
            if ( $season_row_num > 0 ) {
                tlt_callboard_sheets_write( TLT_CALLBOARD_SHEET_ID, "Season!M{$season_row_num}", [[ $url ]] );
            }
            return tlt_cb_ok( [ 'url' => $url, 'exists' => true, 'source' => 'drive' ] );
        }
    }

    return tlt_cb_ok( [ 'url' => '', 'exists' => false, 'source' => 'none' ] );
}

function tlt_cb_get_season_link( $req, $col_index, $label ) {
    $show = tlt_cb_s( $req->get_param( 'show' ) );
    if ( $show === '' ) return new WP_Error( 'missing_show', 'show query param required', [ 'status' => 400 ] );
    $rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_SHEET_ID, 'Season!A2:N' );
    if ( is_wp_error( $rows ) ) return $rows;
    foreach ( $rows as $r ) {
        if ( tlt_cb_s( $r[1] ?? '' ) !== $show ) continue;
        // Return the URL directly (empty string if not cached) so the frontend
        // can call `window.open(url)` without unwrapping — matches the original
        // GAS getScheduleLink / getContactSheetLink return shape.
        return tlt_cb_ok( tlt_cb_s( $r[ $col_index ] ?? '' ) );
    }
    return tlt_cb_ok( '' );
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
        $date_iso = tlt_cb_iso_date( $r[4] ?? '' );
        if ( $show === '' || $date_iso === '' ) continue;
        $out[] = [
            'show'          => $show,
            'eventType'     => $type,
            'notes'         => tlt_cb_s( $r[2] ?? '' ),
            'date'          => $date_iso,
            'time'          => tlt_cb_s( $r[5] ?? '' ),
            'endTime'       => tlt_cb_s( $r[7] ?? '' ),
            'isPerformance' => in_array( $type, $performance_types, true ),
        ];
    }
    // Frontend uses `calendarEvents[0].date` / `[last].date` as season bounds,
    // so sort ascending by ISO date (string sort is fine on YYYY-MM-DD).
    usort( $out, function ( $a, $b ) { return strcmp( $a['date'], $b['date'] ); } );
    return tlt_cb_ok( $out );
}

/* ===========================================================================
 * ENDPOINT: GET /calendar-conflicts
 * → { "YYYY-MM-DD": [ { show, firstName, lastName, role, eventType, notes }, ... ] }
 * ======================================================================== */
function tlt_callboard_ep_get_calendar_conflicts( WP_REST_Request $req ) {
    // Read from "Production Team Conflicts" tab — production team members
    // flagging dates they can't attend. Schema:
    //   A: Show           B: Contact ID   C: First Name   D: Last Name
    //   E: Role           F: Event Type   G: Event Date   H: Notes
    //   I: Submitted At   J: Last Updated
    // (Actor conflicts live in the separate "Conflicts" tab — those are for
    //  CastingManager audition scheduling, NOT what the calendar renders.)
    $rows = tlt_callboard_sheet_rows(
        TLT_CALLBOARD_SHEET_ID,
        "'Production Team Conflicts'!A2:J"
    );
    if ( is_wp_error( $rows ) ) return $rows;

    $by_date = [];
    foreach ( $rows as $r ) {
        $date = tlt_cb_iso_date( $r[6] ?? '' );
        if ( $date === '' ) continue;
        $by_date[ $date ][] = [
            'show'      => tlt_cb_s( $r[0] ?? '' ),
            'contactId' => tlt_cb_s( $r[1] ?? '' ),
            'firstName' => tlt_cb_s( $r[2] ?? '' ),
            'lastName'  => tlt_cb_s( $r[3] ?? '' ),
            'role'      => tlt_cb_s( $r[4] ?? '' ),
            'eventType' => tlt_cb_s( $r[5] ?? '' ),
            'notes'     => tlt_cb_s( $r[7] ?? '' ),
        ];
    }
    return tlt_cb_ok( $by_date );
}

/* ===========================================================================
 * ENDPOINT: GET /program?show=Foo
 * → getProgramData shape: { show, season, info, staff, productionTeam,
 *   bios: {team, cast}, italicizeTitles }. Same payload used by
 *   /program-export → InDesign consumer. Old Phase 2 stub always returned
 *   empty bios; frontend then displayed "0 of N bios submitted".
 * ======================================================================== */
function tlt_callboard_ep_get_program( WP_REST_Request $req ) {
    $show = tlt_cb_s( $req->get_param( 'show' ) );
    if ( $show === '' ) return new WP_Error( 'missing_show', 'show query param required', [ 'status' => 400 ] );
    $data = tlt_cb_program_get_data( $show );
    if ( is_wp_error( $data ) ) return $data;
    return tlt_cb_ok( $data );
}

/* ===========================================================================
 * PHASE 2 MUTATIONS
 * ======================================================================== */

/**
 * ENDPOINT: POST /set-ok-to-send
 * Body: { show, role, firstName, initials, isActor }
 *   - Production Teams: match by (show, role, firstName), write to col P (index 15).
 *   - Actors:           match by (show, character, firstName), same col P.
 *
 * Returns: { ok, wroteTo: "SheetTab!P<row>", initials }
 *
 * The frontend usually calls this after verify-approval succeeds so we trust
 * the initials it passes. Auth-required — only logged-in staff can write.
 */
function tlt_callboard_ep_set_ok_to_send( WP_REST_Request $req ) {
    $body = $req->get_json_params();
    if ( ! is_array( $body ) ) $body = [];
    $show      = tlt_cb_s( $body['show']      ?? '' );
    $role      = tlt_cb_s( $body['role']      ?? '' );  // role (team) OR character (actor); may be empty for actors
    $first     = tlt_cb_s( $body['firstName'] ?? '' );
    $initials  = tlt_cb_s( $body['initials']  ?? '' ); // empty string = uncheck (clear cell)

    if ( $show === '' || $first === '' ) {
        return new WP_Error( 'missing_params', 'show + firstName required.', [ 'status' => 400 ] );
    }
    if ( $initials !== '' && ! preg_match( '/^[A-Z]{1,4}$/', $initials ) ) {
        return new WP_Error( 'bad_initials', 'initials must be 1-4 uppercase letters (or empty to clear).', [ 'status' => 400 ] );
    }

    // Try Production Teams first (match by show + role + firstName), then fall
    // back to Actors (match by show + firstName, optionally + character if the
    // caller passed one). This lets the existing frontend call with just role
    // OR character interchangeably.
    $team_rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_SHEET_ID, "'Production Teams'!A2:Q", TLT_CALLBOARD_CACHE_TTL, true );
    if ( is_wp_error( $team_rows ) ) return $team_rows;

    $found_tab = ''; $row_1based = 0;
    if ( $role !== '' ) {
        $row_1based = tlt_cb_find_row( $team_rows, [ 0 => $show, 1 => $role, 2 => $first ], 1 );
        if ( $row_1based ) $found_tab = "'Production Teams'";
    }

    if ( ! $row_1based ) {
        $actor_rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_SHEET_ID, 'Actors!A2:Q', TLT_CALLBOARD_CACHE_TTL, true );
        if ( is_wp_error( $actor_rows ) ) return $actor_rows;
        $match = [ 0 => $show, 2 => $first ];
        if ( $role !== '' ) $match[1] = $role;   // col B on Actors = character
        $row_1based = tlt_cb_find_row( $actor_rows, $match, 1 );
        if ( $row_1based ) $found_tab = 'Actors';
    }

    if ( ! $row_1based ) {
        return new WP_Error( 'row_not_found', 'No row matched the given show / role / firstName.', [ 'status' => 404 ] );
    }

    $cell = $found_tab . '!P' . $row_1based;
    $write = tlt_callboard_sheets_write( TLT_CALLBOARD_SHEET_ID, $cell, [ [ $initials ] ] );
    if ( is_wp_error( $write ) ) return $write;

    return tlt_cb_ok( [
        'wroteTo'  => $cell,
        'initials' => $initials,
    ] );
}

/* ---------------------------------------------------------------------------
 * Internal helper — fanout a contact's phone/email/name to every Production
 * Teams + Actors row that matches. Match key: prefer email (case-insensitive)
 * so a name-change is safe; fall back to firstName+lastName so a first-time
 * email add still finds the person.
 *
 * Returns int count of updated rows (0 if none matched).
 * ------------------------------------------------------------------------- */
function tlt_cb_sync_contact_to_shows( $first, $last, $new_email, $new_phone, $prev_email = '', $prev_first = '', $prev_last = '', $new_alt = '', $prev_alt = '' ) {
    // Case-insensitive versions of every candidate email for matching.
    $prev_primary_lc = strtolower( trim( (string) $prev_email ) );
    $prev_alt_lc     = strtolower( trim( (string) $prev_alt ) );
    $new_primary_lc  = strtolower( trim( (string) $new_email ) );
    $new_alt_lc      = strtolower( trim( (string) $new_alt ) );
    $need_first = strtolower( trim( (string) $prev_first ?: $first ) );
    $need_last  = strtolower( trim( (string) $prev_last  ?: $last ) );

    $updated = 0;
    $targets = [
        [ 'tab' => "'Production Teams'", 'range' => "'Production Teams'!A2:S" ],
        [ 'tab' => 'Actors',             'range' => 'Actors!A2:S' ],
    ];
    foreach ( $targets as $t ) {
        $rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_SHEET_ID, $t['range'], TLT_CALLBOARD_CACHE_TTL, true );
        if ( is_wp_error( $rows ) ) continue;
        foreach ( $rows as $i => $r ) {
            $row_email_lc = strtolower( tlt_cb_s( $r[7] ?? '' ) );
            $row_first_lc = strtolower( tlt_cb_s( $r[2] ?? '' ) );
            $row_last_lc  = strtolower( tlt_cb_s( $r[4] ?? '' ) );

            // How does this row match the contact?
            //   1. Row's email == primary (or was previously primary)  → this is a "primary-context" row (staff role, etc.)
            //   2. Row's email == alt (or was previously alt)          → "alt-context" row (personal-email role)
            //   3. Row matches by name and email is empty              → primary-context by default
            //   4. Row matches by name but has a totally different email → skip; don't clobber intentional divergence
            $context = null;
            if ( $row_email_lc !== '' && (
                    $row_email_lc === $prev_primary_lc || $row_email_lc === $new_primary_lc
                 ) ) {
                $context = 'primary';
            } elseif ( $row_email_lc !== '' && $prev_alt_lc !== '' && (
                    $row_email_lc === $prev_alt_lc || $row_email_lc === $new_alt_lc
                 ) ) {
                $context = 'alt';
            } elseif ( $row_email_lc === '' && $row_first_lc === $need_first && $row_last_lc === $need_last ) {
                $context = 'primary';   // fresh assign — assume primary
            }
            if ( $context === null ) continue;

            // Choose the email to write based on the row's context.
            $email_to_write = $context === 'alt' ? $new_email : $new_email;
            if ( $context === 'alt' && $new_alt !== '' ) $email_to_write = $new_alt;

            $row_1based = $i + 2;
            $range = $t['tab'] . '!C' . $row_1based . ':H' . $row_1based;
            $write = tlt_callboard_sheets_write( TLT_CALLBOARD_SHEET_ID, $range, [ [
                $first,
                tlt_cb_s( $r[3] ?? '' ),   // preserve middle
                $last,
                tlt_cb_s( $r[5] ?? '' ),   // preserve suffix
                $new_phone,
                $email_to_write,
            ] ] );
            if ( ! is_wp_error( $write ) ) $updated++;
        }
    }
    return $updated;
}

/* ===========================================================================
 * ENDPOINT: POST /save-contact
 * Body: contactData — { firstName, middleName?, lastName, suffix?, pronouns?,
 *                       phone?, email, notes?, skills? }
 *
 * Upsert to Contactbook keyed by email (case-insensitive). If no email match,
 * fall back to firstName+lastName. If still no match, assign the next
 * TLT-NNNN ID and append a new row. Preserves K/L/M (bio token, sent, login)
 * on existing rows. Then fans out to Production Teams + Actors.
 * ======================================================================== */
function tlt_callboard_ep_save_contact( WP_REST_Request $req ) {
    $body = $req->get_json_params();
    if ( ! is_array( $body ) ) return new WP_Error( 'bad_body', 'JSON body required.', [ 'status' => 400 ] );

    $first  = tlt_cb_s( $body['firstName']  ?? '' );
    $last   = tlt_cb_s( $body['lastName']   ?? '' );
    $email  = tlt_cb_s( $body['email']      ?? '' );

    if ( $first === '' || $last === '' ) {
        return new WP_Error( 'missing_names', 'firstName + lastName required.', [ 'status' => 400 ] );
    }
    if ( $email === '' ) {
        return new WP_Error( 'missing_email', 'email required (used for identity + fanout matching).', [ 'status' => 400 ] );
    }

    $middle    = tlt_cb_s( $body['middleName'] ?? '' );
    $suffix    = tlt_cb_s( $body['suffix']     ?? '' );
    $pronouns  = tlt_cb_s( $body['pronouns']   ?? '' );
    $phone     = tlt_cb_s( $body['phone']      ?? '' );
    $notes     = tlt_cb_s( $body['notes']      ?? '' );
    $alt_email = tlt_cb_s( $body['altEmail']   ?? '' );  // optional — col P
    $skills   = $body['skills'] ?? '';
    if ( is_array( $skills ) ) $skills = implode( ',', array_map( 'trim', $skills ) );
    else                        $skills = tlt_cb_s( $skills );

    $email_lc = strtolower( $email );

    $rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_CONTACTBOOK_ID, 'Contactbook!A2:P', TLT_CALLBOARD_CACHE_TTL, true );
    if ( is_wp_error( $rows ) ) return $rows;

    $existing_row_1based = 0;
    $existing_id = ''; $prev_first = ''; $prev_last = ''; $prev_email = ''; $prev_alt = '';
    $prev_token = ''; $prev_token_sent = ''; $prev_last_login = '';
    $prev_date_added = ''; $prev_added_by = '';

    // Match by primary email → alt email → name+last, in that order.
    foreach ( $rows as $i => $r ) {
        $row_email_lc = strtolower( tlt_cb_s( $r[7] ?? '' ) );
        $row_alt_lc   = strtolower( tlt_cb_s( $r[15] ?? '' ) );
        $row_first_lc = strtolower( tlt_cb_s( $r[1] ?? '' ) );
        $row_last_lc  = strtolower( tlt_cb_s( $r[3] ?? '' ) );
        $hit = ( $email_lc !== '' && $row_email_lc === $email_lc )
            || ( $email_lc !== '' && $row_alt_lc   === $email_lc )
            || ( $row_first_lc === strtolower( $first ) && $row_last_lc === strtolower( $last ) );
        if ( ! $hit ) continue;
        // Prefer the strongest match: email > name.
        $email_matched = ( $email_lc !== '' && ( $row_email_lc === $email_lc || $row_alt_lc === $email_lc ) );
        if ( $existing_row_1based && ! $email_matched ) continue; // already have a match
        $existing_row_1based = $i + 2;
        $existing_id     = tlt_cb_s( $r[0] ?? '' );
        $prev_first      = tlt_cb_s( $r[1] ?? '' );
        $prev_last       = tlt_cb_s( $r[3] ?? '' );
        $prev_email      = tlt_cb_s( $r[7] ?? '' );
        $prev_alt        = tlt_cb_s( $r[15] ?? '' );
        $prev_token      = tlt_cb_s( $r[10] ?? '' );
        $prev_token_sent = tlt_cb_s( $r[11] ?? '' );
        $prev_last_login = tlt_cb_s( $r[12] ?? '' );
        $prev_date_added = tlt_cb_s( $r[13] ?? '' );
        $prev_added_by   = tlt_cb_s( $r[14] ?? '' );
        if ( $email_matched ) break;
    }

    if ( ! $existing_row_1based ) {
        // Assign next TLT-NNNN id (numeric max + 1, 4-digit padded).
        $max_id = 0;
        foreach ( $rows as $r ) {
            if ( preg_match( '/^TLT-(\d+)$/', tlt_cb_s( $r[0] ?? '' ), $m ) ) {
                $max_id = max( $max_id, (int) $m[1] );
            }
        }
        $new_id = 'TLT-' . str_pad( (string)( $max_id + 1 ), 4, '0', STR_PAD_LEFT );
        $current_user = tlt_callboard_current_user( $req );
        $row = [
            $new_id, $first, $middle, $last, $suffix, $pronouns, $phone, $email, $notes, $skills,
            '', '', '',                       // bio token, sent, login — blank on new
            date( 'Y-m-d' ),                  // date added
            $current_user ? tlt_cb_s( $current_user['name'] ) : '',
            $alt_email,                       // col P
        ];
        $write = tlt_callboard_sheets_write(
            TLT_CALLBOARD_CONTACTBOOK_ID,
            'Contactbook!A' . ( count( $rows ) + 2 ),
            [ $row ]
        );
        if ( is_wp_error( $write ) ) return $write;
        $existing_id = $new_id;
    } else {
        // Preserve prev_alt if the caller didn't send an altEmail field.
        $save_alt = array_key_exists( 'altEmail', $body ) ? $alt_email : $prev_alt;
        $row = [
            $existing_id, $first, $middle, $last, $suffix, $pronouns, $phone, $email, $notes, $skills,
            $prev_token, $prev_token_sent, $prev_last_login, $prev_date_added, $prev_added_by,
            $save_alt,
        ];
        $write = tlt_callboard_sheets_write(
            TLT_CALLBOARD_CONTACTBOOK_ID,
            'Contactbook!A' . $existing_row_1based . ':P' . $existing_row_1based,
            [ $row ]
        );
        if ( is_wp_error( $write ) ) return $write;
    }

    // Fanout — passes both emails so the fanout keeps each show-row on its
    // original "context" (primary-email row stays primary; alt row stays alt).
    $save_alt = isset( $save_alt ) ? $save_alt : ( array_key_exists( 'altEmail', $body ) ? $alt_email : $prev_alt );
    $updated_show_rows = tlt_cb_sync_contact_to_shows(
        $first, $last, $email, $phone,
        $prev_email, $prev_first, $prev_last,
        $save_alt, $prev_alt
    );

    return tlt_cb_ok( [
        'contactId'         => $existing_id,
        'created'           => ! $existing_row_1based ? false : true,   // false = new row was appended
        'updatedShowRows'   => $updated_show_rows,
    ] );
}

/* ===========================================================================
 * ENDPOINT: POST /delete-contact
 * Body: { firstName, lastName }
 *
 * Deletes the Contactbook row for that person. Uses spreadsheets.batchUpdate
 * with deleteDimension so the sheet doesn't get a hole. Does NOT touch
 * Production Teams / Actors rows — deleting a Contactbook entry is metadata
 * cleanup; existing show assignments stay intact.
 * ======================================================================== */
function tlt_callboard_ep_delete_contact( WP_REST_Request $req ) {
    $body = $req->get_json_params();
    if ( ! is_array( $body ) ) return new WP_Error( 'bad_body', 'JSON body required.', [ 'status' => 400 ] );
    $first = tlt_cb_s( $body['firstName'] ?? '' );
    $last  = tlt_cb_s( $body['lastName']  ?? '' );
    if ( $first === '' || $last === '' ) {
        return new WP_Error( 'missing_names', 'firstName + lastName required.', [ 'status' => 400 ] );
    }
    $rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_CONTACTBOOK_ID, 'Contactbook!A2:O', TLT_CALLBOARD_CACHE_TTL, true );
    if ( is_wp_error( $rows ) ) return $rows;

    $first_lc = strtolower( $first );
    $last_lc  = strtolower( $last );
    $row_1based = 0;
    foreach ( $rows as $i => $r ) {
        if ( strtolower( tlt_cb_s( $r[1] ?? '' ) ) !== $first_lc ) continue;
        if ( strtolower( tlt_cb_s( $r[3] ?? '' ) ) !== $last_lc  ) continue;
        $row_1based = $i + 2; break;
    }
    if ( ! $row_1based ) {
        return new WP_Error( 'not_found', 'No contact matched.', [ 'status' => 404 ] );
    }

    // Look up the Contactbook sheet's numeric sheetId (needed for batchUpdate).
    $token = tlt_callboard_google_access_token();
    if ( is_wp_error( $token ) ) return $token;
    $meta = wp_remote_get(
        'https://sheets.googleapis.com/v4/spreadsheets/' . TLT_CALLBOARD_CONTACTBOOK_ID
        . '?fields=sheets(properties(sheetId,title))',
        [ 'timeout' => 15, 'headers' => [ 'Authorization' => 'Bearer ' . $token ] ]
    );
    if ( is_wp_error( $meta ) ) return $meta;
    $meta_data = json_decode( wp_remote_retrieve_body( $meta ), true );
    $sheet_id = null;
    foreach ( $meta_data['sheets'] ?? [] as $s ) {
        if ( ( $s['properties']['title'] ?? '' ) === 'Contactbook' ) {
            $sheet_id = $s['properties']['sheetId']; break;
        }
    }
    if ( $sheet_id === null ) return new WP_Error( 'sheet_missing', 'Contactbook tab not found.', [ 'status' => 500 ] );

    // Delete the row via batchUpdate.
    $body_req = [
        'requests' => [ [
            'deleteDimension' => [
                'range' => [
                    'sheetId'    => $sheet_id,
                    'dimension'  => 'ROWS',
                    'startIndex' => $row_1based - 1,   // 0-based, exclusive-end
                    'endIndex'   => $row_1based,
                ],
            ],
        ] ],
    ];
    $resp = wp_remote_post(
        'https://sheets.googleapis.com/v4/spreadsheets/' . TLT_CALLBOARD_CONTACTBOOK_ID . ':batchUpdate',
        [
            'timeout' => 30,
            'headers' => [ 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json' ],
            'body'    => wp_json_encode( $body_req ),
        ]
    );
    if ( is_wp_error( $resp ) ) return $resp;
    $code = wp_remote_retrieve_response_code( $resp );
    if ( $code < 200 || $code >= 300 ) {
        return new WP_Error( 'delete_failed', 'batchUpdate returned ' . $code . ': ' . wp_remote_retrieve_body( $resp ) );
    }

    // Purge range cache so subsequent /contacts reads reflect the delete.
    global $wpdb;
    tlt_cb_bump_cache();

    return tlt_cb_ok( [ 'deleted' => true, 'rowIndex' => $row_1based ] );
}

/* ---------------------------------------------------------------------------
 * SHARED WRITE HELPERS (used by the 2a-4/5/6 endpoints below).
 * ------------------------------------------------------------------------- */

/** Look up the numeric sheetId for a tab. Needed for row-delete via batchUpdate. */
function tlt_cb_get_sheet_id( $spreadsheet_id, $tab_title ) {
    $token = tlt_callboard_google_access_token();
    if ( is_wp_error( $token ) ) return $token;
    $resp = wp_remote_get(
        'https://sheets.googleapis.com/v4/spreadsheets/' . $spreadsheet_id
        . '?fields=sheets(properties(sheetId,title))',
        [ 'timeout' => 15, 'headers' => [ 'Authorization' => 'Bearer ' . $token ] ]
    );
    if ( is_wp_error( $resp ) ) return $resp;
    $data = json_decode( wp_remote_retrieve_body( $resp ), true );
    foreach ( $data['sheets'] ?? [] as $s ) {
        if ( ( $s['properties']['title'] ?? '' ) === $tab_title ) {
            return (int) $s['properties']['sheetId'];
        }
    }
    return new WP_Error( 'tab_missing', "Tab '$tab_title' not found." );
}

/** Delete a specific row via batchUpdate deleteDimension. Purges range caches. */
function tlt_cb_delete_row( $spreadsheet_id, $tab_title, $row_1based ) {
    $sheet_id = tlt_cb_get_sheet_id( $spreadsheet_id, $tab_title );
    if ( is_wp_error( $sheet_id ) ) return $sheet_id;
    $token = tlt_callboard_google_access_token();
    if ( is_wp_error( $token ) ) return $token;

    $body = [ 'requests' => [ [ 'deleteDimension' => [ 'range' => [
        'sheetId'    => $sheet_id,
        'dimension'  => 'ROWS',
        'startIndex' => $row_1based - 1,
        'endIndex'   => $row_1based,
    ] ] ] ] ];
    $resp = wp_remote_post(
        'https://sheets.googleapis.com/v4/spreadsheets/' . $spreadsheet_id . ':batchUpdate',
        [
            'timeout' => 30,
            'headers' => [ 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json' ],
            'body'    => wp_json_encode( $body ),
        ]
    );
    if ( is_wp_error( $resp ) ) return $resp;
    $code = wp_remote_retrieve_response_code( $resp );
    if ( $code < 200 || $code >= 300 ) {
        return new WP_Error( 'delete_row_failed', 'batchUpdate ' . $code . ': ' . wp_remote_retrieve_body( $resp ) );
    }
    global $wpdb;
    tlt_cb_bump_cache();
    return true;
}

/**
 * Upsert to Contactbook by email (then name). Same logic as ep_save_contact
 * but callable internally from other mutations (addRole / addActor / update).
 * Returns the Contact ID (existing or newly assigned).
 */
function tlt_cb_upsert_contact( $first, $middle, $last, $suffix, $email, $phone, $notes = '', $pronouns = '', $skills = '', $added_by = '' ) {
    if ( $first === '' || $last === '' || $email === '' ) return ''; // caller-side sanity
    $email_lc = strtolower( $email );
    $rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_CONTACTBOOK_ID, 'Contactbook!A2:P', TLT_CALLBOARD_CACHE_TTL, true );
    if ( is_wp_error( $rows ) ) return '';

    $existing_row = 0;
    $existing_id = ''; $prev_email = ''; $prev_alt = ''; $prev_phone = '';
    $prev_middle = ''; $prev_suffix = ''; $prev_notes = ''; $prev_pronouns = ''; $prev_skills = '';
    $prev_tok = ''; $prev_sent = ''; $prev_login = ''; $prev_added = ''; $prev_by = '';
    $matched_by_email = false;
    foreach ( $rows as $i => $r ) {
        $row_email = strtolower( tlt_cb_s( $r[7] ?? '' ) );
        $row_alt   = strtolower( tlt_cb_s( $r[15] ?? '' ) );
        $row_first = strtolower( tlt_cb_s( $r[1] ?? '' ) );
        $row_last  = strtolower( tlt_cb_s( $r[3] ?? '' ) );
        $hit_email = ( $row_email === $email_lc || $row_alt === $email_lc );
        $hit_name  = ( $row_first === strtolower( $first ) && $row_last === strtolower( $last ) );
        if ( ! $hit_email && ! $hit_name ) continue;
        if ( $existing_row && ! $hit_email ) continue; // already have a match; only email-hit beats it
        $existing_row = $i + 2;
        $existing_id  = tlt_cb_s( $r[0] ?? '' );
        $prev_middle  = tlt_cb_s( $r[2] ?? '' );
        $prev_suffix  = tlt_cb_s( $r[4] ?? '' );
        $prev_pronouns= tlt_cb_s( $r[5] ?? '' );
        $prev_phone   = tlt_cb_s( $r[6] ?? '' );
        $prev_email   = tlt_cb_s( $r[7] ?? '' );
        $prev_notes   = tlt_cb_s( $r[8] ?? '' );
        $prev_skills  = tlt_cb_s( $r[9] ?? '' );
        $prev_tok     = tlt_cb_s( $r[10] ?? '' );
        $prev_sent    = tlt_cb_s( $r[11] ?? '' );
        $prev_login   = tlt_cb_s( $r[12] ?? '' );
        $prev_added   = tlt_cb_s( $r[13] ?? '' );
        $prev_by      = tlt_cb_s( $r[14] ?? '' );
        $prev_alt     = tlt_cb_s( $r[15] ?? '' );
        $matched_by_email = $hit_email;
        if ( $hit_email ) break;
    }

    if ( $existing_row ) {
        // Rule: the new email overwrites primary UNLESS it matches primary or alt.
        //   - matches primary → no change (import confirming known info)
        //   - matches alt     → no change (import is using their "second-context" email like Frank's personal)
        //   - anything else   → overwrite primary (email actually changed; import is authoritative)
        // For the "preserve on second context" case to work, the alt column
        // must have been pre-set. This puts the burden on Blake to set alt for
        // staff-who-also-act BEFORE the CastingManager sync runs.
        $prev_email_lc = strtolower( $prev_email );
        $prev_alt_lc   = strtolower( $prev_alt );
        $email_lc_arg  = strtolower( $email );
        if ( $email_lc_arg === $prev_email_lc || ( $prev_alt_lc !== '' && $email_lc_arg === $prev_alt_lc ) ) {
            $save_primary = $prev_email;
            $save_alt     = $prev_alt;
        } else {
            $save_primary = $email;
            $save_alt     = $prev_alt;
        }
        // For OTHER fields, if the caller supplied one, use it; else preserve existing.
        $save_phone  = $phone    !== '' ? $phone    : $prev_phone;
        $save_middle = $middle   !== '' ? $middle   : $prev_middle;
        $save_suffix = $suffix   !== '' ? $suffix   : $prev_suffix;
        $save_notes  = $notes    !== '' ? $notes    : $prev_notes;
        $save_skills = $skills   !== '' ? $skills   : $prev_skills;
        $save_pron   = $pronouns !== '' ? $pronouns : $prev_pronouns;
        $row = [
            $existing_id, $first, $save_middle, $last, $save_suffix, $save_pron, $save_phone,
            $save_primary, $save_notes, $save_skills,
            $prev_tok, $prev_sent, $prev_login, $prev_added, $prev_by,
            $save_alt,
        ];
        tlt_callboard_sheets_write( TLT_CALLBOARD_CONTACTBOOK_ID, 'Contactbook!A' . $existing_row . ':P' . $existing_row, [ $row ] );
        return $existing_id;
    }
    // New: assign next TLT-NNNN.
    $max_id = 0;
    foreach ( $rows as $r ) {
        if ( preg_match( '/^TLT-(\d+)$/', tlt_cb_s( $r[0] ?? '' ), $m ) ) $max_id = max( $max_id, (int) $m[1] );
    }
    $new_id = 'TLT-' . str_pad( (string)( $max_id + 1 ), 4, '0', STR_PAD_LEFT );
    $row = [ $new_id, $first, $middle, $last, $suffix, $pronouns, $phone, $email, $notes, $skills, '', '', '', date( 'Y-m-d' ), $added_by, '' /* alt */ ];
    tlt_callboard_sheets_write( TLT_CALLBOARD_CONTACTBOOK_ID, 'Contactbook!A' . ( count( $rows ) + 2 ), [ $row ] );
    return $new_id;
}

/* ===========================================================================
 * ENDPOINT: POST /add-role
 * Body: { show, roleData: { role, firstName?, middleName?, lastName?, suffix?, email?, phone? } }
 * ======================================================================== */
function tlt_callboard_ep_add_role( WP_REST_Request $req ) {
    $body = $req->get_json_params(); if ( ! is_array( $body ) ) return new WP_Error( 'bad_body', 'JSON required.', [ 'status' => 400 ] );
    $show = tlt_cb_s( $body['show'] ?? '' );
    $rd   = $body['roleData'] ?? [];
    $role = tlt_cb_s( $rd['role'] ?? '' );
    if ( $show === '' || $role === '' ) return new WP_Error( 'missing_params', 'show + roleData.role required.', [ 'status' => 400 ] );

    $rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_SHEET_ID, "'Production Teams'!A2:S", TLT_CALLBOARD_CACHE_TTL, true );
    if ( is_wp_error( $rows ) ) return $rows;

    $first  = tlt_cb_s( $rd['firstName']  ?? '' );
    $middle = tlt_cb_s( $rd['middleName'] ?? '' );
    $last   = tlt_cb_s( $rd['lastName']   ?? '' );
    $suffix = tlt_cb_s( $rd['suffix']     ?? '' );
    $email  = tlt_cb_s( $rd['email']      ?? '' );
    $phone  = tlt_cb_s( $rd['phone']      ?? '' );

    $new_row = [ $show, $role, $first, $middle, $last, $suffix, $phone, $email, 'Not Started' ];
    $range = "'Production Teams'!A" . ( count( $rows ) + 2 );
    $write = tlt_callboard_sheets_write( TLT_CALLBOARD_SHEET_ID, $range, [ $new_row ] );
    if ( is_wp_error( $write ) ) return $write;

    if ( $first !== '' && $last !== '' && $email !== '' ) {
        $user = tlt_callboard_current_user( $req );
        tlt_cb_upsert_contact( $first, $middle, $last, $suffix, $email, $phone, '', '', '', $user ? tlt_cb_s( $user['name'] ) : '' );
    }
    return tlt_cb_ok( [ 'added' => true, 'atRow' => count( $rows ) + 2 ] );
}

/* ===========================================================================
 * ENDPOINT: POST /update-person
 * Body: { show, role, personData: { firstName, middleName, lastName, suffix, email, phone, notes? } }
 * Updates Production Teams cols C..H (name+contact) and M (notes) for the
 * row matching (show, role).
 * ======================================================================== */
function tlt_callboard_ep_update_person( WP_REST_Request $req ) {
    $body = $req->get_json_params(); if ( ! is_array( $body ) ) return new WP_Error( 'bad_body', 'JSON required.', [ 'status' => 400 ] );
    $show = tlt_cb_s( $body['show'] ?? '' );
    $role = tlt_cb_s( $body['role'] ?? '' );
    $pd   = $body['personData'] ?? [];
    if ( $show === '' || $role === '' ) return new WP_Error( 'missing_params', 'show + role required.', [ 'status' => 400 ] );

    $rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_SHEET_ID, "'Production Teams'!A2:S", TLT_CALLBOARD_CACHE_TTL, true );
    if ( is_wp_error( $rows ) ) return $rows;

    $row_1based = tlt_cb_find_row( $rows, [ 0 => $show, 1 => $role ], 1 );
    if ( ! $row_1based ) return new WP_Error( 'row_not_found', 'No matching Production Teams row.', [ 'status' => 404 ] );

    $first  = tlt_cb_s( $pd['firstName']  ?? '' );
    $middle = tlt_cb_s( $pd['middleName'] ?? '' );
    $last   = tlt_cb_s( $pd['lastName']   ?? '' );
    $suffix = tlt_cb_s( $pd['suffix']     ?? '' );
    $email  = tlt_cb_s( $pd['email']      ?? '' );
    $phone  = tlt_cb_s( $pd['phone']      ?? '' );
    $notes  = array_key_exists( 'notes', $pd ) ? tlt_cb_s( $pd['notes'] ) : null;

    // Cols C..H = name + contact
    $write = tlt_callboard_sheets_write(
        TLT_CALLBOARD_SHEET_ID,
        "'Production Teams'!C" . $row_1based . ':H' . $row_1based,
        [ [ $first, $middle, $last, $suffix, $phone, $email ] ]
    );
    if ( is_wp_error( $write ) ) return $write;
    if ( $notes !== null ) {
        tlt_callboard_sheets_write(
            TLT_CALLBOARD_SHEET_ID, "'Production Teams'!M" . $row_1based, [ [ $notes ] ]
        );
    }
    if ( $first !== '' && $last !== '' && $email !== '' ) {
        $user = tlt_callboard_current_user( $req );
        tlt_cb_upsert_contact( $first, $middle, $last, $suffix, $email, $phone, $notes ?: '', '', '', $user ? tlt_cb_s( $user['name'] ) : '' );
    }
    return tlt_cb_ok( [ 'updated' => true, 'row' => $row_1based ] );
}

/* ===========================================================================
 * ENDPOINT: POST /delete-role  →  Body: { show, role }
 * ======================================================================== */
function tlt_callboard_ep_delete_role( WP_REST_Request $req ) {
    $body = $req->get_json_params(); if ( ! is_array( $body ) ) return new WP_Error( 'bad_body', 'JSON required.', [ 'status' => 400 ] );
    $show = tlt_cb_s( $body['show'] ?? '' );
    $role = tlt_cb_s( $body['role'] ?? '' );
    if ( $show === '' || $role === '' ) return new WP_Error( 'missing_params', 'show + role required.', [ 'status' => 400 ] );
    $rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_SHEET_ID, "'Production Teams'!A2:S", TLT_CALLBOARD_CACHE_TTL, true );
    if ( is_wp_error( $rows ) ) return $rows;
    $row = tlt_cb_find_row( $rows, [ 0 => $show, 1 => $role ], 1 );
    if ( ! $row ) return new WP_Error( 'row_not_found', 'No matching row.', [ 'status' => 404 ] );
    $del = tlt_cb_delete_row( TLT_CALLBOARD_SHEET_ID, 'Production Teams', $row );
    if ( is_wp_error( $del ) ) return $del;
    return tlt_cb_ok( [ 'deleted' => true, 'row' => $row ] );
}

/* ===========================================================================
 * ENDPOINT: POST /remove-person  →  Body: { show, role }
 * Clears cols C..H (name/contact) + M (notes) but keeps the role slot.
 * ======================================================================== */
function tlt_callboard_ep_remove_person( WP_REST_Request $req ) {
    $body = $req->get_json_params(); if ( ! is_array( $body ) ) return new WP_Error( 'bad_body', 'JSON required.', [ 'status' => 400 ] );
    $show = tlt_cb_s( $body['show'] ?? '' );
    $role = tlt_cb_s( $body['role'] ?? '' );
    if ( $show === '' || $role === '' ) return new WP_Error( 'missing_params', 'show + role required.', [ 'status' => 400 ] );
    $rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_SHEET_ID, "'Production Teams'!A2:S", TLT_CALLBOARD_CACHE_TTL, true );
    if ( is_wp_error( $rows ) ) return $rows;
    $row_1based = tlt_cb_find_row( $rows, [ 0 => $show, 1 => $role ], 1 );
    if ( ! $row_1based ) return new WP_Error( 'row_not_found', 'No matching row.', [ 'status' => 404 ] );

    $write = tlt_callboard_sheets_write(
        TLT_CALLBOARD_SHEET_ID,
        "'Production Teams'!C" . $row_1based . ':H' . $row_1based,
        [ [ '', '', '', '', '', '' ] ]
    );
    if ( is_wp_error( $write ) ) return $write;
    tlt_callboard_sheets_write( TLT_CALLBOARD_SHEET_ID, "'Production Teams'!M" . $row_1based, [ [ '' ] ] );
    return tlt_cb_ok( [ 'removed' => true, 'row' => $row_1based ] );
}

/* ===========================================================================
 * ENDPOINT: POST /add-actor
 * Body: { show, actorData: { character, firstName, middleName?, lastName, suffix?, email, phone? } }
 * ======================================================================== */
function tlt_callboard_ep_add_actor( WP_REST_Request $req ) {
    $body = $req->get_json_params(); if ( ! is_array( $body ) ) return new WP_Error( 'bad_body', 'JSON required.', [ 'status' => 400 ] );
    $show = tlt_cb_s( $body['show'] ?? '' );
    $ad   = $body['actorData'] ?? [];
    $character = tlt_cb_s( $ad['character'] ?? '' );
    $first     = tlt_cb_s( $ad['firstName'] ?? '' );
    $last      = tlt_cb_s( $ad['lastName']  ?? '' );
    $email     = tlt_cb_s( $ad['email']     ?? '' );
    if ( $show === '' || $character === '' || $first === '' || $last === '' || $email === '' ) {
        return new WP_Error( 'missing_params', 'show + character + first/last/email required.', [ 'status' => 400 ] );
    }
    $middle = tlt_cb_s( $ad['middleName'] ?? '' );
    $suffix = tlt_cb_s( $ad['suffix']     ?? '' );
    $phone  = tlt_cb_s( $ad['phone']      ?? '' );

    $rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_SHEET_ID, 'Actors!A2:S', TLT_CALLBOARD_CACHE_TTL, true );
    if ( is_wp_error( $rows ) ) return $rows;
    $new_row = [ $show, $character, $first, $middle, $last, $suffix, $phone, $email, 'Not Started' ];
    $write = tlt_callboard_sheets_write( TLT_CALLBOARD_SHEET_ID, 'Actors!A' . ( count( $rows ) + 2 ), [ $new_row ] );
    if ( is_wp_error( $write ) ) return $write;

    $user = tlt_callboard_current_user( $req );
    tlt_cb_upsert_contact( $first, $middle, $last, $suffix, $email, $phone, '', '', '', $user ? tlt_cb_s( $user['name'] ) : '' );
    return tlt_cb_ok( [ 'added' => true, 'atRow' => count( $rows ) + 2 ] );
}

/* ===========================================================================
 * ENDPOINT: POST /remove-actor
 * Body: { show, character, firstName, lastName }
 * ======================================================================== */
function tlt_callboard_ep_remove_actor( WP_REST_Request $req ) {
    $body = $req->get_json_params(); if ( ! is_array( $body ) ) return new WP_Error( 'bad_body', 'JSON required.', [ 'status' => 400 ] );
    $show      = tlt_cb_s( $body['show']      ?? '' );
    $character = tlt_cb_s( $body['character'] ?? '' );
    $first     = tlt_cb_s( $body['firstName'] ?? '' );
    $last      = tlt_cb_s( $body['lastName']  ?? '' );
    if ( $show === '' || $first === '' || $last === '' ) {
        return new WP_Error( 'missing_params', 'show + firstName + lastName required.', [ 'status' => 400 ] );
    }
    $rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_SHEET_ID, 'Actors!A2:S', TLT_CALLBOARD_CACHE_TTL, true );
    if ( is_wp_error( $rows ) ) return $rows;
    // Match on show + first + last; also character if provided (character alone
    // isn't reliable — same character name in multiple shows).
    $match = [ 0 => $show, 2 => $first, 4 => $last ];
    if ( $character !== '' ) $match[1] = $character;
    $row_1based = tlt_cb_find_row( $rows, $match, 1 );
    if ( ! $row_1based ) return new WP_Error( 'row_not_found', 'No matching actor.', [ 'status' => 404 ] );
    $del = tlt_cb_delete_row( TLT_CALLBOARD_SHEET_ID, 'Actors', $row_1based );
    if ( is_wp_error( $del ) ) return $del;
    return tlt_cb_ok( [ 'removed' => true, 'row' => $row_1based ] );
}

/* ===========================================================================
 * ENDPOINT: POST /import-actors
 * Body: { show, actors: [ { character, firstName, middleName?, lastName, suffix?, email?, phone? }, ... ] }
 * Bulk-appends rows to Actors + upserts each contact. Used by the "Import from
 * CastingManager paste" flow. Returns { imported, contactbookAdded }.
 * ======================================================================== */
function tlt_callboard_ep_import_actors( WP_REST_Request $req ) {
    $body = $req->get_json_params(); if ( ! is_array( $body ) ) return new WP_Error( 'bad_body', 'JSON required.', [ 'status' => 400 ] );
    $show = tlt_cb_s( $body['show'] ?? '' );
    $actors = $body['actors'] ?? [];
    if ( $show === '' || ! is_array( $actors ) || count( $actors ) === 0 ) {
        return new WP_Error( 'missing_params', 'show + non-empty actors[] required.', [ 'status' => 400 ] );
    }
    $rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_SHEET_ID, 'Actors!A2:S', TLT_CALLBOARD_CACHE_TTL, true );
    if ( is_wp_error( $rows ) ) return $rows;

    $new_rows = [];
    $upserts = 0;
    $user = tlt_callboard_current_user( $req );
    $added_by = $user ? tlt_cb_s( $user['name'] ) : '';
    foreach ( $actors as $a ) {
        $first = tlt_cb_s( $a['firstName'] ?? '' );
        $last  = tlt_cb_s( $a['lastName']  ?? '' );
        if ( $first === '' || $last === '' ) continue;
        $character = tlt_cb_s( $a['character']  ?? '' );
        $middle    = tlt_cb_s( $a['middleName'] ?? '' );
        $suffix    = tlt_cb_s( $a['suffix']     ?? '' );
        $email     = tlt_cb_s( $a['email']      ?? '' );
        $phone     = tlt_cb_s( $a['phone']      ?? '' );
        $new_rows[] = [ $show, $character, $first, $middle, $last, $suffix, $phone, $email, 'Not Started' ];
        if ( $email !== '' ) {
            tlt_cb_upsert_contact( $first, $middle, $last, $suffix, $email, $phone, '', '', '', $added_by );
            $upserts++;
        }
    }
    if ( count( $new_rows ) === 0 ) return new WP_Error( 'no_valid_rows', 'No actor rows had first+last name.', [ 'status' => 400 ] );

    // Append via values.append so Sheets picks the right start row automatically.
    $token = tlt_callboard_google_access_token();
    if ( is_wp_error( $token ) ) return $token;
    $resp = wp_remote_post(
        'https://sheets.googleapis.com/v4/spreadsheets/' . TLT_CALLBOARD_SHEET_ID
        . '/values/' . rawurlencode( 'Actors!A2' ) . ':append?valueInputOption=USER_ENTERED&insertDataOption=INSERT_ROWS',
        [
            'timeout' => 60,
            'headers' => [ 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json' ],
            'body'    => wp_json_encode( [ 'values' => $new_rows ] ),
        ]
    );
    if ( is_wp_error( $resp ) ) return $resp;
    $code = wp_remote_retrieve_response_code( $resp );
    if ( $code < 200 || $code >= 300 ) {
        return new WP_Error( 'append_failed', 'append ' . $code . ': ' . wp_remote_retrieve_body( $resp ) );
    }
    global $wpdb;
    tlt_cb_bump_cache();
    return tlt_cb_ok( [ 'imported' => count( $new_rows ), 'contactbookAdded' => $upserts ] );
}

/* ===========================================================================
 * ENDPOINT: POST /save-program-fields
 * Body: { show, fields: { author?, legal?, a1?, a2?, intermission?, place?, ... } }
 * Writes to the Programs tab. Matches columns dynamically by header name so
 * Blake can add more editable fields without a code change.
 * ======================================================================== */
function tlt_callboard_ep_save_program_fields( WP_REST_Request $req ) {
    $body = $req->get_json_params(); if ( ! is_array( $body ) ) return new WP_Error( 'bad_body', 'JSON required.', [ 'status' => 400 ] );
    $show = tlt_cb_s( $body['show'] ?? '' );
    $fields = $body['fields'] ?? [];
    if ( $show === '' || ! is_array( $fields ) ) return new WP_Error( 'missing_params', 'show + fields required.', [ 'status' => 400 ] );

    $rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_SHEET_ID, 'Programs!A1:Z', TLT_CALLBOARD_CACHE_TTL, true );
    if ( is_wp_error( $rows ) ) return $rows;

    $headers = $rows[0] ?? [];
    // Frontend field name → possible column-header labels (loose match).
    $field_to_labels = [
        'author'       => [ 'Author', 'Playwright' ],
        'legal'        => [ 'Legal', 'Attribution', 'Legal/Attribution' ],
        'a1'           => [ 'Act 1', 'A1', 'Act 1 run time' ],
        'a2'           => [ 'Act 2', 'A2', 'Act 2 run time' ],
        'intermission' => [ 'Intermission' ],
        'place'        => [ 'Place', 'Setting' ],
    ];
    $col_index_for_field = function ( $field ) use ( $headers, $field_to_labels ) {
        $labels = $field_to_labels[ $field ] ?? [ $field ];
        foreach ( $labels as $label ) {
            $target = strtolower( trim( $label ) );
            foreach ( $headers as $i => $h ) {
                if ( strtolower( trim( (string) $h ) ) === $target ) return (int) $i;
            }
        }
        return -1;
    };

    // Find (or create) the show's row.
    $row_1based = 0;
    foreach ( array_slice( $rows, 1 ) as $i => $r ) {
        if ( tlt_cb_s( $r[0] ?? '' ) === $show ) { $row_1based = $i + 2; break; }
    }
    if ( ! $row_1based ) {
        // Append a new row with the show name in col A + provided fields.
        $new_row = [ $show ];
        // Pad up to max header column.
        for ( $i = 1; $i < count( $headers ); $i++ ) $new_row[] = '';
        foreach ( $fields as $k => $v ) {
            $idx = $col_index_for_field( $k );
            if ( $idx >= 0 ) $new_row[ $idx ] = tlt_cb_s( $v );
        }
        $write = tlt_callboard_sheets_write(
            TLT_CALLBOARD_SHEET_ID, 'Programs!A' . ( count( $rows ) + 1 ), [ $new_row ]
        );
        if ( is_wp_error( $write ) ) return $write;
        return tlt_cb_ok( [ 'created' => true, 'row' => count( $rows ) + 1 ] );
    }

    // Update: write each provided field to its column.
    $updated = 0; $skipped = [];
    foreach ( $fields as $k => $v ) {
        $idx = $col_index_for_field( $k );
        if ( $idx < 0 ) { $skipped[] = $k; continue; }
        $col_letter = chr( ord( 'A' ) + $idx );
        $write = tlt_callboard_sheets_write(
            TLT_CALLBOARD_SHEET_ID,
            'Programs!' . $col_letter . $row_1based,
            [ [ tlt_cb_s( $v ) ] ]
        );
        if ( ! is_wp_error( $write ) ) $updated++;
    }
    return tlt_cb_ok( [ 'updated' => $updated, 'row' => $row_1based, 'skipped' => $skipped ] );
}

/* ===========================================================================
 * ENDPOINT: POST /sync-contactbook
 *
 * Walks every Contactbook row and pushes name/phone/email into any matching
 * Production Teams + Actors rows. Batches all updates into one Sheets
 * batchUpdate call for speed (66 contacts × 2 tabs = <30s cold).
 *
 * Returns { checked, updated } counts.
 * ======================================================================== */
function tlt_callboard_ep_sync_contactbook( WP_REST_Request $req ) {
    // Fresh reads — user just triggered the sync, they want current data.
    $contacts = tlt_callboard_sheet_rows( TLT_CALLBOARD_CONTACTBOOK_ID, 'Contactbook!A2:P', TLT_CALLBOARD_CACHE_TTL, true );
    if ( is_wp_error( $contacts ) ) return $contacts;
    $pt = tlt_callboard_sheet_rows( TLT_CALLBOARD_SHEET_ID, "'Production Teams'!A2:S", TLT_CALLBOARD_CACHE_TTL, true );
    if ( is_wp_error( $pt ) ) return $pt;
    $ac = tlt_callboard_sheet_rows( TLT_CALLBOARD_SHEET_ID, 'Actors!A2:S', TLT_CALLBOARD_CACHE_TTL, true );
    if ( is_wp_error( $ac ) ) return $ac;

    // Build lookup keyed by lowercase email + (first|last) for O(1) hits.
    $by_email = [];
    $by_name  = [];
    foreach ( $contacts as $r ) {
        $first = tlt_cb_s( $r[1] ?? '' );
        $last  = tlt_cb_s( $r[3] ?? '' );
        $email = tlt_cb_s( $r[7] ?? '' );
        $alt   = tlt_cb_s( $r[15] ?? '' );
        $phone = tlt_cb_s( $r[6] ?? '' );
        $middle= tlt_cb_s( $r[2] ?? '' );
        $suffix= tlt_cb_s( $r[4] ?? '' );
        if ( $first === '' && $last === '' && $email === '' ) continue;
        $c = compact( 'first', 'middle', 'last', 'suffix', 'phone', 'email', 'alt' );
        if ( $email !== '' ) $by_email[ strtolower( $email ) ] = $c;
        if ( $alt   !== '' ) $by_email[ strtolower( $alt ) ]   = $c;
        if ( $first !== '' || $last !== '' ) $by_name[ strtolower( $first . '|' . $last ) ] = $c;
    }

    // Build one batchUpdate request per mismatched row. Use values.batchUpdate
    // rather than looping tlt_callboard_sheets_write() so this stays fast.
    $data = [];
    $checked = 0;
    $updated = 0;

    $walk = function ( $tab, $rows ) use ( &$data, &$checked, &$updated, $by_email, $by_name ) {
        foreach ( $rows as $i => $r ) {
            $first = tlt_cb_s( $r[2] ?? '' );
            $last  = tlt_cb_s( $r[4] ?? '' );
            $email = tlt_cb_s( $r[7] ?? '' );
            $phone = tlt_cb_s( $r[6] ?? '' );
            if ( $first === '' && $last === '' && $email === '' ) continue;
            $checked++;

            $c = null;
            $matched_context = null;   // 'primary' | 'alt' | null
            $email_lc = strtolower( $email );
            $name_key = strtolower( $first . '|' . $last );
            if ( $email_lc !== '' && isset( $by_email[ $email_lc ] ) ) {
                $c = $by_email[ $email_lc ];
                $matched_context = ( strtolower( $c['alt'] ?? '' ) === $email_lc ) ? 'alt' : 'primary';
            } elseif ( isset( $by_name[ $name_key ] ) ) {
                $c = $by_name[ $name_key ];
                $matched_context = 'primary'; // default when the row has no email yet
            }
            if ( ! $c ) continue;

            // The email we push depends on whether the row was "primary-context" or "alt-context".
            $push_email = ( $matched_context === 'alt' && ! empty( $c['alt'] ) ) ? $c['alt'] : $c['email'];

            // Compare and, if any of name/phone/email differs, queue an update.
            if ( $first === $c['first'] && $last === $c['last']
              && $email === $push_email && $phone === $c['phone'] ) continue;

            $row_1based = $i + 2;
            $data[] = [
                'range'  => $tab . '!C' . $row_1based . ':H' . $row_1based,
                'values' => [ [
                    $c['first'],
                    tlt_cb_s( $r[3] ?? '' ),   // preserve middle from show row
                    $c['last'],
                    tlt_cb_s( $r[5] ?? '' ),   // preserve suffix from show row
                    $c['phone'],
                    $push_email,
                ] ],
            ];
            $updated++;
        }
    };
    $walk( "'Production Teams'", $pt );
    $walk( 'Actors',              $ac );

    if ( $updated > 0 ) {
        $token = tlt_callboard_google_access_token();
        if ( is_wp_error( $token ) ) return $token;
        $resp = wp_remote_post(
            'https://sheets.googleapis.com/v4/spreadsheets/' . TLT_CALLBOARD_SHEET_ID . '/values:batchUpdate',
            [
                'timeout' => 60,
                'headers' => [ 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json' ],
                'body'    => wp_json_encode( [ 'valueInputOption' => 'USER_ENTERED', 'data' => $data ] ),
            ]
        );
        if ( is_wp_error( $resp ) ) return $resp;
        $code = wp_remote_retrieve_response_code( $resp );
        if ( $code < 200 || $code >= 300 ) {
            return new WP_Error( 'batch_failed', 'Sheets batchUpdate returned ' . $code . ': ' . wp_remote_retrieve_body( $resp ) );
        }
        // Clear range caches so subsequent reads see the fresh state.
        global $wpdb;
    tlt_cb_bump_cache();
    }

    return tlt_cb_ok( [ 'checked' => $checked, 'updated' => $updated ] );
}

/* ===========================================================================
 * ENDPOINT: POST /purge-cache
 * Clears every WP transient this plugin uses to cache Sheets reads. The next
 * request will re-fetch from Google (whatever your sheet currently contains).
 * Used by the "Refresh from Sheet" buttons on tabs when Chris edits the sheet
 * directly and wants that to show up in the callboard without waiting for
 * the 60s TTL to expire.
 * ======================================================================== */
function tlt_callboard_ep_purge_cache( WP_REST_Request $req ) {
    global $wpdb;
    tlt_cb_bump_cache();
    // Object cache too (in case a caching plugin is layered over WP transients).
    return tlt_cb_ok( [ 'purged' => true ] );
}

/* ===========================================================================
 * ============  CONTACT SHEET GENERATOR  ====================================
 *
 * Port of ContactSheetGenerator.js. Copies a template Doc into a Drive folder,
 * populates it with a CAST table and a PRODUCTION TEAM table via the Docs API,
 * caches the URL in Season col M, and returns the URL.
 *
 * Flow:
 *   /contact-sheet-link      → { url, exists }  (checks cache + drive scan)
 *   /contact-sheet-generate  → generates without deleting existing (first time)
 *   /contact-sheet-regenerate → trashes existing then regenerates
 *
 * Requires SA Editor access to TLT_CALLBOARD_CS_FOLDER_ID and Reader access
 * to TLT_CALLBOARD_CS_TEMPLATE_ID.
 * ======================================================================== */

/**
 * Format a phone number the same way GAS ContactSheetGenerator does.
 * Strips non-digits, drops a leading 1, and produces "(206) 555-1212".
 * If the input is not a recognizable US number, returns the original.
 */
function tlt_cb_contact_sheet_format_phone( $phone ) {
    $phone = tlt_cb_s( $phone );
    if ( $phone === '' ) return '';
    $digits = preg_replace( '/\D/', '', $phone );
    if ( strlen( $digits ) === 11 && $digits[0] === '1' ) $digits = substr( $digits, 1 );
    if ( strlen( $digits ) === 10 ) {
        return '(' . substr( $digits, 0, 3 ) . ') '
             . substr( $digits, 3, 3 ) . '-'
             . substr( $digits, 6 );
    }
    return $phone;
}

/**
 * Read every source tab we need to build a contact sheet for one show and
 * assemble the cast + production-team arrays.
 *
 * @param string $show
 * @return array|WP_Error {
 *   season, season_long, show_sm_email, season_row_num,
 *   cast: [ { name, pronouns, role, phone, email }, ... ],
 *   team: [ { name, pronouns, role, phone, email }, ... ],
 * }
 */
function tlt_cb_contact_sheet_assemble_data( $show ) {
    $show = tlt_cb_s( $show );
    if ( $show === '' ) return new WP_Error( 'missing_show', 'show is required' );

    // Callboard sheet — read all the tabs we need in one batchGet.
    $ranges = tlt_callboard_sheets_get( TLT_CALLBOARD_SHEET_ID, [
        'Season!A1:N',
        'Actors!A2:H',
        'Production Teams!A2:E',
        'Theatre!A2:D',
    ] );
    if ( is_wp_error( $ranges ) ) return $ranges;

    $season_rows  = $ranges['Season!A1:N']            ?? [];
    $actor_rows   = $ranges['Actors!A2:H']            ?? [];
    $team_rows    = $ranges['Production Teams!A2:E'] ?? [];
    $theatre_rows = $ranges['Theatre!A2:D']           ?? [];

    // Contactbook — separate spreadsheet. First col is Contact ID (col A).
    // First name is col B; skip rows with empty first name.
    $contactbook_rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_CONTACTBOOK_ID, 'Contactbook!A2:H' );
    if ( is_wp_error( $contactbook_rows ) ) return $contactbook_rows;

    // Pull "Current Season" + "Current Season Long" out of Season key/value rows.
    // The Season tab holds config in col A/B until "Show1..N" rows appear.
    $season       = '';
    $season_long  = '';
    foreach ( $season_rows as $r ) {
        $label = tlt_cb_s( $r[0] ?? '' );
        $val   = tlt_cb_s( $r[1] ?? '' );
        if ( $label === 'Current Season' )      $season      = $val;
        if ( $label === 'Current Season Long' ) $season_long = $val;
    }

    // Find the show's row in Season for SM email (col E, index 4) and the
    // 1-based row number so we can write the URL back after generation.
    $show_sm_email  = '';
    $season_row_num = 0;
    foreach ( $season_rows as $i => $r ) {
        if ( tlt_cb_s( $r[1] ?? '' ) !== $show ) continue;
        $show_sm_email  = tlt_cb_s( $r[4] ?? '' );
        $season_row_num = $i + 1; // A1:N started at row 1
        break;
    }

    // Build a case-insensitive first+last → contactbook row lookup.
    $contact_lookup = [];
    foreach ( $contactbook_rows as $r ) {
        $first = tlt_cb_s( $r[1] ?? '' );
        if ( $first === '' ) continue;
        $last  = tlt_cb_s( $r[3] ?? '' );
        $key   = strtolower( $first ) . '|' . strtolower( $last );
        // Only take the first match (matches GAS Array.find semantics).
        if ( isset( $contact_lookup[ $key ] ) ) continue;
        $contact_lookup[ $key ] = [
            'first'    => $first,
            'middle'   => tlt_cb_s( $r[2] ?? '' ),
            'last'     => $last,
            'suffix'   => tlt_cb_s( $r[4] ?? '' ),
            'pronouns' => tlt_cb_s( $r[5] ?? '' ),
            'phone'    => tlt_cb_s( $r[6] ?? '' ),
            'email'    => tlt_cb_s( $r[7] ?? '' ),
        ];
    }
    $find_contact = function ( $first, $last ) use ( $contact_lookup ) {
        $key = strtolower( tlt_cb_s( $first ) ) . '|' . strtolower( tlt_cb_s( $last ) );
        return $contact_lookup[ $key ] ?? [];
    };

    // Theatre tab — role label → person name.
    $theatre_by_label = [];
    foreach ( $theatre_rows as $r ) {
        $label = tlt_cb_s( $r[0] ?? '' );
        if ( $label === '' ) continue;
        $theatre_by_label[ $label ] = tlt_cb_s( $r[1] ?? '' );
    }
    $get_setting = function ( $label ) use ( $theatre_by_label ) {
        return $theatre_by_label[ $label ] ?? '';
    };

    // --- CAST from Actors tab ---
    $cast = [];
    foreach ( $actor_rows as $r ) {
        if ( tlt_cb_s( $r[0] ?? '' ) !== $show ) continue;
        $first = tlt_cb_s( $r[2] ?? '' );
        $mid   = tlt_cb_s( $r[3] ?? '' );
        $last  = tlt_cb_s( $r[4] ?? '' );
        $suf   = tlt_cb_s( $r[5] ?? '' );
        $name_parts = array_filter( [ $first, $mid, $last, $suf ], function ( $x ) { return $x !== ''; } );
        $cast[] = [
            'name'     => implode( ' ', $name_parts ),
            'pronouns' => '',
            'role'     => tlt_cb_s( $r[1] ?? '' ),
            'phone'    => tlt_cb_contact_sheet_format_phone( tlt_cb_s( $r[6] ?? '' ) ),
            'email'    => tlt_cb_s( $r[7] ?? '' ),
        ];
    }

    // --- Production team from Production Teams tab, merged by full name ---
    // teamMap key = lowercased "First Middle Last" so the Theatre-tab join
    // below can find the same person even if a middle name is present.
    $team_map = [];
    foreach ( $team_rows as $r ) {
        if ( tlt_cb_s( $r[0] ?? '' ) !== $show ) continue;
        $first = tlt_cb_s( $r[2] ?? '' );
        $last  = tlt_cb_s( $r[4] ?? '' );
        if ( $first === '' || $last === '' ) continue;

        $role    = tlt_cb_s( $r[1] ?? '' );
        $contact = $find_contact( $first, $last );
        $full    = implode( ' ', array_filter( [ $first, $contact['middle'] ?? '', $last ], function ( $x ) { return $x !== ''; } ) );
        $key     = strtolower( $full );

        // Stage Managers use the show-specific SM email (Season col E) instead
        // of their personal contactbook email.
        $email_for_role = ( $role === 'Stage Manager' && $show_sm_email !== '' )
            ? $show_sm_email
            : ( $contact['email'] ?? '' );

        if ( isset( $team_map[ $key ] ) ) {
            $team_map[ $key ]['role'] .= ' / ' . $role;
            if ( $role === 'Stage Manager' && $show_sm_email !== '' ) {
                $team_map[ $key ]['email'] = $show_sm_email;
            }
        } else {
            $team_map[ $key ] = [
                'name'     => $full,
                'pronouns' => $contact['pronouns'] ?? '',
                'role'     => $role,
                'phone'    => tlt_cb_contact_sheet_format_phone( $contact['phone'] ?? '' ),
                'email'    => $email_for_role,
            ];
        }
    }

    // --- Staff from Theatre tab, merged if already in team_map ---
    $staff_roles = [
        [ 'label' => 'Managing Artistic Director',   'role' => 'Managing Artistic Director'   ],
        [ 'label' => 'Technical Director',           'role' => 'Technical Director'           ],
        [ 'label' => 'Associate Producing Director', 'role' => 'Associate Producing Director' ],
        [ 'label' => 'Production Manager',           'role' => 'Production Manager'           ],
        [ 'label' => 'Lead Carpenter',               'role' => 'Lead Carpenter'               ],
        [ 'label' => 'Shop Technician',              'role' => 'Shop Technician'              ],
    ];
    foreach ( $staff_roles as $s ) {
        $full_name = $get_setting( $s['label'] );
        if ( $full_name === '' ) continue;
        $parts   = preg_split( '/\s+/', trim( $full_name ) );
        $first   = $parts[0] ?? '';
        $last    = $parts[ count( $parts ) - 1 ] ?? '';
        $contact = $find_contact( $first, $last );

        // Find existing team member by first+last (ignoring any middle name in the key).
        $existing_key = null;
        $first_lc = strtolower( $first );
        $last_lc  = strtolower( $last );
        foreach ( array_keys( $team_map ) as $k ) {
            $kp = explode( ' ', $k );
            if ( ( $kp[0] ?? '' ) === $first_lc && ( $kp[ count( $kp ) - 1 ] ?? '' ) === $last_lc ) {
                $existing_key = $k;
                break;
            }
        }

        if ( $existing_key !== null ) {
            $team_map[ $existing_key ]['role'] .= ' / ' . $s['role'];
        } else {
            $team_map[ strtolower( $full_name ) ] = [
                'name'     => $full_name,
                'pronouns' => $contact['pronouns'] ?? '',
                'role'     => $s['role'],
                'phone'    => tlt_cb_contact_sheet_format_phone( $contact['phone'] ?? '' ),
                'email'    => $contact['email'] ?? '',
            ];
        }
    }

    return [
        'season'         => $season,
        'season_long'    => $season_long,
        'show_sm_email'  => $show_sm_email,
        'season_row_num' => $season_row_num,
        'cast'           => $cast,
        'team'           => array_values( $team_map ),
    ];
}

/**
 * Compute the doc name for a show — used both for creating the new doc and
 * for finding + trashing an existing one during regenerate.
 */
function tlt_cb_contact_sheet_doc_name( $show, $season_long ) {
    return $show . ' - ' . $season_long . ' Contact Sheet';
}

/**
 * Trash every doc in the contact sheet folder whose name matches this show.
 * Called by /contact-sheet-regenerate before generating a fresh copy.
 *
 * @return int|WP_Error Count of files trashed
 */
function tlt_cb_contact_sheet_trash_existing( $show, $season_long ) {
    $name  = tlt_cb_contact_sheet_doc_name( $show, $season_long );
    $files = tlt_cb_drive_find_in_folder( TLT_CALLBOARD_CS_FOLDER_ID, $name );
    if ( is_wp_error( $files ) ) return $files;
    $count = 0;
    foreach ( $files as $f ) {
        $r = tlt_cb_drive_trash( $f['id'] );
        if ( is_wp_error( $r ) ) return $r;
        $count++;
    }
    return $count;
}

/**
 * Build a "Range" object for a Docs API request that covers the given
 * inclusive-start / exclusive-end indices. Extracted so the doc-building
 * code below reads cleaner.
 */
function tlt_cb_docs_range( $start, $end ) {
    return [ 'startIndex' => $start, 'endIndex' => $end ];
}

/**
 * Populate a freshly-copied template doc with the CAST + PRODUCTION TEAM
 * tables. Runs in three phases (three batchUpdate round trips) so index
 * math stays local to each phase:
 *
 *   Phase 1: Set body margins, insert all header/title text + section
 *            headers (as plain paragraphs, no tables yet), apply their
 *            paragraph and text styles.
 *   Phase 2: Insert the two tables at the correct spots between the
 *            section headers. Reads the doc back to find exact positions.
 *   Phase 3: Fill each table's cells with row content + style them.
 *
 * @return true|WP_Error
 */
function tlt_cb_contact_sheet_build_doc( $doc_id, $show, $season, $season_long, array $cast, array $team ) {
    // -------------- Phase 1: text scaffolding + margins + top styling --
    // Structure we're building at the top of the body (each line = paragraph):
    //   L1: {show}: Contact Sheet     (font 14, bold, centered)
    //   L2: Season {season}            (font 12, centered)
    //   L3: (blank)
    //   L4: CAST                       (font 12, bold, centered, spacingBefore=12, spacingAfter=4)
    //   L5: (blank — will be replaced by table in phase 2)
    //   L6: PRODUCTION TEAM            (same style as L4)
    //   L7: (blank — will be replaced by table in phase 2)
    //
    // We insert everything at index 1 (the top of the body, right after the
    // implicit section break). To keep index math simple we insert LAST-first
    // so each earlier insert doesn't shift the ones we've already recorded.

    $title_text  = $show . ': Contact Sheet';
    $season_text = 'Season ' . $season;
    $cast_hdr    = 'CAST';
    $team_hdr    = 'PRODUCTION TEAM';

    // Order the lines top-to-bottom, then reverse the request order below so
    // the last line is inserted first (subsequent inserts push it down).
    $lines = [
        [ 'text' => $title_text ],  // L1
        [ 'text' => $season_text ], // L2
        [ 'text' => '' ],           // L3 blank
        [ 'text' => $cast_hdr ],    // L4
        [ 'text' => '' ],           // L5 blank (cast table target)
        [ 'text' => $team_hdr ],    // L6
        [ 'text' => '' ],           // L7 blank (team table target)
    ];

    // Build reverse-order insert requests; each inserts at index 1 with a
    // trailing "\n" so it becomes its own paragraph.
    $insert_reqs = [];
    for ( $i = count( $lines ) - 1; $i >= 0; $i-- ) {
        $insert_reqs[] = [
            'insertText' => [
                'location' => [ 'index' => 1 ],
                'text'     => $lines[ $i ]['text'] . "\n",
            ],
        ];
    }

    // Now compute the final start/end indices of each paragraph so we can
    // apply styling in the same batchUpdate. Because we inserted top-down
    // conceptually, the FIRST line starts at index 1 and each subsequent
    // line starts after the previous line's text + its newline.
    $line_ranges = [];
    $cursor = 1;
    foreach ( $lines as $line ) {
        // Docs API indices count UTF-16 code units. For BMP chars (all of
        // ours) that equals character count — NOT bytes. strlen() is bytes
        // so it drifts on multi-byte chars (em-dash "—" is 3 bytes / 1 char).
        $len = mb_strlen( $line['text'], 'UTF-8' );
        // The paragraph occupies indices [cursor, cursor + len + 1)
        //   — cursor..cursor+len is the text, cursor+len is the newline.
        $line_ranges[] = [
            'text_start' => $cursor,
            'text_end'   => $cursor + $len,       // exclusive
            'para_start' => $cursor,
            'para_end'   => $cursor + $len + 1,   // include newline
        ];
        $cursor += $len + 1;
    }

    // Margin update — 18pt on all sides (matches GAS behavior).
    $margin_req = [
        'updateDocumentStyle' => [
            'documentStyle' => [
                'marginTop'    => [ 'magnitude' => 18, 'unit' => 'PT' ],
                'marginBottom' => [ 'magnitude' => 18, 'unit' => 'PT' ],
                'marginLeft'   => [ 'magnitude' => 18, 'unit' => 'PT' ],
                'marginRight'  => [ 'magnitude' => 18, 'unit' => 'PT' ],
            ],
            'fields' => 'marginTop,marginBottom,marginLeft,marginRight',
        ],
    ];

    // Styling requests. We build these in a fixed order — Docs API applies
    // them post-inserts.
    $style_reqs = [];

    // L1: title — font 14, bold, centered.
    $style_reqs[] = [
        'updateTextStyle' => [
            'range'     => tlt_cb_docs_range( $line_ranges[0]['text_start'], $line_ranges[0]['text_end'] ),
            'textStyle' => [
                'fontSize' => [ 'magnitude' => 14, 'unit' => 'PT' ],
                'bold'     => true,
            ],
            'fields' => 'fontSize,bold',
        ],
    ];
    $style_reqs[] = [
        'updateParagraphStyle' => [
            'range'          => tlt_cb_docs_range( $line_ranges[0]['para_start'], $line_ranges[0]['para_end'] ),
            'paragraphStyle' => [ 'alignment' => 'CENTER' ],
            'fields'         => 'alignment',
        ],
    ];
    // L2: season — font 12, not bold, centered.
    $style_reqs[] = [
        'updateTextStyle' => [
            'range'     => tlt_cb_docs_range( $line_ranges[1]['text_start'], $line_ranges[1]['text_end'] ),
            'textStyle' => [
                'fontSize' => [ 'magnitude' => 12, 'unit' => 'PT' ],
                'bold'     => false,
            ],
            'fields' => 'fontSize,bold',
        ],
    ];
    $style_reqs[] = [
        'updateParagraphStyle' => [
            'range'          => tlt_cb_docs_range( $line_ranges[1]['para_start'], $line_ranges[1]['para_end'] ),
            'paragraphStyle' => [ 'alignment' => 'CENTER' ],
            'fields'         => 'alignment',
        ],
    ];
    // L4 + L6: section headers — font 12, bold, centered, spacingBefore=12, spacingAfter=4.
    foreach ( [ 3, 5 ] as $li ) {
        $style_reqs[] = [
            'updateTextStyle' => [
                'range'     => tlt_cb_docs_range( $line_ranges[ $li ]['text_start'], $line_ranges[ $li ]['text_end'] ),
                'textStyle' => [
                    'fontSize' => [ 'magnitude' => 12, 'unit' => 'PT' ],
                    'bold'     => true,
                ],
                'fields' => 'fontSize,bold',
            ],
        ];
        $style_reqs[] = [
            'updateParagraphStyle' => [
                'range'          => tlt_cb_docs_range( $line_ranges[ $li ]['para_start'], $line_ranges[ $li ]['para_end'] ),
                'paragraphStyle' => [
                    'alignment'     => 'CENTER',
                    'spaceAbove'    => [ 'magnitude' => 12, 'unit' => 'PT' ],
                    'spaceBelow'    => [ 'magnitude' => 4,  'unit' => 'PT' ],
                ],
                'fields' => 'alignment,spaceAbove,spaceBelow',
            ],
        ];
    }

    // Phase 1 batchUpdate: margins + all inserts + styling.
    $requests = array_merge( [ $margin_req ], $insert_reqs, $style_reqs );
    $r = tlt_cb_docs_batch_update( $doc_id, $requests );
    if ( is_wp_error( $r ) ) return $r;

    // -------------- Phase 2: insert both tables ------------------------
    // Re-fetch the doc to find where the CAST + PRODUCTION TEAM paragraphs
    // now live. We insert each table just AFTER the corresponding section
    // header (into what will become the blank paragraph after it).

    $doc = tlt_cb_docs_get( $doc_id, 'body(content(startIndex,endIndex,paragraph(elements(startIndex,endIndex,textRun(content)))))' );
    if ( is_wp_error( $doc ) ) return $doc;

    // Find the paragraph containing the CAST header (exact text match).
    $cast_para_end = null;
    $team_para_end = null;
    foreach ( ( $doc['body']['content'] ?? [] ) as $el ) {
        if ( empty( $el['paragraph']['elements'] ) ) continue;
        $text = '';
        foreach ( $el['paragraph']['elements'] as $pe ) {
            $text .= $pe['textRun']['content'] ?? '';
        }
        $trimmed = rtrim( $text, "\n" );
        if ( $trimmed === $cast_hdr && $cast_para_end === null ) $cast_para_end = $el['endIndex'];
        if ( $trimmed === $team_hdr && $team_para_end === null ) $team_para_end = $el['endIndex'];
    }
    if ( $cast_para_end === null || $team_para_end === null ) {
        return new WP_Error( 'cs_marker_missing', 'Could not find CAST or PRODUCTION TEAM header in the copied doc.' );
    }

    // Insert TEAM table first (it's later in the doc) so its index isn't
    // shifted by the CAST table insertion.
    $team_rows_count = 1 + count( $team ); // header + data rows
    $cast_rows_count = 1 + count( $cast );
    $cols            = 5;

    // The "blank paragraph after the header" occupies indices
    // [para_end, para_end + 1) — a single "\n". Inserting a table at
    // para_end places it BEFORE that newline, which is what we want.
    $phase2 = [
        [
            'insertTable' => [
                'rows'     => $team_rows_count,
                'columns'  => $cols,
                'location' => [ 'index' => $team_para_end ],
            ],
        ],
        [
            'insertTable' => [
                'rows'     => $cast_rows_count,
                'columns'  => $cols,
                'location' => [ 'index' => $cast_para_end ],
            ],
        ],
    ];
    $r = tlt_cb_docs_batch_update( $doc_id, $phase2 );
    if ( is_wp_error( $r ) ) return $r;

    // -------------- Phase 3: fill both tables' cells -------------------
    // Re-fetch the doc — now we walk body.content, find the two tables (in
    // document order they're CAST then PRODUCTION TEAM), and for each cell
    // read its first paragraph's startIndex — that's where we insert text.

    $doc = tlt_cb_docs_get( $doc_id, 'body(content(startIndex,endIndex,table(rows,columns,tableRows(tableCells(content(startIndex,endIndex,paragraph(elements(startIndex,endIndex))))))))' );
    if ( is_wp_error( $doc ) ) return $doc;

    $tables = [];
    foreach ( ( $doc['body']['content'] ?? [] ) as $el ) {
        if ( isset( $el['table'] ) ) $tables[] = $el['table'];
    }
    if ( count( $tables ) < 2 ) {
        return new WP_Error( 'cs_tables_missing', 'Expected 2 tables after insert, found ' . count( $tables ) );
    }

    $header_row  = [ 'Name', 'Pronouns', 'Role', 'Phone', 'Email' ];
    $col_widths  = [ 105, 65, 138, 83, 185 ]; // matches GAS

    // Build the ordered list of (cell start index, text to insert) pairs for
    // BOTH tables. We collect them ALL first, sort by index DESCENDING, and
    // insert in that order — that way each insert doesn't shift the indices
    // of the still-pending inserts. Classic backwards-insert trick.
    $inserts = [];

    // Table 0 = CAST, Table 1 = PRODUCTION TEAM (document order matches
    // phase-2 insertion order because CAST is earlier in the body).
    $data_by_table = [ $cast, $team ];
    foreach ( $tables as $ti => $tbl ) {
        $rows = $tbl['tableRows'] ?? [];
        $data = $data_by_table[ $ti ];
        foreach ( $rows as $ri => $row ) {
            $cells = $row['tableCells'] ?? [];
            foreach ( $cells as $ci => $cell ) {
                $first_para = $cell['content'][0]['paragraph'] ?? null;
                if ( ! $first_para ) continue;
                $cell_para_start = $cell['content'][0]['startIndex'] ?? null;
                if ( $cell_para_start === null ) continue;

                if ( $ri === 0 ) {
                    $val = $header_row[ $ci ] ?? '';
                } else {
                    $data_row = $data[ $ri - 1 ] ?? [];
                    $val = tlt_cb_s( [
                        $data_row['name']     ?? '',
                        $data_row['pronouns'] ?? '',
                        $data_row['role']     ?? '',
                        $data_row['phone']    ?? '',
                        $data_row['email']    ?? '',
                    ][ $ci ] ?? '' );
                }

                if ( $val === '' ) continue; // no need to insert empty text

                $inserts[] = [
                    'index' => $cell_para_start,
                    'text'  => $val,
                    // Track table+cell coords for post-insert styling.
                    'table' => $ti,
                    'row'   => $ri,
                    'col'   => $ci,
                    'len'   => mb_strlen( $val, 'UTF-8' ),
                ];
            }
        }
    }

    // Sort DESCENDING by index so later inserts don't shift earlier ones'
    // target indices.
    usort( $inserts, function ( $a, $b ) { return $b['index'] - $a['index']; } );

    // Phase 3a — inserts only. Splitting from styling because inserts into
    // table 0 grow the doc, shifting table 1's startIndex; if we ran cell/col
    // styling in the same batch, table 1's styling would target a stale index.
    $insert_requests = [];
    foreach ( $inserts as $ins ) {
        $insert_requests[] = [
            'insertText' => [
                'location' => [ 'index' => $ins['index'] ],
                'text'     => $ins['text'],
            ],
        ];
    }
    if ( ! empty( $insert_requests ) ) {
        $r = tlt_cb_docs_batch_update( $doc_id, $insert_requests );
        if ( is_wp_error( $r ) ) return $r;
    }

    // Phase 3b — column widths + cell padding. Re-fetch table start indices
    // now that inserts have shifted things.
    $doc2 = tlt_cb_docs_get( $doc_id, 'body(content(startIndex,table(tableStyle(tableColumnProperties))))' );
    if ( is_wp_error( $doc2 ) ) return $doc2;
    $table_start_indices = [];
    foreach ( ( $doc2['body']['content'] ?? [] ) as $el ) {
        if ( isset( $el['table'] ) ) $table_start_indices[] = $el['startIndex'] ?? null;
    }

    $style_requests = [];
    foreach ( $table_start_indices as $ti => $start ) {
        if ( $start === null ) continue;
        foreach ( $col_widths as $ci => $w ) {
            $style_requests[] = [
                'updateTableColumnProperties' => [
                    'tableStartLocation'    => [ 'index' => $start ],
                    'columnIndices'         => [ $ci ],
                    'tableColumnProperties' => [
                        'widthType' => 'FIXED_WIDTH',
                        'width'     => [ 'magnitude' => $w, 'unit' => 'PT' ],
                    ],
                    'fields' => 'widthType,width',
                ],
            ];
        }
        // Note: updateTableCellStyle expects EITHER tableStartLocation OR
        // tableRange at the request level — Google's docs list them as a
        // `oneof cells`. The location the API actually reads from lives
        // inside tableRange.tableCellLocation.tableStartLocation.
        $style_requests[] = [
            'updateTableCellStyle' => [
                'tableRange' => [
                    'tableCellLocation' => [
                        'tableStartLocation' => [ 'index' => $start ],
                        'rowIndex'           => 0,
                        'columnIndex'        => 0,
                    ],
                    'rowSpan'    => 1,
                    'columnSpan' => $cols,
                ],
                'tableCellStyle' => [
                    'paddingTop'    => [ 'magnitude' => 4, 'unit' => 'PT' ],
                    'paddingBottom' => [ 'magnitude' => 4, 'unit' => 'PT' ],
                    'paddingLeft'   => [ 'magnitude' => 6, 'unit' => 'PT' ],
                    'paddingRight'  => [ 'magnitude' => 6, 'unit' => 'PT' ],
                ],
                'fields' => 'paddingTop,paddingBottom,paddingLeft,paddingRight',
            ],
        ];
        $data_count = ( $ti === 0 ) ? count( $cast ) : count( $team );
        if ( $data_count > 0 ) {
            $style_requests[] = [
                'updateTableCellStyle' => [
                    'tableRange' => [
                        'tableCellLocation' => [
                            'tableStartLocation' => [ 'index' => $start ],
                            'rowIndex'           => 1,
                            'columnIndex'        => 0,
                        ],
                        'rowSpan'    => $data_count,
                        'columnSpan' => $cols,
                    ],
                    'tableCellStyle' => [
                        'paddingTop'    => [ 'magnitude' => 3, 'unit' => 'PT' ],
                        'paddingBottom' => [ 'magnitude' => 3, 'unit' => 'PT' ],
                        'paddingLeft'   => [ 'magnitude' => 6, 'unit' => 'PT' ],
                        'paddingRight'  => [ 'magnitude' => 6, 'unit' => 'PT' ],
                    ],
                    'fields' => 'paddingTop,paddingBottom,paddingLeft,paddingRight',
                ],
            ];
        }
    }
    if ( ! empty( $style_requests ) ) {
        $r = tlt_cb_docs_batch_update( $doc_id, $style_requests );
        if ( is_wp_error( $r ) ) return $r;
    }

    // -------------- Phase 4: text styling on filled cells --------------
    // GAS sets all cell text to font 10 (bold for header row, not bold for
    // data rows). We do this after phase 3 so we can compute exact cell
    // text ranges from the current doc structure.

    $doc3 = tlt_cb_docs_get( $doc_id, 'body(content(table(tableRows(tableCells(content(startIndex,endIndex,paragraph(elements(startIndex,endIndex,textRun(content)))))))))' );
    if ( is_wp_error( $doc3 ) ) return $doc3;

    $tables3 = [];
    foreach ( ( $doc3['body']['content'] ?? [] ) as $el ) {
        if ( isset( $el['table'] ) ) $tables3[] = $el['table'];
    }

    $style_reqs4 = [];
    foreach ( $tables3 as $ti => $tbl ) {
        $rows = $tbl['tableRows'] ?? [];
        foreach ( $rows as $ri => $row ) {
            $cells = $row['tableCells'] ?? [];
            $is_header_row = ( $ri === 0 );
            foreach ( $cells as $cell ) {
                foreach ( ( $cell['content'] ?? [] ) as $item ) {
                    if ( empty( $item['paragraph']['elements'] ) ) continue;
                    foreach ( $item['paragraph']['elements'] as $pe ) {
                        if ( empty( $pe['textRun']['content'] ) ) continue;
                        $s = $pe['startIndex'] ?? null;
                        $e = $pe['endIndex']   ?? null;
                        if ( $s === null || $e === null || $e <= $s ) continue;
                        // Skip the trailing newline character at the end of
                        // the cell's paragraph — style only real text.
                        $content = $pe['textRun']['content'];
                        if ( substr( $content, -1 ) === "\n" ) $e = $e - 1;
                        if ( $e <= $s ) continue;
                        $style_reqs4[] = [
                            'updateTextStyle' => [
                                'range'     => tlt_cb_docs_range( $s, $e ),
                                'textStyle' => [
                                    'fontSize' => [ 'magnitude' => 10, 'unit' => 'PT' ],
                                    'bold'     => $is_header_row,
                                ],
                                'fields' => 'fontSize,bold',
                            ],
                        ];
                    }
                }
            }
        }
    }

    if ( ! empty( $style_reqs4 ) ) {
        $r = tlt_cb_docs_batch_update( $doc_id, $style_reqs4 );
        if ( is_wp_error( $r ) ) return $r;
    }

    return true;
}

/**
 * Public entry point: assemble data, copy template, build doc, write URL
 * back to Season col M, return the URL.
 *
 * @param string $show
 * @param bool   $regenerate If true, trash existing docs matching the name first.
 * @return array|WP_Error { url }
 */
function tlt_cb_contact_sheet_generate( $show, $regenerate = false ) {
    $data = tlt_cb_contact_sheet_assemble_data( $show );
    if ( is_wp_error( $data ) ) return $data;
    if ( $data['season_long'] === '' ) {
        return new WP_Error( 'no_season_long', 'Season "Current Season Long" is empty — set it on the Season tab.' );
    }

    if ( $regenerate ) {
        $trashed = tlt_cb_contact_sheet_trash_existing( $show, $data['season_long'] );
        if ( is_wp_error( $trashed ) ) return $trashed;
    }

    $doc_name = tlt_cb_contact_sheet_doc_name( $show, $data['season_long'] );
    $file     = tlt_cb_drive_copy( TLT_CALLBOARD_CS_TEMPLATE_ID, TLT_CALLBOARD_CS_FOLDER_ID, $doc_name );
    if ( is_wp_error( $file ) ) return $file;
    $doc_id   = $file['id'];

    $r = tlt_cb_contact_sheet_build_doc( $doc_id, $show, $data['season'], $data['season_long'], $data['cast'], $data['team'] );
    if ( is_wp_error( $r ) ) return $r;

    $url = tlt_cb_doc_url( $doc_id );

    // Cache the URL in Season col M so subsequent /contact-sheet-link calls
    // don't need to scan Drive.
    if ( $data['season_row_num'] > 0 ) {
        $write = tlt_callboard_sheets_write( TLT_CALLBOARD_SHEET_ID, "Season!M{$data['season_row_num']}", [[ $url ]] );
        if ( is_wp_error( $write ) ) return $write; // still surface the URL? For now bail.
    }

    // Auto-refresh the show-folder PDF if one was previously distributed.
    tlt_cb_generator_refresh_show_pdf( $show, $doc_id, $doc_name . '.pdf' );

    return [ 'url' => $url ];
}

/* ---------------------------------------------------------------------------
 * Endpoint handlers.
 * ------------------------------------------------------------------------- */

/**
 * POST /contact-sheet-generate  { show }
 * Generates the doc without trashing an existing one. Used for the first
 * generation of a show's contact sheet.
 */
function tlt_callboard_ep_contact_sheet_generate( WP_REST_Request $req ) {
    $body = $req->get_json_params();
    $show = tlt_cb_s( is_array( $body ) ? ( $body['show'] ?? '' ) : '' );
    if ( $show === '' ) return new WP_Error( 'missing_show', 'show is required', [ 'status' => 400 ] );
    $r = tlt_cb_contact_sheet_generate( $show, false );
    if ( is_wp_error( $r ) ) return $r;
    return tlt_cb_ok( $r );
}

/**
 * POST /contact-sheet-regenerate  { show }
 * Trashes the existing doc(s) in the folder matching this show's name, then
 * generates a fresh one. Used by the "Regenerate" button in the modal.
 */
function tlt_callboard_ep_contact_sheet_regenerate( WP_REST_Request $req ) {
    $body = $req->get_json_params();
    $show = tlt_cb_s( is_array( $body ) ? ( $body['show'] ?? '' ) : '' );
    if ( $show === '' ) return new WP_Error( 'missing_show', 'show is required', [ 'status' => 400 ] );
    $r = tlt_cb_contact_sheet_generate( $show, true );
    if ( is_wp_error( $r ) ) return $r;
    return tlt_cb_ok( $r );
}

/**
 * POST /contact-sheet-add-to-show  { show }
 * Export the current contact sheet doc as a PDF and drop it in
 * {season}/{show}/General/. The Google Doc stays canonical in the Contact
 * Sheets folder. Later regenerates automatically refresh this PDF too, so
 * users only have to click once per show.
 */
function tlt_callboard_ep_contact_sheet_add_to_show( WP_REST_Request $req ) {
    $body = $req->get_json_params();
    $show = tlt_cb_s( is_array( $body ) ? ( $body['show'] ?? '' ) : '' );
    if ( $show === '' ) return new WP_Error( 'missing_show', 'show is required', [ 'status' => 400 ] );
    return tlt_cb_send_generator_pdf_to_show(
        $show,
        TLT_CALLBOARD_CS_FOLDER_ID,
        'M',
        function ( $show, $season_long ) { return tlt_cb_contact_sheet_doc_name( $show, $season_long ); },
        'contact sheet'
    );
}

/**
 * POST /tech-schedule-add-to-show  { show }
 * Same as above, for the tech schedule.
 */
function tlt_callboard_ep_tech_schedule_add_to_show( WP_REST_Request $req ) {
    $body = $req->get_json_params();
    $show = tlt_cb_s( is_array( $body ) ? ( $body['show'] ?? '' ) : '' );
    if ( $show === '' ) return new WP_Error( 'missing_show', 'show is required', [ 'status' => 400 ] );
    return tlt_cb_send_generator_pdf_to_show(
        $show,
        TLT_CALLBOARD_TS_FOLDER_ID,
        'N',
        function ( $show, $season_long ) { return $show . ' - ' . $season_long . ' Tech Schedule'; },
        'tech schedule'
    );
}

/**
 * Export a generator doc as PDF and drop it in {show}/General/. Called by both
 * the "Add PDF to show Drive" button and (indirectly) by regenerate via
 * tlt_cb_generator_refresh_show_pdf.
 *
 * @param string   $show
 * @param string   $primary_folder_id  Where the canonical Doc lives.
 * @param string   $url_col_letter     Season col letter caching the doc URL.
 * @param callable $name_builder       ( $show, $season_long ) → doc filename (no .pdf).
 * @param string   $doc_label          Human label for error messages.
 */
function tlt_cb_send_generator_pdf_to_show( $show, $primary_folder_id, $url_col_letter, $name_builder, $doc_label ) {
    // Resolve season config for the show's row + season name.
    $season_rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_SHEET_ID, 'Season!A1:N', 60, true );
    if ( is_wp_error( $season_rows ) ) return $season_rows;
    $season_long = tlt_cb_season_setting( $season_rows, 'Current Season Long' );
    if ( $season_long === '' ) return new WP_Error( 'no_season_long', 'Season "Current Season Long" is empty.' );

    // Show name lives in col B on the Season tab (col A is the slot label
    // "Show1", "Show2", …). Match same convention as the other generators.
    $row_num = 0;
    foreach ( $season_rows as $i => $r ) {
        if ( tlt_cb_s( $r[1] ?? '' ) === $show ) { $row_num = $i + 1; break; }
    }

    // Look up the doc: URL cache preferred, folder scan fallback.
    $doc_id = null;
    if ( $row_num > 0 ) {
        // Column letter is 0-based-index-of-M + row_num offset — we already
        // read the whole row above; just grab the cached URL directly.
        $col_index = ord( strtoupper( $url_col_letter ) ) - ord( 'A' );
        $cached_url = tlt_cb_s( $season_rows[ $row_num - 1 ][ $col_index ] ?? '' );
        if ( $cached_url !== '' && preg_match( '~/document/d/([^/?]+)~', $cached_url, $m ) ) {
            $doc_id = $m[1];
        }
    }
    $doc_name = $name_builder( $show, $season_long );
    if ( ! $doc_id ) {
        $files = tlt_cb_drive_find_in_folder( $primary_folder_id, $doc_name );
        if ( is_wp_error( $files ) ) return $files;
        if ( empty( $files ) ) return new WP_Error( 'no_doc', 'No ' . $doc_label . ' found for ' . $show . '. Generate it first.' );
        $doc_id = $files[0]['id'];
    }

    $general_id = tlt_cb_resolve_show_general_folder( $show );
    if ( is_wp_error( $general_id ) ) return $general_id;

    $pdf_filename = $doc_name . '.pdf';
    $r = tlt_cb_generator_publish_pdf( $doc_id, $general_id, $pdf_filename );
    if ( is_wp_error( $r ) ) return $r;

    return tlt_cb_ok( [
        'folder'     => $show . ' / General',
        'folder_url' => 'https://drive.google.com/drive/folders/' . rawurlencode( $general_id ),
        'pdf'        => $pdf_filename,
    ] );
}

/**
 * Called after a regenerate to keep an already-distributed PDF in sync with
 * the canonical Doc. If no PDF matching $pdf_filename lives in $show's General
 * folder, this is a no-op (the show hasn't opted in to PDF distribution yet).
 * All failures are silent — regenerate always succeeds from the caller's view.
 */
function tlt_cb_generator_refresh_show_pdf( $show, $doc_id, $pdf_filename ) {
    $general_id = tlt_cb_resolve_show_general_folder( $show );
    if ( is_wp_error( $general_id ) ) return;
    $existing = tlt_cb_drive_find_in_folder( $general_id, $pdf_filename );
    if ( is_wp_error( $existing ) || empty( $existing ) ) return; // never distributed → nothing to refresh
    tlt_cb_generator_publish_pdf( $doc_id, $general_id, $pdf_filename );
}

/**
 * Resolve the {season}/{show}/General/ folder ID, creating General if missing.
 */
function tlt_cb_resolve_show_general_folder( $show ) {
    $season_folder_id = tlt_cb_emergency_season_folder_id();
    if ( $season_folder_id === '' ) return new WP_Error( 'no_season_folder', 'Season folder not set in Season tab.' );
    $show_folder_id = tlt_cb_emergency_find_child_folder( $season_folder_id, $show );
    if ( ! $show_folder_id ) return new WP_Error( 'no_show_folder', 'No Drive folder found for "' . $show . '" in the season folder.' );
    return tlt_cb_emergency_find_or_create_child_folder( $show_folder_id, 'General' );
}

/**
 * Export $doc_id as PDF and write it into $folder_id as $pdf_filename,
 * replacing any prior PDF with the same name. Returns true or WP_Error.
 */
function tlt_cb_generator_publish_pdf( $doc_id, $folder_id, $pdf_filename ) {
    $pdf_bytes = tlt_cb_contract_export_pdf( $doc_id );
    if ( is_wp_error( $pdf_bytes ) ) return $pdf_bytes;
    $up = tlt_cb_emergency_replace_pdf_in_folder( $folder_id, $pdf_filename, $pdf_bytes );
    if ( is_wp_error( $up ) ) return $up;
    return true;
}

/* ===========================================================================
 * ============  SHARED HELPERS FOR ALL GENERATORS  ==========================
 * ======================================================================== */

/**
 * Parse a value from the Sheets API as a PHP DateTime. Values come in as:
 *   - ISO strings ("2026-10-03")
 *   - Serial dates as numeric strings (rare with USER_ENTERED)
 *   - Human strings ("October 3, 2026")
 * Returns null on failure.
 */
function tlt_cb_parse_date( $val ) {
    $val = trim( (string) $val );
    if ( $val === '' ) return null;
    try {
        // Passing the timezone to DateTime's constructor makes PHP interpret
        // date-only strings (like "8/28/2026") as midnight in that timezone
        // rather than UTC midnight (which then gets rolled back a day when
        // formatted in Pacific). This was the "every date is off by one day"
        // bug in generated contracts.
        $tz = new DateTimeZone( 'America/Los_Angeles' );
        return new DateTime( $val, $tz );
    } catch ( Exception $e ) {
        return null;
    }
}

/**
 * Parse time-of-day into a DateTime (date portion undefined). Handles
 * "7:30 PM", "7pm", "19:30", or a full ISO date with time. Returns null.
 */
function tlt_cb_parse_time( $val ) {
    $val = trim( (string) $val );
    if ( $val === '' ) return null;
    // If it looks like a full timestamp, use parse_date.
    if ( preg_match( '/\d{4}-\d{2}-\d{2}/', $val ) ) return tlt_cb_parse_date( $val );
    $ts = strtotime( '2000-01-01 ' . $val );
    if ( $ts === false ) return null;
    return ( new DateTime( '@' . $ts ) )->setTimezone( new DateTimeZone( 'America/Los_Angeles' ) );
}

/**
 * Match GAS formatDate() output.  "Fri, Oct. 3, 2026" (with year) or
 * "Fri, Oct. 3" (without). Empty/null input → "TBD".
 */
function tlt_cb_fmt_date( $val, $include_year = true ) {
    $dt = tlt_cb_parse_date( $val );
    if ( ! $dt ) return 'TBD';
    return $dt->format( 'D, M' ) . '. ' . (int) $dt->format( 'j' ) . ( $include_year ? ', ' . $dt->format( 'Y' ) : '' );
}

/**
 * Match GAS formatTime() output: "7:30pm" or "7pm" (no leading zero on hour).
 * Empty → "".
 */
function tlt_cb_fmt_time( $val ) {
    $dt = tlt_cb_parse_time( $val );
    if ( ! $dt ) return '';
    $h = (int) $dt->format( 'g' );
    $m = (int) $dt->format( 'i' );
    $ampm = strtolower( $dt->format( 'a' ) );
    return $m === 0 ? $h . $ampm : $h . ':' . str_pad( (string) $m, 2, '0', STR_PAD_LEFT ) . $ampm;
}

/**
 * Cache a URL in the Season tab. col1Based indexing to match GAS calls:
 *   L(12) = Bio Doc, M(13) = Contact Sheet, N(14) = Tech Schedule.
 */
function tlt_cb_save_show_doc_url( $show_name, $col1_based, $value ) {
    $rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_SHEET_ID, 'Season!A1:N' );
    if ( is_wp_error( $rows ) ) return $rows;
    foreach ( $rows as $i => $r ) {
        if ( tlt_cb_s( $r[1] ?? '' ) === tlt_cb_s( $show_name ) ) {
            $row_num = $i + 1; // A1:N starts at row 1
            $col_letter = chr( ord( 'A' ) + $col1_based - 1 );
            return tlt_callboard_sheets_write( TLT_CALLBOARD_SHEET_ID, "Season!{$col_letter}{$row_num}", [[ $value ]] );
        }
    }
    return new WP_Error( 'no_season_row', "No Season row found for show '$show_name'" );
}

/**
 * Look up a value on the Season tab config rows (col A = label, col B = value).
 */
function tlt_cb_season_setting( $season_rows, $label ) {
    foreach ( $season_rows as $r ) {
        if ( tlt_cb_s( $r[0] ?? '' ) === $label ) return tlt_cb_s( $r[1] ?? '' );
    }
    return '';
}

/**
 * Find or create a subfolder by name inside a Drive parent folder.
 * Returns the subfolder's ID.
 */
function tlt_cb_drive_folder_or_create( $parent_id, $name ) {
    $token = tlt_callboard_google_access_token();
    if ( is_wp_error( $token ) ) return $token;
    $escaped = str_replace( "'", "\\'", $name );
    $q = "name = '{$escaped}' and '{$parent_id}' in parents and mimeType = 'application/vnd.google-apps.folder' and trashed = false";
    $url = 'https://www.googleapis.com/drive/v3/files?'
         . 'q=' . rawurlencode( $q )
         . '&fields=' . rawurlencode( 'files(id,name)' )
         . '&supportsAllDrives=true&includeItemsFromAllDrives=true';
    $resp = wp_remote_get( $url, [
        'timeout' => 15,
        'headers' => [ 'Authorization' => 'Bearer ' . $token ],
    ] );
    if ( is_wp_error( $resp ) ) return $resp;
    $body = wp_remote_retrieve_body( $resp );
    $data = json_decode( $body, true );
    if ( ! empty( $data['files'][0]['id'] ) ) return $data['files'][0]['id'];
    // Create it.
    $resp = wp_remote_post( 'https://www.googleapis.com/drive/v3/files?supportsAllDrives=true&fields=id', [
        'timeout' => 15,
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'  => 'application/json',
        ],
        'body' => wp_json_encode( [
            'name'     => $name,
            'mimeType' => 'application/vnd.google-apps.folder',
            'parents'  => [ $parent_id ],
        ] ),
    ] );
    if ( is_wp_error( $resp ) ) return $resp;
    $body = wp_remote_retrieve_body( $resp );
    $data = json_decode( $body, true );
    if ( empty( $data['id'] ) ) return new WP_Error( 'drive_folder_create', "Could not create folder '$name': $body" );
    return $data['id'];
}

/**
 * Create a fresh Google Doc directly (no template copy). Returns the doc ID.
 * Moves it into $parent_folder_id — SAs don't have a My Drive so we can't
 * assume the initial parent is literally "root"; look it up + remove
 * whatever the current parents are.
 */
function tlt_cb_docs_create( $title, $parent_folder_id ) {
    $token = tlt_callboard_google_access_token();
    if ( is_wp_error( $token ) ) return $token;
    $resp = wp_remote_post( 'https://docs.googleapis.com/v1/documents', [
        'timeout' => 30,
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'  => 'application/json',
        ],
        'body' => wp_json_encode( [ 'title' => $title ] ),
    ] );
    if ( is_wp_error( $resp ) ) return $resp;
    $body = wp_remote_retrieve_body( $resp );
    $data = json_decode( $body, true );
    if ( empty( $data['documentId'] ) ) return new WP_Error( 'docs_create', "Docs create failed: $body" );
    $doc_id = $data['documentId'];

    // Look up the doc's current parents so we can remove them explicitly.
    $meta_resp = wp_remote_get(
        'https://www.googleapis.com/drive/v3/files/' . $doc_id . '?fields=parents&supportsAllDrives=true',
        [ 'timeout' => 15, 'headers' => [ 'Authorization' => 'Bearer ' . $token ] ]
    );
    $current_parents = [];
    if ( ! is_wp_error( $meta_resp ) ) {
        $meta = json_decode( wp_remote_retrieve_body( $meta_resp ), true );
        if ( ! empty( $meta['parents'] ) && is_array( $meta['parents'] ) ) {
            $current_parents = $meta['parents'];
        }
    }

    // Move: addParents=target, removeParents=<current joined>.
    $url = 'https://www.googleapis.com/drive/v3/files/' . $doc_id
        . '?addParents=' . rawurlencode( $parent_folder_id );
    if ( ! empty( $current_parents ) ) {
        $url .= '&removeParents=' . rawurlencode( implode( ',', $current_parents ) );
    }
    $url .= '&supportsAllDrives=true&fields=id,parents';
    $mv = wp_remote_request( $url, [
        'method'  => 'PATCH',
        'timeout' => 15,
        'headers' => [ 'Authorization' => 'Bearer ' . $token ],
    ] );
    if ( is_wp_error( $mv ) ) return $mv;
    $mv_code = wp_remote_retrieve_response_code( $mv );
    if ( $mv_code < 200 || $mv_code >= 300 ) {
        return new WP_Error( 'docs_create_move', "Doc created but move to folder failed ($mv_code): " . wp_remote_retrieve_body( $mv ) . " — doc left at {$doc_id}" );
    }
    return $doc_id;
}

/* ===========================================================================
 * ============  TECH SCHEDULE GENERATOR  ====================================
 *
 * Port of TechScheduleGenerator.js.  Copies the tech schedule template,
 * replaces <<Tag>> placeholders via replaceAllText, optionally deletes the
 * Tech Run row when Cue to Cue + Tech Run fall on the same day.  Caches URL
 * in Season col N (index 13).
 * ======================================================================== */

/**
 * Read Dates tab data for a show and build the replacements map matching
 * TechScheduleGenerator.js line 98-127.
 *
 * @return array [ 'replacements' => [ tag => value ], 'c2c_same_day' => bool, 'season_long' => string, 'season_row_num' => int ]
 */
function tlt_cb_tech_schedule_assemble( $show ) {
    $ranges = tlt_callboard_sheets_get( TLT_CALLBOARD_SHEET_ID, [
        'Season!A1:N',
        'Dates!A2:H',
    ] );
    if ( is_wp_error( $ranges ) ) return $ranges;
    $season_rows = $ranges['Season!A1:N'] ?? [];
    $dates_rows  = array_values( array_filter( $ranges['Dates!A2:H'] ?? [], function ( $r ) {
        return tlt_cb_s( $r[0] ?? '' ) !== '';
    } ) );

    $season_long   = tlt_cb_season_setting( $season_rows, 'Current Season Long' );
    $season_row_num = 0;
    foreach ( $season_rows as $i => $r ) {
        if ( tlt_cb_s( $r[1] ?? '' ) === $show ) { $season_row_num = $i + 1; break; }
    }

    // Helpers to fetch data from Dates rows for the target show.
    $find = function ( $event_type ) use ( $dates_rows, $show ) {
        foreach ( $dates_rows as $r ) {
            if ( tlt_cb_s( $r[0] ?? '' ) === $show && tlt_cb_s( $r[1] ?? '' ) === $event_type ) return $r;
        }
        return null;
    };
    $get_date = function ( $event_type ) use ( $find ) {
        $r = $find( $event_type ); return $r ? ( $r[4] ?? '' ) : '';
    };
    $get_start = function ( $event_type ) use ( $find ) {
        $r = $find( $event_type ); return $r ? ( $r[5] ?? '' ) : '';
    };
    $get_end = function ( $event_type ) use ( $find ) {
        $r = $find( $event_type ); return $r ? ( $r[7] ?? '' ) : '';
    };
    $rows_of = function ( $event_type ) use ( $dates_rows, $show ) {
        $out = [];
        foreach ( $dates_rows as $r ) {
            if ( tlt_cb_s( $r[0] ?? '' ) === $show && tlt_cb_s( $r[1] ?? '' ) === $event_type ) $out[] = $r;
        }
        return $out;
    };
    $time_range = function ( $event_type ) use ( $get_start, $get_end ) {
        $s = tlt_cb_fmt_time( $get_start( $event_type ) );
        $e = tlt_cb_fmt_time( $get_end( $event_type ) );
        if ( $s === '' ) return '';
        if ( $e === '' ) return $s;
        return $s . ' - ' . $e;
    };

    // Cue to Cue vs Tech Run same-day check.
    $c2c_date_raw  = $get_date( 'Cue to Cue' );
    $tr_date_raw   = $get_date( 'Tech Run' );
    $c2c_dt        = tlt_cb_parse_date( $c2c_date_raw );
    $tr_dt         = tlt_cb_parse_date( $tr_date_raw );
    $c2c_same_day  = $c2c_dt && $tr_dt && $c2c_dt->format( 'Y-m-d' ) === $tr_dt->format( 'Y-m-d' );
    $c2c_label     = $c2c_same_day ? 'Cue to Cue & Tech Run' : 'Cue to Cue';

    // Opening / closing → run dates.
    $opening = $get_date( 'Opening Performance' );
    $closing = $get_date( 'Closing Performance' );
    $run_dates = ( $opening && $closing )
        ? tlt_cb_fmt_date( $opening, false ) . ' - ' . tlt_cb_fmt_date( $closing, true )
        : 'TBD';

    // Dress rehearsals range (first row → last row).
    $dress_rows = $rows_of( 'Dress Rehearsal' );
    $dress_range = 'TBD';
    if ( ! empty( $dress_rows ) ) {
        $dress_start = $dress_rows[0][4] ?? '';
        $dress_end   = $dress_rows[ count( $dress_rows ) - 1 ][4] ?? '';
        if ( $dress_start && $dress_end ) {
            $dress_range = tlt_cb_fmt_date( $dress_start, false ) . ' - ' . tlt_cb_fmt_date( $dress_end, true );
        }
    }

    $replacements = [
        '<<ShowName>>'             => $show,
        '<<RunDates>>'             => $run_dates,
        '<<SeasonLong>>'           => $season_long,
        '<<DesignerRunDate>>'      => tlt_cb_fmt_date( $get_date( 'Designer Run' ) ),
        '<<DesignerRunTime>>'      => $time_range( 'Designer Run' ),
        '<<DryTechDate>>'          => tlt_cb_fmt_date( $get_date( 'Dry Tech' ) ),
        '<<DryTechTime>>'          => $time_range( 'Dry Tech' ),
        '<<CueToCueLabel>>'        => $c2c_label,
        '<<CueToCueDate>>'         => tlt_cb_fmt_date( $c2c_date_raw ),
        '<<CueToCueTime>>'         => $time_range( 'Cue to Cue' ),
        '<<TechRunLabel>>'         => $c2c_same_day ? '' : 'Tech Run',
        '<<TechRunDate>>'          => $c2c_same_day ? '' : tlt_cb_fmt_date( $tr_date_raw ),
        '<<TechRunTime>>'          => $c2c_same_day ? '' : $time_range( 'Tech Run' ),
        '<<DressRehearsalDates>>'  => $dress_range,
        '<<ProductionMeeting1>>'   => tlt_cb_fmt_date( $get_date( 'Production Meeting 1' ) ),
        '<<ProductionMeeting2>>'   => tlt_cb_fmt_date( $get_date( 'Production Meeting 2' ) ),
        '<<ProductionMeeting3>>'   => tlt_cb_fmt_date( $get_date( 'Production Meeting 3' ) ),
        '<<ProductionMeetingTime1>>' => $time_range( 'Production Meeting 1' ),
        '<<ProductionMeetingTime2>>' => $time_range( 'Production Meeting 2' ),
        '<<ProductionMeetingTime3>>' => $time_range( 'Production Meeting 3' ),
        '<<DesignPacket1Date>>'    => tlt_cb_fmt_date( $get_date( 'Design Packet 1' ) ),
        '<<DesignPacket2Date>>'    => tlt_cb_fmt_date( $get_date( 'Design Packet 2' ) ),
        '<<CuesInCuelistDate>>'    => tlt_cb_fmt_date( $get_date( 'Cues in Cuelist' ) ),
        '<<CuesProgrammedDate>>'   => tlt_cb_fmt_date( $get_date( 'Cues Programmed' ) ),
        '<<CostumeParadeDate>>'    => tlt_cb_fmt_date( $get_date( 'Costume/Prop Parade/Headshots' ) ),
        '<<QuickChangeDate>>'      => tlt_cb_fmt_date( $get_date( 'Quick change costumes / final props' ) ),
        '<<FinalCostumesDate>>'    => tlt_cb_fmt_date( $get_date( 'Final Costumes Due' ) ),
        '<<SetDressingDate>>'      => tlt_cb_fmt_date( $get_date( 'Set Dressing Load in' ) ),
    ];

    return [
        'replacements'  => $replacements,
        'c2c_same_day'  => $c2c_same_day,
        'season_long'   => $season_long,
        'season_row_num' => $season_row_num,
    ];
}

/**
 * Delete the Tech Run row from the template's schedule table when Cue to Cue
 * and Tech Run fall on the same day. Finds the row containing the
 * "<<TechRunLabel>>" placeholder and deletes it via Docs API deleteTableRow.
 */
function tlt_cb_tech_schedule_delete_tech_run_row( $doc_id ) {
    $doc = tlt_cb_docs_get( $doc_id );
    if ( is_wp_error( $doc ) ) return $doc;
    foreach ( ( $doc['body']['content'] ?? [] ) as $el ) {
        if ( empty( $el['table'] ) ) continue;
        $table_start = $el['startIndex'] ?? null;
        if ( $table_start === null ) continue;
        $rows = $el['table']['tableRows'] ?? [];
        foreach ( $rows as $ri => $row ) {
            $row_text = '';
            foreach ( ( $row['tableCells'] ?? [] ) as $cell ) {
                foreach ( ( $cell['content'] ?? [] ) as $item ) {
                    foreach ( ( $item['paragraph']['elements'] ?? [] ) as $pe ) {
                        $row_text .= $pe['textRun']['content'] ?? '';
                    }
                }
            }
            if ( strpos( $row_text, '<<TechRunLabel>>' ) !== false ) {
                return tlt_cb_docs_batch_update( $doc_id, [
                    [ 'deleteTableRow' => [
                        'tableCellLocation' => [
                            'tableStartLocation' => [ 'index' => $table_start ],
                            'rowIndex'           => $ri,
                            'columnIndex'        => 0,
                        ],
                    ] ],
                ] );
            }
        }
    }
    return true;
}

/**
 * Public entry point for /tech-schedule-generate.  Deletes existing docs with
 * the same name (mimics GAS "delete then regenerate" behavior — GAS didn't
 * have a separate view/regen split for tech schedules), copies template,
 * conditionally deletes Tech Run row, replaces tags, caches URL in Season N.
 */
function tlt_cb_tech_schedule_generate( $show ) {
    $data = tlt_cb_tech_schedule_assemble( $show );
    if ( is_wp_error( $data ) ) return $data;
    if ( $data['season_long'] === '' ) return new WP_Error( 'no_season_long', 'Season "Current Season Long" is empty.' );

    $doc_name = $show . ' - ' . $data['season_long'] . ' Tech Schedule';

    // Delete any existing (matches GAS behavior of always regenerating fresh).
    $existing = tlt_cb_drive_find_in_folder( TLT_CALLBOARD_TS_FOLDER_ID, $doc_name );
    if ( is_wp_error( $existing ) ) return $existing;
    foreach ( $existing as $f ) tlt_cb_drive_trash( $f['id'] );

    $file = tlt_cb_drive_copy( TLT_CALLBOARD_TS_TEMPLATE_ID, TLT_CALLBOARD_TS_FOLDER_ID, $doc_name );
    if ( is_wp_error( $file ) ) return $file;
    $doc_id = $file['id'];

    // If C2C and Tech Run same-day, drop the Tech Run row BEFORE substituting
    // — otherwise the deleted row would just show up as blank.
    if ( $data['c2c_same_day'] ) {
        $r = tlt_cb_tech_schedule_delete_tech_run_row( $doc_id );
        if ( is_wp_error( $r ) ) return $r;
    }

    // Build one big batchUpdate with all replaceAllText requests.
    $requests = [];
    foreach ( $data['replacements'] as $tag => $value ) {
        $requests[] = [
            'replaceAllText' => [
                'containsText' => [ 'text' => $tag, 'matchCase' => true ],
                'replaceText'  => (string) $value,
            ],
        ];
    }
    $r = tlt_cb_docs_batch_update( $doc_id, $requests );
    if ( is_wp_error( $r ) ) return $r;

    $url = tlt_cb_doc_url( $doc_id );
    // Season col N = 14 (1-based) is Tech Schedule cache.
    if ( $data['season_row_num'] > 0 ) {
        tlt_callboard_sheets_write( TLT_CALLBOARD_SHEET_ID, "Season!N{$data['season_row_num']}", [[ $url ]] );
    }

    // Auto-refresh the show-folder PDF if one was previously distributed.
    tlt_cb_generator_refresh_show_pdf( $show, $doc_id, $doc_name . '.pdf' );

    return [ 'url' => $url ];
}

/* -----  Endpoint  --------------------------------------------------------- */

/**
 * POST /tech-schedule-generate  { show }
 * Always regenerates (mirrors GAS default). Trashes prior and returns { url }.
 */
function tlt_callboard_ep_tech_schedule_generate( WP_REST_Request $req ) {
    $body = $req->get_json_params();
    $show = tlt_cb_s( is_array( $body ) ? ( $body['show'] ?? '' ) : '' );
    if ( $show === '' ) return new WP_Error( 'missing_show', 'show is required', [ 'status' => 400 ] );
    $r = tlt_cb_tech_schedule_generate( $show );
    if ( is_wp_error( $r ) ) return $r;
    return tlt_cb_ok( $r );
}

/* ===========================================================================
 * ============  BIOS DOC COMPILATION  =======================================
 *
 * Port of BiosManager.js compileBiosDoc(). Creates a fresh Doc (no template),
 * moves it into a per-season subfolder under BIOS_FOLDER_ID, and lays out
 * Production Team + Cast sections with per-person bio paragraphs.
 * ======================================================================== */

/**
 * Static role → bio-type mapping. Anything not listed defaults to 'designer'.
 * Matches BiosManager.js line 122-132.
 */
function tlt_cb_bios_role_to_bio_type( $role ) {
    static $map = [
        'Director'            => 'director',
        'Choreographer'       => 'director',
        'Fight Choreographer' => 'director',
        'Music Director'      => 'director',
        'Intimacy Director'   => 'director',
        'Lighting Designer'   => 'designer',
        'Sound Designer'      => 'designer',
        'Scenic Designer'     => 'designer',
        'Costume Designer'    => 'designer',
        'Properties Manager'  => 'designer',
        'Scenic Artist'       => 'designer',
        'Stage Manager'       => 'designer',
        'Assistant Stage Manager' => 'designer',
        'Dialect Coach'       => 'designer',
        'Dramaturg'           => 'designer',
    ];
    return $map[ $role ] ?? 'designer';
}

/**
 * Compile bios data for a show. Returns:
 *  { team_entries: [...], actor_entries: [...], season_long: string, season_row_num: int }
 * Each entry: { firstName, middleName, lastName, suffix, role, bioText }
 * Only entries with a non-empty bioText are included.
 */
function tlt_cb_bios_assemble( $show ) {
    $ranges = tlt_callboard_sheets_get( TLT_CALLBOARD_SHEET_ID, [
        'Season!A1:N',
        'Production Teams!A2:F',
        'Actors!A2:F',
    ] );
    if ( is_wp_error( $ranges ) ) return $ranges;
    $season_rows = $ranges['Season!A1:N']            ?? [];
    $team_rows   = $ranges['Production Teams!A2:F'] ?? [];
    $actor_rows  = $ranges['Actors!A2:F']            ?? [];

    $season_long   = tlt_cb_season_setting( $season_rows, 'Current Season Long' );
    $season_row_num = 0;
    foreach ( $season_rows as $i => $r ) {
        if ( tlt_cb_s( $r[1] ?? '' ) === $show ) { $season_row_num = $i + 1; break; }
    }

    // Contactbook data — separate spreadsheet.
    $cb_ranges = tlt_callboard_sheets_get( TLT_CALLBOARD_CONTACTBOOK_ID, [
        'Contactbook!A2:H',
        'Bios!A2:F',
    ] );
    if ( is_wp_error( $cb_ranges ) ) return $cb_ranges;
    $cb_rows   = $cb_ranges['Contactbook!A2:H'] ?? [];
    $bios_rows = $cb_ranges['Bios!A2:F']        ?? [];

    // Name → contactId lookup (first+last, case-insensitive).
    $contact_id_lookup = [];
    foreach ( $cb_rows as $r ) {
        $first = strtolower( tlt_cb_s( $r[1] ?? '' ) );
        $last  = strtolower( tlt_cb_s( $r[3] ?? '' ) );
        if ( $first === '' ) continue;
        $key = $first . '|' . $last;
        if ( ! isset( $contact_id_lookup[ $key ] ) ) $contact_id_lookup[ $key ] = tlt_cb_s( $r[0] ?? '' );
    }

    // ContactId → bio row lookup.
    $bio_row_by_id = [];
    foreach ( $bios_rows as $r ) {
        $id = tlt_cb_s( $r[0] ?? '' );
        if ( $id !== '' && ! isset( $bio_row_by_id[ $id ] ) ) $bio_row_by_id[ $id ] = $r;
    }
    $bio_col_by_type = [ 'actor' => 1, 'director' => 3, 'designer' => 5 ];

    $get_bio_text = function ( $first, $last, $bio_type ) use ( $contact_id_lookup, $bio_row_by_id, $bio_col_by_type ) {
        $key = strtolower( $first ) . '|' . strtolower( $last );
        $id  = $contact_id_lookup[ $key ] ?? '';
        if ( $id === '' || ! isset( $bio_row_by_id[ $id ] ) ) return '';
        $col = $bio_col_by_type[ $bio_type ] ?? null;
        if ( $col === null ) return '';
        return tlt_cb_s( $bio_row_by_id[ $id ][ $col ] ?? '' );
    };

    // Production team — merge duplicate rows by lastname|firstname.
    $team_map = [];
    foreach ( $team_rows as $r ) {
        if ( tlt_cb_s( $r[0] ?? '' ) !== $show ) continue;
        $first = tlt_cb_s( $r[2] ?? '' );
        if ( $first === '' ) continue;
        $last  = tlt_cb_s( $r[4] ?? '' );
        $mid   = tlt_cb_s( $r[3] ?? '' );
        $suf   = tlt_cb_s( $r[5] ?? '' );
        $role  = tlt_cb_s( $r[1] ?? '' );
        $bio_type = tlt_cb_bios_role_to_bio_type( $role );
        $key   = strtolower( $last ) . '|' . strtolower( $first );
        if ( isset( $team_map[ $key ] ) ) {
            $team_map[ $key ]['role'] .= ' / ' . $role;
            if ( $bio_type === 'director' ) $team_map[ $key ]['bio_type'] = 'director';
        } else {
            $team_map[ $key ] = [
                'firstName' => $first,
                'middleName' => $mid,
                'lastName'  => $last,
                'suffix'    => $suf,
                'role'      => $role,
                'bio_type'  => $bio_type,
            ];
        }
    }

    $team_entries = [];
    foreach ( $team_map as $e ) {
        $bio = $get_bio_text( $e['firstName'], $e['lastName'], $e['bio_type'] );
        if ( $bio === '' ) continue;
        $team_entries[] = [
            'firstName'  => $e['firstName'],
            'middleName' => $e['middleName'],
            'lastName'   => $e['lastName'],
            'suffix'     => $e['suffix'],
            'role'       => $e['role'],
            'bioText'    => $bio,
        ];
    }
    usort( $team_entries, function ( $a, $b ) { return strcmp( $a['lastName'], $b['lastName'] ); } );

    // Actors — Actors sheet col A=show, B=character, C=first, D=middle, E=last, F=suffix.
    $actor_entries = [];
    foreach ( $actor_rows as $r ) {
        if ( tlt_cb_s( $r[0] ?? '' ) !== $show ) continue;
        $first = tlt_cb_s( $r[2] ?? '' );
        if ( $first === '' ) continue;
        $last  = tlt_cb_s( $r[4] ?? '' );
        $mid   = tlt_cb_s( $r[3] ?? '' );
        $char  = tlt_cb_s( $r[1] ?? '' );
        $bio   = $get_bio_text( $first, $last, 'actor' );
        if ( $bio === '' ) continue;
        $actor_entries[] = [
            'firstName'  => $first,
            'middleName' => $mid,
            'lastName'   => $last,
            'suffix'     => '',
            'role'       => $char,
            'bioText'    => $bio,
        ];
    }
    usort( $actor_entries, function ( $a, $b ) { return strcmp( $a['lastName'], $b['lastName'] ); } );

    return [
        'team_entries'   => $team_entries,
        'actor_entries'  => $actor_entries,
        'season_long'    => $season_long,
        'season_row_num' => $season_row_num,
    ];
}

/**
 * Build the bio doc content into a freshly-created Docs document.
 * Inserts everything at the top and tracks running end index.
 */
function tlt_cb_bios_build_doc( $doc_id, $show, $season_long, array $team_entries, array $actor_entries ) {
    // ------ Phase 1: margins + title + subtitle + section headers -------
    // Layout (top-to-bottom):
    //   title    : "SHOW — Bios" (font 14 bold centered)
    //   subtitle : season long (font 11 centered, 16pt after)
    //   "Production Team" (font 11 bold, 18pt before if team empty=false, 6pt after)
    //     For each entry:
    //       "First Middle Last Suffix (Role)" (font 10, 10pt before between entries)
    //       bioText (font 10, 2pt before)
    //   "Cast" (font 11 bold, 18pt before if team empty=false, 6pt after)
    //     Same per-entry pattern.

    $lines = [];

    // Compose flat line list with per-line style hints.
    $push = function ( $text, $style = [] ) use ( &$lines ) {
        $lines[] = [ 'text' => $text, 'style' => $style ];
    };

    $push( $show . ' Bios', [
        'font' => 14, 'bold' => true, 'align' => 'CENTER', 'after' => 4,
    ] );
    $push( $season_long, [
        'font' => 11, 'bold' => false, 'align' => 'CENTER', 'after' => 16,
    ] );

    if ( ! empty( $team_entries ) ) {
        $push( 'Production Team', [
            'font' => 11, 'bold' => true, 'align' => 'START', 'before' => 0, 'after' => 6,
        ] );
        foreach ( $team_entries as $i => $e ) {
            $name_parts = array_filter( [ $e['firstName'], $e['middleName'], $e['lastName'], $e['suffix'] ], function ( $x ) { return $x !== ''; } );
            $full_name  = implode( ' ', $name_parts ) . ( $e['role'] !== '' ? ' (' . $e['role'] . ')' : '' );
            $push( $full_name, [ 'font' => 10, 'bold' => false, 'before' => $i === 0 ? 0 : 10 ] );
            $push( $e['bioText'], [ 'font' => 10, 'bold' => false, 'italic' => false, 'before' => 2 ] );
        }
    }

    if ( ! empty( $actor_entries ) ) {
        $before = ! empty( $team_entries ) ? 18 : 0;
        $push( 'Cast', [ 'font' => 11, 'bold' => true, 'align' => 'START', 'before' => $before, 'after' => 6 ] );
        foreach ( $actor_entries as $i => $e ) {
            $name_parts = array_filter( [ $e['firstName'], $e['middleName'], $e['lastName'], $e['suffix'] ], function ( $x ) { return $x !== ''; } );
            $full_name  = implode( ' ', $name_parts ) . ( $e['role'] !== '' ? ' (' . $e['role'] . ')' : '' );
            $push( $full_name, [ 'font' => 10, 'bold' => false, 'before' => $i === 0 ? 0 : 10 ] );
            $push( $e['bioText'], [ 'font' => 10, 'bold' => false, 'italic' => false, 'before' => 2 ] );
        }
    }

    // Build reverse-order insert requests (each inserts at index 1).
    $insert_reqs = [];
    for ( $i = count( $lines ) - 1; $i >= 0; $i-- ) {
        $insert_reqs[] = [ 'insertText' => [ 'location' => [ 'index' => 1 ], 'text' => $lines[ $i ]['text'] . "\n" ] ];
    }

    // Compute line ranges after all inserts settled. Docs API uses UTF-16
    // code units for indexing, so use mb_strlen (chars) not strlen (bytes).
    $ranges = [];
    $cursor = 1;
    foreach ( $lines as $line ) {
        $len = mb_strlen( $line['text'], 'UTF-8' );
        $ranges[] = [ 'text_start' => $cursor, 'text_end' => $cursor + $len, 'para_end' => $cursor + $len + 1 ];
        $cursor += $len + 1;
    }

    // Margins: top/bottom 36pt, left/right 54pt (matches GAS).
    $requests = [
        [ 'updateDocumentStyle' => [
            'documentStyle' => [
                'marginTop'    => [ 'magnitude' => 36, 'unit' => 'PT' ],
                'marginBottom' => [ 'magnitude' => 36, 'unit' => 'PT' ],
                'marginLeft'   => [ 'magnitude' => 54, 'unit' => 'PT' ],
                'marginRight'  => [ 'magnitude' => 54, 'unit' => 'PT' ],
            ],
            'fields' => 'marginTop,marginBottom,marginLeft,marginRight',
        ] ],
    ];
    $requests = array_merge( $requests, $insert_reqs );

    // Style each line.
    foreach ( $lines as $i => $line ) {
        $s     = $ranges[ $i ]['text_start'];
        $e     = $ranges[ $i ]['text_end'];
        $pe    = $ranges[ $i ]['para_end'];
        $style = $line['style'];

        if ( $e > $s ) {
            $text_style = [];
            $text_fields = [];
            if ( isset( $style['font'] ) )   { $text_style['fontSize'] = [ 'magnitude' => $style['font'], 'unit' => 'PT' ]; $text_fields[] = 'fontSize'; }
            if ( isset( $style['bold'] ) )   { $text_style['bold']     = (bool) $style['bold']; $text_fields[] = 'bold'; }
            if ( isset( $style['italic'] ) ) { $text_style['italic']   = (bool) $style['italic']; $text_fields[] = 'italic'; }
            if ( ! empty( $text_style ) ) {
                $requests[] = [ 'updateTextStyle' => [
                    'range' => tlt_cb_docs_range( $s, $e ),
                    'textStyle' => $text_style,
                    'fields' => implode( ',', $text_fields ),
                ] ];
            }
        }

        $para_style  = [];
        $para_fields = [];
        if ( isset( $style['align'] ) )  { $para_style['alignment']  = $style['align'];                            $para_fields[] = 'alignment'; }
        if ( isset( $style['before'] ) ) { $para_style['spaceAbove'] = [ 'magnitude' => (int) $style['before'], 'unit' => 'PT' ]; $para_fields[] = 'spaceAbove'; }
        if ( isset( $style['after'] ) )  { $para_style['spaceBelow'] = [ 'magnitude' => (int) $style['after'],  'unit' => 'PT' ]; $para_fields[] = 'spaceBelow'; }
        if ( ! empty( $para_style ) ) {
            $requests[] = [ 'updateParagraphStyle' => [
                'range' => tlt_cb_docs_range( $s, $pe ),
                'paragraphStyle' => $para_style,
                'fields' => implode( ',', $para_fields ),
            ] ];
        }
    }

    return tlt_cb_docs_batch_update( $doc_id, $requests );
}

/**
 * Entry point: assemble → find/create per-season subfolder → trash existing
 * doc with same name → create new doc → move to folder → build content →
 * cache URL in Season col L (12).
 */
function tlt_cb_bios_doc_compile( $show ) {
    $data = tlt_cb_bios_assemble( $show );
    if ( is_wp_error( $data ) ) return $data;
    if ( $data['season_long'] === '' ) return new WP_Error( 'no_season_long', 'Season "Current Season Long" is empty.' );
    if ( empty( $data['team_entries'] ) && empty( $data['actor_entries'] ) ) {
        return new WP_Error( 'no_bios', 'No submitted bios found for ' . $show . '.' );
    }

    $season_folder_id = tlt_cb_drive_folder_or_create( TLT_CALLBOARD_BIOS_FOLDER_ID, $data['season_long'] );
    if ( is_wp_error( $season_folder_id ) ) return $season_folder_id;

    $doc_name = $show . ' ' . $data['season_long'] . ' Bios';
    $existing = tlt_cb_drive_find_in_folder( $season_folder_id, $doc_name );
    if ( is_wp_error( $existing ) ) return $existing;
    foreach ( $existing as $f ) tlt_cb_drive_trash( $f['id'] );

    $doc_id = tlt_cb_docs_create( $doc_name, $season_folder_id );
    if ( is_wp_error( $doc_id ) ) return $doc_id;

    $r = tlt_cb_bios_build_doc( $doc_id, $show, $data['season_long'], $data['team_entries'], $data['actor_entries'] );
    if ( is_wp_error( $r ) ) return $r;

    $url = tlt_cb_doc_url( $doc_id );
    if ( $data['season_row_num'] > 0 ) {
        tlt_callboard_sheets_write( TLT_CALLBOARD_SHEET_ID, "Season!L{$data['season_row_num']}", [[ $url ]] );
    }
    $count = count( $data['team_entries'] ) + count( $data['actor_entries'] );
    return [ 'success' => true, 'docUrl' => $url, 'url' => $url, 'count' => $count ];
}

/* ===========================================================================
 * ============  PROGRAM EXPORT  =============================================
 *
 * Port of ProgramExport.js.  Assembles the full "program bundle" JSON that
 * the InDesign build script consumes, and (optionally) writes it to Drive
 * so it can be downloaded via drive.google.com/uc?export=download&id=…
 * ======================================================================== */

if ( ! defined( 'TLT_CALLBOARD_PROGRAM_EXPORTS_PARENT_ID' ) ) {
    // Default parent for the "TLT Program Exports" subfolder. Override in
    // wp-config.php if Blake wants it in a different Drive location — must
    // be shared with the SA email.
    define( 'TLT_CALLBOARD_PROGRAM_EXPORTS_PARENT_ID', TLT_CALLBOARD_CS_FOLDER_ID );
}

/**
 * Build the same shape as GAS getProgramData(show) — see ProgramExport.js
 * lines 250-275. Consumed by /program (rich version) and /program-export.
 */
function tlt_cb_program_get_data( $show ) {
    $ranges = tlt_callboard_sheets_get( TLT_CALLBOARD_SHEET_ID, [
        'Season!A1:N',
        "'Production Teams'!A2:F",
        'Actors!A2:F',
        'Theatre!A2:D200',
        'Dates!A2:H',
        'Programs!A1:Z',
        // Show Titles is optional — a sheet that may or may not exist. Guarded below.
    ] );
    if ( is_wp_error( $ranges ) ) return $ranges;
    $season_rows = $ranges['Season!A1:N']              ?? [];
    $team_rows   = $ranges["'Production Teams'!A2:F"] ?? [];
    $actor_rows  = $ranges['Actors!A2:F']              ?? [];
    $theatre_rows = $ranges['Theatre!A2:D200']         ?? [];
    $dates_rows  = $ranges['Dates!A2:H']               ?? [];
    $prog_rows   = $ranges['Programs!A1:Z']            ?? [];

    // Contactbook data for bios.
    $cb_ranges = tlt_callboard_sheets_get( TLT_CALLBOARD_CONTACTBOOK_ID, [
        'Contactbook!A2:H',
        'Bios!A2:F',
    ] );
    if ( is_wp_error( $cb_ranges ) ) return $cb_ranges;
    $cb_rows   = $cb_ranges['Contactbook!A2:H'] ?? [];
    $bios_rows = $cb_ranges['Bios!A2:F']        ?? [];

    // --- Programs tab (per-show editable fields) ---
    // Column layout: A=Show B=Author C=Legal D=Act1 E=Act2 F=Intermission G=Place H=Special Thanks
    $prog_row = [];
    foreach ( array_slice( $prog_rows, 1 ) as $r ) {
        if ( tlt_cb_s( $r[0] ?? '' ) === $show ) { $prog_row = $r; break; }
    }
    $fields = [
        'author'        => tlt_cb_s( $prog_row[1] ?? '' ),
        'legal'         => tlt_cb_s( $prog_row[2] ?? '' ),
        'a1'            => tlt_cb_s( $prog_row[3] ?? '' ),
        'a2'            => tlt_cb_s( $prog_row[4] ?? '' ),
        'intermission'  => tlt_cb_s( $prog_row[5] ?? '' ),
        'place'         => tlt_cb_s( $prog_row[6] ?? '' ),
        'specialThanks' => tlt_cb_s( $prog_row[7] ?? '' ),
    ];

    // --- Director ---
    $director = '';
    foreach ( $team_rows as $r ) {
        if ( tlt_cb_s( $r[0] ?? '' ) !== $show ) continue;
        if ( tlt_cb_s( $r[1] ?? '' ) === 'Director' ) {
            $parts = array_filter( [
                tlt_cb_s( $r[2] ?? '' ), // first
                tlt_cb_s( $r[3] ?? '' ), // middle
                tlt_cb_s( $r[4] ?? '' ), // last
                tlt_cb_s( $r[5] ?? '' ), // suffix
            ], function ( $x ) { return $x !== ''; } );
            $director = trim( preg_replace( '/\s+/', ' ', implode( ' ', $parts ) ) );
            break;
        }
    }

    // --- Run (first → last Performance in Dates) ---
    $perf_dts = [];
    foreach ( $dates_rows as $r ) {
        if ( tlt_cb_s( $r[0] ?? '' ) !== $show ) continue;
        if ( strpos( tlt_cb_s( $r[1] ?? '' ), 'Performance' ) === false ) continue;
        $dt = tlt_cb_parse_date( $r[4] ?? '' );
        if ( $dt ) $perf_dts[] = $dt;
    }
    usort( $perf_dts, function ( $a, $b ) { return $a->getTimestamp() <=> $b->getTimestamp(); } );
    $fmt = function ( $dt ) { return $dt->format( 'F j, Y' ); };
    $run = '';
    if ( count( $perf_dts ) > 0 ) {
        $first = $perf_dts[0]; $last = end( $perf_dts );
        $run = $first->getTimestamp() === $last->getTimestamp() ? $fmt( $first ) : $fmt( $first ) . ' – ' . $fmt( $last );
    }

    // --- Staff (Theatre tab, only rows with numeric order) ---
    $staff = [];
    foreach ( $theatre_rows as $r ) {
        $order_raw = $r[3] ?? '';
        if ( $order_raw === '' || ! is_numeric( $order_raw ) ) continue;
        $role = tlt_cb_s( $r[0] ?? '' );
        if ( $role === '' ) continue;
        $staff[] = [
            'order' => (int) $order_raw,
            'role'  => $role,
            'name'  => tlt_cb_s( $r[1] ?? '' ),
        ];
    }
    usort( $staff, function ( $a, $b ) { return $a['order'] <=> $b['order']; } );

    // --- Production team (this show's roster, sheet order preserved) ---
    $team = [];
    foreach ( $team_rows as $r ) {
        if ( tlt_cb_s( $r[0] ?? '' ) !== $show ) continue;
        $role = tlt_cb_s( $r[1] ?? '' );
        if ( $role === '' || $role === 'Role' ) continue;
        $first = tlt_cb_s( $r[2] ?? '' );
        $last  = tlt_cb_s( $r[4] ?? '' );
        if ( $first === '' && $last === '' ) continue;
        $parts = array_filter( [ $first, tlt_cb_s( $r[3] ?? '' ), $last, tlt_cb_s( $r[5] ?? '' ) ], function ( $x ) { return $x !== ''; } );
        $team[] = [ 'role' => $role, 'name' => implode( ' ', $parts ) ];
    }
    // Append fixed Theatre roles.
    foreach ( [ 'Lead Carpenter', 'Shop Technician', 'Photography' ] as $role ) {
        foreach ( $theatre_rows as $r ) {
            if ( tlt_cb_s( $r[0] ?? '' ) === $role ) {
                $name = tlt_cb_s( $r[1] ?? '' );
                if ( $name !== '' ) $team[] = [ 'role' => $role, 'name' => $name ];
                break;
            }
        }
    }

    // --- Bios (data version of compileBiosDoc, keeps sheet order) ---
    $contact_id_lookup = [];
    foreach ( $cb_rows as $r ) {
        $first = strtolower( tlt_cb_s( $r[1] ?? '' ) );
        $last  = strtolower( tlt_cb_s( $r[3] ?? '' ) );
        if ( $first === '' ) continue;
        $key = $first . '|' . $last;
        if ( ! isset( $contact_id_lookup[ $key ] ) ) $contact_id_lookup[ $key ] = tlt_cb_s( $r[0] ?? '' );
    }
    $bio_row_by_id = [];
    foreach ( $bios_rows as $r ) {
        $id = tlt_cb_s( $r[0] ?? '' );
        if ( $id !== '' && ! isset( $bio_row_by_id[ $id ] ) ) $bio_row_by_id[ $id ] = $r;
    }
    $bio_col_by_type = [ 'actor' => 1, 'director' => 3, 'designer' => 5 ];

    $get_contact_id = function ( $first, $last ) use ( $contact_id_lookup ) {
        return $contact_id_lookup[ strtolower( $first ) . '|' . strtolower( $last ) ] ?? '';
    };
    $get_bio_text = function ( $contact_id, $bio_type ) use ( $bio_row_by_id, $bio_col_by_type ) {
        if ( $contact_id === '' ) return '';
        $col = $bio_col_by_type[ $bio_type ] ?? null;
        if ( $col === null ) return '';
        return tlt_cb_s( $bio_row_by_id[ $contact_id ][ $col ] ?? '' );
    };
    $headshot_name = function ( $first, $last ) {
        $c = function ( $s ) { return preg_replace( '/[^A-Za-z0-9]/', '', $s ); };
        return $c( $last ) . '_' . $c( $first ) . '.jpg';
    };

    // Team bios (merge multiple roles by lastname|firstname). Preserves order
    // of first appearance in Production Teams.
    $team_bio_map = [];
    foreach ( $team_rows as $r ) {
        if ( tlt_cb_s( $r[0] ?? '' ) !== $show ) continue;
        $first = tlt_cb_s( $r[2] ?? '' );
        if ( $first === '' ) continue;
        $last  = tlt_cb_s( $r[4] ?? '' );
        $mid   = tlt_cb_s( $r[3] ?? '' );
        $suf   = tlt_cb_s( $r[5] ?? '' );
        $role  = tlt_cb_s( $r[1] ?? '' );
        $bio_type = tlt_cb_bios_role_to_bio_type( $role );
        $key = strtolower( $last ) . '|' . strtolower( $first );
        if ( isset( $team_bio_map[ $key ] ) ) {
            $team_bio_map[ $key ]['role'] .= ' / ' . $role;
            if ( $bio_type === 'director' ) $team_bio_map[ $key ]['bio_type'] = 'director';
        } else {
            $team_bio_map[ $key ] = [
                'first'    => $first, 'middle' => $mid, 'last' => $last, 'suffix' => $suf,
                'role'     => $role, 'bio_type' => $bio_type,
            ];
        }
    }
    $team_bios = [];
    foreach ( $team_bio_map as $e ) {
        $cid = $get_contact_id( $e['first'], $e['last'] );
        $team_bios[] = [
            'name'      => implode( ' ', array_filter( [ $e['first'], $e['middle'], $e['last'], $e['suffix'] ], function ( $x ) { return $x !== ''; } ) ),
            'role'      => $e['role'],
            'bio'       => $get_bio_text( $cid, $e['bio_type'] ),
            'headshot'  => $headshot_name( $e['first'], $e['last'] ),
            'contactId' => $cid,
        ];
    }

    $cast_bios = [];
    foreach ( $actor_rows as $r ) {
        if ( tlt_cb_s( $r[0] ?? '' ) !== $show ) continue;
        $first = tlt_cb_s( $r[2] ?? '' );
        if ( $first === '' ) continue;
        $last  = tlt_cb_s( $r[4] ?? '' );
        $mid   = tlt_cb_s( $r[3] ?? '' );
        $cid   = $get_contact_id( $first, $last );
        $cast_bios[] = [
            'name'      => implode( ' ', array_filter( [ $first, $mid, $last ], function ( $x ) { return $x !== ''; } ) ),
            'role'      => tlt_cb_s( $r[1] ?? '' ),  // character
            'bio'       => $get_bio_text( $cid, 'actor' ),
            'headshot'  => $headshot_name( $first, $last ),
            'contactId' => $cid,
        ];
    }

    // --- Italicize titles (union of Show Titles tab + ShowList named-range shows) ---
    $italicize = [];
    // Try Show Titles tab (optional).
    $show_titles_range = tlt_callboard_sheets_get( TLT_CALLBOARD_SHEET_ID, [ "'Show Titles'!A1:A" ] );
    if ( ! is_wp_error( $show_titles_range ) ) {
        $skip = [ 'title', 'show', 'shows', 'show title', 'show titles' ];
        foreach ( ( $show_titles_range["'Show Titles'!A1:A"] ?? [] ) as $r ) {
            $t = tlt_cb_s( $r[0] ?? '' );
            if ( $t === '' || in_array( strtolower( $t ), $skip, true ) ) continue;
            $italicize[] = $t;
        }
    }
    // Merge in shows from the Season tab.
    foreach ( $season_rows as $r ) {
        $lbl = tlt_cb_s( $r[0] ?? '' );
        if ( strpos( $lbl, 'Show' ) === 0 && preg_match( '/^Show\d+$/', $lbl ) ) {
            $t = tlt_cb_s( $r[1] ?? '' );
            if ( $t !== '' ) $italicize[] = $t;
        }
    }
    $seen = []; $italicize_dedup = [];
    foreach ( $italicize as $t ) {
        $k = strtolower( $t );
        if ( ! isset( $seen[ $k ] ) ) { $seen[ $k ] = true; $italicize_dedup[] = $t; }
    }

    $season_long = tlt_cb_season_setting( $season_rows, 'Current Season Long' );

    return [
        'show'   => $show,
        'season' => $season_long,
        'info'   => [
            'title'         => $show,
            'author'        => $fields['author'],
            'director'      => $director,
            'legal'         => $fields['legal'],
            'run'           => $run,
            'a1'            => $fields['a1'],
            'a2'            => $fields['a2'],
            'intermission'  => $fields['intermission'],
            'place'         => $fields['place'],
            'specialThanks' => $fields['specialThanks'],
        ],
        'staff'          => $staff,
        'productionTeam' => $team,
        'bios' => [
            'team' => $team_bios,
            'cast' => $cast_bios,
        ],
        'italicizeTitles' => $italicize_dedup,
    ];
}

/**
 * POST /program-export  { show }  →  { id, name, url }
 * Writes JSON to Drive so the frontend can download via ?export=download&id=…
 */
function tlt_callboard_ep_program_export( WP_REST_Request $req ) {
    $body = $req->get_json_params();
    $show = tlt_cb_s( is_array( $body ) ? ( $body['show'] ?? '' ) : '' );
    if ( $show === '' ) return new WP_Error( 'missing_show', 'show is required', [ 'status' => 400 ] );

    $data = tlt_cb_program_get_data( $show );
    if ( is_wp_error( $data ) ) return $data;
    $json = wp_json_encode( $data, JSON_PRETTY_PRINT );

    // Find or create the "TLT Program Exports" subfolder inside the parent.
    $folder_id = tlt_cb_drive_folder_or_create( TLT_CALLBOARD_PROGRAM_EXPORTS_PARENT_ID, 'TLT Program Exports' );
    if ( is_wp_error( $folder_id ) ) return $folder_id;

    $file_name = $show . ' - Program.json';

    // Trash any prior exports with this name.
    $existing = tlt_cb_drive_find_in_folder( $folder_id, $file_name );
    if ( is_wp_error( $existing ) ) return $existing;
    foreach ( $existing as $f ) tlt_cb_drive_trash( $f['id'] );

    // Multipart upload to Drive files.create.
    $token = tlt_callboard_google_access_token();
    if ( is_wp_error( $token ) ) return $token;
    $boundary = 'tltcbboundary' . bin2hex( random_bytes( 6 ) );
    $metadata = wp_json_encode( [
        'name'     => $file_name,
        'mimeType' => 'application/json',
        'parents'  => [ $folder_id ],
    ] );
    $mp_body =
        "--{$boundary}\r\n" .
        "Content-Type: application/json; charset=UTF-8\r\n\r\n" .
        $metadata . "\r\n" .
        "--{$boundary}\r\n" .
        "Content-Type: application/json\r\n\r\n" .
        $json . "\r\n" .
        "--{$boundary}--";

    $resp = wp_remote_post( 'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&supportsAllDrives=true&fields=id,name', [
        'timeout' => 60,
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'  => 'multipart/related; boundary=' . $boundary,
        ],
        'body' => $mp_body,
    ] );
    if ( is_wp_error( $resp ) ) return $resp;
    $body_resp = wp_remote_retrieve_body( $resp );
    $file = json_decode( $body_resp, true );
    if ( empty( $file['id'] ) ) return new WP_Error( 'drive_upload', "Program export upload failed: $body_resp" );

    return tlt_cb_ok( [
        'id'   => $file['id'],
        'name' => $file['name'] ?? $file_name,
        'url'  => 'https://drive.google.com/uc?export=download&id=' . $file['id'],
    ] );
}

/* ===========================================================================
 * ============  MAIL HELPER (Resend)  =======================================
 *
 * All outbound email — bio requests, contract send notifications, resends —
 * goes through Resend's REST API. Blake must set TLT_CALLBOARD_RESEND_KEY in
 * wp-config.php. If unset, the send functions return a clear WP_Error.
 * ======================================================================== */

/**
 * @param string|array $to    recipient(s) — string or list
 * @param string       $subject
 * @param string       $html
 * @param array        $opts { text?, replyTo?, bcc?, from? }
 * @return array|WP_Error  Resend response body decoded
 */
function tlt_cb_send_mail( $to, $subject, $html, array $opts = [] ) {
    if ( ! defined( 'TLT_CALLBOARD_RESEND_KEY' ) || TLT_CALLBOARD_RESEND_KEY === '' ) {
        return new WP_Error( 'mail_not_configured',
            'Outbound email is not configured. Add `define( "TLT_CALLBOARD_RESEND_KEY", "re_..." );` to wp-config.php.' );
    }
    $from     = $opts['from']    ?? TLT_CALLBOARD_MAIL_FROM;
    $reply_to = $opts['replyTo'] ?? TLT_CALLBOARD_MAIL_REPLY_TO;
    $bcc      = $opts['bcc']     ?? TLT_CALLBOARD_MAIL_BCC;
    $text     = $opts['text']    ?? tlt_cb_html_to_text( $html );

    $payload = [
        'from'    => $from,
        'to'      => is_array( $to ) ? $to : [ (string) $to ],
        'subject' => $subject,
        'html'    => $html,
        'text'    => $text,
    ];
    if ( $reply_to !== '' ) $payload['reply_to'] = $reply_to;
    if ( $bcc !== '' )       $payload['bcc']      = is_array( $bcc ) ? $bcc : [ $bcc ];

    $resp = wp_remote_post( TLT_CALLBOARD_RESEND_URL, [
        'timeout' => 30,
        'headers' => [
            'Authorization' => 'Bearer ' . TLT_CALLBOARD_RESEND_KEY,
            'Content-Type'  => 'application/json',
        ],
        'body' => wp_json_encode( $payload ),
    ] );
    if ( is_wp_error( $resp ) ) return $resp;
    $code = wp_remote_retrieve_response_code( $resp );
    $body = wp_remote_retrieve_body( $resp );
    if ( $code < 200 || $code >= 300 ) {
        return new WP_Error( 'mail_send_http', "Resend returned $code: $body" );
    }
    return json_decode( $body, true );
}

/**
 * Cheap HTML → text fallback for the text part of outbound emails.
 */
function tlt_cb_html_to_text( $html ) {
    $html = preg_replace( '#<br\s*/?>#i', "\n", $html );
    $html = preg_replace( '#</p>#i', "\n\n", $html );
    $html = preg_replace( '#</h[1-6]>#i', "\n\n", $html );
    $text = wp_strip_all_tags( $html, true );
    return trim( html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
}

/**
 * Emergency Info URL derivation matches GAS (BIO_APP_URL rooted variant).
 * If the "Emergency Info Url" Season setting isn't set, derives one from
 * BIO_APP_URL by trimming ?query, trailing /index/index.html, then appending
 * /emergency-info.
 */
function tlt_cb_emergency_info_base( $season_rows ) {
    $emergency_url = tlt_cb_season_setting( $season_rows, 'Emergency Info Url' );
    if ( $emergency_url !== '' ) return $emergency_url;
    $bio_app_url = tlt_cb_season_setting( $season_rows, 'Bio App Url' );
    if ( $bio_app_url === '' ) return '';
    $base = preg_replace( '/\?.*$/', '', $bio_app_url );
    $base = preg_replace( '#/?index(\.html)?/?$#', '', $base );
    return rtrim( $base, '/' ) . '/emergency-info';
}

/**
 * Generate or fetch a Contactbook Bio Token. If the contact row has an empty
 * col K (index 10), a new token is written along with col L (Token Sent Date).
 *
 * Returns [ 'token' => '...', 'contactbook_row_num' => int, 'primary_email' => '...' ]
 * or WP_Error.
 *
 * $seed is used only for the SHA input — usually the email or contact ID so
 * the same person gets a stable token per generation attempt.
 */
function tlt_cb_ensure_bio_token( $first_name, $last_name, $seed ) {
    $cb_rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_CONTACTBOOK_ID, 'Contactbook!A2:O' );
    if ( is_wp_error( $cb_rows ) ) return $cb_rows;
    $target_row_num = 0;
    $existing_token = '';
    $primary_email  = '';
    $first_lc = strtolower( $first_name );
    $last_lc  = strtolower( $last_name );
    foreach ( $cb_rows as $i => $r ) {
        if ( strtolower( tlt_cb_s( $r[1] ?? '' ) ) === $first_lc &&
             strtolower( tlt_cb_s( $r[3] ?? '' ) ) === $last_lc ) {
            $target_row_num = $i + 2; // sheet is 1-based; header is row 1 (A1); A2 starts at index 0
            $existing_token = tlt_cb_s( $r[10] ?? '' );
            $primary_email  = tlt_cb_s( $r[7]  ?? '' );
            break;
        }
    }
    if ( $target_row_num === 0 ) {
        return new WP_Error( 'no_contactbook_row', "No Contactbook row for {$first_name} {$last_name}." );
    }
    if ( $existing_token !== '' ) {
        return [ 'token' => $existing_token, 'contactbook_row_num' => $target_row_num, 'primary_email' => $primary_email ];
    }
    // Generate SHA-256-based token, alphanumeric, 32 chars.
    $hash  = hash( 'sha256', $seed . microtime( true ) . random_bytes( 8 ), true );
    $b64   = rtrim( strtr( base64_encode( $hash ), '+/', '__' ), '=' );
    $alnum = preg_replace( '/[^A-Za-z0-9]/', '', $b64 );
    $token = substr( $alnum, 0, 32 );
    $today = ( new DateTime( 'now', new DateTimeZone( 'America/Los_Angeles' ) ) )->format( 'Y-m-d' );
    // Write cols K + L on the target row.
    $w1 = tlt_callboard_sheets_write( TLT_CALLBOARD_CONTACTBOOK_ID, "Contactbook!K{$target_row_num}", [[ $token ]] );
    if ( is_wp_error( $w1 ) ) return $w1;
    $w2 = tlt_callboard_sheets_write( TLT_CALLBOARD_CONTACTBOOK_ID, "Contactbook!L{$target_row_num}", [[ $today ]] );
    if ( is_wp_error( $w2 ) ) return $w2;
    return [ 'token' => $token, 'contactbook_row_num' => $target_row_num, 'primary_email' => $primary_email ];
}

/**
 * Build the single-show bio request email HTML. Matches the visual layout of
 * ContractGenerator.js sendBioRequestEmail (contents are the port surface —
 * this is the "welcome to the show" email with bio + emergency info + comp
 * code + SM info + show folder link).
 *
 * $ctx : associative context
 *   - fullName, firstName
 *   - show
 *   - role                (skips show-folder block when === 'Actor')
 *   - season              (short: "26-27")
 *   - bioLink, emergencyLink
 *   - handbookUrl
 *   - showFolderUrl       (may be '')
 *   - smName, smEmail
 *   - compCode, compCode2
 *   - contactEmail        (footer mailto)
 */
function tlt_cb_bio_email_html( array $ctx ) {
    $red = '#a2242a';
    $show     = htmlspecialchars( $ctx['show'] ?? '', ENT_QUOTES );
    $first    = htmlspecialchars( $ctx['firstName'] ?? '', ENT_QUOTES );
    $bio_link_raw = (string) ( $ctx['bioLink'] ?? '' );
    $emg_link_raw = (string) ( $ctx['emergencyLink'] ?? '' );
    $handbook_raw = (string) ( $ctx['handbookUrl'] ?? '' );
    $folder       = trim( (string) ( $ctx['showFolderUrl'] ?? '' ) );
    $bio_link = htmlspecialchars( $bio_link_raw, ENT_QUOTES );
    $emg_link = htmlspecialchars( $emg_link_raw, ENT_QUOTES );
    $handbook = htmlspecialchars( $handbook_raw, ENT_QUOTES );
    $sm_name  = htmlspecialchars( $ctx['smName']  ?? '', ENT_QUOTES );
    $sm_email = htmlspecialchars( $ctx['smEmail'] ?? '', ENT_QUOTES );
    $comp     = htmlspecialchars( $ctx['compCode']  ?? '', ENT_QUOTES );
    $comp2    = htmlspecialchars( $ctx['compCode2'] ?? '', ENT_QUOTES );
    $role     = tlt_cb_s( $ctx['role'] ?? '' );
    $footer_email = htmlspecialchars( $ctx['contactEmail'] ?? 'tlt@tacomalittletheatre.com', ENT_QUOTES );
    $skip_folder = strcasecmp( $role, 'Actor' ) === 0;

    // Centered button.
    $btn = function ( $url, $label ) use ( $red ) {
        if ( $url === '' ) return '';
        return '<div style="text-align:center; margin:14px 0;"><a href="' . $url . '" style="display:inline-block; padding:12px 28px; background:' . $red . '; color:#fff; text-decoration:none; border-radius:4px; font-weight:600; font-size:14px;">' . $label . '</a></div>';
    };
    // Fallback URL block below a button.
    $fallback_url = function ( $url ) {
        if ( $url === '' ) return '';
        $esc = htmlspecialchars( $url, ENT_QUOTES );
        return '<p style="margin:8px 0 0; font-size:12px; color:#888; word-break:break-all; text-align:center;">If the button doesn\'t work, copy and paste:<br><a href="' . $esc . '" style="color:#888;">' . $esc . '</a></p>';
    };
    $section_header = function ( $title ) {
        return '<h3 style="margin:0 0 8px; font-size:16px; font-weight:600; color:#222;">' . htmlspecialchars( $title, ENT_QUOTES ) . '</h3>';
    };
    // Softer divider — light gray hairline.
    $divider = '<div style="height:1px; background:#eee; margin:24px 0;"></div>';

    // Show Drive folder section (skip for actors) — button + fallback URL.
    $show_folder_section = '';
    if ( $folder !== '' && ! $skip_folder ) {
        $folder_esc = htmlspecialchars( $folder, ENT_QUOTES );
        $show_folder_section =
            $divider .
            $section_header( 'Show Drive Folder' ) .
            '<p style="margin:0 0 4px; font-size:14px;">Scripts, design packets, and other production materials for <strong>' . $show . '</strong> live here.</p>' .
            $btn( $folder_esc, 'Open Show Folder' ) .
            $fallback_url( $folder );
    }

    // Stage manager — soft light-red panel, no hard border.
    $sm_section = '';
    if ( $sm_name !== '' || $sm_email !== '' ) {
        $sm_email_link = $sm_email !== ''
            ? '<a href="mailto:' . $sm_email . '" style="color:' . $red . ';">' . $sm_email . '</a>'
            : '';
        $sm_section =
            $divider .
            '<div style="background:#faf3f3; border-radius:6px; padding:14px 18px;">' .
            '<div style="font-size:11px; font-weight:700; letter-spacing:0.8px; color:' . $red . '; text-transform:uppercase; margin-bottom:6px;">Your Stage Manager</div>' .
            '<div style="font-size:14px; color:#222;">' . $sm_name . '</div>' .
            ( $sm_email_link !== '' ? '<div style="font-size:13px; margin-top:2px;">' . $sm_email_link . '</div>' : '' ) .
            '</div>';
    }

    // Comp codes — soft light-gray panel.
    $comp_section = '';
    if ( $comp !== '' || $comp2 !== '' ) {
        $inner = '';
        if ( $comp !== '' ) {
            $inner .= '<div style="margin-bottom:4px; font-size:13px;"><strong>Comp code:</strong> <code style="background:#fff; padding:2px 8px; border-radius:3px; font-family:monospace;">' . $comp . '</code></div>';
        }
        if ( $comp2 !== '' ) {
            $inner .= '<div style="font-size:13px;"><strong>Future-shows comp code:</strong> <code style="background:#fff; padding:2px 8px; border-radius:3px; font-family:monospace;">' . $comp2 . '</code></div>';
        }
        $comp_section =
            '<div style="margin-top:16px; background:#f7f7f7; border-radius:6px; padding:12px 16px;">' . $inner . '</div>';
    }

    return
        '<div style="font-family:Arial,Helvetica,sans-serif; max-width:640px; margin:auto; color:#222; line-height:1.55; background:#fff;">' .
        '<div style="background:' . $red . '; color:#fff; padding:18px 24px; font-size:18px; font-weight:600;">Tacoma Little Theatre</div>' .
        '<div style="padding:24px;">' .

        '<p style="margin:0 0 14px; font-size:14px;"><strong>Hi ' . $first . ',</strong></p>' .
        '<p style="margin:0 0 14px; font-size:14px;">Your contract for <strong>' . $show . '</strong> has been sent for your signature.</p>' .
        '<p style="margin:0 0 14px; font-size:14px;">A couple of things to take care of at your convenience:</p>' .
        '<p style="margin:0 0 14px; font-size:13px; color:#555;"><strong>Note:</strong> If the buttons below don\'t open correctly, try opening them in Firefox, Safari, or a private/incognito window.</p>' .

        $divider .

        // Your Bio section
        $section_header( 'Your Bio' ) .
        '<p style="margin:0 0 6px; font-size:14px;">We need a bio for our programs and promotional materials. Submit or update yours here.</p>' .
        $btn( $bio_link, 'Submit Your Bio' ) .
        $fallback_url( $bio_link_raw ) .
        '<p style="margin:16px 0 8px; font-size:14px;">A few things to know:</p>' .
        '<ul style="margin:0 0 4px 20px; padding:0; font-size:14px; color:#333;">' .
            '<li style="margin:0 0 6px;">If you\'ve submitted a bio before, it will be pre-filled — just confirm or update it.</li>' .
            '<li style="margin:0 0 6px;">If you\'re working in multiple roles this season, you can submit a separate bio for each.</li>' .
            '<li style="margin:0;">Your links are unique to you — please don\'t share them.</li>' .
        '</ul>' .

        $divider .

        // Emergency Info section
        $section_header( 'Emergency Info & Background Check Release' ) .
        '<p style="margin:0 0 6px; font-size:14px;">Please also complete this single form covering emergency contacts, medical info, and a Washington State Patrol background check release. It only needs to be filled out once — if you\'re on multiple shows this season, we\'ll use the same info for all of them.</p>' .
        $btn( $emg_link, 'Submit Emergency Info' ) .
        $fallback_url( $emg_link_raw ) .

        $divider .

        // Handbook section — button + fallback (Blake likes buttons on this one)
        $section_header( 'Cast & Crew Handbook' ) .
        '<p style="margin:0 0 6px; font-size:14px;">Please review our Cast &amp; Crew Handbook — it covers policies, comp codes, and what to expect during the production.</p>' .
        $btn( $handbook, 'Read Handbook' ) .
        $fallback_url( $handbook_raw ) .

        $show_folder_section .
        $sm_section .
        $comp_section .

        '<p style="margin:28px 0 0; font-size:13px; color:#666;">Questions? Reply to this email or reach us at <a href="mailto:' . $footer_email . '" style="color:' . $red . ';">' . $footer_email . '</a>.</p>' .
        '</div></div>';
}

/**
 * Combined-contract version of the bio email. Replaces the per-show blocks
 * with a season reference table (one row per show).
 *
 * $ctx.perShow = [ [ 'show' => '...', 'folder' => '...', 'smName' => '...', 'smEmail' => '...', 'compCode' => '...', 'compCode2' => '...' ], ... ]
 */
function tlt_cb_combined_bio_email_html( array $ctx ) {
    $red   = '#a2242a';
    $first = htmlspecialchars( $ctx['firstName']       ?? '', ENT_QUOTES );
    $shows_display = htmlspecialchars( $ctx['showsDisplay'] ?? '', ENT_QUOTES );
    $bio_link_raw = (string) ( $ctx['bioLink'] ?? '' );
    $emg_link_raw = (string) ( $ctx['emergencyLink'] ?? '' );
    $handbook_raw = (string) ( $ctx['handbookUrl'] ?? '' );
    $bio_link = htmlspecialchars( $bio_link_raw, ENT_QUOTES );
    $emg_link = htmlspecialchars( $emg_link_raw, ENT_QUOTES );
    $handbook = htmlspecialchars( $handbook_raw, ENT_QUOTES );
    $footer_email = htmlspecialchars( $ctx['contactEmail'] ?? 'tlt@tacomalittletheatre.com', ENT_QUOTES );
    $per_show = $ctx['perShow'] ?? [];
    $role     = tlt_cb_s( $ctx['role'] ?? '' );
    $skip_folder = strcasecmp( $role, 'Actor' ) === 0;

    $btn = function ( $url, $label ) use ( $red ) {
        if ( $url === '' ) return '';
        return '<div style="text-align:center; margin:14px 0;"><a href="' . $url . '" style="display:inline-block; padding:12px 28px; background:' . $red . '; color:#fff; text-decoration:none; border-radius:4px; font-weight:600; font-size:14px;">' . $label . '</a></div>';
    };
    $fallback_url = function ( $url ) {
        if ( $url === '' ) return '';
        $esc = htmlspecialchars( $url, ENT_QUOTES );
        return '<p style="margin:8px 0 0; font-size:12px; color:#888; word-break:break-all; text-align:center;">If the button doesn\'t work, copy and paste:<br><a href="' . $esc . '" style="color:#888;">' . $esc . '</a></p>';
    };
    $section_header = function ( $title ) {
        return '<h3 style="margin:0 0 8px; font-size:16px; font-weight:600; color:#222;">' . htmlspecialchars( $title, ENT_QUOTES ) . '</h3>';
    };
    $divider = '<div style="height:1px; background:#eee; margin:24px 0;"></div>';

    // Season reference table — one row per show, softer borders.
    $rows_html = '';
    foreach ( $per_show as $ps ) {
        $show_esc  = htmlspecialchars( $ps['show'] ?? '', ENT_QUOTES );
        $folder    = trim( (string) ( $ps['folder'] ?? '' ) );
        $folder_html = ( $folder !== '' && ! $skip_folder )
            ? '<br><a href="' . htmlspecialchars( $folder, ENT_QUOTES ) . '" style="color:' . $red . '; font-size:12px;">Show folder</a>' : '';
        $sm_name  = htmlspecialchars( $ps['smName']  ?? '', ENT_QUOTES );
        $sm_email = htmlspecialchars( $ps['smEmail'] ?? '', ENT_QUOTES );
        $sm_html  = $sm_name;
        if ( $sm_email !== '' ) $sm_html .= '<br><a href="mailto:' . $sm_email . '" style="color:' . $red . '; font-size:12px;">' . $sm_email . '</a>';
        $comp   = htmlspecialchars( $ps['compCode']  ?? '', ENT_QUOTES );
        $comp2  = htmlspecialchars( $ps['compCode2'] ?? '', ENT_QUOTES );
        $rows_html .=
            '<tr style="border-top:1px solid #eee;">' .
            '<td style="padding:10px 8px; vertical-align:top;"><strong>' . $show_esc . '</strong>' . $folder_html . '</td>' .
            '<td style="padding:10px 8px; vertical-align:top;">' . $sm_html . '</td>' .
            '<td style="padding:10px 8px; vertical-align:top; font-family:monospace;">' . $comp . '</td>' .
            '<td style="padding:10px 8px; vertical-align:top; font-family:monospace;">' . $comp2 . '</td>' .
            '</tr>';
    }

    return
        '<div style="font-family:Arial,Helvetica,sans-serif; max-width:680px; margin:auto; color:#222; line-height:1.55; background:#fff;">' .
        '<div style="background:' . $red . '; color:#fff; padding:18px 24px; font-size:18px; font-weight:600;">Tacoma Little Theatre</div>' .
        '<div style="padding:24px;">' .

        '<p style="margin:0 0 14px; font-size:14px;"><strong>Hi ' . $first . ',</strong></p>' .
        '<p style="margin:0 0 14px; font-size:14px;">Your combined contract covering <strong>' . $shows_display . '</strong> has been sent for your signature. Once signed, the two forms below cover you for every show in the group — you only need to fill each out once.</p>' .
        '<p style="margin:0 0 14px; font-size:13px; color:#555;"><strong>Note:</strong> If the buttons below don\'t open correctly, try opening them in Firefox, Safari, or a private/incognito window.</p>' .

        $divider .

        // Your Bio section
        $section_header( 'Your Bio' ) .
        '<p style="margin:0 0 6px; font-size:14px;">We need a bio for our programs and promotional materials. Submit or update yours here.</p>' .
        $btn( $bio_link, 'Submit Your Bio' ) .
        $fallback_url( $bio_link_raw ) .
        '<p style="margin:16px 0 8px; font-size:14px;">A few things to know:</p>' .
        '<ul style="margin:0 0 4px 20px; padding:0; font-size:14px; color:#333;">' .
            '<li style="margin:0 0 6px;">If you\'ve submitted a bio before, it will be pre-filled — just confirm or update it.</li>' .
            '<li style="margin:0 0 6px;">If you\'re working in multiple roles this season, you can submit a separate bio for each.</li>' .
            '<li style="margin:0;">Your links are unique to you — please don\'t share them.</li>' .
        '</ul>' .

        $divider .

        // Emergency Info section
        $section_header( 'Emergency Info & Background Check Release' ) .
        '<p style="margin:0 0 6px; font-size:14px;">Please also complete this single form covering emergency contacts, medical info, and a Washington State Patrol background check release. It only needs to be filled out once — we\'ll use the same info for every show in your group.</p>' .
        $btn( $emg_link, 'Submit Emergency Info' ) .
        $fallback_url( $emg_link_raw ) .

        $divider .

        // Handbook section
        $section_header( 'Cast & Crew Handbook' ) .
        '<p style="margin:0 0 6px; font-size:14px;">Please review our Cast &amp; Crew Handbook — it covers policies, comp codes, and what to expect during the production.</p>' .
        $btn( $handbook, 'Read Handbook' ) .
        $fallback_url( $handbook_raw ) .

        $divider .

        // Season reference table
        $section_header( 'Season Reference' ) .
        '<p style="margin:0 0 12px; font-size:14px;">Stage managers, drive folders, and comp codes for each show:</p>' .
        '<table style="border-collapse:collapse; width:100%; font-size:13px;">' .
        '<thead><tr style="border-bottom:2px solid ' . $red . '; text-align:left;">' .
        '<th style="padding:8px;">Show</th><th style="padding:8px;">Stage Manager</th><th style="padding:8px;">Comp Code</th><th style="padding:8px;">Future-Shows Comp Code</th>' .
        '</tr></thead><tbody>' . $rows_html . '</tbody></table>' .
        '<p style="margin:24px 0 0; font-size:13px; color:#666;">Questions? Reply to this email or reach us at <a href="mailto:' . $footer_email . '" style="color:' . $red . ';">' . $footer_email . '</a>.</p>' .
        '</div></div>';
}

/**
 * Bio email for a single show — assembles context from the sheet, then sends.
 * Called after successful contract send (or from resend/bulk-request paths).
 */
function tlt_cb_send_bio_request_email( $email, $full_name, $first_name, $last_name, $show, $role ) {
    if ( trim( (string) $email ) === '' ) {
        return new WP_Error( 'no_email', "No email on file for $full_name." );
    }
    $ranges = tlt_callboard_sheets_get( TLT_CALLBOARD_SHEET_ID, [
        'Season!A1:N',
        "'Production Teams'!A2:E",
    ] );
    if ( is_wp_error( $ranges ) ) return $ranges;
    $season_rows = $ranges['Season!A1:N']            ?? [];
    $team_rows   = $ranges["'Production Teams'!A2:E"] ?? [];

    $season = tlt_cb_season_setting( $season_rows, 'Current Season' );
    // Find this show's Season row (SM email, comp codes, folder URL).
    $sm_email = ''; $comp = ''; $comp2 = ''; $folder = '';
    foreach ( $season_rows as $r ) {
        if ( tlt_cb_s( $r[1] ?? '' ) === $show ) {
            $comp     = tlt_cb_s( $r[2]  ?? '' );
            $comp2    = tlt_cb_s( $r[3]  ?? '' );
            $sm_email = tlt_cb_s( $r[4]  ?? '' );
            $folder   = tlt_cb_s( $r[10] ?? '' );  // col K
            break;
        }
    }
    // Look up Stage Manager name for this show from Production Teams.
    $sm_name = '';
    foreach ( $team_rows as $r ) {
        if ( tlt_cb_s( $r[0] ?? '' ) === $show && tlt_cb_s( $r[1] ?? '' ) === 'Stage Manager' ) {
            $sm_name = trim( tlt_cb_s( $r[2] ?? '' ) . ' ' . tlt_cb_s( $r[4] ?? '' ) );
            break;
        }
    }

    $tok = tlt_cb_ensure_bio_token( $first_name, $last_name, $email );
    if ( is_wp_error( $tok ) ) return $tok;
    $bio_app_url = tlt_cb_season_setting( $season_rows, 'Bio App Url' );
    if ( $bio_app_url === '' ) return new WP_Error( 'no_bio_app', 'Bio App Url not set in Season config.' );
    $bio_link = $bio_app_url . '?token=' . rawurlencode( $tok['token'] ) . '&show=' . rawurlencode( $show );
    $emergency_base = tlt_cb_emergency_info_base( $season_rows );
    $emergency_link = $emergency_base !== '' ? $emergency_base . '?token=' . rawurlencode( $tok['token'] ) : '';

    $html = tlt_cb_bio_email_html( [
        'fullName' => $full_name, 'firstName' => $first_name,
        'show' => $show, 'role' => $role, 'season' => $season,
        'bioLink' => $bio_link, 'emergencyLink' => $emergency_link,
        'handbookUrl'   => TLT_CALLBOARD_HANDBOOK_URL,
        'showFolderUrl' => $folder,
        'smName' => $sm_name, 'smEmail' => $sm_email,
        'compCode' => $comp, 'compCode2' => $comp2,
    ] );
    $subject = 'TLT Season ' . $season . ' — Welcome to ' . $show;
    return tlt_cb_send_mail( $email, $subject, $html );
}

/**
 * Combined-shows variant. $shows is array of show names.
 */
function tlt_cb_send_combined_bio_request_email( $email, $full_name, $first_name, $last_name, array $shows, $role ) {
    if ( trim( (string) $email ) === '' ) {
        return new WP_Error( 'no_email', "No email on file for $full_name." );
    }
    $ranges = tlt_callboard_sheets_get( TLT_CALLBOARD_SHEET_ID, [
        'Season!A1:N',
        "'Production Teams'!A2:E",
    ] );
    if ( is_wp_error( $ranges ) ) return $ranges;
    $season_rows = $ranges['Season!A1:N']            ?? [];
    $team_rows   = $ranges["'Production Teams'!A2:E"] ?? [];
    $season = tlt_cb_season_setting( $season_rows, 'Current Season' );

    $per_show = [];
    foreach ( $shows as $sh ) {
        $sm_email = ''; $comp = ''; $comp2 = ''; $folder = '';
        foreach ( $season_rows as $r ) {
            if ( tlt_cb_s( $r[1] ?? '' ) === $sh ) {
                $comp     = tlt_cb_s( $r[2]  ?? '' );
                $comp2    = tlt_cb_s( $r[3]  ?? '' );
                $sm_email = tlt_cb_s( $r[4]  ?? '' );
                $folder   = tlt_cb_s( $r[10] ?? '' );
                break;
            }
        }
        $sm_name = '';
        foreach ( $team_rows as $r ) {
            if ( tlt_cb_s( $r[0] ?? '' ) === $sh && tlt_cb_s( $r[1] ?? '' ) === 'Stage Manager' ) {
                $sm_name = trim( tlt_cb_s( $r[2] ?? '' ) . ' ' . tlt_cb_s( $r[4] ?? '' ) );
                break;
            }
        }
        $per_show[] = [
            'show'      => $sh,
            'folder'    => $folder,
            'smName'    => $sm_name,
            'smEmail'   => $sm_email,
            'compCode'  => $comp,
            'compCode2' => $comp2,
        ];
    }

    $tok = tlt_cb_ensure_bio_token( $first_name, $last_name, $email );
    if ( is_wp_error( $tok ) ) return $tok;
    $bio_app_url = tlt_cb_season_setting( $season_rows, 'Bio App Url' );
    if ( $bio_app_url === '' ) return new WP_Error( 'no_bio_app', 'Bio App Url not set in Season config.' );
    $bio_link = $bio_app_url . '?token=' . rawurlencode( $tok['token'] ) . '&shows=' . rawurlencode( implode( ',', $shows ) );
    $emergency_base = tlt_cb_emergency_info_base( $season_rows );
    $emergency_link = $emergency_base !== '' ? $emergency_base . '?token=' . rawurlencode( $tok['token'] ) : '';

    // Shows display: "A and B", "A, B, and C".
    $shows_display = $shows[0];
    if ( count( $shows ) === 2 ) $shows_display = $shows[0] . ' and ' . $shows[1];
    elseif ( count( $shows ) > 2 ) $shows_display = implode( ', ', array_slice( $shows, 0, -1 ) ) . ', and ' . $shows[ count( $shows ) - 1 ];

    $html = tlt_cb_combined_bio_email_html( [
        'firstName' => $first_name, 'showsDisplay' => $shows_display, 'role' => $role,
        'bioLink' => $bio_link, 'emergencyLink' => $emergency_link,
        'handbookUrl' => TLT_CALLBOARD_HANDBOOK_URL, 'perShow' => $per_show,
    ] );
    $subject = 'TLT Season ' . $season . ' — Welcome to ' . $shows_display;
    return tlt_cb_send_mail( $email, $subject, $html );
}

/**
 * POST /bios-doc-compile  { show }
 */
function tlt_callboard_ep_bios_doc_compile( WP_REST_Request $req ) {
    $body = $req->get_json_params();
    $show = tlt_cb_s( is_array( $body ) ? ( $body['show'] ?? '' ) : '' );
    if ( $show === '' ) return new WP_Error( 'missing_show', 'show is required', [ 'status' => 400 ] );
    $r = tlt_cb_bios_doc_compile( $show );
    if ( is_wp_error( $r ) ) return $r;
    return tlt_cb_ok( $r );
}

/**
 * Rewrite GET /schedule-link to match the contact-sheet-link shape so the
 * frontend can drive a View/Regenerate modal. Was: plain URL string.
 * Now: { url, exists, source }.
 */
function tlt_callboard_ep_get_schedule_link_v2( WP_REST_Request $req ) {
    $show = tlt_cb_s( $req->get_param( 'show' ) );
    if ( $show === '' ) return new WP_Error( 'missing_show', 'show query param required', [ 'status' => 400 ] );

    $season_rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_SHEET_ID, 'Season!A1:N' );
    if ( is_wp_error( $season_rows ) ) return $season_rows;

    $season_long    = '';
    $cached_url     = '';
    $season_row_num = 0;
    foreach ( $season_rows as $i => $r ) {
        if ( tlt_cb_s( $r[0] ?? '' ) === 'Current Season Long' ) $season_long = tlt_cb_s( $r[1] ?? '' );
        if ( tlt_cb_s( $r[1] ?? '' ) === $show ) {
            $cached_url = tlt_cb_s( $r[13] ?? '' );
            $season_row_num = $i + 1;
        }
    }
    if ( $cached_url !== '' ) return tlt_cb_ok( [ 'url' => $cached_url, 'exists' => true, 'source' => 'cache' ] );

    if ( $season_long !== '' ) {
        $doc_name = $show . ' - ' . $season_long . ' Tech Schedule';
        $files = tlt_cb_drive_find_in_folder( TLT_CALLBOARD_TS_FOLDER_ID, $doc_name );
        if ( is_wp_error( $files ) ) return $files;
        if ( ! empty( $files ) ) {
            $url = tlt_cb_doc_url( $files[0]['id'] );
            if ( $season_row_num > 0 ) tlt_callboard_sheets_write( TLT_CALLBOARD_SHEET_ID, "Season!N{$season_row_num}", [[ $url ]] );
            return tlt_cb_ok( [ 'url' => $url, 'exists' => true, 'source' => 'drive' ] );
        }
    }
    return tlt_cb_ok( [ 'url' => '', 'exists' => false, 'source' => 'none' ] );
}

/* ===========================================================================
 * ============  BIO REQUEST EMAIL ENDPOINTS  ================================
 * ======================================================================== */

/**
 * POST /bios-send-requests  { show }
 * Sends the welcome/bio-request email to every unique person on the show's
 * Production Teams + Actors roster. Skips those with no email on file.
 * Returns { sent, skipped }.
 */
function tlt_callboard_ep_bios_send_requests( WP_REST_Request $req ) {
    $body = $req->get_json_params();
    $show = tlt_cb_s( is_array( $body ) ? ( $body['show'] ?? '' ) : '' );
    if ( $show === '' ) return new WP_Error( 'missing_show', 'show is required', [ 'status' => 400 ] );

    $ranges = tlt_callboard_sheets_get( TLT_CALLBOARD_SHEET_ID, [
        "'Production Teams'!A2:E",
        'Actors!A2:E',
    ] );
    if ( is_wp_error( $ranges ) ) return $ranges;
    $team_rows  = $ranges["'Production Teams'!A2:E"] ?? [];
    $actor_rows = $ranges['Actors!A2:E']              ?? [];

    // Combined dedup by first+last.
    $seen = []; $people = [];
    foreach ( $team_rows as $r ) {
        if ( tlt_cb_s( $r[0] ?? '' ) !== $show ) continue;
        $first = tlt_cb_s( $r[2] ?? '' );
        if ( $first === '' ) continue;
        $last  = tlt_cb_s( $r[4] ?? '' );
        $key   = strtolower( $first ) . '|' . strtolower( $last );
        if ( isset( $seen[ $key ] ) ) continue;
        $seen[ $key ] = true;
        $people[] = [ 'first' => $first, 'last' => $last, 'role' => tlt_cb_s( $r[1] ?? '' ) ];
    }
    foreach ( $actor_rows as $r ) {
        if ( tlt_cb_s( $r[0] ?? '' ) !== $show ) continue;
        $first = tlt_cb_s( $r[2] ?? '' );
        if ( $first === '' ) continue;
        $last  = tlt_cb_s( $r[4] ?? '' );
        $key   = strtolower( $first ) . '|' . strtolower( $last );
        if ( isset( $seen[ $key ] ) ) continue;
        $seen[ $key ] = true;
        $people[] = [ 'first' => $first, 'last' => $last, 'role' => 'Actor' ];
    }

    // Look up emails in Contactbook.
    $cb_rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_CONTACTBOOK_ID, 'Contactbook!A2:H' );
    if ( is_wp_error( $cb_rows ) ) return $cb_rows;
    $email_by_name = [];
    foreach ( $cb_rows as $r ) {
        $first = strtolower( tlt_cb_s( $r[1] ?? '' ) );
        if ( $first === '' ) continue;
        $last  = strtolower( tlt_cb_s( $r[3] ?? '' ) );
        $email_by_name[ $first . '|' . $last ] = tlt_cb_s( $r[7] ?? '' );
    }

    $sent = 0; $skipped = 0; $errors = [];
    foreach ( $people as $p ) {
        $email = $email_by_name[ strtolower( $p['first'] ) . '|' . strtolower( $p['last'] ) ] ?? '';
        if ( $email === '' ) { $skipped++; continue; }
        $full = trim( $p['first'] . ' ' . $p['last'] );
        $r = tlt_cb_send_bio_request_email( $email, $full, $p['first'], $p['last'], $show, $p['role'] );
        if ( is_wp_error( $r ) ) { $skipped++; $errors[] = $full . ': ' . $r->get_error_message(); }
        else $sent++;
    }

    return tlt_cb_ok( [
        'sent'    => $sent,
        'skipped' => $skipped,
        'errors'  => $errors,
    ] );
}

/**
 * POST /bios-resend  { show, firstName, lastName, role }
 * Send a single bio-request email; auto-detects combined-contract group via
 * col S on Production Teams/Actors and sends the combined variant if present.
 */
function tlt_callboard_ep_bios_resend( WP_REST_Request $req ) {
    $body = $req->get_json_params() ?: [];
    $show  = tlt_cb_s( $body['show']      ?? '' );
    $first = tlt_cb_s( $body['firstName'] ?? '' );
    $last  = tlt_cb_s( $body['lastName']  ?? '' );
    $role  = tlt_cb_s( $body['role']      ?? '' );
    if ( $show === '' || $first === '' || $last === '' ) {
        return new WP_Error( 'missing_args', 'show, firstName, lastName required', [ 'status' => 400 ] );
    }

    // Look up email.
    $cb_rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_CONTACTBOOK_ID, 'Contactbook!A2:H' );
    if ( is_wp_error( $cb_rows ) ) return $cb_rows;
    $email = '';
    foreach ( $cb_rows as $r ) {
        if ( strtolower( tlt_cb_s( $r[1] ?? '' ) ) === strtolower( $first )
          && strtolower( tlt_cb_s( $r[3] ?? '' ) ) === strtolower( $last ) ) {
            $email = tlt_cb_s( $r[7] ?? '' ); break;
        }
    }
    if ( $email === '' ) return new WP_Error( 'no_email', "No email on file for {$first} {$last}." );

    // Determine combined group (col S on Production Teams/Actors).
    $shows = tlt_cb_find_combined_shows_for_row( $show, $first, $last );
    $full  = $first . ' ' . $last;
    if ( count( $shows ) > 1 ) {
        $r = tlt_cb_send_combined_bio_request_email( $email, $full, $first, $last, $shows, $role );
    } else {
        $r = tlt_cb_send_bio_request_email( $email, $full, $first, $last, $show, $role );
    }
    if ( is_wp_error( $r ) ) return $r;
    return tlt_cb_ok( [ 'success' => true, 'sent' => true, 'combined' => count( $shows ) > 1, 'shows' => $shows ] );
}

/**
 * Reproduces GAS _findCombinedShowsForRow. Returns [ show ] when there's no
 * combined group.
 */
function tlt_cb_find_combined_shows_for_row( $show, $first_name, $last_name ) {
    $ranges = tlt_callboard_sheets_get( TLT_CALLBOARD_SHEET_ID, [
        "'Production Teams'!A2:S",
        'Actors!A2:S',
    ] );
    if ( is_wp_error( $ranges ) ) return [ $show ];
    $rows_by_tab = [
        $ranges["'Production Teams'!A2:S"] ?? [],
        $ranges['Actors!A2:S']              ?? [],
    ];
    $first_lc = strtolower( $first_name );
    $last_lc  = strtolower( $last_name );

    $group_id = '';
    foreach ( $rows_by_tab as $rows ) {
        foreach ( $rows as $r ) {
            if ( tlt_cb_s( $r[0] ?? '' ) !== $show ) continue;
            if ( strtolower( tlt_cb_s( $r[2] ?? '' ) ) !== $first_lc ) continue;
            if ( strtolower( tlt_cb_s( $r[4] ?? '' ) ) !== $last_lc  ) continue;
            $group_id = tlt_cb_s( $r[18] ?? '' ); // col S
            break 2;
        }
    }
    if ( $group_id === '' ) return [ $show ];

    $shows = [];
    foreach ( $rows_by_tab as $rows ) {
        foreach ( $rows as $r ) {
            if ( tlt_cb_s( $r[18] ?? '' ) === $group_id ) {
                $s = tlt_cb_s( $r[0] ?? '' );
                if ( $s !== '' ) $shows[ $s ] = true;
            }
        }
    }
    if ( empty( $shows ) ) return [ $show ];
    return array_keys( $shows );
}

/* ===========================================================================
 * ============  CONTRACT GENERATOR  =========================================
 *
 * Port of ContractGenerator.js.  This is the beast — it copies a template
 * Doc (one of 4 based on role type), fills placeholders + conditional
 * sections + a Duties block + key dates via Docs API, saves to Drive under
 * a season/show subfolder, updates sheet status. Send flow exports PDF,
 * uploads to OpenSign, sends the bio welcome email.
 *
 * Requires: OpenSign key + Resend key in wp-config.php.
 *
 * Template selection is driven by the Duties sheet, col B ("template" —
 * one of "General", "Director", "Actor", "Operator"). Defaults to General.
 * ======================================================================== */

/**
 * Look up the Google Docs template ID for a template type string.
 */
function tlt_cb_contract_template_id( $type ) {
    switch ( $type ) {
        case 'Director': return TLT_CALLBOARD_TPL_DIRECTOR;
        case 'Actor':    return TLT_CALLBOARD_TPL_ACTOR;
        case 'Operator': return TLT_CALLBOARD_TPL_OPERATOR;
        case 'General':
        default:         return TLT_CALLBOARD_TPL_GENERAL;
    }
}

/**
 * Fetch a spreadsheet's named ranges via Sheets API v4 spreadsheets.get,
 * then batchGet the actual range values. Returns [ name => flat string ].
 */
function tlt_cb_get_named_ranges( array $names ) {
    static $cache = null;
    if ( $cache !== null ) return array_intersect_key( $cache, array_flip( $names ) );
    $token = tlt_callboard_google_access_token();
    if ( is_wp_error( $token ) ) return $token;
    $url = 'https://sheets.googleapis.com/v4/spreadsheets/' . TLT_CALLBOARD_SHEET_ID . '?fields=namedRanges,sheets(properties(sheetId,title))';
    $resp = wp_remote_get( $url, [
        'timeout' => 15,
        'headers' => [ 'Authorization' => 'Bearer ' . $token ],
    ] );
    if ( is_wp_error( $resp ) ) return $resp;
    $data = json_decode( wp_remote_retrieve_body( $resp ), true );
    if ( empty( $data['namedRanges'] ) ) { $cache = []; return []; }
    $sheet_titles = [];
    foreach ( ( $data['sheets'] ?? [] ) as $s ) {
        $sheet_titles[ $s['properties']['sheetId'] ] = $s['properties']['title'];
    }
    $a1_by_name = [];
    foreach ( $data['namedRanges'] as $nr ) {
        $r = $nr['range'];
        $sheet_id = $r['sheetId'] ?? 0;
        $title    = $sheet_titles[ $sheet_id ] ?? '';
        // Convert col index to letter.
        $col_letter = function ( $i ) {
            $letters = '';
            $i = $i + 1;
            while ( $i > 0 ) { $rem = ( $i - 1 ) % 26; $letters = chr( 65 + $rem ) . $letters; $i = intval( ( $i - 1 ) / 26 ); }
            return $letters;
        };
        $sc = $col_letter( $r['startColumnIndex'] ?? 0 );
        $ec = $col_letter( ( $r['endColumnIndex'] ?? 1 ) - 1 );
        $sr = ( $r['startRowIndex'] ?? 0 ) + 1;
        $er = $r['endRowIndex']   ?? $sr;
        $a1 = "'{$title}'!{$sc}{$sr}:{$ec}{$er}";
        $a1_by_name[ $nr['name'] ] = $a1;
    }
    $cache = [];
    if ( ! empty( $a1_by_name ) ) {
        $vals = tlt_callboard_sheets_get( TLT_CALLBOARD_SHEET_ID, array_values( $a1_by_name ) );
        if ( ! is_wp_error( $vals ) ) {
            foreach ( $a1_by_name as $name => $a1 ) {
                $rows = $vals[ $a1 ] ?? [];
                $flat = [];
                foreach ( $rows as $rr ) foreach ( $rr as $cc ) {
                    $s = tlt_cb_s( $cc ); if ( $s !== '' ) $flat[] = $s;
                }
                $cache[ $name ] = implode( "\n", $flat );
            }
        }
    }
    return array_intersect_key( $cache, array_flip( $names ) );
}

/**
 * Read the Duties Google Doc and parse the [Role:...] block for a role.
 * Returns [ 'overview' => string, 'duties' => [lines], 'specialConditions' => string ].
 */
function tlt_cb_contract_parse_duties_doc( $role ) {
    $doc = tlt_cb_docs_get( TLT_CALLBOARD_DUTIES_DOC_ID, 'body(content(paragraph(elements(textRun(content)))))' );
    if ( is_wp_error( $doc ) ) return $doc;
    $text = '';
    foreach ( ( $doc['body']['content'] ?? [] ) as $el ) {
        foreach ( ( $el['paragraph']['elements'] ?? [] ) as $pe ) {
            $text .= $pe['textRun']['content'] ?? '';
        }
    }
    $role_key   = preg_replace( '/\s+/', '', $role );
    $open_tag   = '[Role:' . $role_key . ']';
    $close_tag  = '[/Role:' . $role_key . ']';
    $empty = [ 'overview' => '', 'duties' => [], 'specialConditions' => '' ];

    $start = strpos( $text, $open_tag );
    if ( $start === false ) return $empty;
    $end = strpos( $text, $close_tag, $start );
    if ( $end === false ) return $empty;
    $block = substr( $text, $start + strlen( $open_tag ), $end - $start - strlen( $open_tag ) );

    $extract = function ( $blob, $tag ) {
        $o = strpos( $blob, "[$tag]" );
        if ( $o === false ) return '';
        $c = strpos( $blob, "[/$tag]", $o );
        if ( $c === false ) return '';
        return trim( substr( $blob, $o + strlen( $tag ) + 2, $c - $o - strlen( $tag ) - 2 ) );
    };

    $overview   = $extract( $block, 'Overview' );
    $duties_raw = $extract( $block, 'Duties' );
    $special    = $extract( $block, 'SpecialConditions' );

    $duties = [];
    foreach ( explode( "\n", $duties_raw ) as $line ) {
        $t = trim( $line );
        if ( $t !== '' ) $duties[] = $t;
    }

    return [
        'overview'          => $overview,
        'duties'            => $duties,
        'specialConditions' => $special,
    ];
}

/**
 * Look up (role) in the Duties sheet — returns:
 *   [ 'template' => 'Director'|..., 'budgetVerbage' => string, 'budgetVerbage2', 'budgetVerbage3',
 *     'key_date_flags' => [ event-type-string => bool ] ]
 * (key_date_flags is filled by inspecting cols G onward, whose headers are event types).
 */
function tlt_cb_contract_get_duties_row( $role ) {
    $duties = tlt_callboard_sheets_get( TLT_CALLBOARD_SHEET_ID, [ 'Duties!A1:AZ' ] );
    if ( is_wp_error( $duties ) ) return $duties;
    $rows = $duties['Duties!A1:AZ'] ?? [];
    if ( count( $rows ) < 2 ) return [ 'template' => 'General', 'budgetVerbage' => '', 'budgetVerbage2' => '', 'budgetVerbage3' => '', 'key_date_flags' => [] ];
    $header = $rows[0];

    $target = null;
    foreach ( array_slice( $rows, 1 ) as $r ) {
        if ( strcasecmp( tlt_cb_s( $r[0] ?? '' ), $role ) === 0 ) { $target = $r; break; }
    }
    if ( $target === null ) return [ 'template' => 'General', 'budgetVerbage' => '', 'budgetVerbage2' => '', 'budgetVerbage3' => '', 'key_date_flags' => [] ];

    $key_date_flags = [];
    for ( $i = 6; $i < count( $header ); $i++ ) { // col G = index 6
        $event = tlt_cb_s( $header[ $i ] ?? '' );
        if ( $event === '' ) continue;
        $flag = tlt_cb_s( $target[ $i ] ?? '' );
        $key_date_flags[ $event ] = ( $flag === 'TRUE' || $flag === '1' || $flag === 'true' || $flag === true );
    }

    return [
        'template'        => tlt_cb_s( $target[1] ?? '' ) ?: 'General',
        'budgetVerbage'   => tlt_cb_s( $target[2] ?? '' ),
        'budgetVerbage2'  => tlt_cb_s( $target[3] ?? '' ),
        'budgetVerbage3'  => tlt_cb_s( $target[4] ?? '' ),
        'key_date_flags'  => $key_date_flags,
    ];
}

/**
 * Build the [ label, date_str ] items for the <<KeyDates>> block for a show,
 * filtered by which events the Duties row flags TRUE.
 *
 * Uses the Settings tab as the label mapping (event key → display text).
 */
function tlt_cb_contract_key_date_items( $show, $key_date_flags ) {
    $ranges = tlt_callboard_sheets_get( TLT_CALLBOARD_SHEET_ID, [
        'Dates!A2:H',
        'Settings!A1:B',
    ] );
    if ( is_wp_error( $ranges ) ) return $ranges;
    $dates_rows    = $ranges['Dates!A2:H']    ?? [];
    $settings_rows = $ranges['Settings!A1:B'] ?? [];
    $label_by_key = [];
    foreach ( $settings_rows as $r ) {
        $k = tlt_cb_s( $r[0] ?? '' );
        if ( $k !== '' ) $label_by_key[ $k ] = tlt_cb_s( $r[1] ?? '' );
    }
    $items = [];
    foreach ( $dates_rows as $r ) {
        if ( tlt_cb_s( $r[0] ?? '' ) !== $show ) continue;
        $event = tlt_cb_s( $r[1] ?? '' );
        if ( empty( $key_date_flags[ $event ] ) ) continue;
        $date_raw = $r[4] ?? '';
        $label    = $label_by_key[ $event ] ?? ( $event . ':' );
        $items[]  = [ 'label' => $label, 'date' => tlt_cb_fmt_date( $date_raw ) ];
    }
    return $items;
}

/**
 * Format performances list as multi-line text: "Fri, October 3, 2025 at 7:30pm".
 */
function tlt_cb_contract_format_performances( $show ) {
    $rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_SHEET_ID, 'Dates!A2:H' );
    if ( is_wp_error( $rows ) ) return '';
    $out = [];
    foreach ( $rows as $r ) {
        if ( tlt_cb_s( $r[0] ?? '' ) !== $show ) continue;
        $type = tlt_cb_s( $r[1] ?? '' );
        if ( strpos( $type, 'Performance' ) === false ) continue;
        $dt = tlt_cb_parse_date( $r[4] ?? '' );
        if ( ! $dt ) continue;
        $tm_raw = $r[5] ?? '';
        $tm     = tlt_cb_fmt_time( $tm_raw );
        $out[] = $dt->format( 'D, F j, Y' ) . ( $tm !== '' ? ' at ' . $tm : '' );
    }
    return implode( "\n", $out );
}

/**
 * Read stipend + up-to-3 budget values from the Budget tab (row per show+role).
 */
function tlt_cb_contract_budget_row( $show, $role ) {
    $rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_SHEET_ID, 'Budget!A2:F' );
    if ( is_wp_error( $rows ) ) return [ 'stipend' => '', 'budget1' => '', 'budget2' => '', 'budget3' => '' ];
    foreach ( $rows as $r ) {
        if ( tlt_cb_s( $r[0] ?? '' ) !== $show ) continue;
        if ( strcasecmp( tlt_cb_s( $r[1] ?? '' ), $role ) !== 0 ) continue;
        return [
            'stipend' => tlt_cb_s( $r[2] ?? '' ),
            'budget1' => tlt_cb_s( $r[3] ?? '' ),
            'budget2' => tlt_cb_s( $r[4] ?? '' ),
            'budget3' => tlt_cb_s( $r[5] ?? '' ),
        ];
    }
    return [ 'stipend' => '', 'budget1' => '', 'budget2' => '', 'budget3' => '' ];
}

/**
 * Look up a value on the Theatre tab (org staff roster).
 */
function tlt_cb_contract_theatre_value( $label, $theatre_rows = null ) {
    if ( $theatre_rows === null ) {
        $theatre_rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_SHEET_ID, 'Theatre!A2:D200' );
        if ( is_wp_error( $theatre_rows ) ) return '';
    }
    foreach ( $theatre_rows as $r ) {
        if ( tlt_cb_s( $r[0] ?? '' ) === $label ) return tlt_cb_s( $r[1] ?? '' );
    }
    return '';
}

/**
 * Format currency: numeric → "$N" (no decimals for whole), else raw string.
 */
function tlt_cb_contract_fmt_currency( $val ) {
    $v = tlt_cb_s( $val );
    if ( $v === '' ) return '';
    if ( is_numeric( $v ) ) {
        $n = (float) $v;
        return '$' . number_format( $n, ( $n == floor( $n ) ) ? 0 : 2 );
    }
    return $v;
}

/**
 * Assemble the full replacements map for a single-show contract.
 * Returns [ 'template' => ..., 'replacements' => [...], 'duties_content' => [...],
 *           'key_date_items' => [...], 'has_budget1/2/3' => bool, 'has_special' => bool ]
 */
function tlt_cb_contract_assemble( $show, $role, $character = '' ) {
    $named = tlt_cb_get_named_ranges( [ 'Mission', 'Vision', 'Board', 'CurrentSeason', 'CurrentSeasonLong' ] );
    if ( is_wp_error( $named ) ) return $named;

    // If named ranges for CurrentSeason / CurrentSeasonLong don't exist, read
    // the same values from the Season tab's label rows (col A = label, col B
    // = value). GAS required the named ranges; port stays working either way.
    if ( empty( $named['CurrentSeason'] ) || empty( $named['CurrentSeasonLong'] ) ) {
        $season_rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_SHEET_ID, 'Season!A1:N' );
        if ( ! is_wp_error( $season_rows ) ) {
            if ( empty( $named['CurrentSeason'] ) )     $named['CurrentSeason']     = tlt_cb_season_setting( $season_rows, 'Current Season' );
            if ( empty( $named['CurrentSeasonLong'] ) ) $named['CurrentSeasonLong'] = tlt_cb_season_setting( $season_rows, 'Current Season Long' );
        }
    }

    $theatre_rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_SHEET_ID, 'Theatre!A2:D200' );
    if ( is_wp_error( $theatre_rows ) ) return $theatre_rows;

    $duties_row = tlt_cb_contract_get_duties_row( $role );
    if ( is_wp_error( $duties_row ) ) return $duties_row;
    $duties_content = tlt_cb_contract_parse_duties_doc( $role );
    if ( is_wp_error( $duties_content ) ) return $duties_content;
    $key_date_items = tlt_cb_contract_key_date_items( $show, $duties_row['key_date_flags'] );
    if ( is_wp_error( $key_date_items ) ) return $key_date_items;
    $budget = tlt_cb_contract_budget_row( $show, $role );

    $get_dates_val = function ( $event_type ) use ( $show ) {
        $rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_SHEET_ID, 'Dates!A2:H' );
        if ( is_wp_error( $rows ) ) return '';
        foreach ( $rows as $r ) {
            if ( tlt_cb_s( $r[0] ?? '' ) === $show && tlt_cb_s( $r[1] ?? '' ) === $event_type ) return $r[4] ?? '';
        }
        return '';
    };

    $replacements = [
        '<<Show>>'         => $show,
        '<<Role>>'         => $role,
        '<<Character>>'    => $character,
        '<<CombinedShow>>' => $show,
        '<<Performances>>' => tlt_cb_contract_format_performances( $show ),
        '<<MAD>>'          => tlt_cb_contract_theatre_value( 'Managing Artistic Director',   $theatre_rows ),
        // Historical templates used <<AD>> for Associate Producing Director;
        // current templates use <<APD>>. Both map to the same Theatre lookup so
        // either works. Empty rows are trimmed out by hide_empty_staff_blocks.
        '<<AD>>'           => tlt_cb_contract_theatre_value( 'Associate Producing Director', $theatre_rows ),
        '<<APD>>'          => tlt_cb_contract_theatre_value( 'Associate Producing Director', $theatre_rows ),
        '<<TD>>'           => tlt_cb_contract_theatre_value( 'Technical Director',           $theatre_rows ),
        '<<DD>>'           => tlt_cb_contract_theatre_value( 'Development Director',         $theatre_rows ),
        '<<ED>>'           => tlt_cb_contract_theatre_value( 'Education Director',           $theatre_rows ),
        '<<OM>>'           => tlt_cb_contract_theatre_value( 'Office Manager',               $theatre_rows ),
        '<<PM>>'           => tlt_cb_contract_theatre_value( 'Production Manager',           $theatre_rows ),
        '<<Mission>>'      => $named['Mission'] ?? '',
        '<<Vision>>'       => $named['Vision']  ?? '',
        // Staff/Marketing/Support-Team markers per GAS _STAFF_BLOCKS — always
        // replaced with empty string. hide_empty_staff_blocks removes the
        // adjacent label paragraph so nothing dangles.
        '<<Staff>>'        => '',
        '<<MC>>'           => '',
        '<<ST>>'           => '',
        '<<Stipend>>'      => tlt_cb_contract_fmt_currency( $budget['stipend'] ),
        '<<Budget>>'       => tlt_cb_contract_fmt_currency( $budget['budget1'] ),
        '<<Budget2>>'      => tlt_cb_contract_fmt_currency( $budget['budget2'] ),
        '<<Budget3>>'      => tlt_cb_contract_fmt_currency( $budget['budget3'] ),
        '<<BudgetVerbage>>'  => $duties_row['budgetVerbage'],
        '<<BudgetVerbage2>>' => $duties_row['budgetVerbage2'],
        '<<BudgetVerbage3>>' => $duties_row['budgetVerbage3'],
        '<<RehearsalStart>>' => tlt_cb_fmt_date( $get_dates_val( 'Rehearsal Start' ) ),
        '<<Opening>>'        => tlt_cb_fmt_date( $get_dates_val( 'Opening Performance' ) ),
        '<<Closing>>'        => tlt_cb_fmt_date( $get_dates_val( 'Closing Performance' ) ),
        '<<Overview>>'       => $duties_content['overview'],
        '<<Name>>'           => '',
        '<<Date>>'           => ( new DateTime( 'now', new DateTimeZone( 'America/Los_Angeles' ) ) )->format( 'F j, Y' ),
    ];

    return [
        'template'         => $duties_row['template'],
        'season'           => $named['CurrentSeason']     ?? '',
        'season_long'      => $named['CurrentSeasonLong'] ?? '',
        'board_value'      => $named['Board']             ?? '',
        'replacements'     => $replacements,
        'duties_content'   => $duties_content,
        'key_date_items'   => $key_date_items,
        'has_budget1'      => $budget['budget1'] !== '',
        'has_budget2'      => $budget['budget2'] !== '',
        'has_budget3'      => $budget['budget3'] !== '',
        'has_special'      => $duties_content['specialConditions'] !== '',
        'has_key_dates'    => count( $key_date_items ) > 0,
    ];
}

/* -----  Contract doc-building helpers  ------------------------------------ */

/**
 * Iterate the doc body top-level content elements, resolving each paragraph's
 * plain text. Returns [ [start, end, text], ... ] one entry per element.
 */
function tlt_cb_contract_walk_paragraphs( $doc_id ) {
    // No fields mask — the mask for a nested body(content(paragraph, table))
    // structure is a source of subtle parse errors and Google returns 400 on
    // any mismatch. Full-doc fetch is fine for the size docs we build.
    $doc = tlt_cb_docs_get( $doc_id );
    if ( is_wp_error( $doc ) ) return $doc;
    $out = [];
    $walk = function ( $items, &$out ) use ( &$walk ) {
        foreach ( $items as $el ) {
            if ( isset( $el['paragraph'] ) ) {
                $text = '';
                foreach ( ( $el['paragraph']['elements'] ?? [] ) as $pe ) {
                    $text .= $pe['textRun']['content'] ?? '';
                }
                $out[] = [
                    'start' => $el['startIndex'] ?? null,
                    'end'   => $el['endIndex']   ?? null,
                    'text'  => $text,
                ];
            } elseif ( isset( $el['table']['tableRows'] ) ) {
                foreach ( $el['table']['tableRows'] as $row ) {
                    foreach ( ( $row['tableCells'] ?? [] ) as $cell ) {
                        $walk( $cell['content'] ?? [], $out );
                    }
                }
            }
        }
    };
    $walk( $doc['body']['content'] ?? [], $out );
    return $out;
}

/**
 * Delete the entire paragraph containing $marker (marker occurs anywhere in
 * the paragraph text). No-op if not found.
 */
function tlt_cb_contract_delete_marker_paragraph( $doc_id, $marker ) {
    // Just use replaceAllText — reliably reaches every doc surface (body,
    // tables, headers, footers) and handles textRun boundaries. Prior
    // walker-based delete could fail silently when the marker sat inside a
    // table cell or split textRun. This leaves an empty paragraph where
    // the marker was (visually a blank line), which is preferable to
    // leaving the literal text behind.
    return tlt_cb_docs_batch_update( $doc_id, [
        [ 'replaceAllText' => [
            'containsText' => [ 'text' => $marker, 'matchCase' => true ],
            'replaceText'  => '',
        ] ],
    ] );
}

/**
 * Replace paragraph containing $marker with an ordered list of lines. Each
 * line becomes its own paragraph. Insertions preserve the paragraph before/
 * after.
 */
function tlt_cb_contract_replace_marker_with_lines( $doc_id, $marker, array $lines ) {
    $paras = tlt_cb_contract_walk_paragraphs( $doc_id );
    if ( is_wp_error( $paras ) ) return $paras;
    foreach ( $paras as $p ) {
        if ( strpos( $p['text'], $marker ) === false ) continue;
        if ( $p['start'] === null || $p['end'] === null ) continue;
        // Two-step: delete the marker's line, then insert the new lines at $p['start'].
        $requests = [
            [ 'deleteContentRange' => [ 'range' => tlt_cb_docs_range( $p['start'], $p['end'] ) ] ],
        ];
        // Insert in reverse order so each new line goes at $p['start'].
        $joined = implode( "\n", $lines ) . "\n";
        $requests[] = [ 'insertText' => [ 'location' => [ 'index' => $p['start'] ], 'text' => $joined ] ];
        return tlt_cb_docs_batch_update( $doc_id, $requests );
    }
    // Marker not found — silent no-op (mirrors GAS: expansion is best-effort).
    return true;
}

/**
 * Handle a conditional bracket section — [$start_tag]...[$end_tag]. If
 * $keep is true, only the two tag markers are removed. Otherwise the entire
 * block including both tags is removed.
 *
 * Uses replaceAllText for the tags-only case (simple) and content-range
 * deletion for the keep=false case.
 */
function tlt_cb_contract_handle_conditional( $doc_id, $start_tag, $end_tag, $keep ) {
    if ( $keep ) {
        return tlt_cb_docs_batch_update( $doc_id, [
            [ 'replaceAllText' => [ 'containsText' => [ 'text' => "[{$start_tag}]", 'matchCase' => true ], 'replaceText' => '' ] ],
            [ 'replaceAllText' => [ 'containsText' => [ 'text' => "[/{$end_tag}]", 'matchCase' => true ], 'replaceText' => '' ] ],
        ] );
    }
    // Delete-block variant: find the text range from first [start] to end of paragraph containing [/end].
    $paras = tlt_cb_contract_walk_paragraphs( $doc_id );
    if ( is_wp_error( $paras ) ) return $paras;
    $sp = null; $ep = null;
    foreach ( $paras as $p ) {
        if ( $sp === null && strpos( $p['text'], "[{$start_tag}]" ) !== false ) $sp = $p;
        if ( $sp !== null && strpos( $p['text'], "[/{$end_tag}]" ) !== false ) { $ep = $p; break; }
    }
    if ( $sp === null || $ep === null ) return true;
    // Delete from sp start to ep end.
    return tlt_cb_docs_batch_update( $doc_id, [
        [ 'deleteContentRange' => [ 'range' => tlt_cb_docs_range( $sp['start'], $ep['end'] ) ] ],
    ] );
}

/**
 * Format the <<Duties>> marker: replace with a list of lines where ALL-CAPS
 * lines become section headers (bold) and other lines are bulleted items.
 * Simplified vs GAS but functionally equivalent — a bit less precision on
 * spacing.
 */
function tlt_cb_contract_expand_duties( $doc_id, array $duties ) {
    if ( empty( $duties ) ) {
        return tlt_cb_contract_delete_marker_paragraph( $doc_id, '<<Duties>>' );
    }
    // Replace the marker paragraph with the duties lines (plain text join).
    $paras = tlt_cb_contract_walk_paragraphs( $doc_id );
    if ( is_wp_error( $paras ) ) return $paras;
    $marker_para = null;
    foreach ( $paras as $p ) if ( strpos( $p['text'], '<<Duties>>' ) !== false ) { $marker_para = $p; break; }
    if ( $marker_para === null ) return true;

    $start = $marker_para['start'];
    // Build text and remember which line indices need bold + which need bullet
    // via later styling passes.
    $joined = '';
    $ranges_by_kind = [ 'header' => [], 'bullet' => [] ];
    // Blake's rules: ALL-CAPS lines render as plain non-bold, non-bulleted
    // (section separators). Non-caps lines render as bulleted list items.
    $cursor = $start;
    foreach ( $duties as $line ) {
        // Docs API positions are code-unit indexed; use mb_strlen (chars).
        $len = mb_strlen( $line, 'UTF-8' );
        $is_caps = ( strtoupper( $line ) === $line && preg_match( '/[A-Z]/', $line ) );
        $ranges_by_kind[ $is_caps ? 'plain' : 'bullet' ][] = [ $cursor, $cursor + $len, $cursor + $len + 1 ];
        $joined .= $line . "\n";
        $cursor += $len + 1;
    }
    $requests = [
        [ 'deleteContentRange' => [ 'range' => tlt_cb_docs_range( $start, $marker_para['end'] ) ] ],
        [ 'insertText' => [ 'location' => [ 'index' => $start ], 'text' => $joined ] ],
    ];
    foreach ( $ranges_by_kind['bullet'] ?? [] as $rr ) {
        $requests[] = [ 'createParagraphBullets' => [
            'range' => tlt_cb_docs_range( $rr[0], $rr[2] ),
            'bulletPreset' => 'BULLET_DISC_CIRCLE_SQUARE',
        ] ];
    }
    return tlt_cb_docs_batch_update( $doc_id, $requests );
}

/**
 * Expand <<KeyDates>> into "Label DATE" lines (date portion bolded).
 */
function tlt_cb_contract_expand_key_dates( $doc_id, array $items, $marker = '<<KeyDates>>' ) {
    if ( empty( $items ) ) {
        return tlt_cb_contract_delete_marker_paragraph( $doc_id, $marker );
    }

    // For <<Compensation>> — use replaceAllText because Blake's template puts
    // the marker in a location my body walker doesn't reach. Prepend a
    // "COMPENSATION" header line then apply styling in a second pass by
    // finding each inserted paragraph via exact-text match.
    if ( $marker === '<<Compensation>>' ) {
        $lines = [ 'COMPENSATION' ];
        foreach ( $items as $it ) {
            $lines[] = trim( $it['label'] ) . ' ' . $it['date'];
        }
        $joined_plain = implode( "\n", $lines );
        $r = tlt_cb_docs_batch_update( $doc_id, [
            [ 'replaceAllText' => [
                'containsText' => [ 'text' => $marker, 'matchCase' => true ],
                'replaceText'  => $joined_plain,
            ] ],
        ] );
        if ( is_wp_error( $r ) ) return $r;

        // Second pass: walk paragraphs, find each line I just inserted by
        // exact text match, apply Century Gothic 10pt (bold on the header,
        // regular on the stipend lines).
        $paras = tlt_cb_contract_walk_paragraphs( $doc_id );
        if ( is_wp_error( $paras ) ) return $paras;
        $target_lines = array_flip( $lines );
        $style_requests = [];
        foreach ( $paras as $p ) {
            $trimmed = rtrim( $p['text'], "\n" );
            if ( ! isset( $target_lines[ $trimmed ] ) ) continue;
            if ( $p['start'] === null || $p['end'] === null ) continue;
            $start = $p['start'];
            $end   = $p['end'] - 1; // exclude the trailing newline
            if ( $end <= $start ) continue;
            $is_header = ( $trimmed === 'COMPENSATION' );
            $style_requests[] = [
                'updateTextStyle' => [
                    'range'     => tlt_cb_docs_range( $start, $end ),
                    'textStyle' => [
                        'weightedFontFamily' => [ 'fontFamily' => 'Century Gothic' ],
                        'fontSize'           => [ 'magnitude' => 10, 'unit' => 'PT' ],
                        'bold'               => $is_header,
                    ],
                    'fields' => 'weightedFontFamily,fontSize,bold',
                ],
            ];
        }
        if ( empty( $style_requests ) ) return true;
        return tlt_cb_docs_batch_update( $doc_id, $style_requests );
    }

    $paras = tlt_cb_contract_walk_paragraphs( $doc_id );
    if ( is_wp_error( $paras ) ) return $paras;
    $marker_para = null;
    foreach ( $paras as $p ) if ( strpos( $p['text'], $marker ) !== false ) { $marker_para = $p; break; }
    if ( $marker_para === null ) {
        // Last-resort fallback for other markers too.
        $joined_plain = '';
        foreach ( $items as $it ) {
            $joined_plain .= trim( $it['label'] ) . ' ' . $it['date'] . "\n";
        }
        return tlt_cb_docs_batch_update( $doc_id, [
            [ 'replaceAllText' => [
                'containsText' => [ 'text' => $marker, 'matchCase' => true ],
                'replaceText'  => rtrim( $joined_plain, "\n" ),
            ] ],
        ] );
    }

    $start   = $marker_para['start'];
    $joined  = '';
    $bolds   = [];
    $cursor  = $start;
    foreach ( $items as $it ) {
        $label = trim( $it['label'] );
        $date  = $it['date'];
        $line  = $label . ' ' . $date;
        $joined .= $line . "\n";
        // Docs API is code-unit indexed; use mb_strlen so em-dash ("—", 3
        // bytes but 1 code unit) doesn't drift the bold range forward.
        $len_label = mb_strlen( $label, 'UTF-8' );
        $len_line  = mb_strlen( $line,  'UTF-8' );
        // `bold_all` items (per-show headers "For X, Opens Y" in combined
        // contracts) get the ENTIRE line bolded so they visually separate
        // groups. Regular items only bold the date portion.
        if ( ! empty( $it['bold_all'] ) ) {
            $bolds[] = [ $cursor, $cursor + $len_line ];
        } else {
            $date_start = $cursor + $len_label + 1;
            $bolds[] = [ $date_start, $date_start + mb_strlen( $date, 'UTF-8' ) ];
        }
        $cursor += $len_line + 1;
    }
    $requests = [
        [ 'deleteContentRange' => [ 'range' => tlt_cb_docs_range( $start, $marker_para['end'] ) ] ],
        [ 'insertText' => [ 'location' => [ 'index' => $start ], 'text' => $joined ] ],
    ];
    foreach ( $bolds as $b ) {
        $requests[] = [ 'updateTextStyle' => [
            'range' => tlt_cb_docs_range( $b[0], $b[1] ),
            'textStyle' => [ 'bold' => true ],
            'fields' => 'bold',
        ] ];
    }
    return tlt_cb_docs_batch_update( $doc_id, $requests );
}

/**
 * Expand <<SpecialConditions>> — split multi-line special conditions block
 * into paragraphs. The marker paragraph itself gets replaced with the first
 * line; subsequent lines are inserted as siblings.
 */
function tlt_cb_contract_expand_special_conditions( $doc_id, $special ) {
    $lines = array_values( array_filter( array_map( 'trim', explode( "\n", $special ) ), function ( $x ) { return $x !== ''; } ) );
    if ( empty( $lines ) ) return tlt_cb_contract_delete_marker_paragraph( $doc_id, '<<SpecialConditions>>' );
    // Replace marker with joined lines.
    return tlt_cb_contract_replace_marker_with_lines( $doc_id, '<<SpecialConditions>>', $lines );
}

/**
 * Parse the multi-line Board named-range content into [{name, title}, …].
 *
 * Accepts either format:
 *   1) "Name, Title" one-liners where the title matches a board office keyword.
 *   2) Name and Title on adjacent lines (title-only line matches the keyword
 *      and attaches to the preceding name).
 * Any line that doesn't match a title pattern starts a new name-only member.
 */
function tlt_cb_contract_parse_board_members( $board_value ) {
    $lines = array_values( array_filter( array_map( 'trim', explode( "\n", (string) $board_value ) ), function ( $x ) { return $x !== ''; } ) );
    $title_regex = '/\b(president|treasurer|secretary|chair)\b/i';
    $members = [];
    foreach ( $lines as $line ) {
        if ( strpos( $line, ',' ) !== false ) {
            $parts = explode( ',', $line, 2 );
            $name_part  = trim( $parts[0] );
            $title_part = trim( $parts[1] );
            if ( $name_part !== '' && preg_match( $title_regex, $title_part ) ) {
                $members[] = [ 'name' => $name_part, 'title' => $title_part ];
                continue;
            }
        }
        if ( ! empty( $members ) && preg_match( $title_regex, $line ) ) {
            $members[ count( $members ) - 1 ]['title'] = $line;
            continue;
        }
        $members[] = [ 'name' => $line, 'title' => '' ];
    }
    return $members;
}

/**
 * Expand <<Board>> — one marker replaces the entire left-column sidebar:
 *   Board of Directors header  → board members from the Board named range
 *   Staff header               → Theatre-tab rows where col E is numeric AND
 *                                col B is non-empty, sorted by col E ascending
 *   Mission header             → paragraph text from Theatre col B
 *   Vision header              → paragraph text from Theatre col B
 *
 * Section labels come verbatim from Theatre rows 3, 5, 7, 9 col A, so Chris
 * can rename "TLT's Mission:" without touching code. Content styling is fixed:
 *   - Section headings   : 8.5pt bold  (4pt spaceAbove on all but the first)
 *   - Names              : 7pt regular
 *   - Titles             : 6.5pt italic
 *   - Mission/Vision text: 7pt regular
 *   - 1pt spaceBelow on the last paragraph of each member so entries breathe
 *
 * Template Doc must contain a single <<Board>> marker in the sidebar. All the
 * old markers (<<MAD>> / <<APD>> / <<TD>> / <<DD>> / <<ED>> / <<OM>> / <<PM>>
 * / <<Mission>> / <<Vision>>) plus their label paragraphs should be removed
 * from the template — they still get replaceAllText'd (as empty), harmlessly,
 * if left behind.
 */
function tlt_cb_contract_expand_board( $doc_id, $board_value ) {
    $theatre = tlt_callboard_sheet_rows( TLT_CALLBOARD_SHEET_ID, 'Theatre!A1:E30' );
    if ( is_wp_error( $theatre ) ) $theatre = [];

    // Section labels — verbatim from Theatre if present, else sensible defaults.
    $lbl_board   = tlt_cb_s( $theatre[2][0] ?? '' ) ?: 'Board of Directors';
    $lbl_mission = tlt_cb_s( $theatre[4][0] ?? '' ) ?: "TLT's Mission:";
    $lbl_vision  = tlt_cb_s( $theatre[6][0] ?? '' ) ?: "TLT's Vision:";
    $lbl_staff   = tlt_cb_s( $theatre[8][0] ?? '' ) ?: 'Staff';

    $mission_text = trim( tlt_cb_s( $theatre[4][1] ?? '' ) );
    $vision_text  = trim( tlt_cb_s( $theatre[6][1] ?? '' ) );

    // Staff rows: col E numeric = display order + "show on contracts" flag.
    // Rows without numeric E are omitted (Lead Carpenter, House Managers, etc.
    // don't get their own contract sidebar block). Empty col B rows are also
    // omitted so an unfilled role (like APD right now) just drops out.
    $staff_members = [];
    foreach ( $theatre as $r ) {
        $title = tlt_cb_s( $r[0] ?? '' );
        $name  = trim( tlt_cb_s( $r[1] ?? '' ) );
        $order = tlt_cb_s( $r[4] ?? '' );
        if ( ! is_numeric( $order ) || $name === '' || $title === '' ) continue;
        $staff_members[] = [ 'name' => $name, 'title' => $title, 'order' => (float) $order ];
    }
    usort( $staff_members, function ( $a, $b ) { return $a['order'] <=> $b['order']; } );

    $board_members = tlt_cb_contract_parse_board_members( $board_value );

    // Compose text + track offsets for each style-relevant range.
    $text = '';
    $heading_ranges = []; // [start, end] — bold 8.5pt, +spaceAbove if not first
    $name_ranges    = []; // [start, end] — 7pt regular
    $title_ranges   = []; // [start, end] — 6.5pt italic
    $body_ranges    = []; // [start, end] — 7pt regular (mission/vision)
    $member_last_para_ranges = []; // 1pt spaceBelow after each member
    $section_heading_para_ranges = []; // 4pt spaceAbove on all but first heading

    $append_heading = function ( $label, $is_first ) use ( &$text, &$heading_ranges, &$section_heading_para_ranges ) {
        $start = mb_strlen( $text, 'UTF-8' );
        $text .= $label . "\n";
        $end   = mb_strlen( $text, 'UTF-8' ) - 1; // exclude trailing \n from text-style range
        $heading_ranges[] = [ $start, $end ];
        if ( ! $is_first ) $section_heading_para_ranges[] = [ $start, $end + 1 ];
    };
    $append_member = function ( $m ) use ( &$text, &$name_ranges, &$title_ranges, &$member_last_para_ranges ) {
        $ns = mb_strlen( $text, 'UTF-8' );
        $text .= $m['name'] . "\n";
        $ne = mb_strlen( $text, 'UTF-8' ) - 1;
        $name_ranges[] = [ $ns, $ne ];
        $last_start = $ns; $last_end = $ne + 1;
        if ( trim( (string) ( $m['title'] ?? '' ) ) !== '' ) {
            $ts = mb_strlen( $text, 'UTF-8' );
            $text .= $m['title'] . "\n";
            $te = mb_strlen( $text, 'UTF-8' ) - 1;
            $title_ranges[] = [ $ts, $te ];
            $last_start = $ts; $last_end = $te + 1;
        }
        $member_last_para_ranges[] = [ $last_start, $last_end ];
    };
    $append_body = function ( $body ) use ( &$text, &$body_ranges ) {
        if ( trim( (string) $body ) === '' ) return;
        $s = mb_strlen( $text, 'UTF-8' );
        $text .= $body . "\n";
        $e = mb_strlen( $text, 'UTF-8' ) - 1;
        $body_ranges[] = [ $s, $e ];
    };

    // Board section
    if ( ! empty( $board_members ) ) {
        $append_heading( $lbl_board, true );
        foreach ( $board_members as $m ) $append_member( $m );
    }
    // Staff section
    if ( ! empty( $staff_members ) ) {
        $append_heading( $lbl_staff, empty( $board_members ) );
        foreach ( $staff_members as $m ) $append_member( [ 'name' => $m['name'], 'title' => $m['title'] ] );
    }
    // Mission section
    if ( $mission_text !== '' ) {
        $append_heading( $lbl_mission, empty( $board_members ) && empty( $staff_members ) );
        $append_body( $mission_text );
    }
    // Vision section
    if ( $vision_text !== '' ) {
        $append_heading( $lbl_vision, empty( $board_members ) && empty( $staff_members ) && $mission_text === '' );
        $append_body( $vision_text );
    }

    if ( $text === '' ) return tlt_cb_contract_delete_marker_paragraph( $doc_id, '<<Board>>' );

    // Locate the marker paragraph so we can splice content in place.
    $paras = tlt_cb_contract_walk_paragraphs( $doc_id );
    if ( is_wp_error( $paras ) ) return $paras;
    $marker_para = null;
    foreach ( $paras as $p ) {
        if ( $p['start'] !== null && strpos( $p['text'], '<<Board>>' ) !== false ) {
            $marker_para = $p;
            break;
        }
    }
    if ( ! $marker_para ) return true;
    $insert_start = $marker_para['start'];

    $requests = [
        [ 'deleteContentRange' => [ 'range' => tlt_cb_docs_range( $marker_para['start'], $marker_para['end'] ) ] ],
        [ 'insertText' => [ 'location' => [ 'index' => $insert_start ], 'text' => $text ] ],
    ];
    // Headings: 8.5pt bold.
    foreach ( $heading_ranges as $r ) {
        $requests[] = [ 'updateTextStyle' => [
            'range'     => tlt_cb_docs_range( $insert_start + $r[0], $insert_start + $r[1] ),
            'textStyle' => [ 'fontSize' => [ 'magnitude' => 8.5, 'unit' => 'PT' ], 'bold' => true, 'italic' => false ],
            'fields'    => 'fontSize,bold,italic',
        ] ];
    }
    // Names: 7pt regular.
    foreach ( $name_ranges as $r ) {
        $requests[] = [ 'updateTextStyle' => [
            'range'     => tlt_cb_docs_range( $insert_start + $r[0], $insert_start + $r[1] ),
            'textStyle' => [ 'fontSize' => [ 'magnitude' => 7, 'unit' => 'PT' ], 'bold' => false, 'italic' => false ],
            'fields'    => 'fontSize,bold,italic',
        ] ];
    }
    // Titles: 6.5pt italic.
    foreach ( $title_ranges as $r ) {
        $requests[] = [ 'updateTextStyle' => [
            'range'     => tlt_cb_docs_range( $insert_start + $r[0], $insert_start + $r[1] ),
            'textStyle' => [ 'fontSize' => [ 'magnitude' => 6.5, 'unit' => 'PT' ], 'bold' => false, 'italic' => true ],
            'fields'    => 'fontSize,bold,italic',
        ] ];
    }
    // Mission/Vision body text: 7pt regular.
    foreach ( $body_ranges as $r ) {
        $requests[] = [ 'updateTextStyle' => [
            'range'     => tlt_cb_docs_range( $insert_start + $r[0], $insert_start + $r[1] ),
            'textStyle' => [ 'fontSize' => [ 'magnitude' => 7, 'unit' => 'PT' ], 'bold' => false, 'italic' => false ],
            'fields'    => 'fontSize,bold,italic',
        ] ];
    }
    // 1pt spaceBelow on the last paragraph of each member.
    foreach ( $member_last_para_ranges as $r ) {
        $requests[] = [ 'updateParagraphStyle' => [
            'range'          => tlt_cb_docs_range( $insert_start + $r[0], $insert_start + $r[1] ),
            'paragraphStyle' => [ 'spaceBelow' => [ 'magnitude' => 1, 'unit' => 'PT' ] ],
            'fields'         => 'spaceBelow',
        ] ];
    }
    // 4pt spaceAbove on all section headings except the first.
    foreach ( $section_heading_para_ranges as $r ) {
        $requests[] = [ 'updateParagraphStyle' => [
            'range'          => tlt_cb_docs_range( $insert_start + $r[0], $insert_start + $r[1] ),
            'paragraphStyle' => [ 'spaceAbove' => [ 'magnitude' => 4, 'unit' => 'PT' ] ],
            'fields'         => 'spaceAbove',
        ] ];
    }

    return tlt_cb_docs_batch_update( $doc_id, $requests );
}

/**
 * Collapse duplicate immediate-repeat tags like "<<Show>><<Show>>" → "<<Show>>".
 * Runs several tags up to 5 times each.
 */
function tlt_cb_contract_collapse_duplicate_tags( $doc_id ) {
    $tags = [ '<<Show>>', '<<Role>>', '<<Name>>', '<<Character>>', '<<Date>>' ];
    $requests = [];
    foreach ( $tags as $t ) {
        for ( $i = 0; $i < 5; $i++ ) {
            $requests[] = [ 'replaceAllText' => [
                'containsText' => [ 'text' => $t . $t, 'matchCase' => true ],
                'replaceText'  => $t,
            ] ];
        }
    }
    return tlt_cb_docs_batch_update( $doc_id, $requests );
}

/**
 * Hide empty staff blocks. Walks paragraphs; for any paragraph whose exact
 * text is one of the known role labels (MAD, TD, AD, DD, ED, OM, PM), if
 * the paragraph directly ABOVE is empty (because <<MAD>> was replaced with
 * ''), delete both. Ported from _hideEmptyStaffBlocks.
 */
function tlt_cb_contract_hide_empty_staff_blocks( $doc_id ) {
    $labels = [
        'Managing Artistic Director',
        'Associate Producing Director',
        'Associate Director',
        'Technical Director',
        'Development Director',
        'Education Director',
        'Office Manager',
        'Production Manager',
    ];
    $paras = tlt_cb_contract_walk_paragraphs( $doc_id );
    if ( is_wp_error( $paras ) ) return $paras;

    // Build list of ranges to delete (from empty-tag paragraph start to label paragraph end).
    // Walk in REVERSE index order so deletions don't shift earlier ones.
    $to_delete = [];
    for ( $i = 1; $i < count( $paras ); $i++ ) {
        $curr = $paras[ $i ];
        $prev = $paras[ $i - 1 ];
        $curr_trim = trim( rtrim( $curr['text'], "\n" ) );
        $prev_trim = trim( rtrim( $prev['text'], "\n" ) );
        if ( in_array( $curr_trim, $labels, true ) && $prev_trim === '' && $prev['start'] !== null && $curr['end'] !== null ) {
            $to_delete[] = [ 'start' => $prev['start'], 'end' => $curr['end'] ];
        }
    }
    usort( $to_delete, function ( $a, $b ) { return $b['start'] - $a['start']; } );
    $requests = [];
    foreach ( $to_delete as $d ) {
        $requests[] = [ 'deleteContentRange' => [ 'range' => tlt_cb_docs_range( $d['start'], $d['end'] ) ] ];
    }
    if ( empty( $requests ) ) return true;
    return tlt_cb_docs_batch_update( $doc_id, $requests );
}

/**
 * Update Production Teams or Actors row for a contract status change.
 * @param string $status   'Generated' | 'Sent for Signature' | 'Not Started'
 * @param string $link_or_id  For 'Generated' = doc URL. For 'Sent for Signature' = OpenSign ID.
 * @param array  $opts     [ 'combinedContractId' => 'CC-...' | null ]
 */
function tlt_cb_contract_update_status( $show, $role, $first_name, $status, $link_or_id, array $opts = [] ) {
    $is_actor = strcasecmp( $role, 'Actor' ) === 0;
    $tab = $is_actor ? 'Actors' : "'Production Teams'";
    $rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_SHEET_ID, "{$tab}!A2:S" );
    if ( is_wp_error( $rows ) ) return $rows;

    $target_row = 0;
    foreach ( $rows as $i => $r ) {
        if ( tlt_cb_s( $r[0] ?? '' ) !== $show ) continue;
        if ( strcasecmp( tlt_cb_s( $r[2] ?? '' ), $first_name ) !== 0 ) continue;
        if ( ! $is_actor && tlt_cb_s( $r[1] ?? '' ) !== $role ) continue;
        $target_row = $i + 2; // A2 offset
        break;
    }
    if ( $target_row === 0 ) return new WP_Error( 'no_status_row', "No {$tab} row for ({$show}, {$role}, {$first_name})" );

    // Compute updates.
    $sheet_tab = $is_actor ? 'Actors' : 'Production Teams';
    $today = ( new DateTime( 'now', new DateTimeZone( 'America/Los_Angeles' ) ) )->format( 'Y-m-d' );

    // Grab existing combined ID from col S.
    $target_row_data = $rows[ $target_row - 2 ];
    $existing_ccid = tlt_cb_s( $target_row_data[18] ?? '' );

    $updates = [
        [ "'{$sheet_tab}'!I{$target_row}", [[ $status ]] ],
        [ "'{$sheet_tab}'!L{$target_row}", [[ $link_or_id ]] ],
    ];
    if ( $status === 'Sent for Signature' ) {
        $updates[] = [ "'{$sheet_tab}'!J{$target_row}", [[ $today ]] ];
    }
    if ( ! empty( $opts['combinedContractId'] ) ) {
        $ccid = $opts['combinedContractId'];
        $updates[] = [ "'{$sheet_tab}'!S{$target_row}", [[ $ccid ]] ];
        $existing_ccid = $ccid; // propagate below
    }

    // Apply target row updates first.
    foreach ( $updates as $u ) {
        $r = tlt_callboard_sheets_write( TLT_CALLBOARD_SHEET_ID, $u[0], $u[1] );
        if ( is_wp_error( $r ) ) return $r;
    }

    // Propagate to other rows with the same combined ID (across both tabs).
    if ( $existing_ccid !== '' ) {
        foreach ( [ [ 'Production Teams', "'Production Teams'!A2:S" ], [ 'Actors', 'Actors!A2:S' ] ] as $tab_pair ) {
            $tabname = $tab_pair[0];
            $tab_rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_SHEET_ID, $tab_pair[1] );
            if ( is_wp_error( $tab_rows ) ) continue;
            foreach ( $tab_rows as $j => $rr ) {
                if ( tlt_cb_s( $rr[18] ?? '' ) !== $existing_ccid ) continue;
                $rn = $j + 2;
                if ( $tabname === $sheet_tab && $rn === $target_row ) continue; // already done
                tlt_callboard_sheets_write( TLT_CALLBOARD_SHEET_ID, "'{$tabname}'!I{$rn}", [[ $status ]] );
                tlt_callboard_sheets_write( TLT_CALLBOARD_SHEET_ID, "'{$tabname}'!L{$rn}", [[ $link_or_id ]] );
                if ( $status === 'Sent for Signature' ) {
                    tlt_callboard_sheets_write( TLT_CALLBOARD_SHEET_ID, "'{$tabname}'!J{$rn}", [[ $today ]] );
                }
            }
        }
    }
    return true;
}

/**
 * Find (or create) the "<season> Contracts" folder under the contract root
 * and the show subfolder inside it. Returns show subfolder ID.
 */
function tlt_cb_contract_folder_for_show( $season_long, $show ) {
    $season_folder = tlt_cb_drive_folder_or_create( TLT_CALLBOARD_CONTRACT_ROOT_FOLDER_ID, "{$season_long} Contracts" );
    if ( is_wp_error( $season_folder ) ) return $season_folder;
    $show_folder = tlt_cb_drive_folder_or_create( $season_folder, $show );
    if ( is_wp_error( $show_folder ) ) return $show_folder;
    return $show_folder;
}

/**
 * Full flow: copy template → replace tags → expand markers → conditionals →
 * dedup → hide staff blocks → return doc URL. Sheet update to "Generated"
 * happens in the calling endpoint.
 */
function tlt_cb_contract_generate( $show, $role, $first_name, $last_name, $character = '' ) {
    $data = tlt_cb_contract_assemble( $show, $role, $character );
    if ( is_wp_error( $data ) ) return $data;
    if ( $data['season_long'] === '' ) return new WP_Error( 'no_season_long', 'Season "Current Season Long" is not set.' );

    $show_folder = tlt_cb_contract_folder_for_show( $data['season_long'], $show );
    if ( is_wp_error( $show_folder ) ) return $show_folder;

    // Actors get a sub-subfolder.
    $is_actor = strcasecmp( $role, 'Actor' ) === 0;
    if ( $is_actor ) {
        $show_folder = tlt_cb_drive_folder_or_create( $show_folder, 'Actor Contracts' );
        if ( is_wp_error( $show_folder ) ) return $show_folder;
    }

    $full_name = trim( $first_name . ' ' . $last_name );
    $doc_name  = $full_name . ' - ' . $role . ' - ' . $show;

    // Trash any prior doc with the same name.
    $existing = tlt_cb_drive_find_in_folder( $show_folder, $doc_name );
    if ( is_wp_error( $existing ) ) return $existing;
    foreach ( $existing as $f ) tlt_cb_drive_trash( $f['id'] );

    // Copy template.
    $template_id = tlt_cb_contract_template_id( $data['template'] );
    $file = tlt_cb_drive_copy( $template_id, $show_folder, $doc_name );
    if ( is_wp_error( $file ) ) return $file;
    $doc_id = $file['id'];

    // Update the <<Name>> replacement now that we know the doc's target.
    $reps = $data['replacements'];
    $reps['<<Name>>'] = $full_name;

    // Phase 1: simple replaceAllText for all tags.
    $requests = [];
    foreach ( $reps as $tag => $value ) {
        $requests[] = [ 'replaceAllText' => [
            'containsText' => [ 'text' => $tag, 'matchCase' => true ],
            'replaceText'  => (string) $value,
        ] ];
    }
    $r = tlt_cb_docs_batch_update( $doc_id, $requests );
    if ( is_wp_error( $r ) ) return $r;

    // Phase 2: multi-paragraph expansions.
    tlt_cb_contract_expand_duties( $doc_id, $data['duties_content']['duties'] );
    tlt_cb_contract_expand_key_dates( $doc_id, $data['key_date_items'] );
    tlt_cb_contract_expand_special_conditions( $doc_id, $data['duties_content']['specialConditions'] );
    tlt_cb_contract_expand_board( $doc_id, $data['board_value'] );

    // Single-show contracts don't use <<Compensation>>.
    tlt_cb_contract_delete_marker_paragraph( $doc_id, '<<Compensation>>' );

    // Phase 3: conditional bracket sections.
    tlt_cb_contract_handle_conditional( $doc_id, 'BudgetSection', 'BudgetSection', $data['has_budget1'] );
    tlt_cb_contract_handle_conditional( $doc_id, 'Budget2', 'Budget2', $data['has_budget2'] );
    tlt_cb_contract_handle_conditional( $doc_id, 'Budget3', 'Budget3', $data['has_budget3'] );
    tlt_cb_contract_handle_conditional( $doc_id, 'SpecialConditionsSection', 'SpecialConditionsSection', $data['has_special'] );
    tlt_cb_contract_handle_conditional( $doc_id, 'KeyDatesSection', 'KeyDatesSection', $data['has_key_dates'] );

    // Phase 4: cleanup.
    tlt_cb_contract_collapse_duplicate_tags( $doc_id );
    tlt_cb_contract_hide_empty_staff_blocks( $doc_id );

    $url = tlt_cb_doc_url( $doc_id );

    // Update status.
    $r = tlt_cb_contract_update_status( $show, $role, $first_name, 'Generated', $url );
    if ( is_wp_error( $r ) ) return $r;

    // Look up email in Contactbook — the frontend takes `generated.email` from
    // this response and passes it to /contract-send. If unset, the send fails
    // with "missing email" downstream.
    $email = tlt_cb_contact_email_lookup( $first_name, $last_name, $show ?? ( isset( $shows ) ? $shows[0] : '' ), $role );

    return [
        'success'      => true,
        'docId'        => $doc_id,
        'docUrl'       => $url,
        'docName'      => $doc_name,
        'email'        => $email,
        'fullName'     => $full_name,
        'show'         => $show,
        'role'         => $role,
        'firstName'    => $first_name,
        'lastName'     => $last_name,
        'templateType' => $data['template'],
    ];
}

/**
 * Email lookup for a contract row. Tries Production Teams / Actors first
 * (col H = index 7) then falls back to Contactbook first+last match.
 * Returns '' if nothing found. Used by contract generate response so the
 * frontend can pass email to /contract-send.
 */
function tlt_cb_contact_email_lookup( $first, $last, $show = '', $role = '' ) {
    $first_lc = strtolower( tlt_cb_s( $first ) );
    $last_lc  = strtolower( tlt_cb_s( $last ) );
    $show_s   = tlt_cb_s( $show );
    $role_s   = tlt_cb_s( $role );
    $is_actor = strcasecmp( $role_s, 'Actor' ) === 0;

    // 1. Try the row on Production Teams / Actors (canonical for that show).
    if ( $show_s !== '' && $first !== '' ) {
        $tab_range = $is_actor ? 'Actors!A2:H' : "'Production Teams'!A2:H";
        $rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_SHEET_ID, $tab_range );
        if ( ! is_wp_error( $rows ) ) {
            foreach ( $rows as $r ) {
                if ( tlt_cb_s( $r[0] ?? '' ) !== $show_s ) continue;
                if ( strtolower( tlt_cb_s( $r[2] ?? '' ) ) !== $first_lc ) continue;
                if ( $last_lc !== '' && strtolower( tlt_cb_s( $r[4] ?? '' ) ) !== $last_lc ) continue;
                if ( ! $is_actor && $role_s !== '' && tlt_cb_s( $r[1] ?? '' ) !== $role_s ) continue;
                $email = tlt_cb_s( $r[7] ?? '' );
                if ( $email !== '' ) return $email;
                break;
            }
        }
    }

    // 2. Fall back to Contactbook by first + last.
    $rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_CONTACTBOOK_ID, 'Contactbook!A2:H' );
    if ( is_wp_error( $rows ) ) return '';
    foreach ( $rows as $r ) {
        if ( strtolower( tlt_cb_s( $r[1] ?? '' ) ) === $first_lc
          && strtolower( tlt_cb_s( $r[3] ?? '' ) ) === $last_lc ) {
            return tlt_cb_s( $r[7] ?? '' );
        }
    }
    return '';
}

/**
 * Combined generation. Same core doc; different naming, folder, replacements
 * for compensation, and combined contract ID for status propagation.
 */
function tlt_cb_contract_generate_combined( array $shows, $role, $first_name, $last_name, $character = '' ) {
    if ( count( $shows ) === 1 ) return tlt_cb_contract_generate( $shows[0], $role, $first_name, $last_name, $character );

    // Assemble against the first show for the base replacements.
    $first_show = $shows[0];
    $data = tlt_cb_contract_assemble( $first_show, $role, $character );
    if ( is_wp_error( $data ) ) return $data;
    if ( $data['season_long'] === '' ) return new WP_Error( 'no_season_long', 'Season "Current Season Long" is not set.' );

    // Season "Contracts" root → "Combined Contracts" subfolder.
    $season_folder = tlt_cb_drive_folder_or_create( TLT_CALLBOARD_CONTRACT_ROOT_FOLDER_ID, "{$data['season_long']} Contracts" );
    if ( is_wp_error( $season_folder ) ) return $season_folder;
    $combined_folder = tlt_cb_drive_folder_or_create( $season_folder, 'Combined Contracts' );
    if ( is_wp_error( $combined_folder ) ) return $combined_folder;

    $full_name = trim( $first_name . ' ' . $last_name );
    $doc_name  = $full_name . ' - ' . $role . ' - Combined (' . count( $shows ) . ' shows)';
    $existing = tlt_cb_drive_find_in_folder( $combined_folder, $doc_name );
    if ( is_wp_error( $existing ) ) return $existing;
    foreach ( $existing as $f ) tlt_cb_drive_trash( $f['id'] );

    // Copy template.
    $template_id = tlt_cb_contract_template_id( $data['template'] );
    $file = tlt_cb_drive_copy( $template_id, $combined_folder, $doc_name );
    if ( is_wp_error( $file ) ) return $file;
    $doc_id = $file['id'];

    // Modified replacements for combined.
    $shows_list = count( $shows ) === 2
        ? $shows[0] . ' and ' . $shows[1]
        : implode( ', ', array_slice( $shows, 0, -1 ) ) . ', and ' . $shows[ count( $shows ) - 1 ];
    $reps = $data['replacements'];
    $reps['<<Show>>']         = $shows_list;
    $reps['<<CombinedShow>>'] = 'the ' . $data['season_long'] . ' season';
    $reps['<<Performances>>'] = '';
    // Stipend: sum across shows.
    $total_stipend_num = 0.0; $any_numeric = false;
    foreach ( $shows as $sh ) {
        $b = tlt_cb_contract_budget_row( $sh, $role );
        if ( is_numeric( $b['stipend'] ) ) { $total_stipend_num += (float) $b['stipend']; $any_numeric = true; }
    }
    if ( $any_numeric ) $reps['<<Stipend>>'] = tlt_cb_contract_fmt_currency( $total_stipend_num );
    $reps['<<Name>>']           = $full_name;
    $reps['<<RehearsalStart>>'] = 'See per-show dates below';
    $reps['<<Closing>>']        = 'See per-show dates below';

    // Build combined key dates: per-show header line + items.
    $combined_key_items = [];
    foreach ( $shows as $sh ) {
        $sh_data = tlt_cb_contract_assemble( $sh, $role, $character );
        if ( is_wp_error( $sh_data ) ) continue;
        // Header row: "For <show>, Opens <MMM D, YYYY>"
        $opens = '';
        foreach ( tlt_callboard_sheet_rows( TLT_CALLBOARD_SHEET_ID, 'Dates!A2:H' ) as $r ) {
            if ( tlt_cb_s( $r[0] ?? '' ) === $sh && tlt_cb_s( $r[1] ?? '' ) === 'Opening Performance' ) {
                $dt = tlt_cb_parse_date( $r[4] ?? '' );
                if ( $dt ) $opens = $dt->format( 'M j, Y' );
                break;
            }
        }
        $combined_key_items[] = [
            'label'    => "For {$sh}, Opens",
            'date'     => $opens,
            'bold_all' => true,
        ];
        foreach ( $sh_data['key_date_items'] as $it ) $combined_key_items[] = $it;
    }

    // Compensation items: per-show stipend + season total. Always emit the
    // total line even if some/all stipends are TBD — Blake wants that row
    // present so the signature-page $ amount is unambiguously the "total".
    $comp_items = [];
    foreach ( $shows as $sh ) {
        $b = tlt_cb_contract_budget_row( $sh, $role );
        $s = $b['stipend'] !== '' ? tlt_cb_contract_fmt_currency( $b['stipend'] ) : 'TBD';
        $comp_items[] = [ 'label' => "{$sh} stipend:", 'date' => $s ];
    }
    $total_str = $any_numeric ? tlt_cb_contract_fmt_currency( $total_stipend_num ) : 'TBD';
    $comp_items[] = [ 'label' => 'Season total:', 'date' => $total_str, 'bold_all' => true ];

    // Phase 1: simple replaceAllText.
    $requests = [];
    foreach ( $reps as $tag => $value ) {
        $requests[] = [ 'replaceAllText' => [
            'containsText' => [ 'text' => $tag, 'matchCase' => true ],
            'replaceText'  => (string) $value,
        ] ];
    }
    $r = tlt_cb_docs_batch_update( $doc_id, $requests );
    if ( is_wp_error( $r ) ) return $r;

    // Phase 2: expansions.
    tlt_cb_contract_expand_duties( $doc_id, $data['duties_content']['duties'] );
    tlt_cb_contract_expand_key_dates( $doc_id, $combined_key_items );
    tlt_cb_contract_expand_special_conditions( $doc_id, $data['duties_content']['specialConditions'] );
    tlt_cb_contract_expand_board( $doc_id, $data['board_value'] );

    // Combined uses <<Compensation>>.
    tlt_cb_contract_expand_key_dates( $doc_id, $comp_items, '<<Compensation>>' );

    // Phase 3: conditionals. For combined contracts we ALWAYS keep the
    // BudgetSection because <<Compensation>> lives inside it in the
    // template — deleting the section when has_budget1 is false (e.g., a
    // role with no per-show budget) would nuke the just-expanded
    // compensation lines. The individual budget rows still get replaced
    // with empty strings by replaceAllText — cosmetic, not functional.
    tlt_cb_contract_handle_conditional( $doc_id, 'BudgetSection', 'BudgetSection', true );
    tlt_cb_contract_handle_conditional( $doc_id, 'Budget2', 'Budget2', $data['has_budget2'] );
    tlt_cb_contract_handle_conditional( $doc_id, 'Budget3', 'Budget3', $data['has_budget3'] );
    tlt_cb_contract_handle_conditional( $doc_id, 'SpecialConditionsSection', 'SpecialConditionsSection', $data['has_special'] );
    tlt_cb_contract_handle_conditional( $doc_id, 'KeyDatesSection', 'KeyDatesSection', ! empty( $combined_key_items ) );

    // Phase 4: cleanup.
    tlt_cb_contract_collapse_duplicate_tags( $doc_id );
    tlt_cb_contract_hide_empty_staff_blocks( $doc_id );

    $url = tlt_cb_doc_url( $doc_id );
    $combined_id = 'CC-' . strtoupper( substr( bin2hex( random_bytes( 6 ) ), 0, 12 ) );

    foreach ( $shows as $sh ) {
        $r = tlt_cb_contract_update_status( $sh, $role, $first_name, 'Generated', $url, [ 'combinedContractId' => $combined_id ] );
        if ( is_wp_error( $r ) ) return $r;
    }

    $email = tlt_cb_contact_email_lookup( $first_name, $last_name, $show ?? ( isset( $shows ) ? $shows[0] : '' ), $role );

    return [
        'success'            => true,
        'docId'              => $doc_id,
        'docUrl'             => $url,
        'docName'            => $doc_name,
        'email'              => $email,
        'fullName'           => $full_name,
        'shows'              => $shows,
        'role'               => $role,
        'firstName'          => $first_name,
        'lastName'           => $last_name,
        'templateType'       => $data['template'],
        'combinedContractId' => $combined_id,
    ];
}

/* -----  Contract SEND flow (Docs → PDF → OpenSign → email)  --------------- */

/**
 * Export a Google Doc as PDF via drive.google.com/document/d/{id}/export.
 * Returns raw PDF bytes.
 */
function tlt_cb_contract_export_pdf( $doc_id ) {
    $token = tlt_callboard_google_access_token();
    if ( is_wp_error( $token ) ) return $token;
    $url = 'https://docs.google.com/document/d/' . $doc_id . '/export?format=pdf';
    $resp = wp_remote_get( $url, [
        'timeout' => 90,
        'headers' => [ 'Authorization' => 'Bearer ' . $token ],
    ] );
    if ( is_wp_error( $resp ) ) return $resp;
    $code = wp_remote_retrieve_response_code( $resp );
    $body = wp_remote_retrieve_body( $resp );
    if ( $code < 200 || $code >= 300 ) return new WP_Error( 'pdf_export_http', "PDF export returned $code" );
    return $body;
}

/**
 * Cheap PDF page count — same trick as GAS: count /Page tokens (as byte
 * sequences). Fallback to 3 if we can't detect.
 */
function tlt_cb_contract_pdf_page_count( $pdf_bytes ) {
    if ( ! is_string( $pdf_bytes ) || $pdf_bytes === '' ) return 3;
    preg_match_all( '#/Type\s*/Page[^s]#', $pdf_bytes, $m );
    $n = count( $m[0] );
    return $n > 0 ? $n : 3;
}

/**
 * OpenSign widget layout per template type — port of SIGNATURE_WIDGETS.
 * `page` is added at call time based on PDF page count.
 */
function tlt_cb_contract_opensign_widgets( $template_type ) {
    $checkbox = function ( $x, $y, $w, $h, $value ) {
        return [
            'type' => 'checkbox', 'x' => $x, 'y' => $y, 'w' => $w, 'h' => $h,
            'name' => 'payment_preference', 'value' => $value,
            'options' => [ 'hidelabel' => true, 'validation' => [ 'minselections' => 0, 'maxselections' => 1 ] ],
        ];
    };
    $sig = function ( $x, $y, $w, $h, $required = true ) {
        return [ 'type' => 'signature', 'x' => $x, 'y' => $y, 'w' => $w, 'h' => $h, 'options' => [ 'required' => $required ] ];
    };
    $date = function ( $x, $y, $w, $h ) {
        return [
            'type' => 'date', 'x' => $x, 'y' => $y, 'w' => $w, 'h' => $h,
            'options' => [ 'required' => true, 'signing_date' => true, 'format' => 'MMMM dd, yyyy' ],
        ];
    };
    $donation = [
        'type' => 'textbox', 'x' => 431, 'y' => 228, 'w' => 37, 'h' => 13,
        'name' => 'donation_amount', 'options' => [ 'fontsize' => 10 ],
    ];

    switch ( $template_type ) {
        case 'Actor':
            return [ $sig( 120, 115, 180, 20, true ), $date( 351, 115, 100, 20 ), $sig( 120, 170, 180, 20, false ) ];
        case 'Operator':
            return [ $sig( 120, 114, 180, 20, true ), $date( 350, 114, 100, 20 ) ];
        case 'Director':
        case 'General':
        default:
            return [
                $checkbox( 83, 150, 11, 9, 'option1' ),
                $checkbox( 83, 183, 11, 7, 'option2' ),
                $checkbox( 83, 202, 11, 9, 'option3' ),
                $checkbox( 83, 234, 12, 9, 'option4' ),
                $donation,
                $sig( 122, 326, 180, 15, true ),
                $date( 348, 326, 100, 15 ),
            ];
    }
}

/**
 * POST /createcontact + POST /createdocument to OpenSign. Returns
 * [ 'openSignId' => ..., 'signingUrl' => ... ] or WP_Error.
 */
function tlt_cb_contract_opensign_send( $doc_name, $pdf_bytes, $email, $full_name, $template_type, $combined = false ) {
    if ( ! defined( 'TLT_CALLBOARD_OPENSIGN_KEY' ) || TLT_CALLBOARD_OPENSIGN_KEY === '' ) {
        return new WP_Error( 'opensign_not_configured',
            'OpenSign not configured. Add `define( "TLT_CALLBOARD_OPENSIGN_KEY", "opensign.…" );` to wp-config.php.' );
    }
    $headers = [
        'Content-Type' => 'application/json',
        'x-api-token'  => TLT_CALLBOARD_OPENSIGN_KEY,
    ];
    // 1. Create contact.
    $r1 = wp_remote_post( TLT_CALLBOARD_OPENSIGN_URL . '/createcontact', [
        'timeout' => 30,
        'headers' => $headers,
        'body'    => wp_json_encode( [ 'name' => $full_name, 'email' => $email ] ),
    ] );
    if ( is_wp_error( $r1 ) ) return $r1;
    $b1 = wp_remote_retrieve_body( $r1 );
    $c1 = json_decode( $b1, true );
    if ( empty( $c1['objectId'] ) ) return new WP_Error( 'opensign_contact', "OpenSign createcontact failed: $b1" );
    $contact_id = $c1['objectId'];

    // 2. Create document with widgets stamped on last page.
    $pages = tlt_cb_contract_pdf_page_count( $pdf_bytes );
    $widgets = array_map( function ( $w ) use ( $pages ) { $w['page'] = $pages; return $w; },
        tlt_cb_contract_opensign_widgets( $template_type ) );
    $r2 = wp_remote_post( TLT_CALLBOARD_OPENSIGN_URL . '/createdocument', [
        'timeout' => 60,
        'headers' => $headers,
        'body'    => wp_json_encode( [
            'file'    => base64_encode( $pdf_bytes ),
            'title'   => $doc_name,
            'note'    => 'Please review and sign your Tacoma Little Theatre ' . ( $combined ? 'combined contract' : 'contract' ) . '.',
            'signers' => [ [
                'objectId' => $contact_id,
                'name'     => $full_name,
                'email'    => $email,
                'widgets'  => $widgets,
            ] ],
        ] ),
    ] );
    if ( is_wp_error( $r2 ) ) return $r2;
    $b2 = wp_remote_retrieve_body( $r2 );
    $code2 = wp_remote_retrieve_response_code( $r2 );
    if ( $code2 !== 200 && $code2 !== 201 ) return new WP_Error( 'opensign_doc', "OpenSign createdocument returned $code2: $b2" );
    $c2 = json_decode( $b2, true );
    if ( empty( $c2['objectId'] ) ) return new WP_Error( 'opensign_doc', "OpenSign createdocument had no objectId: $b2" );
    return [
        'openSignId' => $c2['objectId'],
        'signingUrl' => $c2['signurl'] ?? '',
    ];
}

/**
 * Send flow entry point. Called after generate: exports PDF, sends to
 * OpenSign, updates sheet to "Sent for Signature", fires bio email.
 */
function tlt_cb_contract_send( $doc_id, $doc_name, $email, $full_name, $show, $role, $first_name, $last_name, $template_type ) {
    $pdf = tlt_cb_contract_export_pdf( $doc_id );
    if ( is_wp_error( $pdf ) ) return $pdf;
    $os = tlt_cb_contract_opensign_send( $doc_name, $pdf, $email, $full_name, $template_type, false );
    if ( is_wp_error( $os ) ) return $os;
    // GAS overwrote col L (Contract Link) with the OpenSign ID on send.
    // That worked because GAS didn't need to resend without regenerating.
    // Port keeps col L = doc URL across the send so the frontend can extract
    // the docId from contractLink and offer Resend. ContractOrganizer polls
    // the OpenSign drop folder for signed PDFs — it doesn't read openSignId
    // from the sheet, so nothing else needs the OpenSign ID persisted here.
    $r = tlt_cb_contract_update_status( $show, $role, $first_name, 'Sent for Signature', tlt_cb_doc_url( $doc_id ) );
    if ( is_wp_error( $r ) ) return $r;
    // Non-fatal bio email.
    $bio_r = tlt_cb_send_bio_request_email( $email, $full_name, $first_name, $last_name, $show, $role );
    $bio_error = is_wp_error( $bio_r ) ? $bio_r->get_error_message() : null;
    return [
        'success'      => true,
        'openSignId'   => $os['openSignId'],
        'signingUrl'   => $os['signingUrl'],
        'bioEmailError' => $bio_error,
    ];
}

function tlt_cb_contract_send_combined( $doc_id, $doc_name, $email, $full_name, array $shows, $role, $first_name, $last_name, $template_type ) {
    if ( count( $shows ) === 1 ) {
        return tlt_cb_contract_send( $doc_id, $doc_name, $email, $full_name, $shows[0], $role, $first_name, $last_name, $template_type );
    }
    $pdf = tlt_cb_contract_export_pdf( $doc_id );
    if ( is_wp_error( $pdf ) ) return $pdf;
    $os = tlt_cb_contract_opensign_send( $doc_name, $pdf, $email, $full_name, $template_type, true );
    if ( is_wp_error( $os ) ) return $os;
    // Update on shows[0] — the col S propagation covers the rest.
    // Same reasoning as single send: keep col L = doc URL for Resend support.
    $r = tlt_cb_contract_update_status( $shows[0], $role, $first_name, 'Sent for Signature', tlt_cb_doc_url( $doc_id ) );
    if ( is_wp_error( $r ) ) return $r;
    $bio_r = tlt_cb_send_combined_bio_request_email( $email, $full_name, $first_name, $last_name, $shows, $role );
    $bio_error = is_wp_error( $bio_r ) ? $bio_r->get_error_message() : null;
    return [
        'success'       => true,
        'openSignId'    => $os['openSignId'],
        'signingUrl'    => $os['signingUrl'],
        'bioEmailError' => $bio_error,
    ];
}

/**
 * Resend an already-generated contract without the frontend having to know
 * the docId. Server looks up the doc in the show's Drive folder by expected
 * name pattern ({fullName} - {role} - {show} or the combined variant), runs
 * the send flow, and backfills col L with the doc URL so future resends
 * work without a lookup.
 *
 * If the target row is part of a combined-contract group (col S non-empty),
 * routes through the combined send path instead of single.
 *
 * @return array|WP_Error { success, openSignId, signingUrl, bioEmailError }
 */
function tlt_cb_contract_resend( $show, $role, $first_name ) {
    // Fetch the row so we can rebuild the send args.
    $is_actor = strcasecmp( $role, 'Actor' ) === 0;
    $tab_range = $is_actor ? 'Actors!A2:S' : "'Production Teams'!A2:S";
    $rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_SHEET_ID, $tab_range );
    if ( is_wp_error( $rows ) ) return $rows;

    $target = null;
    $first_lc = strtolower( $first_name );
    foreach ( $rows as $r ) {
        if ( tlt_cb_s( $r[0] ?? '' ) !== $show ) continue;
        if ( strtolower( tlt_cb_s( $r[2] ?? '' ) ) !== $first_lc ) continue;
        if ( ! $is_actor && tlt_cb_s( $r[1] ?? '' ) !== $role ) continue;
        $target = $r;
        break;
    }
    if ( $target === null ) return new WP_Error( 'no_row', "No {$role} row for {$first_name} on {$show}." );

    $last  = tlt_cb_s( $target[4] ?? '' );
    $email = tlt_cb_s( $target[7] ?? '' );
    if ( $email === '' ) return new WP_Error( 'no_email', "No email on file for {$first_name} {$last}." );
    $full = trim( $first_name . ' ' . $last );
    $ccid = tlt_cb_s( $target[18] ?? '' );

    // Template type from Duties row (same logic assemble uses).
    $duties = tlt_cb_contract_get_duties_row( $role );
    $template_type = is_array( $duties ) ? ( $duties['template'] ?: 'General' ) : 'General';
    if ( $is_actor ) $template_type = 'Actor';

    // Season long for doc name.
    $named = tlt_cb_get_named_ranges( [ 'CurrentSeasonLong' ] );
    $season_long = $named['CurrentSeasonLong'] ?? '';
    if ( $season_long === '' ) {
        $season_rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_SHEET_ID, 'Season!A1:N' );
        if ( ! is_wp_error( $season_rows ) ) $season_long = tlt_cb_season_setting( $season_rows, 'Current Season Long' );
    }
    if ( $season_long === '' ) return new WP_Error( 'no_season_long', 'Season "Current Season Long" is not set.' );

    // Combined vs single: check col S. If populated + linked shows exist, combined.
    $combined_shows = tlt_cb_find_combined_shows_for_row( $show, $first_name, $last );
    $is_combined = is_array( $combined_shows ) && count( $combined_shows ) > 1;

    // Compute doc name + parent folder for the Drive lookup.
    if ( $is_combined ) {
        $doc_name = $full . ' - ' . $role . ' - Combined (' . count( $combined_shows ) . ' shows)';
        $season_folder = tlt_cb_drive_folder_or_create( TLT_CALLBOARD_CONTRACT_ROOT_FOLDER_ID, "{$season_long} Contracts" );
        if ( is_wp_error( $season_folder ) ) return $season_folder;
        $parent_folder = tlt_cb_drive_folder_or_create( $season_folder, 'Combined Contracts' );
        if ( is_wp_error( $parent_folder ) ) return $parent_folder;
    } else {
        $doc_name = $full . ' - ' . $role . ' - ' . $show;
        $season_folder = tlt_cb_drive_folder_or_create( TLT_CALLBOARD_CONTRACT_ROOT_FOLDER_ID, "{$season_long} Contracts" );
        if ( is_wp_error( $season_folder ) ) return $season_folder;
        $parent_folder = tlt_cb_drive_folder_or_create( $season_folder, $show );
        if ( is_wp_error( $parent_folder ) ) return $parent_folder;
        if ( $is_actor ) {
            $parent_folder = tlt_cb_drive_folder_or_create( $parent_folder, 'Actor Contracts' );
            if ( is_wp_error( $parent_folder ) ) return $parent_folder;
        }
    }

    // Try to find the doc. First check col L (may already be the URL).
    $doc_id = '';
    $link = tlt_cb_s( $target[11] ?? '' );
    if ( preg_match( '#docs\.google\.com/document/d/([^/]+)#', $link, $m ) ) {
        $doc_id = $m[1];
    } else {
        $files = tlt_cb_drive_find_in_folder( $parent_folder, $doc_name );
        if ( is_wp_error( $files ) ) return $files;
        if ( empty( $files ) ) return new WP_Error( 'no_doc', "No generated doc named \"$doc_name\" in the contracts folder. Regenerate the contract first." );
        $doc_id = $files[0]['id'];
    }

    // Run the send flow.
    if ( $is_combined ) {
        return tlt_cb_contract_send_combined( $doc_id, $doc_name, $email, $full, $combined_shows, $role, $first_name, $last, $template_type );
    }
    return tlt_cb_contract_send( $doc_id, $doc_name, $email, $full, $show, $role, $first_name, $last, $template_type );
}

function tlt_cb_contract_delete( $doc_id, $show, $role, $first_name ) {
    if ( $doc_id !== '' ) {
        $t = tlt_cb_drive_trash( $doc_id );
        // Non-fatal — the file may already be gone.
    }
    return tlt_cb_contract_update_status( $show, $role, $first_name, 'Not Started', '' );
}

/* -----  Contract endpoints  ----------------------------------------------- */

function tlt_callboard_ep_contract_generate( WP_REST_Request $req ) {
    $body = $req->get_json_params() ?: [];
    $show  = tlt_cb_s( $body['show']      ?? '' );
    $role  = tlt_cb_s( $body['role']      ?? '' );
    $first = tlt_cb_s( $body['firstName'] ?? '' );
    $last  = tlt_cb_s( $body['lastName']  ?? '' );
    $char  = tlt_cb_s( $body['character'] ?? '' );
    if ( $show === '' || $role === '' || $first === '' ) {
        return new WP_Error( 'missing_args', 'show, role, firstName required', [ 'status' => 400 ] );
    }
    $r = tlt_cb_contract_generate( $show, $role, $first, $last, $char );
    if ( is_wp_error( $r ) ) return $r;
    return tlt_cb_ok( $r );
}

function tlt_callboard_ep_contract_generate_combined( WP_REST_Request $req ) {
    $body = $req->get_json_params() ?: [];
    $shows = $body['shows'] ?? [];
    if ( ! is_array( $shows ) || count( $shows ) === 0 ) {
        return new WP_Error( 'missing_shows', 'shows[] required', [ 'status' => 400 ] );
    }
    $shows = array_map( 'tlt_cb_s', $shows );
    $role  = tlt_cb_s( $body['role']      ?? '' );
    $first = tlt_cb_s( $body['firstName'] ?? '' );
    $last  = tlt_cb_s( $body['lastName']  ?? '' );
    $char  = tlt_cb_s( $body['character'] ?? '' );
    if ( $role === '' || $first === '' ) return new WP_Error( 'missing_args', 'role, firstName required', [ 'status' => 400 ] );
    $r = tlt_cb_contract_generate_combined( $shows, $role, $first, $last, $char );
    if ( is_wp_error( $r ) ) return $r;
    return tlt_cb_ok( $r );
}

/**
 * Look up lastName from Production Teams / Actors by (show, role, firstName).
 * Used by contract-send when the frontend didn't include it in the body
 * (the source GAS Index.html function signature never passed lastName).
 * Returns '' if no row matches.
 */
function tlt_cb_contract_lookup_last_name( $show, $role, $first_name ) {
    $is_actor = strcasecmp( $role, 'Actor' ) === 0;
    $tab_range = $is_actor ? 'Actors!A2:E' : "'Production Teams'!A2:E";
    $rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_SHEET_ID, $tab_range );
    if ( is_wp_error( $rows ) ) return '';
    $first_lc = strtolower( $first_name );
    foreach ( $rows as $r ) {
        if ( tlt_cb_s( $r[0] ?? '' ) !== $show ) continue;
        if ( strtolower( tlt_cb_s( $r[2] ?? '' ) ) !== $first_lc ) continue;
        if ( ! $is_actor && tlt_cb_s( $r[1] ?? '' ) !== $role ) continue;
        return tlt_cb_s( $r[4] ?? '' );
    }
    return '';
}

function tlt_callboard_ep_contract_send( WP_REST_Request $req ) {
    $body = $req->get_json_params() ?: [];
    $doc_id = tlt_cb_s( $body['docId']    ?? '' );
    $doc_name = tlt_cb_s( $body['docName'] ?? '' );
    $email  = tlt_cb_s( $body['email']    ?? '' );
    $full   = tlt_cb_s( $body['fullName'] ?? '' );
    $show   = tlt_cb_s( $body['show']     ?? '' );
    $role   = tlt_cb_s( $body['role']     ?? '' );
    $first  = tlt_cb_s( $body['firstName']?? '' );
    $last   = tlt_cb_s( $body['lastName'] ?? '' );
    $tpl    = tlt_cb_s( $body['templateType'] ?? 'General' );
    if ( $doc_id === '' || $email === '' || $show === '' || $role === '' || $first === '' ) {
        return new WP_Error( 'missing_args', 'docId, email, show, role, firstName required', [ 'status' => 400 ] );
    }
    // The frontend's sendContractFromWebapp signature (docId, docName, email,
    // fullName, show, role, firstName, templateType) never passed lastName —
    // look it up so the bio email step's Contactbook match can succeed.
    if ( $last === '' ) $last = tlt_cb_contract_lookup_last_name( $show, $role, $first );
    $r = tlt_cb_contract_send( $doc_id, $doc_name, $email, $full, $show, $role, $first, $last, $tpl );
    if ( is_wp_error( $r ) ) return $r;
    return tlt_cb_ok( $r );
}

function tlt_callboard_ep_contract_send_combined( WP_REST_Request $req ) {
    $body = $req->get_json_params() ?: [];
    $doc_id = tlt_cb_s( $body['docId']    ?? '' );
    $doc_name = tlt_cb_s( $body['docName'] ?? '' );
    $email  = tlt_cb_s( $body['email']    ?? '' );
    $full   = tlt_cb_s( $body['fullName'] ?? '' );
    $shows  = $body['shows'] ?? [];
    if ( ! is_array( $shows ) || count( $shows ) === 0 ) return new WP_Error( 'missing_shows', 'shows[] required', [ 'status' => 400 ] );
    $shows  = array_map( 'tlt_cb_s', $shows );
    $role   = tlt_cb_s( $body['role']         ?? '' );
    $first  = tlt_cb_s( $body['firstName']    ?? '' );
    $last   = tlt_cb_s( $body['lastName']     ?? '' );
    $tpl    = tlt_cb_s( $body['templateType'] ?? 'General' );
    if ( $doc_id === '' || $email === '' || $role === '' || $first === '' ) {
        return new WP_Error( 'missing_args', 'docId, email, role, firstName required', [ 'status' => 400 ] );
    }
    // Same lastName fallback as single (look up on shows[0]).
    if ( $last === '' ) $last = tlt_cb_contract_lookup_last_name( $shows[0], $role, $first );
    $r = tlt_cb_contract_send_combined( $doc_id, $doc_name, $email, $full, $shows, $role, $first, $last, $tpl );
    if ( is_wp_error( $r ) ) return $r;
    return tlt_cb_ok( $r );
}

/**
 * POST /contract-resend  { show, role, firstName }
 * Trigger send flow for an already-generated contract without needing docId.
 * Server looks up the doc in Drive by expected name, sends via OpenSign,
 * fires welcome email. Works even for rows where col L was overwritten with
 * an OpenSign ID by the older send logic.
 */
function tlt_callboard_ep_contract_resend( WP_REST_Request $req ) {
    $body = $req->get_json_params() ?: [];
    $show  = tlt_cb_s( $body['show']      ?? '' );
    $role  = tlt_cb_s( $body['role']      ?? '' );
    $first = tlt_cb_s( $body['firstName'] ?? '' );
    if ( $show === '' || $role === '' || $first === '' ) {
        return new WP_Error( 'missing_args', 'show, role, firstName required', [ 'status' => 400 ] );
    }
    $r = tlt_cb_contract_resend( $show, $role, $first );
    if ( is_wp_error( $r ) ) return $r;
    return tlt_cb_ok( $r );
}

function tlt_callboard_ep_contract_delete( WP_REST_Request $req ) {
    $body = $req->get_json_params() ?: [];
    $doc_id = tlt_cb_s( $body['docId'] ?? '' );
    $show   = tlt_cb_s( $body['show']  ?? '' );
    $role   = tlt_cb_s( $body['role']  ?? '' );
    $first  = tlt_cb_s( $body['firstName'] ?? '' );
    if ( $show === '' || $role === '' || $first === '' ) {
        return new WP_Error( 'missing_args', 'show, role, firstName required', [ 'status' => 400 ] );
    }
    $r = tlt_cb_contract_delete( $doc_id, $show, $role, $first );
    if ( is_wp_error( $r ) ) return $r;
    return tlt_cb_ok( [ 'success' => true ] );
}

/* ===========================================================================
 * ============  BIO SUBMISSION API — PUBLIC (TOKEN-AUTHED)  =================
 *
 * Ports TLTBioApp.gs bio-flow endpoints. All routes are public (no Bearer
 * token) — the `token` URL/body param is the auth, looked up against
 * Contactbook col K. Netlify frontend at tlt-bio.netlify.app (and the new
 * Cloudways-hosted frontend at /bio/) both call these.
 *
 * Endpoints:
 *   GET  /bio-contact?token=X               → { contact, bios, shows }
 *   POST /bio-submit                        → save one bio + mark submitted
 *   POST /bio-update-contact                → update pronouns + phone
 *   POST /bio-save-conflicts                → save rehearsal conflicts
 * ======================================================================== */

// Bio type → Bios-tab column indices. Matches TLTBioApp BIO_TYPES.
function tlt_cb_bio_types() {
    return [
        'actor'    => [ 'label' => 'Actor Bio',           'bioCol' => 1, 'updatedCol' => 2 ],
        'director' => [ 'label' => 'Director Bio',        'bioCol' => 3, 'updatedCol' => 4 ],
        'designer' => [ 'label' => 'Production Team Bio', 'bioCol' => 5, 'updatedCol' => 6 ],
    ];
}

// Duties-tab key-date column names → Dates-tab event types (production
// team conflict availability). Matches TLTBioApp CONFLICT_COL_TO_EVENT.
function tlt_cb_bio_conflict_col_to_event() {
    return [
        'Opening Performance'               => [ 'Opening Performance' ],
        'Closing Performance'               => [ 'Closing Performance' ],
        'Rehearsal Start'                   => [ 'Rehearsal Start' ],
        'Production Meetings'               => [ 'Production Meeting 1', 'Production Meeting 2', 'Production Meeting 3' ],
        'Designer Run'                      => [ 'Designer Run' ],
        'Costume Parade'                    => [ 'Costume/Prop Parade/Headshots' ],
        'Prop Parade'                       => [ 'Costume/Prop Parade/Headshots' ],
        'Headshots'                         => [ 'Costume/Prop Parade/Headshots' ],
        'Costume/Prop Parade and Headshots' => [ 'Costume/Prop Parade/Headshots' ],
        'Dry Tech'                          => [ 'Dry Tech' ],
        'Cue to Cue'                        => [ 'Cue to Cue' ],
        'Tech Run'                          => [ 'Tech Run' ],
        'Cue to Cue and Tech Run'           => [ 'Cue to Cue', 'Tech Run' ],
        'Dress Rehearsals'                  => [ 'Dress Rehearsal' ],
        'Performance'                       => [ 'Performance' ],
    ];
}

// Role → bio type (dupe of TLTBioApp ROLE_TO_BIO_TYPE and the callboard's
// role_to_bio_type in BiosManager — kept together here for clarity).
function tlt_cb_bio_role_to_type( $role ) {
    static $map = null;
    if ( $map === null ) {
        $map = [
            'Director' => 'director', 'Choreographer' => 'director',
            'Fight Choreographer' => 'director', 'Music Director' => 'director',
            'Intimacy Director' => 'director',
            'Lighting Designer' => 'designer', 'Sound Designer' => 'designer',
            'Scenic Designer' => 'designer', 'Costume Designer' => 'designer',
            'Properties Manager' => 'designer', 'Scenic Artist' => 'designer',
            'Stage Manager' => 'designer', 'Assistant Stage Manager' => 'designer',
            'Dialect Coach' => 'designer', 'Dramaturg' => 'designer',
        ];
    }
    return $map[ $role ] ?? 'designer';
}

/**
 * Look up a Contactbook row by bio token. Returns:
 *   [ 'row_num_1b' => int, 'row' => [cols], 'contactId' => string ]
 * or WP_Error on miss.
 * Also stamps col M (index 12, Last Bio App Login) with the current time
 * so the sheet reflects when a link was hit.
 */
function tlt_cb_bio_find_by_token( $token ) {
    $token = trim( (string) $token );
    if ( $token === '' ) return new WP_Error( 'no_token', 'No token provided.' );
    $rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_CONTACTBOOK_ID, 'Contactbook!A2:P', 1, true );
    if ( is_wp_error( $rows ) ) return $rows;
    foreach ( $rows as $i => $r ) {
        if ( trim( tlt_cb_s( $r[10] ?? '' ) ) === $token ) {
            $row_num = $i + 2; // A2 offset
            // Update col M = Last Bio App Login. Fire-and-forget (non-fatal).
            $now = ( new DateTime( 'now', new DateTimeZone( 'America/Los_Angeles' ) ) )->format( 'Y-m-d H:i:s' );
            tlt_callboard_sheets_write( TLT_CALLBOARD_CONTACTBOOK_ID, "Contactbook!M{$row_num}", [[ $now ]] );
            return [
                'row_num_1b' => $row_num,
                'row'        => $r,
                'contactId'  => tlt_cb_s( $r[0] ?? '' ),
            ];
        }
    }
    return new WP_Error( 'no_contact', 'Link not found or expired. Please contact TLT staff.' );
}

/**
 * Get shows for a person by first + last name from Callboard sheet.
 * Returns array of { show, role, character?, bioType, isActor } matching
 * TLTBioApp getShowsForContact.
 */
function tlt_cb_bio_get_shows_for( $first, $last ) {
    $ranges = tlt_callboard_sheets_get( TLT_CALLBOARD_SHEET_ID, [
        "'Production Teams'!A2:F",
        'Actors!A2:F',
    ] );
    if ( is_wp_error( $ranges ) ) return [];
    $team_rows  = $ranges["'Production Teams'!A2:F"] ?? [];
    $actor_rows = $ranges['Actors!A2:F']              ?? [];

    $first_lc = strtolower( trim( (string) $first ) );
    $last_lc  = strtolower( trim( (string) $last ) );

    // Merge team roles per show (director trumps designer on the bioType).
    $show_map = [];
    foreach ( $team_rows as $r ) {
        $show = tlt_cb_s( $r[0] ?? '' );
        $role = tlt_cb_s( $r[1] ?? '' );
        $rf   = strtolower( tlt_cb_s( $r[2] ?? '' ) );
        $rl   = strtolower( tlt_cb_s( $r[4] ?? '' ) );
        if ( $show === '' || $role === '' || $rf === '' ) continue;
        if ( $rf !== $first_lc || $rl !== $last_lc ) continue;
        $bt = tlt_cb_bio_role_to_type( $role );
        if ( ! isset( $show_map[ $show ] ) ) {
            $show_map[ $show ] = [ 'show' => $show, 'role' => $role, 'bioType' => $bt, 'isActor' => false ];
        } elseif ( $bt === 'director' && $show_map[ $show ]['bioType'] !== 'director' ) {
            $show_map[ $show ]['bioType'] = 'director';
            $show_map[ $show ]['role']    = $role;
        } elseif ( $show_map[ $show ]['bioType'] === $bt ) {
            $show_map[ $show ]['role'] .= ' / ' . $role;
        }
    }
    $out = array_values( $show_map );

    foreach ( $actor_rows as $r ) {
        $show = tlt_cb_s( $r[0] ?? '' );
        $rf   = strtolower( tlt_cb_s( $r[2] ?? '' ) );
        $rl   = strtolower( tlt_cb_s( $r[4] ?? '' ) );
        if ( $show === '' || $rf === '' ) continue;
        if ( $rf !== $first_lc || $rl !== $last_lc ) continue;
        $out[] = [
            'show'      => $show,
            'role'      => 'Actor',
            'character' => tlt_cb_s( $r[1] ?? '' ),
            'bioType'   => 'actor',
            'isActor'   => true,
        ];
    }

    // Stable sort by first-appearance show order + team-before-actor.
    $order = []; $i = 0;
    foreach ( $out as $s ) { if ( ! isset( $order[ $s['show'] ] ) ) $order[ $s['show'] ] = $i++; }
    usort( $out, function ( $a, $b ) use ( $order ) {
        $sa = $order[ $a['show'] ]; $sb = $order[ $b['show'] ];
        if ( $sa !== $sb ) return $sa <=> $sb;
        return ( $a['isActor'] ? 1 : 0 ) <=> ( $b['isActor'] ? 1 : 0 );
    } );
    return $out;
}

/**
 * Read bio word limit per show from Season tab (cols F/G/H = actor/director/designer).
 * Defaults 100/200/150.
 */
function tlt_cb_bio_word_limit_for( $show, $bioType ) {
    $defaults = [ 'actor' => 100, 'director' => 200, 'designer' => 150 ];
    $col_idx  = [ 'actor' => 5, 'director' => 6, 'designer' => 7 ];
    if ( ! isset( $col_idx[ $bioType ] ) ) return $defaults[ $bioType ] ?? 150;
    $rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_SHEET_ID, 'Season!A1:N' );
    if ( is_wp_error( $rows ) ) return $defaults[ $bioType ];
    foreach ( $rows as $r ) {
        if ( tlt_cb_s( $r[1] ?? '' ) === $show ) {
            $n = (int) tlt_cb_s( $r[ $col_idx[ $bioType ] ] ?? '' );
            return $n > 0 ? $n : $defaults[ $bioType ];
        }
    }
    return $defaults[ $bioType ];
}

/**
 * Read Duties row for a role, return array of checked key-date column
 * labels (col G+). Mirrors TLTBioApp _getCheckedKeyDateColsForRole.
 */
function tlt_cb_bio_checked_key_date_cols_for_role( $role ) {
    $rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_SHEET_ID, 'Duties!A1:AZ' );
    if ( is_wp_error( $rows ) || count( $rows ) < 2 ) return [];
    $header = $rows[0];
    $target = null;
    foreach ( array_slice( $rows, 1 ) as $r ) {
        if ( trim( tlt_cb_s( $r[0] ?? '' ) ) === trim( (string) $role ) ) { $target = $r; break; }
    }
    if ( ! $target ) return [];
    $out = [];
    for ( $i = 6; $i < count( $header ); $i++ ) {
        $v = $target[ $i ] ?? '';
        if ( $v === true || $v === 'TRUE' || $v === 'true' || $v === '1' ) {
            $label = trim( tlt_cb_s( $header[ $i ] ?? '' ) );
            if ( $label !== '' ) $out[] = $label;
        }
    }
    return $out;
}

/**
 * For a (show, role), return the list of dated events the role attends:
 *   [ { eventType, date: YYYY-MM-DD, dateLabel: "Fri, Oct 3" }, ... ]
 * Only events with a start time (skips pure deadlines).
 */
function tlt_cb_bio_events_for_show_role( $show, $role ) {
    $checked = tlt_cb_bio_checked_key_date_cols_for_role( $role );
    if ( empty( $checked ) ) return [];
    $map = tlt_cb_bio_conflict_col_to_event();
    $event_types = [];
    foreach ( $checked as $col ) {
        if ( isset( $map[ $col ] ) ) foreach ( $map[ $col ] as $et ) $event_types[ $et ] = true;
    }
    if ( empty( $event_types ) ) return [];

    $dates_rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_SHEET_ID, 'Dates!A2:H' );
    if ( is_wp_error( $dates_rows ) ) return [];
    $out = [];
    foreach ( $dates_rows as $r ) {
        if ( tlt_cb_s( $r[0] ?? '' ) !== $show ) continue;
        $et = tlt_cb_s( $r[1] ?? '' );
        if ( ! isset( $event_types[ $et ] ) ) continue;
        if ( trim( tlt_cb_s( $r[5] ?? '' ) ) === '' ) continue; // skip pure deadlines (no time)
        $dt = tlt_cb_parse_date( $r[4] ?? '' );
        if ( ! $dt ) continue;
        $out[] = [
            'eventType' => $et,
            'date'      => $dt->format( 'Y-m-d' ),
            'dateLabel' => $dt->format( 'D, M j' ),
        ];
    }
    usort( $out, function ( $a, $b ) {
        return $a['date'] === $b['date']
            ? strcmp( $a['eventType'], $b['eventType'] )
            : strcmp( $a['date'], $b['date'] );
    } );
    return $out;
}

/**
 * Read existing production-team conflicts stored on the callboard sheet's
 * "Production Team Conflicts" tab for a (contactId, show).
 */
function tlt_cb_bio_get_existing_conflicts( $contactId, $show ) {
    if ( $contactId === '' || $show === '' ) return [];
    // Try existing tab; if the sheet doesn't have it yet, return empty.
    $rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_SHEET_ID, "'Production Team Conflicts'!A2:H" );
    if ( is_wp_error( $rows ) ) return [];
    $out = [];
    foreach ( $rows as $r ) {
        if ( tlt_cb_s( $r[0] ?? '' ) !== $show ) continue;
        if ( trim( tlt_cb_s( $r[1] ?? '' ) ) !== trim( $contactId ) ) continue;
        $out[] = [
            'eventType' => tlt_cb_s( $r[5] ?? '' ),
            'date'      => tlt_cb_s( $r[6] ?? '' ),
            'notes'     => tlt_cb_s( $r[7] ?? '' ),
        ];
    }
    return $out;
}

/**
 * Read actor CM conflicts from Callboard sheet's Conflicts tab (populated
 * by castingmanager_sync.py). Full-name match, case-insensitive.
 */
function tlt_cb_bio_get_cm_conflicts( $show, $first, $last ) {
    $rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_SHEET_ID, 'Conflicts!A2:F' );
    if ( is_wp_error( $rows ) ) return [];
    $full_lc = strtolower( trim( $first . ' ' . $last ) );
    $out = [];
    foreach ( $rows as $r ) {
        // CM Conflicts tab layout (approximate): A=show, B=contactId?, C=fullName, D=date, E=eventType, F=notes
        if ( tlt_cb_s( $r[0] ?? '' ) !== $show ) continue;
        $row_full_lc = strtolower( trim( tlt_cb_s( $r[2] ?? '' ) ) );
        if ( $row_full_lc !== $full_lc ) continue;
        $dt = tlt_cb_parse_date( $r[3] ?? '' );
        $out[] = [
            'date'      => $dt ? $dt->format( 'Y-m-d' ) : tlt_cb_s( $r[3] ?? '' ),
            'eventType' => tlt_cb_s( $r[4] ?? '' ) ?: 'Conflict',
            'notes'     => tlt_cb_s( $r[5] ?? '' ),
        ];
    }
    return $out;
}

/**
 * Get-or-create the "Production Team Conflicts" tab. Returns the sheet's
 * name so callers can pass it to sheets_write. Adds a header row if new.
 */
function tlt_cb_bio_ensure_conflicts_tab() {
    $tab = 'Production Team Conflicts';
    // Try to read A1 — if the sheet exists we succeed. If not, we get a 400.
    $r = tlt_callboard_sheets_get( TLT_CALLBOARD_SHEET_ID, [ "'{$tab}'!A1:J1" ], 1, true );
    if ( ! is_wp_error( $r ) ) return $tab;
    // Create it via spreadsheets.batchUpdate.
    $token = tlt_callboard_google_access_token();
    if ( is_wp_error( $token ) ) return $token;
    $resp = wp_remote_post(
        'https://sheets.googleapis.com/v4/spreadsheets/' . TLT_CALLBOARD_SHEET_ID . ':batchUpdate',
        [
            'timeout' => 15,
            'headers' => [ 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json' ],
            'body' => wp_json_encode( [
                'requests' => [ [ 'addSheet' => [ 'properties' => [ 'title' => $tab ] ] ] ],
            ] ),
        ]
    );
    if ( is_wp_error( $resp ) ) return $resp;
    $code = wp_remote_retrieve_response_code( $resp );
    if ( $code < 200 || $code >= 300 ) {
        return new WP_Error( 'create_tab', "addSheet failed ($code): " . wp_remote_retrieve_body( $resp ) );
    }
    // Header row.
    $headers = [ 'Show', 'Contact ID', 'First Name', 'Last Name', 'Role', 'Event Type', 'Event Date', 'Notes', 'Submitted At', 'Last Updated' ];
    tlt_callboard_sheets_write( TLT_CALLBOARD_SHEET_ID, "'{$tab}'!A1:J1", [ $headers ] );
    return $tab;
}

/**
 * Update col N (bio status) + col O (bio type) on Production Teams / Actors
 * for a matching row (show + firstName).
 */
function tlt_cb_bio_mark_submitted( $show, $role, $first, $bioType ) {
    $is_actor = ( strcasecmp( $role, 'Actor' ) === 0 );
    $tab = $is_actor ? 'Actors' : "'Production Teams'";
    $tab_display = $is_actor ? 'Actors' : "Production Teams";
    $rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_SHEET_ID, "{$tab}!A2:E", 1, true );
    if ( is_wp_error( $rows ) ) return $rows;
    $first_lc = strtolower( trim( $first ) );
    $show_trim = trim( $show );
    foreach ( $rows as $i => $r ) {
        if ( trim( tlt_cb_s( $r[0] ?? '' ) ) !== $show_trim ) continue;
        if ( strtolower( trim( tlt_cb_s( $r[2] ?? '' ) ) ) !== $first_lc ) continue;
        $row_num = $i + 2;
        tlt_callboard_sheets_write( TLT_CALLBOARD_SHEET_ID, "'{$tab_display}'!N{$row_num}", [[ 'Submitted' ]] );
        tlt_callboard_sheets_write( TLT_CALLBOARD_SHEET_ID, "'{$tab_display}'!O{$row_num}", [[ $bioType  ]] );
        if ( $is_actor ) break; // GAS only stamps first match for actors
    }
    return true;
}

/**
 * Append a row to Bio Log in Contactbook. Non-fatal on failure.
 */
function tlt_cb_bio_log( $contactId, $contact_row, $show, $bioType, $action_type, $bioText = '' ) {
    $labels = tlt_cb_bio_types();
    $bio_label = $labels[ $bioType ]['label'] ?? $bioType;
    $action_label = ( $action_type === 'update' ) ? 'Updated & Submitted' : 'Used Existing';
    // Get current row count to build LOG ID.
    $existing = tlt_callboard_sheet_rows( TLT_CALLBOARD_CONTACTBOOK_ID, 'Bio Log!A2:A', 1, true );
    $next_num = is_wp_error( $existing ) ? 1 : ( count( $existing ) + 1 );
    $log_id   = 'LOG-' . str_pad( (string) $next_num, 4, '0', STR_PAD_LEFT );
    $now      = ( new DateTime( 'now', new DateTimeZone( 'America/Los_Angeles' ) ) )->format( 'Y-m-d H:i:s' );

    $row = [
        $log_id, $now, $contactId,
        tlt_cb_s( $contact_row[1] ?? '' ), // first
        tlt_cb_s( $contact_row[3] ?? '' ), // last
        tlt_cb_s( $contact_row[7] ?? '' ), // email
        $show, $bio_label, $action_label,
        '', $bioText, 'Bio App',
    ];
    // Append via values.append.
    $token = tlt_callboard_google_access_token();
    if ( is_wp_error( $token ) ) return;
    $url = 'https://sheets.googleapis.com/v4/spreadsheets/' . TLT_CALLBOARD_CONTACTBOOK_ID
        . '/values/' . rawurlencode( "'Bio Log'!A:L" ) . ':append?valueInputOption=USER_ENTERED&insertDataOption=INSERT_ROWS';
    wp_remote_post( $url, [
        'timeout' => 15,
        'headers' => [ 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json' ],
        'body' => wp_json_encode( [ 'values' => [ $row ] ] ),
    ] );
    tlt_cb_bump_cache();
}

/* -----  Endpoint handlers  ------------------------------------------------ */

/**
 * GET /bio-contact?token=X
 */
function tlt_callboard_ep_bio_contact( WP_REST_Request $req ) {
    $token = tlt_cb_s( $req->get_param( 'token' ) );
    if ( $token === '' ) return rest_ensure_response( [ 'error' => 'No token provided.' ] );

    $lookup = tlt_cb_bio_find_by_token( $token );
    if ( is_wp_error( $lookup ) ) return rest_ensure_response( [ 'error' => $lookup->get_error_message() ] );

    $row = $lookup['row'];
    $contact = [
        'contactId'  => $lookup['contactId'],
        'firstName'  => tlt_cb_s( $row[1] ?? '' ),
        'middleName' => tlt_cb_s( $row[2] ?? '' ),
        'lastName'   => tlt_cb_s( $row[3] ?? '' ),
        'suffix'     => tlt_cb_s( $row[4] ?? '' ),
        'pronouns'   => tlt_cb_s( $row[5] ?? '' ),
        'phone'      => tlt_cb_s( $row[6] ?? '' ),
        'email'      => tlt_cb_s( $row[7] ?? '' ),
        'notes'      => tlt_cb_s( $row[8] ?? '' ),
        'rowIndex'   => $lookup['row_num_1b'],
    ];

    // Bios (from Contactbook.Bios by contactId).
    $bios = [];
    $bios_rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_CONTACTBOOK_ID, 'Bios!A2:G' );
    $bio_row = null;
    if ( ! is_wp_error( $bios_rows ) ) {
        foreach ( $bios_rows as $r ) {
            if ( trim( tlt_cb_s( $r[0] ?? '' ) ) === $lookup['contactId'] ) { $bio_row = $r; break; }
        }
    }
    foreach ( tlt_cb_bio_types() as $key => $cfg ) {
        $bios[ $key ] = [
            'text'    => $bio_row ? tlt_cb_s( $bio_row[ $cfg['bioCol'] ] ?? '' )     : '',
            'updated' => $bio_row ? tlt_cb_s( $bio_row[ $cfg['updatedCol'] ] ?? '' ) : '',
        ];
    }

    // Shows + enrichment.
    $shows = tlt_cb_bio_get_shows_for( $contact['firstName'], $contact['lastName'] );
    $enriched = [];
    foreach ( $shows as $s ) {
        $s['bioWordLimit'] = tlt_cb_bio_word_limit_for( $s['show'], $s['bioType'] );
        if ( ! empty( $s['isActor'] ) ) {
            $s['cmConflicts'] = tlt_cb_bio_get_cm_conflicts( $s['show'], $contact['firstName'], $contact['lastName'] );
        } else {
            $first_role = explode( ' / ', $s['role'] )[0];
            $s['events']            = tlt_cb_bio_events_for_show_role( $s['show'], $first_role );
            $s['existingConflicts'] = tlt_cb_bio_get_existing_conflicts( $lookup['contactId'], $s['show'] );
        }
        $enriched[] = $s;
    }

    return rest_ensure_response( [
        'contact' => $contact,
        'bios'    => $bios,
        'shows'   => $enriched,
    ] );
}

/**
 * POST /bio-submit  { token, bioType, bioText, show, role, action_type }
 * Frontend expects a plain object (no {ok, data} wrapper).
 */
function tlt_callboard_ep_bio_submit( WP_REST_Request $req ) {
    $body = $req->get_json_params() ?: [];
    $token       = tlt_cb_s( $body['token']       ?? '' );
    $bioType     = tlt_cb_s( $body['bioType']     ?? '' );
    $bioText     = tlt_cb_s( $body['bioText']     ?? '' );
    $show        = tlt_cb_s( $body['show']        ?? '' );
    $role        = tlt_cb_s( $body['role']        ?? '' );
    $action_type = tlt_cb_s( $body['action_type'] ?? 'use' );

    $types = tlt_cb_bio_types();
    if ( ! isset( $types[ $bioType ] ) ) {
        return rest_ensure_response( [ 'success' => false, 'error' => 'Invalid bio type.' ] );
    }
    $cfg = $types[ $bioType ];

    $lookup = tlt_cb_bio_find_by_token( $token );
    if ( is_wp_error( $lookup ) ) {
        return rest_ensure_response( [ 'success' => false, 'error' => $lookup->get_error_message() ] );
    }
    $contactId = $lookup['contactId'];
    $first     = tlt_cb_s( $lookup['row'][1] ?? '' );

    if ( $action_type === 'update' ) {
        // Find or create the Bios-tab row for this contactId.
        $bios_rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_CONTACTBOOK_ID, 'Bios!A2:G', 1, true );
        if ( is_wp_error( $bios_rows ) ) $bios_rows = [];
        $target = 0;
        foreach ( $bios_rows as $i => $r ) {
            if ( trim( tlt_cb_s( $r[0] ?? '' ) ) === $contactId ) { $target = $i + 2; break; }
        }
        $now = ( new DateTime( 'now', new DateTimeZone( 'America/Los_Angeles' ) ) )->format( 'Y-m-d H:i:s' );
        if ( $target === 0 ) {
            // Append a fresh row with contactId in col A, bio text in bioCol, timestamp in updatedCol.
            $new_row = array_fill( 0, 7, '' );
            $new_row[0] = $contactId;
            $new_row[ $cfg['bioCol']     ] = $bioText;
            $new_row[ $cfg['updatedCol'] ] = $now;
            $token_g = tlt_callboard_google_access_token();
            if ( ! is_wp_error( $token_g ) ) {
                $url = 'https://sheets.googleapis.com/v4/spreadsheets/' . TLT_CALLBOARD_CONTACTBOOK_ID
                    . '/values/' . rawurlencode( 'Bios!A:G' ) . ':append?valueInputOption=USER_ENTERED&insertDataOption=INSERT_ROWS';
                wp_remote_post( $url, [
                    'timeout' => 15,
                    'headers' => [ 'Authorization' => 'Bearer ' . $token_g, 'Content-Type' => 'application/json' ],
                    'body' => wp_json_encode( [ 'values' => [ $new_row ] ] ),
                ] );
                tlt_cb_bump_cache();
            }
        } else {
            // Update bio text + updated timestamp.
            $bio_col_letter     = chr( ord( 'A' ) + $cfg['bioCol'] );
            $updated_col_letter = chr( ord( 'A' ) + $cfg['updatedCol'] );
            tlt_callboard_sheets_write( TLT_CALLBOARD_CONTACTBOOK_ID, "Bios!{$bio_col_letter}{$target}", [[ $bioText ]] );
            tlt_callboard_sheets_write( TLT_CALLBOARD_CONTACTBOOK_ID, "Bios!{$updated_col_letter}{$target}", [[ $now ]] );
        }
    }

    // A single designer/director bio is shared across every show that person is on.
    // Frontend can send `shows: [{show,role}, ...]` to mark all of them submitted in one call.
    // Falls back to the singular {show, role} pair for actor bios and legacy callers.
    $targets = [];
    if ( is_array( $body['shows'] ?? null ) && ! empty( $body['shows'] ) ) {
        foreach ( $body['shows'] as $t ) {
            if ( ! is_array( $t ) ) continue;
            $ts = tlt_cb_s( $t['show'] ?? '' );
            $tr = tlt_cb_s( $t['role'] ?? '' );
            if ( $ts !== '' ) $targets[] = [ 'show' => $ts, 'role' => $tr ];
        }
    }
    if ( ! $targets ) {
        $targets[] = [ 'show' => $show, 'role' => $role ];
    }
    foreach ( $targets as $t ) {
        tlt_cb_bio_mark_submitted( $t['show'], $t['role'], $first, $bioType );
        tlt_cb_bio_log( $contactId, $lookup['row'], $t['show'], $bioType, $action_type, $bioText );
    }

    return rest_ensure_response( [ 'success' => true, 'submittedForShows' => array_map( function( $t ) { return $t['show']; }, $targets ) ] );
}

/**
 * POST /bio-update-contact  { token, firstName?, middleName?, lastName?, suffix?, pronouns?, phone?, email? }
 *
 * Writes any provided fields to the caller's Contactbook row. If first/middle/last/suffix
 * change, ALSO cascades the new name to the caller's Actors + Production Teams rows so the
 * callboard's show lookup (which matches by name, not contactId) keeps finding them. Without
 * the cascade, a name correction here would silently orphan them from every show.
 *
 * Emergency Info's name fields are NOT touched — that's legal name and can differ.
 *
 * Columns: A=Contact ID, B=First, C=Middle, D=Last, E=Suffix, F=Pronouns, G=Phone, H=Email
 */
function tlt_callboard_ep_bio_update_contact( WP_REST_Request $req ) {
    $body = $req->get_json_params() ?: [];
    $token = tlt_cb_s( $body['token'] ?? '' );
    $lookup = tlt_cb_bio_find_by_token( $token );
    if ( is_wp_error( $lookup ) ) return rest_ensure_response( [ 'success' => false, 'error' => $lookup->get_error_message() ] );
    $row_num  = $lookup['row_num_1b'];
    $cb_row   = $lookup['row'];
    $old_first = tlt_cb_s( $cb_row[1] ?? '' );
    $old_last  = tlt_cb_s( $cb_row[3] ?? '' );

    $col_map = [
        'firstName'  => 'B',
        'middleName' => 'C',
        'lastName'   => 'D',
        'suffix'     => 'E',
        'pronouns'   => 'F',
        'phone'      => 'G',
        'email'      => 'H',
    ];
    foreach ( $col_map as $field => $col ) {
        if ( array_key_exists( $field, $body ) && $body[ $field ] !== null ) {
            tlt_callboard_sheets_write( TLT_CALLBOARD_CONTACTBOOK_ID, "Contactbook!{$col}{$row_num}", [[ tlt_cb_s( $body[ $field ] ) ]] );
        }
    }

    // Cascade name change to Actors + Production Teams (they match by name, not contactId).
    $new_first = array_key_exists( 'firstName', $body ) && $body['firstName'] !== null ? tlt_cb_s( $body['firstName'] ) : $old_first;
    $new_last  = array_key_exists( 'lastName',  $body ) && $body['lastName']  !== null ? tlt_cb_s( $body['lastName']  ) : $old_last;
    $cascaded  = [ 'actors' => 0, 'teams' => 0 ];
    $name_changed = ( strcasecmp( trim( $new_first ), trim( $old_first ) ) !== 0
                     || strcasecmp( trim( $new_last ), trim( $old_last ) ) !== 0 );
    if ( $name_changed && $old_first !== '' && $old_last !== '' ) {
        $cascaded['actors'] = tlt_cb_bio_cascade_name_to_tab( 'Actors', $old_first, $old_last, $new_first, $new_last );
        $cascaded['teams']  = tlt_cb_bio_cascade_name_to_tab( "'Production Teams'", $old_first, $old_last, $new_first, $new_last );
    }
    tlt_cb_bump_cache();

    return rest_ensure_response( [ 'success' => true, 'cascaded' => $cascaded ] );
}

/**
 * Rewrite col C (first) + col E (last) on every row of $tab where the person matches
 * the old name. Used by /bio-update-contact when someone corrects their name. Returns
 * the number of rows updated.
 */
function tlt_cb_bio_cascade_name_to_tab( $tab, $old_first, $old_last, $new_first, $new_last ) {
    $rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_SHEET_ID, "{$tab}!A2:E", 1, true );
    if ( is_wp_error( $rows ) ) return 0;
    $of = strtolower( trim( $old_first ) );
    $ol = strtolower( trim( $old_last  ) );
    $count = 0;
    foreach ( $rows as $i => $r ) {
        $rf = strtolower( trim( tlt_cb_s( $r[2] ?? '' ) ) );
        $rl = strtolower( trim( tlt_cb_s( $r[4] ?? '' ) ) );
        if ( $rf !== $of || $rl !== $ol ) continue;
        $rn = $i + 2;
        tlt_callboard_sheets_write( TLT_CALLBOARD_SHEET_ID, "{$tab}!C{$rn}", [[ $new_first ]] );
        tlt_callboard_sheets_write( TLT_CALLBOARD_SHEET_ID, "{$tab}!E{$rn}", [[ $new_last  ]] );
        $count++;
    }
    return $count;
}

/**
 * POST /bio-save-conflicts  { token, show, role, conflicts:[{date,eventType,notes}] }
 * Wipes existing (contactId, show) rows then appends new.
 */
function tlt_callboard_ep_bio_save_conflicts( WP_REST_Request $req ) {
    $body = $req->get_json_params() ?: [];
    $token = tlt_cb_s( $body['token'] ?? '' );
    $show  = tlt_cb_s( $body['show']  ?? '' );
    $role  = tlt_cb_s( $body['role']  ?? '' );
    $conflicts = is_array( $body['conflicts'] ?? null ) ? $body['conflicts'] : [];

    if ( $token === '' || $show === '' ) return rest_ensure_response( [ 'error' => 'Missing token or show.' ] );

    $lookup = tlt_cb_bio_find_by_token( $token );
    if ( is_wp_error( $lookup ) ) return rest_ensure_response( [ 'error' => $lookup->get_error_message() ] );
    $contactId = $lookup['contactId'];
    $first = tlt_cb_s( $lookup['row'][1] ?? '' );
    $last  = tlt_cb_s( $lookup['row'][3] ?? '' );

    $tab = tlt_cb_bio_ensure_conflicts_tab();
    if ( is_wp_error( $tab ) ) return rest_ensure_response( [ 'error' => $tab->get_error_message() ] );

    // Delete existing (contactId, show) rows. We need to know their row numbers
    // in the sheet — read the tab and use spreadsheets.batchUpdate deleteDimension.
    $rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_SHEET_ID, "'{$tab}'!A2:J", 1, true );
    if ( ! is_wp_error( $rows ) ) {
        $to_delete = [];
        foreach ( $rows as $i => $r ) {
            if ( trim( tlt_cb_s( $r[1] ?? '' ) ) === $contactId && trim( tlt_cb_s( $r[0] ?? '' ) ) === trim( $show ) ) {
                $to_delete[] = $i + 2; // 1-based sheet row
            }
        }
        if ( ! empty( $to_delete ) ) {
            // Look up the sheetId for the tab.
            $token_g = tlt_callboard_google_access_token();
            if ( ! is_wp_error( $token_g ) ) {
                $meta = wp_remote_get(
                    'https://sheets.googleapis.com/v4/spreadsheets/' . TLT_CALLBOARD_SHEET_ID . '?fields=sheets(properties(sheetId,title))',
                    [ 'headers' => [ 'Authorization' => 'Bearer ' . $token_g ] ]
                );
                $sheet_id = null;
                if ( ! is_wp_error( $meta ) ) {
                    $data = json_decode( wp_remote_retrieve_body( $meta ), true );
                    foreach ( ( $data['sheets'] ?? [] ) as $s ) {
                        if ( ( $s['properties']['title'] ?? '' ) === $tab ) {
                            $sheet_id = $s['properties']['sheetId'];
                            break;
                        }
                    }
                }
                if ( $sheet_id !== null ) {
                    rsort( $to_delete ); // delete highest row first so indices stay valid
                    $requests = [];
                    foreach ( $to_delete as $row_1b ) {
                        $requests[] = [ 'deleteDimension' => [
                            'range' => [
                                'sheetId'    => $sheet_id,
                                'dimension'  => 'ROWS',
                                'startIndex' => $row_1b - 1,
                                'endIndex'   => $row_1b,
                            ],
                        ] ];
                    }
                    wp_remote_post(
                        'https://sheets.googleapis.com/v4/spreadsheets/' . TLT_CALLBOARD_SHEET_ID . ':batchUpdate',
                        [
                            'headers' => [ 'Authorization' => 'Bearer ' . $token_g, 'Content-Type' => 'application/json' ],
                            'body' => wp_json_encode( [ 'requests' => $requests ] ),
                        ]
                    );
                    tlt_cb_bump_cache();
                }
            }
        }
    }

    // Append new conflicts via values.append.
    $saved = 0;
    if ( ! empty( $conflicts ) ) {
        $now = ( new DateTime( 'now', new DateTimeZone( 'America/Los_Angeles' ) ) )->format( 'Y-m-d H:i:s' );
        $values = [];
        foreach ( $conflicts as $c ) {
            $date = tlt_cb_s( $c['date']      ?? '' );
            $et   = tlt_cb_s( $c['eventType'] ?? '' );
            $notes = tlt_cb_s( $c['notes']    ?? '' );
            if ( $date === '' || $et === '' ) continue;
            $values[] = [ $show, $contactId, $first, $last, $role, $et, $date, $notes, $now, $now ];
            $saved++;
        }
        if ( ! empty( $values ) ) {
            $token_g = tlt_callboard_google_access_token();
            if ( ! is_wp_error( $token_g ) ) {
                $url = 'https://sheets.googleapis.com/v4/spreadsheets/' . TLT_CALLBOARD_SHEET_ID
                    . '/values/' . rawurlencode( "'{$tab}'!A:J" ) . ':append?valueInputOption=USER_ENTERED&insertDataOption=INSERT_ROWS';
                wp_remote_post( $url, [
                    'headers' => [ 'Authorization' => 'Bearer ' . $token_g, 'Content-Type' => 'application/json' ],
                    'body' => wp_json_encode( [ 'values' => $values ] ),
                ] );
                tlt_cb_bump_cache();
            }
        }
    }

    return rest_ensure_response( [ 'success' => true, 'saved' => $saved ] );
}

/* ============================================================================
 * EMERGENCY INFO — Phase B/C port from TLTBioApp.js
 * ============================================================================
 *
 * Endpoints:
 *   GET  /bio-emergency         → prefill data for the emergency form
 *   POST /bio-emergency-submit  → upsert Emergency Info row + generate PDFs + email flag
 *
 * PDF generation is SYNCHRONOUS. GAS queued this because Apps Script cold-starts
 * are slow (30s+ each) and Docs API is slower still. From Cloudways we get sub-2s
 * copy/export cycles, so the full submit round-trip runs ~8-12s. Frontend already
 * shows a "Generating..." spinner during submit so this is fine.
 * ------------------------------------------------------------------------- */

/**
 * The 38 headers of the Emergency Info tab, in column order.
 */
function tlt_cb_emergency_headers() {
    return [
        'Contact ID',              // A  (0)
        'First Name',              // B  (1)
        'Middle Name',             // C  (2)
        'Last Name',               // D  (3)
        'DOB',                     // E  (4)
        'Over 18',                 // F  (5)
        'Address',                 // G  (6)
        'Home Phone',              // H  (7)
        'Mobile Phone',            // I  (8)
        'Guardian Name',           // J  (9)
        'Guardian Address',        // K  (10)
        'Guardian Home Phone',     // L  (11)
        'Guardian Mobile Phone',   // M  (12)
        'Emergency Contact 1 Name', // N (13)
        'Emergency Contact 1 Phone', // O (14)
        'Emergency Contact 2 Name', // P (15)
        'Emergency Contact 2 Phone', // Q (16)
        'Food Allergy',            // R  (17)
        'Food Allergy Detail',     // S  (18)
        'Costume Allergy',         // T  (19)
        'Costume Allergy Detail',  // U  (20)
        'Other Allergy',           // V  (21)
        'Other Allergy Detail',    // W  (22)
        'Medical Conditions',      // X  (23)
        'Insurance',               // Y  (24)
        'Physician Name',          // Z  (25)
        'Physician Phone',         // AA (26)
        'Hospital Preference',     // AB (27)
        'ER Care Preference',      // AC (28)
        'Medical Signature',       // AD (29)
        'Medical Signed Date',     // AE (30)
        'Aliases',                 // AF (31)
        'Conviction',              // AG (32)
        'Conviction Detail',       // AH (33)
        'WATCH Signature',         // AI (34)
        'WATCH Signed Date',       // AJ (35)
        'Submitted At',            // AK (36)
        'Last Updated',            // AL (37)
    ];
}

/**
 * Ensure the Emergency Info tab exists in the Contactbook spreadsheet with header row.
 * Idempotent — silently no-ops if already present.
 */
function tlt_cb_emergency_ensure_tab() {
    $tab = TLT_CALLBOARD_EMERGENCY_TAB;
    $rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_CONTACTBOOK_ID, "'{$tab}'!A1:AL1", 0, true );
    if ( ! is_wp_error( $rows ) ) return true;
    $tok = tlt_callboard_google_access_token();
    if ( is_wp_error( $tok ) ) return $tok;
    $body = [ 'requests' => [ [ 'addSheet' => [ 'properties' => [ 'title' => $tab, 'gridProperties' => [ 'frozenRowCount' => 1 ] ] ] ] ] ];
    wp_remote_post( 'https://sheets.googleapis.com/v4/spreadsheets/' . TLT_CALLBOARD_CONTACTBOOK_ID . ':batchUpdate', [
        'headers' => [ 'Authorization' => 'Bearer ' . $tok, 'Content-Type' => 'application/json' ],
        'body'    => wp_json_encode( $body ),
        'timeout' => 20,
    ] );
    tlt_callboard_sheets_write( TLT_CALLBOARD_CONTACTBOOK_ID, "'{$tab}'!A1:AL1", [ tlt_cb_emergency_headers() ] );
    tlt_cb_bump_cache();
    return true;
}

/**
 * Utility: parse "1"/"true"/"yes"/"TRUE" as boolean true.
 */
function tlt_cb_emergency_is_truthy( $v ) {
    if ( $v === true ) return true;
    $s = strtolower( trim( (string) $v ) );
    return in_array( $s, [ 'true', 'yes', '1' ], true );
}

/**
 * Convert a raw sheet row into a public emergency-info object (for prefill).
 * Excludes signatures + signed dates — talent must re-sign every submission.
 */
function tlt_cb_emergency_row_to_object( $row, $row_index_1b ) {
    return [
        'firstName'            => tlt_cb_s( $row[1]  ?? '' ),
        'middleName'           => tlt_cb_s( $row[2]  ?? '' ),
        'lastName'             => tlt_cb_s( $row[3]  ?? '' ),
        'dob'                  => tlt_cb_s( $row[4]  ?? '' ),
        'over18'               => tlt_cb_s( $row[5]  ?? '' ),
        'address'              => tlt_cb_s( $row[6]  ?? '' ),
        'homePhone'            => tlt_cb_s( $row[7]  ?? '' ),
        'mobilePhone'          => tlt_cb_s( $row[8]  ?? '' ),
        'guardianName'         => tlt_cb_s( $row[9]  ?? '' ),
        'guardianAddress'      => tlt_cb_s( $row[10] ?? '' ),
        'guardianHomePhone'    => tlt_cb_s( $row[11] ?? '' ),
        'guardianMobilePhone'  => tlt_cb_s( $row[12] ?? '' ),
        'ec1Name'              => tlt_cb_s( $row[13] ?? '' ),
        'ec1Phone'             => tlt_cb_s( $row[14] ?? '' ),
        'ec2Name'              => tlt_cb_s( $row[15] ?? '' ),
        'ec2Phone'             => tlt_cb_s( $row[16] ?? '' ),
        'foodAllergy'          => tlt_cb_emergency_is_truthy( $row[17] ?? false ),
        'foodAllergyDetail'    => tlt_cb_s( $row[18] ?? '' ),
        'costumeAllergy'       => tlt_cb_emergency_is_truthy( $row[19] ?? false ),
        'costumeAllergyDetail' => tlt_cb_s( $row[20] ?? '' ),
        'otherAllergy'         => tlt_cb_emergency_is_truthy( $row[21] ?? false ),
        'otherAllergyDetail'   => tlt_cb_s( $row[22] ?? '' ),
        'medicalConditions'    => tlt_cb_s( $row[23] ?? '' ),
        'insurance'            => tlt_cb_s( $row[24] ?? '' ),
        'physicianName'        => tlt_cb_s( $row[25] ?? '' ),
        'physicianPhone'       => tlt_cb_s( $row[26] ?? '' ),
        'hospitalPref'         => tlt_cb_s( $row[27] ?? '' ),
        'ercarePref'           => tlt_cb_s( $row[28] ?? '' ),
        'aliases'              => tlt_cb_s( $row[31] ?? '' ),
        'conviction'           => tlt_cb_s( $row[32] ?? '' ),
        'convictionDetail'     => tlt_cb_s( $row[33] ?? '' ),
        'submittedAt'          => tlt_cb_s( $row[36] ?? '' ),
        'rowIndex'             => $row_index_1b,
    ];
}

/**
 * Read an existing emergency info row by contact ID. Returns [row, rowIndex1b] or null.
 */
function tlt_cb_emergency_find_row_by_contact_id( $contactId ) {
    $tab = TLT_CALLBOARD_EMERGENCY_TAB;
    $rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_CONTACTBOOK_ID, "'{$tab}'!A2:AL", 1, true );
    if ( is_wp_error( $rows ) ) return null;
    $target = trim( (string) $contactId );
    foreach ( $rows as $i => $r ) {
        if ( trim( tlt_cb_s( $r[0] ?? '' ) ) === $target ) {
            return [ 'row' => $r, 'rowIndex1b' => $i + 2 ];
        }
    }
    return null;
}

/**
 * Upsert an Emergency Info row for a contact. Returns true or WP_Error.
 * Preserves the original Submitted At timestamp on update.
 */
function tlt_cb_emergency_write_row( $contactId, $contact_row, $data ) {
    $tab = TLT_CALLBOARD_EMERGENCY_TAB;
    $existing = tlt_cb_emergency_find_row_by_contact_id( $contactId );
    $tz = new DateTimeZone( 'America/Los_Angeles' );
    $now = ( new DateTime( 'now', $tz ) )->format( 'Y-m-d H:i:s' );
    $submittedAt = $existing ? tlt_cb_s( $existing['row'][36] ?? '' ) : $now;
    if ( $submittedAt === '' ) $submittedAt = $now;

    $row_values = [
        $contactId,
        tlt_cb_s( $data['firstName']            ?? '' ),
        tlt_cb_s( $data['middleName']           ?? '' ),
        tlt_cb_s( $data['lastName']             ?? '' ),
        tlt_cb_s( $data['dob']                  ?? '' ),
        tlt_cb_s( $data['over18']               ?? '' ),
        tlt_cb_s( $data['address']              ?? '' ),
        tlt_cb_s( $data['homePhone']            ?? '' ),
        tlt_cb_s( $data['mobilePhone']          ?? '' ),
        tlt_cb_s( $data['guardianName']         ?? '' ),
        tlt_cb_s( $data['guardianAddress']      ?? '' ),
        tlt_cb_s( $data['guardianHomePhone']    ?? '' ),
        tlt_cb_s( $data['guardianMobilePhone']  ?? '' ),
        tlt_cb_s( $data['ec1Name']              ?? '' ),
        tlt_cb_s( $data['ec1Phone']             ?? '' ),
        tlt_cb_s( $data['ec2Name']              ?? '' ),
        tlt_cb_s( $data['ec2Phone']             ?? '' ),
        tlt_cb_emergency_is_truthy( $data['foodAllergy']    ?? false ),
        tlt_cb_s( $data['foodAllergyDetail']    ?? '' ),
        tlt_cb_emergency_is_truthy( $data['costumeAllergy'] ?? false ),
        tlt_cb_s( $data['costumeAllergyDetail'] ?? '' ),
        tlt_cb_emergency_is_truthy( $data['otherAllergy']   ?? false ),
        tlt_cb_s( $data['otherAllergyDetail']   ?? '' ),
        tlt_cb_s( $data['medicalConditions']    ?? '' ),
        tlt_cb_s( $data['insurance']            ?? '' ),
        tlt_cb_s( $data['physicianName']        ?? '' ),
        tlt_cb_s( $data['physicianPhone']       ?? '' ),
        tlt_cb_s( $data['hospitalPref']         ?? '' ),
        tlt_cb_s( $data['ercarePref']           ?? '' ),
        tlt_cb_s( $data['medicalSignature']     ?? '' ),
        tlt_cb_s( $data['medicalDate']          ?? '' ),
        tlt_cb_s( $data['aliases']              ?? '' ),
        tlt_cb_s( $data['conviction']           ?? '' ),
        tlt_cb_s( $data['convictionDetail']     ?? '' ),
        tlt_cb_s( $data['watchSignature']       ?? '' ),
        tlt_cb_s( $data['watchDate']            ?? '' ),
        $submittedAt,
        $now,
    ];

    if ( $existing ) {
        $r = $existing['rowIndex1b'];
        $res = tlt_callboard_sheets_write( TLT_CALLBOARD_CONTACTBOOK_ID, "'{$tab}'!A{$r}:AL{$r}", [ $row_values ] );
        if ( is_wp_error( $res ) ) return $res;
    } else {
        $tok = tlt_callboard_google_access_token();
        if ( is_wp_error( $tok ) ) return $tok;
        $url = 'https://sheets.googleapis.com/v4/spreadsheets/' . TLT_CALLBOARD_CONTACTBOOK_ID
            . '/values/' . rawurlencode( "'{$tab}'!A:AL" ) . ':append?valueInputOption=USER_ENTERED&insertDataOption=INSERT_ROWS';
        $resp = wp_remote_post( $url, [
            'headers' => [ 'Authorization' => 'Bearer ' . $tok, 'Content-Type' => 'application/json' ],
            'body'    => wp_json_encode( [ 'values' => [ $row_values ] ] ),
            'timeout' => 30,
        ] );
        if ( is_wp_error( $resp ) ) return $resp;
        tlt_cb_bump_cache();
    }

    // Canonical-copy side effect: write mobile phone back to Contactbook col G (Phone).
    $mobile = trim( tlt_cb_s( $data['mobilePhone'] ?? '' ) );
    if ( $mobile !== '' ) {
        $cb_rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_CONTACTBOOK_ID, 'Contactbook!A2:A', 1, true );
        if ( ! is_wp_error( $cb_rows ) ) {
            foreach ( $cb_rows as $i => $cb_r ) {
                if ( trim( tlt_cb_s( $cb_r[0] ?? '' ) ) === (string) $contactId ) {
                    $rn = $i + 2;
                    tlt_callboard_sheets_write( TLT_CALLBOARD_CONTACTBOOK_ID, "Contactbook!G{$rn}", [[ $mobile ]] );
                    break;
                }
            }
        }
    }

    return true;
}

/**
 * Medical PDF tag map — matches GAS _medicalTagMap.
 */
function tlt_cb_emergency_medical_tag_map( $full_name, $d ) {
    $over18 = strtolower( tlt_cb_s( $d['over18'] ?? '' ) );
    $over18_disp = $over18 === 'yes' ? 'Yes' : ( $over18 === 'no' ? 'No' : '' );

    $guardian_block = 'N/A (18+)';
    if ( $over18 === 'no' ) {
        $parts = [];
        $gn = tlt_cb_s( $d['guardianName']        ?? '' );
        $ga = tlt_cb_s( $d['guardianAddress']     ?? '' );
        $gh = tlt_cb_s( $d['guardianHomePhone']   ?? '' );
        $gm = tlt_cb_s( $d['guardianMobilePhone'] ?? '' );
        if ( $gn !== '' ) $parts[] = $gn;
        if ( $ga !== '' ) $parts[] = $ga;
        if ( $gh !== '' ) $parts[] = 'Home: ' . $gh;
        if ( $gm !== '' ) $parts[] = 'Mobile: ' . $gm;
        $guardian_block = implode( ' - ', $parts );
        if ( $guardian_block === '' ) $guardian_block = 'None on file';
    }

    $allergy_out = function( $flag, $detail ) {
        if ( tlt_cb_emergency_is_truthy( $flag ) ) {
            $t = trim( tlt_cb_s( $detail ) );
            return $t !== '' ? $t : 'Yes (no detail provided)';
        }
        return 'None';
    };

    return [
        '<<FullName>>'          => $full_name,
        '<<DOB>>'               => tlt_cb_s( $d['dob']              ?? '' ),
        '<<Address>>'           => tlt_cb_s( $d['address']          ?? '' ),
        '<<HomePhone>>'         => tlt_cb_s( $d['homePhone']        ?? '' ),
        '<<MobilePhone>>'       => tlt_cb_s( $d['mobilePhone']      ?? '' ),
        '<<Over18>>'            => $over18_disp,
        '<<GuardianBlock>>'     => $guardian_block,
        '<<EC1Name>>'           => tlt_cb_s( $d['ec1Name']          ?? '' ),
        '<<EC1Phone>>'          => tlt_cb_s( $d['ec1Phone']         ?? '' ),
        '<<EC2Name>>'           => tlt_cb_s( $d['ec2Name']          ?? '' ),
        '<<EC2Phone>>'          => tlt_cb_s( $d['ec2Phone']         ?? '' ),
        '<<FoodAllergy>>'       => $allergy_out( $d['foodAllergy']    ?? false, $d['foodAllergyDetail']    ?? '' ),
        '<<CostumeAllergy>>'    => $allergy_out( $d['costumeAllergy'] ?? false, $d['costumeAllergyDetail'] ?? '' ),
        '<<OtherAllergy>>'      => $allergy_out( $d['otherAllergy']   ?? false, $d['otherAllergyDetail']   ?? '' ),
        '<<MedicalConditions>>' => trim( tlt_cb_s( $d['medicalConditions'] ?? '' ) ) !== '' ? tlt_cb_s( $d['medicalConditions'] ) : 'None reported',
        '<<Insurance>>'         => tlt_cb_s( $d['insurance']        ?? '' ),
        '<<PhysicianName>>'     => tlt_cb_s( $d['physicianName']    ?? '' ),
        '<<PhysicianPhone>>'    => tlt_cb_s( $d['physicianPhone']   ?? '' ),
        '<<HospitalPref>>'      => tlt_cb_s( $d['hospitalPref']     ?? '' ),
        '<<ERCarePref>>'        => tlt_cb_s( $d['ercarePref']       ?? '' ),
        '<<MedicalSignature>>'  => tlt_cb_s( $d['medicalSignature'] ?? '' ),
        '<<MedicalDate>>'       => tlt_cb_s( $d['medicalDate']      ?? '' ),
    ];
}

/**
 * WATCH PDF tag map — matches GAS _watchTagMap.
 */
function tlt_cb_emergency_watch_tag_map( $full_name, $d ) {
    $conviction = strtolower( tlt_cb_s( $d['conviction'] ?? '' ) );
    $conviction_disp = $conviction === 'yes' ? 'Yes' : ( $conviction === 'no' ? 'No' : '' );
    $conviction_detail = $conviction === 'yes' ? tlt_cb_s( $d['convictionDetail'] ?? '' ) : 'N/A';
    return [
        '<<FullName>>'         => $full_name,
        '<<Aliases>>'          => trim( tlt_cb_s( $d['aliases'] ?? '' ) ) !== '' ? tlt_cb_s( $d['aliases'] ) : 'None',
        '<<DOB>>'              => tlt_cb_s( $d['dob'] ?? '' ),
        '<<Conviction>>'       => $conviction_disp,
        '<<ConvictionDetail>>' => $conviction_detail,
        '<<WatchSignature>>'   => tlt_cb_s( $d['watchSignature'] ?? '' ),
        '<<WatchDate>>'        => tlt_cb_s( $d['watchDate']      ?? '' ),
    ];
}

/**
 * Read season folder Drive ID from the Season tab. Matches GAS _readSeasonFolderIdFromSheet.
 * Looks for label rows: "Season Drive" / "Season Drive Folder ID" / "Season Folder" in col A.
 */
function tlt_cb_emergency_season_folder_id() {
    $rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_SHEET_ID, 'Season!A1:B20', 60, true );
    if ( is_wp_error( $rows ) ) return '';
    $labels = [ 'season drive', 'season drive folder id', 'season drive url', 'season folder' ];
    foreach ( $rows as $r ) {
        $label = strtolower( trim( tlt_cb_s( $r[0] ?? '' ) ) );
        if ( ! in_array( $label, $labels, true ) ) continue;
        $val = trim( tlt_cb_s( $r[1] ?? '' ) );
        if ( $val === '' ) continue;
        if ( preg_match( '~/folders/([a-zA-Z0-9_-]+)~', $val, $m ) ) return $m[1];
        return $val;
    }
    return '';
}

/**
 * Look for a subfolder by exact name in a Drive folder. Returns folder ID or null.
 */
function tlt_cb_emergency_find_child_folder( $parent_id, $name ) {
    $tok = tlt_callboard_google_access_token();
    if ( is_wp_error( $tok ) ) return null;
    $q = "'{$parent_id}' in parents and name='" . str_replace( "'", "\\'", $name ) . "' and mimeType='application/vnd.google-apps.folder' and trashed=false";
    $url = 'https://www.googleapis.com/drive/v3/files?q=' . rawurlencode( $q ) . '&fields=files(id,name)&supportsAllDrives=true&includeItemsFromAllDrives=true';
    $resp = wp_remote_get( $url, [ 'headers' => [ 'Authorization' => 'Bearer ' . $tok ], 'timeout' => 20 ] );
    if ( is_wp_error( $resp ) ) return null;
    $body = json_decode( wp_remote_retrieve_body( $resp ), true );
    $files = $body['files'] ?? [];
    return $files ? $files[0]['id'] : null;
}

/**
 * Find or create a subfolder by name in a Drive folder. Returns folder ID or WP_Error.
 */
function tlt_cb_emergency_find_or_create_child_folder( $parent_id, $name ) {
    $existing = tlt_cb_emergency_find_child_folder( $parent_id, $name );
    if ( $existing ) return $existing;
    $tok = tlt_callboard_google_access_token();
    if ( is_wp_error( $tok ) ) return $tok;
    $resp = wp_remote_post( 'https://www.googleapis.com/drive/v3/files?supportsAllDrives=true', [
        'headers' => [ 'Authorization' => 'Bearer ' . $tok, 'Content-Type' => 'application/json' ],
        'body'    => wp_json_encode( [ 'name' => $name, 'mimeType' => 'application/vnd.google-apps.folder', 'parents' => [ $parent_id ] ] ),
        'timeout' => 20,
    ] );
    if ( is_wp_error( $resp ) ) return $resp;
    $body = json_decode( wp_remote_retrieve_body( $resp ), true );
    if ( empty( $body['id'] ) ) return new WP_Error( 'folder_create_failed', 'Could not create folder ' . $name );
    return $body['id'];
}

/**
 * Ensure a Drive folder is shared with the given email as editor. Idempotent.
 */
function tlt_cb_emergency_ensure_folder_shared( $folder_id, $email ) {
    $email_lc = strtolower( trim( $email ) );
    if ( $email_lc === '' ) return true;
    $tok = tlt_callboard_google_access_token();
    if ( is_wp_error( $tok ) ) return $tok;
    $list = wp_remote_get( 'https://www.googleapis.com/drive/v3/files/' . $folder_id . '/permissions?fields=permissions(id,type,emailAddress,role)&supportsAllDrives=true',
        [ 'headers' => [ 'Authorization' => 'Bearer ' . $tok ], 'timeout' => 20 ] );
    if ( ! is_wp_error( $list ) ) {
        $body = json_decode( wp_remote_retrieve_body( $list ), true );
        foreach ( ( $body['permissions'] ?? [] ) as $p ) {
            if ( strtolower( $p['emailAddress'] ?? '' ) === $email_lc ) return true;
        }
    }
    wp_remote_post( 'https://www.googleapis.com/drive/v3/files/' . $folder_id . '/permissions?sendNotificationEmail=false&supportsAllDrives=true', [
        'headers' => [ 'Authorization' => 'Bearer ' . $tok, 'Content-Type' => 'application/json' ],
        'body'    => wp_json_encode( [ 'type' => 'user', 'role' => 'writer', 'emailAddress' => $email ] ),
        'timeout' => 20,
    ] );
    return true;
}

/**
 * Bootstrap the medical/WATCH template Docs on first use. Stores ID in wp_options.
 * Returns Doc ID or WP_Error.
 */
function tlt_cb_emergency_bootstrap_template( $kind ) {
    $opt = ( $kind === 'watch' ) ? 'tlt_cb_emergency_watch_template_id' : 'tlt_cb_emergency_medical_template_id';
    $id = get_option( $opt, '' );
    if ( $id ) {
        // Verify the doc still exists (may have been trashed).
        $tok = tlt_callboard_google_access_token();
        if ( ! is_wp_error( $tok ) ) {
            $resp = wp_remote_get( 'https://www.googleapis.com/drive/v3/files/' . $id . '?fields=id,trashed&supportsAllDrives=true',
                [ 'headers' => [ 'Authorization' => 'Bearer ' . $tok ], 'timeout' => 15 ] );
            $body = json_decode( wp_remote_retrieve_body( $resp ), true );
            if ( isset( $body['id'] ) && empty( $body['trashed'] ) ) return $id;
        }
    }
    $parent = TLT_CALLBOARD_EMERGENCY_TEMPLATE_PARENT_FOLDER;
    $title  = ( $kind === 'watch' )
        ? 'TLT Emergency Info Template - WATCH Release'
        : 'TLT Emergency Info Template - Medical';
    $doc_id = tlt_cb_docs_create( $title, $parent );
    if ( is_wp_error( $doc_id ) ) return $doc_id;

    // Populate. Uses batchUpdate insertText + updateTextStyle.
    $requests = ( $kind === 'watch' )
        ? tlt_cb_emergency_build_watch_template_requests()
        : tlt_cb_emergency_build_medical_template_requests();
    $res = tlt_cb_docs_batch_update( $doc_id, $requests );
    if ( is_wp_error( $res ) ) return $res;

    update_option( $opt, $doc_id, false );
    return $doc_id;
}

/**
 * Docs API requests to build the medical template body from scratch.
 * Rendered top-to-bottom by inserting at index 1.
 * Mirrors _populateMedicalTemplate in TLTBioApp.js (lines 769-808).
 */
function tlt_cb_emergency_build_medical_template_requests() {
    $lines = [];
    $lines[] = [ 'text' => "Emergency Medical Form\n", 'style' => 'HEADING_1' ];
    $lines[] = [ 'text' => "\n" ];
    $fields = [
        [ 'Name',                '<<FullName>>' ],
        [ 'Date of Birth',       '<<DOB>>' ],
        [ 'Address',             '<<Address>>' ],
        [ 'Home Phone',          '<<HomePhone>>' ],
        [ 'Mobile Phone',        '<<MobilePhone>>' ],
        [ 'Age 18+',             '<<Over18>>' ],
        [ 'Parent / Guardian',   '<<GuardianBlock>>' ],
        [ 'Emergency Contact 1', '<<EC1Name>> - <<EC1Phone>>' ],
        [ 'Emergency Contact 2', '<<EC2Name>> - <<EC2Phone>>' ],
        [ 'Food Allergies',      '<<FoodAllergy>>' ],
        [ 'Costume Allergies',   '<<CostumeAllergy>>' ],
        [ 'Other Allergies',     '<<OtherAllergy>>' ],
        [ 'Medical Conditions',  '<<MedicalConditions>>' ],
        [ 'Insurance',           '<<Insurance>>' ],
        [ 'Primary Physician',   '<<PhysicianName>> - <<PhysicianPhone>>' ],
        [ 'Hospital Preference', '<<HospitalPref>>' ],
        [ 'ER Care Preference',  '<<ERCarePref>>' ],
    ];
    foreach ( $fields as $f ) {
        $lines[] = [ 'label' => $f[0] . ': ', 'value' => $f[1] . "\n" ];
    }
    $lines[] = [ 'text' => "\n" ];
    $lines[] = [ 'text' => "In the event of a physical injury or illness, I hereby release, discharge and/or otherwise indemnify Tacoma Little Theatre, their employees and associated personnel, including the owners and employees of offsite rehearsal spaces, from any and all liability regarding a claim of injury or illness as a result of my participation in this production at Tacoma Little Theatre. In the event of an emergency, I hereby give my consent for emergency medical care prescribed by a duly licensed Doctor of Medicine or Doctor of Dentistry.\n", 'italic' => true ];
    $lines[] = [ 'text' => "\n" ];
    $lines[] = [ 'label' => 'Signed electronically by: ', 'value' => "<<MedicalSignature>>\n" ];
    $lines[] = [ 'label' => 'Date: ', 'value' => "<<MedicalDate>>\n" ];

    return tlt_cb_emergency_render_template_lines( $lines );
}

/**
 * Docs API requests to build the WATCH template body from scratch.
 * Mirrors _populateWatchTemplate in TLTBioApp.js (lines 810-839).
 */
function tlt_cb_emergency_build_watch_template_requests() {
    $lines = [];
    $lines[] = [ 'text' => "Washington State Patrol Criminal Background Check Release Form\n", 'style' => 'HEADING_1' ];
    $lines[] = [ 'text' => "\n" ];
    $lines[] = [ 'text' => "Tacoma Little Theatre will use this information and subsequent record only in making initial employment or engagement decisions. Further dissemination or use of this record is strictly prohibited without written permission from the applicant.\n", 'italic' => true ];
    $lines[] = [ 'text' => "\n" ];
    $fields = [
        [ 'Applicant Name',            '<<FullName>>' ],
        [ 'Alias / Maiden / Other',    '<<Aliases>>' ],
        [ 'Date of Birth',             '<<DOB>>' ],
        [ 'Convicted of a crime against children or other persons?', '<<Conviction>>' ],
        [ 'If Yes, please specify',    '<<ConvictionDetail>>' ],
    ];
    foreach ( $fields as $f ) {
        $lines[] = [ 'label' => $f[0] . ': ', 'value' => $f[1] . "\n" ];
    }
    $lines[] = [ 'text' => "\n" ];
    $lines[] = [ 'text' => "I declare that the above information is true and accurate. I grant Tacoma Little Theatre permission to conduct a Washington State Patrol criminal history background check using the above information.\n", 'italic' => true ];
    $lines[] = [ 'text' => "\n" ];
    $lines[] = [ 'label' => 'Signed electronically by: ', 'value' => "<<WatchSignature>>\n" ];
    $lines[] = [ 'label' => 'Date: ', 'value' => "<<WatchDate>>\n" ];

    return tlt_cb_emergency_render_template_lines( $lines );
}

/**
 * Turn structured template lines into Docs API batchUpdate requests.
 * We append everything at endOfSegmentLocation via multiple insertText calls,
 * tracking a running index to emit updateTextStyle ranges (bold labels, italics).
 * NOTE: Docs indices are UTF-16 code units. All template text here is ASCII
 *       except " - " (hyphens, not em-dash) and standard punctuation, so
 *       mb_strlen(..., 'UTF-8') == UTF-16 code units for our purposes.
 */
function tlt_cb_emergency_render_template_lines( $lines ) {
    $requests = [];
    $cursor = 1; // Docs body starts at index 1.
    $body = '';

    foreach ( $lines as $ln ) {
        if ( isset( $ln['label'] ) ) {
            // Bold label then non-bold value.
            $label_len = mb_strlen( $ln['label'], 'UTF-8' );
            $value_len = mb_strlen( $ln['value'], 'UTF-8' );
            $body .= $ln['label'] . $ln['value'];
            $requests[] = [ 'insertText' => [ 'location' => [ 'index' => $cursor ], 'text' => $ln['label'] ] ];
            $requests[] = [ 'updateTextStyle' => [
                'range' => [ 'startIndex' => $cursor, 'endIndex' => $cursor + $label_len ],
                'textStyle' => [ 'bold' => true ],
                'fields' => 'bold',
            ] ];
            $cursor += $label_len;
            $requests[] = [ 'insertText' => [ 'location' => [ 'index' => $cursor ], 'text' => $ln['value'] ] ];
            $requests[] = [ 'updateTextStyle' => [
                'range' => [ 'startIndex' => $cursor, 'endIndex' => $cursor + $value_len ],
                'textStyle' => [ 'bold' => false ],
                'fields' => 'bold',
            ] ];
            $cursor += $value_len;
        } else {
            $text = $ln['text'];
            $len  = mb_strlen( $text, 'UTF-8' );
            $body .= $text;
            $requests[] = [ 'insertText' => [ 'location' => [ 'index' => $cursor ], 'text' => $text ] ];
            if ( isset( $ln['style'] ) && $ln['style'] === 'HEADING_1' ) {
                $requests[] = [ 'updateParagraphStyle' => [
                    'range' => [ 'startIndex' => $cursor, 'endIndex' => $cursor + $len ],
                    'paragraphStyle' => [ 'namedStyleType' => 'HEADING_1' ],
                    'fields' => 'namedStyleType',
                ] ];
            }
            if ( ! empty( $ln['italic'] ) ) {
                $requests[] = [ 'updateTextStyle' => [
                    'range' => [ 'startIndex' => $cursor, 'endIndex' => $cursor + $len ],
                    'textStyle' => [ 'italic' => true ],
                    'fields' => 'italic',
                ] ];
            }
            $cursor += $len;
        }
    }
    return $requests;
}

/**
 * Copy the template Doc, run replaceAllText for every tag, export as PDF, trash the copy.
 * Returns raw PDF bytes (string) or WP_Error.
 */
function tlt_cb_emergency_generate_pdf( $template_id, $tag_map, $temp_name ) {
    $copy = tlt_cb_drive_copy( $template_id, TLT_CALLBOARD_EMERGENCY_TEMPLATE_PARENT_FOLDER, '__temp_' . $temp_name . '_' . time() );
    if ( is_wp_error( $copy ) ) return $copy;
    $temp_id = $copy['id'] ?? '';
    if ( ! $temp_id ) return new WP_Error( 'copy_no_id', 'Drive copy returned no id' );

    // Build one replaceAllText request per tag.
    $requests = [];
    foreach ( $tag_map as $find => $repl ) {
        $requests[] = [ 'replaceAllText' => [
            'containsText' => [ 'text' => $find, 'matchCase' => true ],
            'replaceText' => (string) $repl,
        ] ];
    }
    $res = tlt_cb_docs_batch_update( $temp_id, $requests );
    if ( is_wp_error( $res ) ) {
        tlt_cb_drive_trash( $temp_id );
        return $res;
    }

    // Export as PDF bytes.
    $pdf = tlt_cb_contract_export_pdf( $temp_id );
    tlt_cb_drive_trash( $temp_id );
    if ( is_wp_error( $pdf ) ) return $pdf;
    return $pdf;
}

/**
 * Multipart-upload a raw PDF blob to a Drive folder as {name}.
 * If a file with the same name exists in the folder, trash it first.
 * Returns file ID or WP_Error.
 */
function tlt_cb_emergency_replace_pdf_in_folder( $folder_id, $filename, $pdf_bytes ) {
    // Any file with the same name has to be evicted before the new one lands or
    // we accumulate duplicates. Preferred path: trash it. Fallback for legacy
    // GAS-era files owned by other users (SA lacks canTrash on those): rename
    // the old file with a timestamp suffix so ours wins the canonical name.
    $existing = tlt_cb_drive_find_in_folder( $folder_id, $filename );
    if ( ! is_wp_error( $existing ) && is_array( $existing ) ) {
        foreach ( $existing as $f ) {
            if ( empty( $f['id'] ) ) continue;
            $tres = tlt_cb_drive_trash( $f['id'] );
            if ( is_wp_error( $tres ) ) {
                $dot = strrpos( $filename, '.' );
                $ext = $dot !== false ? substr( $filename, $dot ) : '';
                $stem = $dot !== false ? substr( $filename, 0, $dot ) : $filename;
                $stamp = date( 'Y-m-d-His' );
                tlt_cb_drive_rename( $f['id'], $stem . ' (superseded ' . $stamp . ')' . $ext );
            }
        }
    }

    $tok = tlt_callboard_google_access_token();
    if ( is_wp_error( $tok ) ) return $tok;

    $boundary = 'tlt_cb_pdf_' . bin2hex( random_bytes( 8 ) );
    $meta = wp_json_encode( [ 'name' => $filename, 'parents' => [ $folder_id ], 'mimeType' => 'application/pdf' ] );
    $body = "--{$boundary}\r\n"
          . "Content-Type: application/json; charset=UTF-8\r\n\r\n"
          . $meta . "\r\n"
          . "--{$boundary}\r\n"
          . "Content-Type: application/pdf\r\n"
          . "Content-Transfer-Encoding: binary\r\n\r\n"
          . $pdf_bytes . "\r\n"
          . "--{$boundary}--";

    $resp = wp_remote_post( 'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&supportsAllDrives=true', [
        'headers' => [
            'Authorization' => 'Bearer ' . $tok,
            'Content-Type'  => 'multipart/related; boundary=' . $boundary,
        ],
        'body' => $body,
        'timeout' => 60,
    ] );
    if ( is_wp_error( $resp ) ) return $resp;
    $code = wp_remote_retrieve_response_code( $resp );
    $rb = json_decode( wp_remote_retrieve_body( $resp ), true );
    if ( $code < 200 || $code >= 300 || empty( $rb['id'] ) ) {
        return new WP_Error( 'upload_failed', 'Drive upload failed: HTTP ' . $code . ' ' . wp_remote_retrieve_body( $resp ) );
    }
    return $rb['id'];
}

/**
 * List distinct shows for a contact by name (first+last, case-insensitive).
 * Scans Production Teams + Actors — matches GAS getShowsForContact behavior.
 */
function tlt_cb_emergency_shows_for_contact( $first, $last, $contactId = '' ) {
    $shows = [];
    $seen  = [];
    $f_lc = strtolower( trim( $first ) );
    $l_lc = strtolower( trim( $last  ) );
    if ( $f_lc === '' && $l_lc === '' ) return [];

    foreach ( [ "'Production Teams'!A2:E", 'Actors!A2:E' ] as $range ) {
        $rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_SHEET_ID, $range, 60, true );
        if ( is_wp_error( $rows ) ) continue;
        foreach ( $rows as $r ) {
            $show = trim( tlt_cb_s( $r[0] ?? '' ) );
            if ( $show === '' ) continue;
            $rf = strtolower( trim( tlt_cb_s( $r[2] ?? '' ) ) );
            $rl = strtolower( trim( tlt_cb_s( $r[4] ?? '' ) ) );
            if ( $rf !== $f_lc || $rl !== $l_lc ) continue;
            if ( isset( $seen[ $show ] ) ) continue;
            $seen[ $show ] = true;
            $shows[] = $show;
        }
    }
    return $shows;
}

/**
 * Write "Submitted" to Emergency Info status columns for every matching Production Teams/Actors row
 * for the named show. Emergency status lives in cols Q (17) + R (18) — separate from bio status.
 */
function tlt_cb_emergency_mark_submitted_for_show( $show, $first, $last ) {
    $tz = new DateTimeZone( 'America/Los_Angeles' );
    $now = ( new DateTime( 'now', $tz ) )->format( 'Y-m-d H:i:s' );
    $f_lc = strtolower( trim( $first ) );
    $l_lc = strtolower( trim( $last ) );
    $show_trim = trim( $show );
    foreach ( [
        [ 'range' => "'Production Teams'!A2:E", 'tab' => "'Production Teams'" ],
        [ 'range' => 'Actors!A2:E',              'tab' => 'Actors' ],
    ] as $conf ) {
        $rows = tlt_callboard_sheet_rows( TLT_CALLBOARD_SHEET_ID, $conf['range'], 1, true );
        if ( is_wp_error( $rows ) ) continue;
        foreach ( $rows as $i => $r ) {
            if ( trim( tlt_cb_s( $r[0] ?? '' ) ) !== $show_trim ) continue;
            if ( strtolower( trim( tlt_cb_s( $r[2] ?? '' ) ) ) !== $f_lc ) continue;
            if ( strtolower( trim( tlt_cb_s( $r[4] ?? '' ) ) ) !== $l_lc ) continue;
            $rn = $i + 2;
            tlt_callboard_sheets_write( TLT_CALLBOARD_SHEET_ID, "{$conf['tab']}!Q{$rn}", [[ 'Submitted' ]] );
            tlt_callboard_sheets_write( TLT_CALLBOARD_SHEET_ID, "{$conf['tab']}!R{$rn}", [[ $now         ]] );
        }
    }
}

/**
 * Send the WATCH review flag email via Resend when conviction=yes.
 */
function tlt_cb_emergency_send_watch_flag( $to_email, $full_name, $data, $watch_file_id = '' ) {
    $dob = tlt_cb_s( $data['dob'] ?? '' );
    if ( $dob === '' ) $dob = '(not provided)';
    $detail = trim( tlt_cb_s( $data['convictionDetail'] ?? '' ) );
    if ( $detail === '' ) $detail = '(no detail provided)';
    $file_url = $watch_file_id ? 'https://drive.google.com/file/d/' . rawurlencode( $watch_file_id ) . '/view' : '';
    $btn = $file_url
        ? '<p style="margin:16px 0"><a href="' . esc_url( $file_url ) . '" style="background:#a2242a;color:#fff;text-decoration:none;padding:10px 20px;border-radius:6px;display:inline-block;font-weight:bold;">Open WATCH PDF in Drive</a></p>'
        : '';
    $html = '<div style="font-family:Arial,sans-serif;max-width:640px;margin:auto;padding:20px;color:#222;">'
          . '<div style="background:#a2242a;color:#fff;padding:14px 20px;border-radius:8px 8px 0 0;">'
          . '<h1 style="margin:0;font-size:20px;">TLT — WATCH Review Needed</h1>'
          . '</div>'
          . '<div style="background:#fff;padding:20px;border:1px solid #eee;border-top:0;border-radius:0 0 8px 8px;">'
          . '<p style="margin:0 0 8px;"><strong>Name:</strong> ' . esc_html( $full_name ) . '</p>'
          . '<p style="margin:0 0 8px;"><strong>Date of Birth:</strong> ' . esc_html( $dob ) . '</p>'
          . '<p style="margin:0 0 8px;"><strong>Applicant reported a conviction against children or other persons.</strong></p>'
          . '<p style="margin:0 0 8px;"><strong>Detail provided:</strong></p>'
          . '<p style="margin:0 0 8px;padding:10px;background:#f6f6f6;border-radius:6px;white-space:pre-wrap;">' . esc_html( $detail ) . '</p>'
          . $btn
          . '<p style="margin:16px 0 0;font-size:13px;color:#666;">This flag was raised automatically from the WATCH release form submission.</p>'
          . '</div>'
          . '</div>';
    return tlt_cb_send_mail( $to_email, 'WATCH Review Needed: ' . $full_name, $html );
}

/**
 * Append a Bio Log row for an Emergency Info submission. Non-fatal on failure.
 */
function tlt_cb_emergency_log( $contactId, $contact_row, $is_update ) {
    $existing = tlt_callboard_sheet_rows( TLT_CALLBOARD_CONTACTBOOK_ID, 'Bio Log!A2:A', 1, true );
    $next_num = is_wp_error( $existing ) ? 1 : ( count( $existing ) + 1 );
    $log_id   = 'LOG-' . str_pad( (string) $next_num, 4, '0', STR_PAD_LEFT );
    $tz = new DateTimeZone( 'America/Los_Angeles' );
    $now = ( new DateTime( 'now', $tz ) )->format( 'Y-m-d H:i:s' );
    $row = [
        $log_id, $now, $contactId,
        tlt_cb_s( $contact_row[1] ?? '' ),
        tlt_cb_s( $contact_row[3] ?? '' ),
        tlt_cb_s( $contact_row[7] ?? '' ),
        '', 'Emergency Info', $is_update ? 'Updated' : 'Submitted',
        '', '', 'Bio App',
    ];
    $tok = tlt_callboard_google_access_token();
    if ( is_wp_error( $tok ) ) return;
    $url = 'https://sheets.googleapis.com/v4/spreadsheets/' . TLT_CALLBOARD_CONTACTBOOK_ID
        . '/values/' . rawurlencode( "'Bio Log'!A:L" ) . ':append?valueInputOption=USER_ENTERED&insertDataOption=INSERT_ROWS';
    wp_remote_post( $url, [
        'headers' => [ 'Authorization' => 'Bearer ' . $tok, 'Content-Type' => 'application/json' ],
        'body'    => wp_json_encode( [ 'values' => [ $row ] ] ),
        'timeout' => 20,
    ] );
    tlt_cb_bump_cache();
}

/* ---------------------------------------------------------------------------
 * REST endpoints
 * ------------------------------------------------------------------------- */

/**
 * GET /bio-emergency?token=X
 * Response: { contact:{...}, emergencyInfo: null | {...} }
 */
function tlt_callboard_ep_bio_emergency( WP_REST_Request $req ) {
    $token = trim( (string) $req->get_param( 'token' ) );
    if ( $token === '' ) return rest_ensure_response( [ 'error' => 'No token provided.' ] );

    $found = tlt_cb_bio_find_by_token( $token );
    if ( is_wp_error( $found ) ) return rest_ensure_response( [ 'error' => $found->get_error_message() ] );
    $cb_row    = $found['row'];
    $contactId = $found['contactId'];

    tlt_cb_emergency_ensure_tab();
    $existing = tlt_cb_emergency_find_row_by_contact_id( $contactId );
    $eiObject = $existing ? tlt_cb_emergency_row_to_object( $existing['row'], $existing['rowIndex1b'] ) : null;

    return rest_ensure_response( [
        'contact' => [
            'contactId'  => $contactId,
            'firstName'  => tlt_cb_s( $cb_row[1] ?? '' ),
            'middleName' => tlt_cb_s( $cb_row[2] ?? '' ),
            'lastName'   => tlt_cb_s( $cb_row[3] ?? '' ),
            'phone'      => tlt_cb_s( $cb_row[6] ?? '' ),
            'email'      => tlt_cb_s( $cb_row[7] ?? '' ),
        ],
        'emergencyInfo' => $eiObject,
    ] );
}

/**
 * POST /bio-emergency-submit  { token, data:{...} }
 *
 * Fast path (blocks the response, ~3-5s):
 *   1) Validate token.
 *   2) Upsert the Emergency Info row (this is the "sent" the submitter waits for).
 *   3) Append a Bio Log entry.
 *
 * Slow path (deferred via register_shutdown_function so the user doesn't wait):
 *   4) Bootstrap templates on first use (~4s one-time cost).
 *   5) Docs API replaceAllText + PDF export for medical + WATCH.
 *   6) Multipart upload PDFs to every relevant Drive folder (~2-4s each).
 *   7) Send WATCH review flag email if conviction=yes.
 *
 * Response is {success:true} the moment the sheet row lands. If a PDF or upload
 * fails in the background it's silent — matches the original GAS queue-and-forget
 * semantics. Blake can spot-check Drive folders after submission if needed.
 */
function tlt_callboard_ep_bio_emergency_submit( WP_REST_Request $req ) {
    $body = $req->get_json_params();
    $token = trim( (string) ( $body['token'] ?? '' ) );
    $data  = is_array( $body['data'] ?? null ) ? $body['data'] : [];
    if ( $token === '' ) return rest_ensure_response( [ 'success' => false, 'error' => 'No token provided.' ] );

    $found = tlt_cb_bio_find_by_token( $token );
    if ( is_wp_error( $found ) ) return rest_ensure_response( [ 'success' => false, 'error' => $found->get_error_message() ] );
    $cb_row    = $found['row'];
    $contactId = $found['contactId'];

    tlt_cb_emergency_ensure_tab();
    $is_update = tlt_cb_emergency_find_row_by_contact_id( $contactId ) !== null;
    $write = tlt_cb_emergency_write_row( $contactId, $cb_row, $data );
    if ( is_wp_error( $write ) ) return rest_ensure_response( [ 'success' => false, 'error' => 'Could not save Emergency Info: ' . $write->get_error_message() ] );

    tlt_cb_emergency_log( $contactId, $cb_row, $is_update );

    // Send the JSON response and close the HTTP connection RIGHT NOW so the
    // submitter isn't stuck watching a spinner for 30-45s while we generate
    // 8 PDFs and upload them to Drive. Cloudways nginx buffers WP's normal
    // REST output past the shutdown hook, so we serialize + echo manually.
    $response_json = wp_json_encode( [ 'success' => true ] );
    if ( ! headers_sent() ) {
        @header( 'Content-Type: application/json; charset=UTF-8' );
        @header( 'Content-Length: ' . strlen( $response_json ) );
        @header( 'Connection: close' );
        @header( 'X-Accel-Buffering: no' ); // Tell nginx not to buffer FPM output.
    }
    // Drop any WP-added output buffers so what we echo goes straight to the wire.
    while ( ob_get_level() > 0 ) { @ob_end_clean(); }
    echo $response_json;
    if ( function_exists( 'fastcgi_finish_request' ) ) {
        @fastcgi_finish_request();
    } elseif ( function_exists( 'litespeed_finish_request' ) ) {
        @litespeed_finish_request();
    } else {
        @flush();
    }

    // We're now detached from the client. Do the slow work with headroom.
    @ignore_user_abort( true );
    @set_time_limit( 180 );

    tlt_cb_emergency_process_pdfs_bg( [ 'contactId' => $contactId, 'cb_row' => $cb_row, 'data' => $data ] );

    // Prevent WP's REST server from also echoing a response.
    exit;
}

/**
 * The slow half of bio-emergency-submit — Docs+Drive PDF generation and upload.
 * Runs after the client has already received {success:true}. Failures land in
 * the PHP error log; the submitter never sees them.
 */
function tlt_cb_emergency_process_pdfs_bg( $ctx ) {
    $contactId = $ctx['contactId'];
    $cb_row    = $ctx['cb_row'];
    $data      = $ctx['data'];

    $first = tlt_cb_s( $cb_row[1] ?? '' );
    $mid   = tlt_cb_s( $cb_row[2] ?? '' );
    $last  = tlt_cb_s( $cb_row[3] ?? '' );
    $suf   = tlt_cb_s( $cb_row[4] ?? '' );
    $parts = array_filter( [ $first, $mid, $last, $suf ], function( $s ) { return trim( (string) $s ) !== ''; } );
    $full_name = implode( ' ', $parts );
    $filename  = $last . ', ' . $first . '.pdf';

    $season_folder_id = tlt_cb_emergency_season_folder_id();
    if ( $season_folder_id === '' ) {
        error_log( 'TLT emergency PDF gen aborted: no season folder id configured.' );
        return;
    }

    // -------- Medical PDF --------
    try {
        $medical_template_id = tlt_cb_emergency_bootstrap_template( 'medical' );
        if ( is_wp_error( $medical_template_id ) ) throw new Exception( $medical_template_id->get_error_message() );
        $medical_pdf = tlt_cb_emergency_generate_pdf( $medical_template_id, tlt_cb_emergency_medical_tag_map( $full_name, $data ), $filename );
        if ( is_wp_error( $medical_pdf ) ) throw new Exception( $medical_pdf->get_error_message() );

        $shows = tlt_cb_emergency_shows_for_contact( $first, $last, $contactId );
        foreach ( $shows as $show_name ) {
            $show_folder_id = tlt_cb_emergency_find_child_folder( $season_folder_id, $show_name );
            if ( ! $show_folder_id ) continue;
            $sm_id = tlt_cb_emergency_find_or_create_child_folder( $show_folder_id, 'Stage Management' );
            if ( is_wp_error( $sm_id ) ) continue;
            $mf_id = tlt_cb_emergency_find_or_create_child_folder( $sm_id, 'Medical Forms' );
            if ( is_wp_error( $mf_id ) ) continue;
            $up = tlt_cb_emergency_replace_pdf_in_folder( $mf_id, $filename, $medical_pdf );
            if ( is_wp_error( $up ) ) continue;
            tlt_cb_emergency_mark_submitted_for_show( $show_name, $first, $last );
        }
    } catch ( Exception $e ) {
        error_log( 'TLT medical PDF gen failed for ' . $contactId . ': ' . $e->getMessage() );
    }

    // -------- WATCH PDF --------
    try {
        $watch_template_id = tlt_cb_emergency_bootstrap_template( 'watch' );
        if ( is_wp_error( $watch_template_id ) ) throw new Exception( $watch_template_id->get_error_message() );
        $watch_pdf = tlt_cb_emergency_generate_pdf( $watch_template_id, tlt_cb_emergency_watch_tag_map( $full_name, $data ), $filename );
        if ( is_wp_error( $watch_pdf ) ) throw new Exception( $watch_pdf->get_error_message() );

        $watch_folder_id = tlt_cb_emergency_find_or_create_child_folder( $season_folder_id, 'WATCH' );
        if ( is_wp_error( $watch_folder_id ) ) throw new Exception( $watch_folder_id->get_error_message() );
        tlt_cb_emergency_ensure_folder_shared( $watch_folder_id, TLT_CALLBOARD_EMERGENCY_WATCH_SHARE_EMAIL );

        $watch_file_id = tlt_cb_emergency_replace_pdf_in_folder( $watch_folder_id, $filename, $watch_pdf );
        if ( is_wp_error( $watch_file_id ) ) throw new Exception( $watch_file_id->get_error_message() );

        if ( strtolower( trim( tlt_cb_s( $data['conviction'] ?? '' ) ) ) === 'yes' ) {
            tlt_cb_emergency_send_watch_flag( TLT_CALLBOARD_EMERGENCY_WATCH_SHARE_EMAIL, $full_name, $data, $watch_file_id );
        }
    } catch ( Exception $e ) {
        error_log( 'TLT WATCH PDF gen failed for ' . $contactId . ': ' . $e->getMessage() );
    }
}
