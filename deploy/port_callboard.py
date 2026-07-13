"""Port the GAS Callboard Index.html to the WordPress-hosted /callboard/.

Strategy: don't touch any of the 53 `google.script.run.xxx()` call sites.
Instead, inject a compat shim that presents the same `google.script.run` API
but routes every call through `fetch()` to our REST endpoints.

Also injects:
  - login overlay HTML right after <body>
  - auth token handling
  - <base> tag removal (GAS-specific)
"""
import re
from pathlib import Path

SRC = Path(r'C:/Users/blake/dev/TLT_Website/deploy/callboard-gas-reference/Index.html')
DST = Path(r'C:/Users/blake/dev/TLT_Website/deploy/callboard-frontend/index.html')

html = SRC.read_text(encoding='utf-8')

# -----------------------------------------------------------------------------
# 1a. Remove GAS-specific <base target="_top"> — not needed outside iframes.
# -----------------------------------------------------------------------------
html = re.sub(r'<base target="_top">\s*\n', '', html)

# -----------------------------------------------------------------------------
# 1b. Add <meta charset="UTF-8"> — GAS defaulted to it, nginx does not. Without
#     this, emoji glyphs render as mojibake (âœ‰ instead of ✉).
# -----------------------------------------------------------------------------
if '<meta charset' not in html:
    html = html.replace('<head>', '<head>\n  <meta charset="UTF-8">', 1)

# -----------------------------------------------------------------------------
# 2. Inject the login overlay HTML right after <body>.
#    Hidden by default; shim reveals when no valid token exists.
# -----------------------------------------------------------------------------
LOGIN_HTML = '''
<!-- ==== TLT Callboard: WordPress-hosted login gate ==== -->
<div id="cb-login-overlay" style="display:none; position:fixed; inset:0; background:#f5f5f5; z-index:99999; align-items:center; justify-content:center;">
  <div style="background:#fff; border:1px solid #e0e0e0; border-radius:10px; padding:32px; max-width:380px; width:calc(100% - 40px); box-shadow:0 4px 24px rgba(0,0,0,0.08);">
    <h2 style="margin:0 0 6px; font-size:20px; font-weight:600;">TLT Callboard</h2>
    <p style="margin:0 0 20px; color:#666; font-size:14px;">Enter your Callboard password.</p>
    <form id="cb-login-form" autocomplete="on">
      <label for="cb-login-pw" style="display:block; font-size:12px; font-weight:600; color:#555; letter-spacing:.5px; text-transform:uppercase; margin-bottom:6px;">Password</label>
      <input id="cb-login-pw" name="password" type="password" required autocomplete="current-password"
             style="width:100%; padding:10px 12px; font-size:15px; border:1px solid #e0e0e0; border-radius:4px; margin-bottom:14px;" />
      <button id="cb-login-submit" type="submit" style="width:100%; padding:11px; background:#a2242a; color:#fff; border:none; border-radius:4px; font-size:15px; font-weight:600; letter-spacing:.4px; cursor:pointer;">Sign In</button>
      <div id="cb-login-err" style="color:#a2242a; font-size:13px; margin-top:10px; min-height:18px;"></div>
    </form>
  </div>
</div>
'''

html = html.replace('<body>', '<body>' + LOGIN_HTML, 1)

# -----------------------------------------------------------------------------
# 3. Inject compat shim + auth JS right after the first <script> tag that
#    contains real app code (the one with `let dashboardData = null;`).
# -----------------------------------------------------------------------------
SHIM = r'''
  /* ==================================================================
     TLT Callboard — WordPress compat shim.

     Every `google.script.run.xxx(args)` call routes through this shim.
     The frontend code below stays UNCHANGED — the shim just intercepts.

     - Reads: routed to GET /wp-json/callboard/v1/<path>
     - Mutations: currently unimplemented on the WP backend; the shim
       shows a clear alert and (optionally) opens the old GAS callboard
       so the user can complete the action there.
     ================================================================== */
  const CB_API_BASE  = '/wp-json/callboard/v1';
  const CB_TOKEN_KEY = 'tlt_callboard_token';
  const CB_OLD_URL   = ''; // TODO(Blake): paste the current GAS webapp URL here for
                           //   mutations to bounce to. Empty = just show an alert.

  function cbGetToken()   { return localStorage.getItem(CB_TOKEN_KEY); }
  function cbSetToken(t)  { localStorage.setItem(CB_TOKEN_KEY, t); }
  function cbClearToken() { localStorage.removeItem(CB_TOKEN_KEY); }

  async function cbApi(path, opts = {}) {
    const token = cbGetToken();
    const headers = Object.assign({ 'Accept': 'application/json' }, opts.headers || {});
    if (token) headers['Authorization'] = 'Bearer ' + token;
    if (opts.body && !(opts.body instanceof FormData)) headers['Content-Type'] = 'application/json';
    const resp = await fetch(CB_API_BASE + path, Object.assign({}, opts, {
      headers,
      body: opts.body ? JSON.stringify(opts.body) : undefined,
    }));
    let json = null;
    try { json = await resp.json(); } catch (_) {}
    if (!resp.ok) {
      const err = new Error((json && (json.message || json.code)) || ('HTTP ' + resp.status));
      err.status = resp.status;
      err.body = json;
      throw err;
    }
    return json;
  }

  // Map GAS method name → REST path builder.
  const CB_READ_ROUTES = {
    getInitialData:            () => '/initial-data',
    getDashboardData:          () => '/dashboard',
    getShows:                  () => '/shows',
    getCurrentSeason:          () => '/current-season',
    getRoles:                  () => '/roles',
    getShowRoster:             (show) => '/show-roster?show=' + encodeURIComponent(show || ''),
    getActorsForShow:          (show) => '/actors-for-show?show=' + encodeURIComponent(show || ''),
    getActors:                 () => '/actors',
    getSalesData:              () => '/sales',
    getBiosData:               () => '/bios',
    getContacts:               () => '/contacts',
    getContractsPageData:      (force) => '/contracts' + (force ? '?force=1' : ''),
    // Same endpoint but tell it to return the array shape (frontend uses this
    // after mutations to refresh — expects Array, not {shows, contracts}).
    getContractsData:          () => '/contracts?shape=array',
    getFullSeasonData:         () => '/full-season',
    getCombinableShows:        (show, role, first, last) => '/combinable-shows'
                                 + '?show='      + encodeURIComponent(show  || '')
                                 + '&role='      + encodeURIComponent(role  || '')
                                 + '&firstName=' + encodeURIComponent(first || '')
                                 + '&lastName='  + encodeURIComponent(last  || ''),
    getScheduleLink:           (show) => '/schedule-link?show=' + encodeURIComponent(show || ''),
    getContactSheetLink:       (show) => '/contact-sheet-link?show=' + encodeURIComponent(show || ''),
    getSeasonCalendarEvents:   () => '/calendar-events',
    getSeasonCalendarConflicts:() => '/calendar-conflicts',
    getProgramData:            (show) => '/program?show=' + encodeURIComponent(show || ''),
    verifyApprovalPassword:    (pw)   => '/verify-approval?password=' + encodeURIComponent(pw || ''),
  };

  // POST mutations (Phase 2) that are actually ported. Each maps a GAS method
  // name → { path, buildBody(args) }. If the map has an entry, we POST to the
  // WP endpoint instead of bouncing to the not-implemented fallback.
  const CB_WRITE_ROUTES = {
    setOkToSend: {
      path: '/set-ok-to-send',
      // GAS signature: setOkToSend(show, roleOrCharacter, firstName, initials, isActor)
      body: function (show, role, firstName, initials, isActor) {
        return { show: show, role: role, firstName: firstName, initials: initials, isActor: !!isActor };
      },
    },
    saveContact: {
      path: '/save-contact',
      // GAS signature: saveContact(contactData) — full object
      body: function (contactData) { return contactData || {}; },
    },
    deleteContact: {
      path: '/delete-contact',
      // GAS signature: deleteContact(firstName, lastName)
      body: function (firstName, lastName) { return { firstName: firstName, lastName: lastName }; },
    },
    syncContactbook: {
      // New endpoint (not in the original GAS callboard) — bulk-fanout every
      // Contactbook row into Production Teams + Actors. Triggered by the
      // manual "Sync from Contactbook" button we add on the Contacts tab.
      path: '/sync-contactbook',
      body: function () { return {}; },
    },
    addRole: {
      path: '/add-role',
      body: function (show, roleData) { return { show: show, roleData: roleData || {} }; },
    },
    updatePerson: {
      path: '/update-person',
      body: function (show, role, personData) { return { show: show, role: role, personData: personData || {} }; },
    },
    deleteRole: {
      path: '/delete-role',
      body: function (show, role) { return { show: show, role: role }; },
    },
    removePerson: {
      path: '/remove-person',
      body: function (show, role) { return { show: show, role: role }; },
    },
    addActor: {
      path: '/add-actor',
      body: function (show, actorData) { return { show: show, actorData: actorData || {} }; },
    },
    removeActor: {
      path: '/remove-actor',
      body: function (show, character, firstName, lastName) { return { show: show, character: character, firstName: firstName, lastName: lastName }; },
    },
    importActors: {
      path: '/import-actors',
      body: function (show, actors) { return { show: show, actors: actors || [] }; },
    },
    saveProgramFields: {
      path: '/save-program-fields',
      body: function (show, fields) { return { show: show, fields: fields || {} }; },
    },
    generateContactSheet: {
      // First-time generation (no existing doc). Server copies the template,
      // fills it via Docs API, caches URL in Season col M. Returns { url }.
      path: '/contact-sheet-generate',
      body: function (show) { return { show: show }; },
    },
    regenerateContactSheet: {
      // Same as above but trashes existing doc(s) in the Drive folder first.
      // Used by the "Regenerate" button in the contact sheet modal.
      path: '/contact-sheet-regenerate',
      body: function (show) { return { show: show }; },
    },
    addContactSheetPdfToShow: {
      // Export the current contact sheet as a PDF into {season}/{show}/General/.
      // The canonical Doc stays in the Contact Sheets folder. Future regenerates
      // auto-refresh the PDF if one has already been distributed.
      path: '/contact-sheet-add-to-show',
      body: function (show) { return { show: show }; },
    },
    // Tech schedule — GAS calls this getScheduleLink but the OLD behavior was
    // to silently generate on missing cache. We wire the new modal on top and
    // have generateTechSchedule map to the fresh-generate endpoint.
    generateTechSchedule: {
      path: '/tech-schedule-generate',
      body: function (show) { return { show: show }; },
    },
    addTechSchedulePdfToShow: {
      // Same PDF-export pattern as contact sheet's add-to-show.
      path: '/tech-schedule-add-to-show',
      body: function (show) { return { show: show }; },
    },
    // Bios
    compileBiosDoc: {
      path: '/bios-doc-compile',
      body: function (show) { return { show: show }; },
    },
    sendBioRequestsForShow: {
      path: '/bios-send-requests',
      body: function (show) { return { show: show }; },
    },
    resendBioRequest: {
      path: '/bios-resend',
      body: function (show, firstName, lastName, role) {
        return { show: show, firstName: firstName, lastName: lastName, role: role };
      },
    },
    // Program export
    exportProgramFile: {
      path: '/program-export',
      body: function (show) { return { show: show }; },
    },
    // Contracts
    generateContractFromWebapp: {
      path: '/contract-generate',
      // GAS signature: (show, role, firstName, lastName, character)
      body: function (show, role, firstName, lastName, character) {
        return { show: show, role: role, firstName: firstName, lastName: lastName, character: character };
      },
    },
    generateCombinedContractFromWebapp: {
      path: '/contract-generate-combined',
      // GAS signature: (shows[], role, firstName, lastName, character)
      body: function (shows, role, firstName, lastName, character) {
        return { shows: shows || [], role: role, firstName: firstName, lastName: lastName, character: character };
      },
    },
    sendContractFromWebapp: {
      path: '/contract-send',
      // GAS signature: (docId, docName, email, fullName, show, role, firstName, templateType)
      body: function (docId, docName, email, fullName, show, role, firstName, templateType) {
        return {
          docId: docId, docName: docName, email: email, fullName: fullName,
          show: show, role: role, firstName: firstName, lastName: '',
          templateType: templateType || 'General',
        };
      },
    },
    sendCombinedContractFromWebapp: {
      path: '/contract-send-combined',
      // GAS signature: (docId, docName, email, fullName, shows[], role, firstName, templateType)
      body: function (docId, docName, email, fullName, shows, role, firstName, templateType) {
        return {
          docId: docId, docName: docName, email: email, fullName: fullName,
          shows: shows || [], role: role, firstName: firstName, lastName: '',
          templateType: templateType || 'General',
        };
      },
    },
    deleteGeneratedContract: {
      path: '/contract-delete',
      // GAS signature: (docId, show, role, firstName)
      body: function (docId, show, role, firstName) {
        return { docId: docId, show: show, role: role, firstName: firstName };
      },
    },
    resendContractFromWebapp: {
      // New endpoint — no GAS equivalent. Server looks up the doc in Drive
      // by name, runs the send flow (OpenSign + welcome email). Works even
      // when col L was overwritten with an OpenSign ID by earlier sends.
      path: '/contract-resend',
      body: function (show, role, firstName) {
        return { show: show, role: role, firstName: firstName };
      },
    },
    purgeCache: {
      path: '/purge-cache',
      body: function () { return {}; },
    },
  };

  // Every mutation not yet ported gets bounced with a clear message. All the
  // contract / bios / program-export mutations are now handled by their
  // WordPress endpoints — this set is only for anything explicitly unported.
  const CB_MUTATIONS = new Set([]);

  function cbMutationFallback(method) {
    const msg = 'This action ("' + method + '") isn\'t ported to the new Callboard yet.\n\n' +
                (CB_OLD_URL
                    ? 'Open the old Callboard to complete it?'
                    : 'For now, use the old Callboard to complete it.');
    if (CB_OLD_URL && confirm(msg)) window.open(CB_OLD_URL, '_blank');
    else if (!CB_OLD_URL) alert(msg);
  }

  // Build a real `google.script.run` that emulates GAS's chainable API.
  (function () {
    function makeRunner() {
      let succ = null, fail = null;
      const handler = {
        withSuccessHandler(fn) { succ = fn; return this; },
        withFailureHandler(fn) { fail = fn; return this; },
      };
      return new Proxy(handler, {
        get(target, prop) {
          if (prop in target) return target[prop];
          // Any other property = method name.
          return function (...args) {
            const capturedSucc = succ, capturedFail = fail;
            succ = null; fail = null;
            if (CB_READ_ROUTES[prop]) {
              const path = CB_READ_ROUTES[prop].apply(null, args);
              cbApi(path)
                .then(r => capturedSucc && capturedSucc(r.data))
                .catch(e => {
                  if (e.status === 401) { cbClearToken(); cbShowLogin(); return; }
                  if (capturedFail) capturedFail(e);
                  else console.error('[callboard] ' + prop + ' failed:', e);
                });
              return;
            }
            if (CB_WRITE_ROUTES[prop]) {
              const spec = CB_WRITE_ROUTES[prop];
              const body = spec.body.apply(null, args);
              cbApi(spec.path, { method: 'POST', body: body })
                .then(r => {
                  // Contract send returns a soft error field when the follow-up
                  // welcome/bio email fails (OpenSign step succeeded, so the
                  // frontend treats the call as fully successful and never
                  // surfaces it). Alert here so Blake sees WHY the email
                  // didn't arrive.
                  if (r && r.data && r.data.bioEmailError) {
                    try { showAlert('Contract went to OpenSign successfully, but the welcome/bio email failed:\\n\\n' + r.data.bioEmailError); } catch (_) {}
                  }
                  if (capturedSucc) capturedSucc(r.data);
                })
                .catch(e => {
                  if (e.status === 401) { cbClearToken(); cbShowLogin(); return; }
                  if (capturedFail) capturedFail(e);
                  else console.error('[callboard] ' + prop + ' failed:', e);
                });
              return;
            }
            if (CB_MUTATIONS.has(prop)) {
              cbMutationFallback(prop);
              if (capturedFail) capturedFail(new Error('not_implemented'));
              return;
            }
            console.warn('[callboard] unknown google.script.run method:', prop);
            if (capturedFail) capturedFail(new Error('unknown_method: ' + prop));
          };
        }
      });
    }
    window.google = window.google || {};
    window.google.script = window.google.script || {};
    // The shim: replace `.run` with a fresh chainable Proxy per property access.
    Object.defineProperty(window.google.script, 'run', {
      configurable: true,
      get: makeRunner
    });
    // Also stub google.script.host so any close()/setHeight() calls no-op.
    window.google.script.host = { close: () => window.close(), setHeight: () => {} };
  })();

  /* ---- Login overlay control ---- */

  function cbShowLogin() {
    const el = document.getElementById('cb-login-overlay');
    if (el) el.style.display = 'flex';
    setTimeout(() => {
      const pw = document.getElementById('cb-login-pw');
      if (pw) pw.focus();
    }, 50);
  }
  function cbHideLogin() {
    const el = document.getElementById('cb-login-overlay');
    if (el) el.style.display = 'none';
  }

  document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('cb-login-form');
    if (!form) return;
    form.addEventListener('submit', async function (e) {
      e.preventDefault();
      const btn = document.getElementById('cb-login-submit');
      const err = document.getElementById('cb-login-err');
      err.textContent = '';
      btn.disabled = true; btn.textContent = 'Signing in…';
      try {
        const r = await cbApi('/login', {
          method: 'POST',
          body: { password: document.getElementById('cb-login-pw').value }
        });
        cbSetToken(r.token);
        cbHideLogin();
        // Reload so the boot code (which fires getInitialData) re-runs cleanly.
        location.reload();
      } catch (e) {
        err.textContent = e.status === 401 ? 'Password not recognized.' : (e.message || 'Login failed.');
        btn.disabled = false; btn.textContent = 'Sign In';
      }
    });

    // No token → show login immediately, before the app boot code fires.
    if (!cbGetToken()) cbShowLogin();
  });

  /* ==================================================================
     End of compat shim. Everything below is the original Callboard JS.
     ================================================================== */

'''

# Insert the shim right after the FIRST <script> tag that has real app code.
# We anchor on `let dashboardData = null;` — that's the first line of the boot
# module in the original file.
anchor = 'let dashboardData = null;'
assert anchor in html, 'boot anchor not found — file structure may have changed'
html = html.replace(anchor, SHIM.strip() + '\n\n  ' + anchor, 1)

# -----------------------------------------------------------------------------
# 4. Add a couple of cross-app helpers Blake might want later:
#    - A "Sign out" affordance in the header. Non-invasive: keyboard shortcut.
# -----------------------------------------------------------------------------
LOGOUT_SHORTCUT = '''
  <!-- Ctrl+Shift+L to sign out of the new Callboard -->
  <script>
    document.addEventListener('keydown', function (e) {
      if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key.toLowerCase() === 'l') {
        cbClearToken(); location.reload();
      }
    });
  </script>
'''
html = html.replace('</body>', LOGOUT_SHORTCUT + '</body>', 1)

# -----------------------------------------------------------------------------
# 4b. Add left/right padding to the Sales view so cards don't sit flush
#     against the container edge on wide screens.
# -----------------------------------------------------------------------------
html = html.replace(
    '<div id="sales-view" style="display:none;">',
    '<div id="sales-view" style="display:none; padding: 0 24px;">',
    1,
)

# -----------------------------------------------------------------------------
# 4e. Contact modal: add an "Alt Email" field right below the primary email
#     so people with two contexts (staff + actor, etc.) can have both stored.
#     Wired to Contactbook col P by the backend.
# -----------------------------------------------------------------------------
old_email_block = '''      <label>Email <span style="color:#d93025">*</span></label>
      <input type="email" id="contact-email">
    </div>
    <div class="form-group">
      <label>Phone</label>'''
new_email_block = '''      <label>Email <span style="color:#d93025">*</span></label>
      <input type="email" id="contact-email">
    </div>
    <div class="form-group">
      <label>Alt Email <span style="color:#888; font-weight:normal; font-size:12px;">(optional — for people with a separate personal / role email)</span></label>
      <input type="email" id="contact-altEmail">
    </div>
    <div class="form-group">
      <label>Phone</label>'''
assert old_email_block in html, 'Contact modal email row not found'
html = html.replace(old_email_block, new_email_block, 1)

# -----------------------------------------------------------------------------
# 4d. Contacts tab — add a "Sync from Contactbook" button next to Refresh so
#     Chris can push manual sheet edits out to Production Teams + Actors.
# -----------------------------------------------------------------------------
old_ct_row = '''      <button class="btn btn-secondary" onclick="loadContacts(true)">↻ Refresh</button>
      <button class="btn btn-primary" onclick="openAddContactModal()">+ Add Contact</button>'''
new_ct_row = '''      <button class="btn btn-secondary" onclick="loadContacts(true)">↻ Refresh</button>
      <button class="btn btn-secondary" id="cb-sync-btn" onclick="cbRunContactbookSync(this)" title="Push name/phone/email changes from Contactbook to every Production Teams + Actors row">↻ Sync from Contactbook</button>
      <button class="btn btn-primary" onclick="openAddContactModal()">+ Add Contact</button>'''
assert old_ct_row in html, 'Contacts header buttons row not found'
html = html.replace(old_ct_row, new_ct_row, 1)

# -----------------------------------------------------------------------------
# 4a2. Sales top stacked bar — scale each bucket segment as a % of CAPACITY
#      instead of % of total sold, so the segments visually fill up to the
#      capacityPct number shown at the top of each card. The per-bucket rows
#      below the bar still show % of total sold (mix breakdown).
# -----------------------------------------------------------------------------
old_stacked = '''      const stackedBars = buckets.map(b =>
        `<div title="${b.label}: ${b.count} (${b.pct}%)" style="width:${Math.min(b.pct,100)}%; height:100%; background:${b.color}; display:inline-block;"></div>`
      ).join('');'''
new_stacked = '''      // Top bar: each bucket segment is a slice of house capacity, so the total
      // filled portion equals capacityPct (matches the % shown at the top).
      const stackedBars = buckets.map(function (b) {
        const capShare = s.capacity > 0 ? Math.min((b.count / s.capacity) * 100, 100) : 0;
        return '<div title="' + b.label + ': ' + b.count + ' (' + b.pct + '% of sold, '
          + capShare.toFixed(1) + '% of capacity)" style="width:' + capShare
          + '%; height:100%; background:' + b.color + '; display:inline-block;"></div>';
      }).join('');'''
assert old_stacked in html, 'stacked-bar block not found'
html = html.replace(old_stacked, new_stacked, 1)

# -----------------------------------------------------------------------------
# 4b2. Bios tab Emergency Info column — REMOVED: Blake now maintains this
#      column directly in the source Index.html (added in the Callboard
#      Crossover pull). The renderer already outputs the emergency status
#      column in the right position.
# -----------------------------------------------------------------------------

# -----------------------------------------------------------------------------
# 6. Show visibility toolbar — a per-tab preference for hiding shows that
#    have already closed. Persisted to localStorage, applied on every render.
#    Injected at the top of Contracts + Bios; renderContracts is rewritten
#    to group by show (team → actors) with the same collapsible cards Bios
#    already has.
# -----------------------------------------------------------------------------
SHOW_VISIBILITY_MODULE = r'''
  /* ==================================================================
     Auto-collapse show cards for shows whose opening night has passed.
     Shows can always be clicked to expand. Uses dashboardData (already
     fetched at boot) as the source-of-truth for opening nights.
     ================================================================== */
  /* Wrap submitContact so its contactData payload includes altEmail. The
     original grabs specific fields; we just spread ours on top. */
  (function () {
    var origSubmit = window.submitContact;
    if (typeof origSubmit !== 'function') return;
    window.submitContact = function () {
      var altEl = document.getElementById('contact-altEmail');
      var alt = altEl ? altEl.value.trim() : '';
      // Monkey-patch google.script.run.saveContact ONCE to attach altEmail
      // to the next call's contactData, then unpatch.
      var runProxy = google.script.run;
      var origSave = runProxy.saveContact;
      var patched = false;
      Object.defineProperty(runProxy, 'saveContact', {
        configurable: true,
        writable: true,
        value: function (contactData) {
          if (!patched) {
            patched = true;
            contactData = Object.assign({}, contactData || {}, { altEmail: alt });
            // Restore original after this call.
            delete runProxy.saveContact;
          }
          return origSave.call(this, contactData);
        }
      });
      return origSubmit.apply(this, arguments);
    };
  })();

  /* When populating the contact modal for editing, prefill altEmail too. */
  (function () {
    var origEdit = window.openEditContactModal;
    if (typeof origEdit === 'function') {
      window.openEditContactModal = function (contact) {
        origEdit(contact);
        var el = document.getElementById('contact-altEmail');
        if (el) el.value = (contact && contact.altEmail) || '';
      };
    }
    var origAdd = window.openAddContactModal;
    if (typeof origAdd === 'function') {
      window.openAddContactModal = function () {
        origAdd.apply(this, arguments);
        var el = document.getElementById('contact-altEmail');
        if (el) el.value = '';
      };
    }
  })();

  /* Wrap every existing loadXxx(force) so that when the user clicks a
     "↻ Refresh" button (which calls loadXxx(true)), we FIRST purge the
     server-side cache. That way direct sheet edits show up immediately,
     not after the 60s cache TTL. Called with no args (normal boot fetch),
     the wrapper is a no-op and the original runs untouched. */
  (function () {
    var toWrap = ['loadFullSeason','loadContacts','loadContracts','loadBios',
                   'loadActors','loadSales','loadCalendar','refreshDashboard'];
    toWrap.forEach(function (name) {
      var orig = window[name];
      if (typeof orig !== 'function') return;
      window[name] = function (force) {
        var args = arguments;
        if (!force) { return orig.apply(this, args); }
        google.script.run
          .withSuccessHandler(function () { orig.apply(null, args); })
          .withFailureHandler(function () { orig.apply(null, args); })
          .purgeCache();
      };
    });
  })();

  /* Manual Contactbook → shows fanout. Wired to the "Sync from Contactbook"
     button on the Contacts tab. Runs in the background; refreshes contacts
     when done. */
  window.cbRunContactbookSync = function (btn) {
    const orig = btn ? btn.textContent : '';
    if (btn) { btn.disabled = true; btn.textContent = 'Syncing…'; }
    google.script.run
      .withSuccessHandler(function (result) {
        if (btn) { btn.disabled = false; btn.textContent = orig || '↻ Sync from Contactbook'; }
        const msg = 'Synced. Checked ' + (result.checked || 0) +
                    ' show-rows, updated ' + (result.updated || 0) + '.';
        if (typeof showAlert === 'function') showAlert(msg);
        else alert(msg);
        // Refresh the currently-visible tab data so the results show up.
        if (typeof loadContacts === 'function') loadContacts(true);
      })
      .withFailureHandler(function (err) {
        if (btn) { btn.disabled = false; btn.textContent = orig || '↻ Sync from Contactbook'; }
        const msg = 'Sync failed: ' + (err && err.message ? err.message : err);
        if (typeof showAlert === 'function') showAlert(msg); else alert(msg);
      })
      .syncContactbook();
  };

  /* "Now" respects ?as_of=YYYY-MM-DD in the URL so Blake can preview
     how the layout will look at a future date. Same idea as the WP
     tlt_today() override. Cookie-persisted for the session. */
  function cbNow() {
    const params = new URLSearchParams(location.search);
    let raw = params.get('as_of');
    if (raw === 'clear') {
      document.cookie = 'tlt_cb_as_of=; Max-Age=0; path=/';
      return Date.now();
    }
    if (!raw) {
      const m = document.cookie.match(/(?:^|;\s*)tlt_cb_as_of=([^;]+)/);
      if (m) raw = decodeURIComponent(m[1]);
    }
    if (raw && /^\d{4}-\d{2}-\d{2}$/.test(raw)) {
      // Save the override in a cookie so it survives page reloads within the
      // same tab even after the query string is dropped (24h expiry).
      if (params.get('as_of') === raw) {
        document.cookie = 'tlt_cb_as_of=' + encodeURIComponent(raw) + '; Max-Age=86400; path=/';
      }
      const ts = Date.parse(raw + 'T12:00:00');
      if (!isNaN(ts)) return ts;
    }
    return Date.now();
  }

  function cbShowHasOpened(showName) {
    if (!dashboardData) return false;
    const row = dashboardData.find(function (d) { return d.show === showName; });
    if (!row || !row.openingNight) return false;
    const ts = Date.parse(row.openingNight);
    if (isNaN(ts)) return false;
    return ts <= cbNow();
  }

  /* ==================================================================
     renderContracts REWRITE — grouped by show, team first then actors,
     each show is a collapsible card. Reuses the existing per-row action
     button behavior via a compact row builder.

     Uses `window.renderContracts = ...` (assignment) instead of a function
     declaration so it definitively overrides the original renderContracts
     declared earlier in the script.
     ================================================================== */
  window.renderContracts = function (data) {
    if (!data) return;
    const container = document.getElementById('contracts-container');
    container.innerHTML = '';

    // All four filters wired: show / role / status / person.
    const showEl   = document.getElementById('contracts-show-filter');
    const roleEl   = document.getElementById('contracts-role-filter');
    const statusEl = document.getElementById('contracts-status-filter');
    const personEl = document.getElementById('contracts-person-filter');
    const showF   = (showEl   && showEl.value)   || '';
    const roleF   = (roleEl   && roleEl.value)   || '';
    const statusF = (statusEl && statusEl.value) || '';
    const pf      = ((personEl && personEl.value) || '').toLowerCase().trim();

    // Populate role dropdown from data. Includes a synthetic "Actor" option
    // at the top whenever the payload has any actor contracts, since actors
    // don't have a Production Teams "role" — they're identified by isActor.
    if (roleEl) {
      const currentRole = roleEl.value;
      const hasActors = data.some(function (c) { return c.isActor; });
      const roles = [...new Set(data.map(function (c) { return c.role; }).filter(Boolean))].sort();
      roleEl.innerHTML = '<option value="">All Roles</option>';
      if (hasActors) {
        const opt = document.createElement('option');
        opt.value = '__actor__';
        opt.textContent = 'Actor';
        if (currentRole === '__actor__') opt.selected = true;
        roleEl.appendChild(opt);
      }
      roles.forEach(function (r) {
        const opt = document.createElement('option');
        opt.value = r; opt.textContent = r;
        if (r === currentRole) opt.selected = true;
        roleEl.appendChild(opt);
      });
    }

    const filtered = data.filter(function (c) {
      if (showF   && c.show           !== showF)   return false;
      if (roleF === '__actor__') {
        if (!c.isActor) return false;
      } else if (roleF && c.role !== roleF) {
        return false;
      }
      if (statusF && c.contractStatus !== statusF) return false;
      if (pf      && (c.fullName || '').toLowerCase().indexOf(pf) === -1) return false;
      return true;
    });

    // Preserve season order — use first-appearance order in the raw payload.
    const showOrder = [];
    data.forEach(function (c) { if (c.show && showOrder.indexOf(c.show) === -1) showOrder.push(c.show); });

    // Group filtered rows.
    const byShow = {};
    filtered.forEach(function (c) {
      if (!byShow[c.show]) byShow[c.show] = { team: [], actors: [] };
      if (c.isActor) byShow[c.show].actors.push(c); else byShow[c.show].team.push(c);
    });

    showOrder.forEach(function (show) {
      const groups = byShow[show];
      if (!groups) return;
      if (groups.team.length === 0 && groups.actors.length === 0) return;

      const total  = groups.team.length + groups.actors.length;
      const signed = [].concat(groups.team, groups.actors).filter(function (c) { return c.contractStatus === 'Signed'; }).length;
      const pct = total > 0 ? Math.round(signed / total * 100) : 0;
      const opened = cbShowHasOpened(show);

      const block = document.createElement('div');
      block.className = 'season-show-block';
      block.innerHTML =
        '<div class="season-show-header" onclick="toggleSeasonShow(this)">' +
          '<div class="season-show-title">' +
            '<h3>' + calEscape(show) + '</h3>' +
            '<span style="font-size:13px; color:' + (opened ? '#aaa' : '#888') + ';">' + signed + ' of ' + total + ' signed' + (opened ? ' · open' : '') + '</span>' +
          '</div>' +
          '<div class="season-show-meta">' +
            '<div style="width:80px; background:#f0f0f0; border-radius:99px; height:6px; overflow:hidden;">' +
              '<div style="width:' + pct + '%; height:100%; background:#4caf50; border-radius:99px;"></div>' +
            '</div>' +
            '<span class="toggle-icon' + (opened ? ' collapsed' : '') + '">▼</span>' +
          '</div>' +
        '</div>' +
        '<div class="season-show-body' + (opened ? ' hidden' : '') + '"></div>';

      const body = block.querySelector('.season-show-body');
      if (groups.team.length)   body.appendChild(cbSectionTable('Production Team', groups.team));
      if (groups.actors.length) body.appendChild(cbSectionTable('Cast', groups.actors));
      container.appendChild(block);
    });

    // Wire all four filter change listeners each render (idempotent —
    // just reassigning oninput / onchange to the same closure).
    const _rerender = function () { renderContracts(allContractsData); };
    if (showEl)   showEl.onchange   = _rerender;
    if (roleEl)   roleEl.onchange   = _rerender;
    if (statusEl) statusEl.onchange = _rerender;
    if (personEl) personEl.oninput  = _rerender;
  }

  function cbSectionTable(title, rows) {
    const wrap = document.createElement('div');
    wrap.style.padding = '10px 4px 4px';
    const h4 = document.createElement('div');
    h4.style.cssText = 'padding:6px 12px; font-size:11px; font-weight:700; letter-spacing:0.6px; text-transform:uppercase; color:#555;';
    h4.textContent = title;
    wrap.appendChild(h4);

    const table = document.createElement('table');
    table.className = 'contracts-table';
    table.style.marginBottom = '4px';
    // Leading column is the bulk-select checkbox that the header buttons
    // ("Generate Selected" / "Send Selected") read. Per-section select-all
    // in the thead only toggles this section's rows.
    table.innerHTML =
      '<thead><tr>' +
        '<th style="width:24px;"><input type="checkbox" class="cb-section-select-all" title="Select all in this section"></th>' +
        '<th>' + (title === 'Cast' ? 'Character' : 'Role') + '</th>' +
        '<th>Name</th>' +
        '<th>Email</th>' +
        '<th style="text-align:center;">OK to Send</th>' +
        '<th>Status</th>' +
        '<th>Sent</th>' +
        '<th>Signed</th>' +
        '<th>Actions</th>' +
      '</tr></thead>' +
      '<tbody></tbody>';

    const tbody = table.querySelector('tbody');
    rows.forEach(function (contract) { tbody.appendChild(cbContractRow(contract)); });
    // Per-section select-all wiring.
    const selAll = table.querySelector('.cb-section-select-all');
    if (selAll) {
      selAll.addEventListener('change', function () {
        table.querySelectorAll('tbody .contract-checkbox').forEach(function (cb) { cb.checked = selAll.checked; });
      });
    }
    wrap.appendChild(table);
    return wrap;
  }

  function cbContractRow(contract) {
    const tr = document.createElement('tr');
    const statusClass = contract.contractStatus === 'Signed'             ? 'signed'
      : contract.contractStatus === 'Sent for Signature' ? 'sent'
      : contract.contractStatus === 'Generated'          ? 'generated'
      : 'not-started';
    const label = contract.isActor ? (contract.character || '') : (contract.role || '');
    // Bulk-select checkbox carries the same data-* attrs the original
    // generateSelected() / sendSelected() functions read.
    tr.innerHTML =
      '<td style="text-align:center;"><input type="checkbox" class="contract-checkbox"' +
        ' data-show="' + calEscape(contract.show) + '"' +
        ' data-role="' + calEscape(contract.role) + '"' +
        ' data-firstname="' + calEscape(contract.firstName) + '"' +
        ' data-lastname="' + calEscape(contract.lastName || '') + '"' +
        ' data-character="' + calEscape(contract.character || '') + '"></td>' +
      '<td>' + calEscape(label) + '</td>' +
      '<td>' + calEscape(contract.fullName || '') + '</td>' +
      '<td>' + (contract.contact && contract.contact.email ? '<a href="mailto:' + calEscape(contract.contact.email) + '">' + calEscape(contract.contact.email) + '</a>' : '') + '</td>' +
      '<td style="text-align:center; white-space:nowrap;">' +
        '<input type="checkbox" class="ok-to-send-checkbox" ' + (contract.okToSend ? 'checked' : '') +
          ' data-show="' + calEscape(contract.show) + '" data-role="' + calEscape(contract.role) + '" data-firstname="' + calEscape(contract.firstName) + '">' +
        (contract.okToSend ? '<span class="approval-initials">' + calEscape(contract.okToSend) + '</span>' : '') +
      '</td>' +
      '<td><span class="status-badge ' + statusClass + '">' + calEscape(contract.contractStatus || 'Not Started') + '</span></td>' +
      '<td style="font-size:12px; color:#888;">' + calEscape(contract.contractSentDate || '') + '</td>' +
      '<td style="font-size:12px; color:#888;">' + calEscape(contract.contractSignedDate || '') + '</td>' +
      '<td><div class="contract-actions"></div></td>';

    const actionsCell = tr.querySelector('.contract-actions');
    cbBuildActionButtons(actionsCell, contract, tr);
    return tr;
  }

  function cbBuildActionButtons(actionsCell, contract, tr) {
    if (!contract.hasTemplate) {
      const noTemplate = document.createElement('span');
      noTemplate.className = 'no-template';
      noTemplate.textContent = 'No template yet';
      actionsCell.appendChild(noTemplate);
      return;
    }
    if (contract.contractStatus === 'Signed') {
      if (contract.contractLink) {
        const a = document.createElement('a');
        a.href = contract.contractLink; a.target = '_blank';
        a.className = 'btn btn-secondary';
        a.style.cssText = 'font-size:11px; padding:4px 10px;';
        a.textContent = 'View Signed';
        actionsCell.appendChild(a);
      }
      return;
    }
    if (contract.contractStatus === 'Not Started' || !contract.contractStatus) {
      const gen = document.createElement('button');
      gen.className = 'btn btn-secondary';
      gen.textContent = 'Generate';
      gen.addEventListener('click', function () { generateSingleContract(contract, tr); });
      actionsCell.appendChild(gen);
      return;
    }
    // Generated / Sent for Signature — show Preview / Send / Regenerate / Delete
    const key = contract.show + '|' + contract.role + '|' + contract.firstName;
    const generated = generatedDocs[key];
    if (generated) {
      cbBtn(actionsCell, 'Preview', 'btn-secondary', function () { window.open(generated.docUrl, '_blank'); });
      cbBtn(actionsCell, 'Send', 'btn-primary', function () {
        const ok = tr.querySelector('.ok-to-send-checkbox');
        if (!ok || !ok.checked) { showAlert('Please get approval from AAD before sending.'); return; }
        requireApproval(function () { sendSingleContract(contract, generated, tr); });
      });
      cbBtn(actionsCell, 'Regenerate', 'btn-secondary', function () { generateSingleContract(contract, tr); });
      cbBtn(actionsCell, 'Delete', 'btn-danger', function () {
        showConfirm('Delete this generated contract? This cannot be undone.', function () {
          actionsCell.innerHTML = '<span style="font-size:12px; color:#aaa;">Deleting...</span>';
          google.script.run
            .withSuccessHandler(function () {
              delete generatedDocs[key];
              google.script.run
                .withSuccessHandler(function (data) { allContractsData = data; renderContracts(data); })
                .getContractsData();
            })
            .deleteGeneratedContract(generated.docId, contract.show, contract.role, contract.firstName);
        });
      });
    } else if (contract.contractLink) {
      cbBtn(actionsCell, 'Preview', 'btn-secondary', function () { window.open(contract.contractLink, '_blank'); });
      cbBtn(actionsCell, 'Regenerate', 'btn-secondary', function () { generateSingleContract(contract, tr); });
      cbBuildDeleteButton(actionsCell, tr, contract);
    } else {
      cbBtn(actionsCell, 'Regenerate', 'btn-secondary', function () { generateSingleContract(contract, tr); });
      cbBuildDeleteButton(actionsCell, tr, contract);
    }
  }

  // Shared Delete button for rows where generatedDocs[key] isn't in memory —
  // typical after a page refresh. Passes null for docId; the backend endpoint
  // /contract-delete looks it up in Drive by expected name. The endpoint also
  // clears the row's sent/signed/status/link cells on the sheet.
  function cbBuildDeleteButton(actionsCell, tr, contract) {
    cbBtn(actionsCell, 'Delete', 'btn-danger', function () {
      showConfirm('Delete this contract? The doc goes to Drive trash, and the status / sent / signed / link cells for this row get cleared.', function () {
        actionsCell.innerHTML = '<span style="font-size:12px; color:#aaa;">Deleting…</span>';
        google.script.run
          .withSuccessHandler(function () {
            const key = contract.show + '|' + contract.role + '|' + contract.firstName;
            delete generatedDocs[key];
            google.script.run
              .withSuccessHandler(function (data) { allContractsData = data; renderContracts(data); })
              .getContractsData();
          })
          .withFailureHandler(function (err) {
            actionsCell.innerHTML = '<span style="font-size:12px; color:#d93025;">Delete failed: ' + (err && err.message || err) + '</span>';
          })
          .deleteGeneratedContract(null, contract.show, contract.role, contract.firstName);
      });
    });
  }
  function cbBtn(parent, label, cls, onclick) {
    const b = document.createElement('button');
    b.className = 'btn ' + cls;
    b.style.cssText = 'font-size:11px; padding:4px 10px;';
    b.textContent = label;
    b.addEventListener('click', onclick);
    parent.appendChild(b);
  }

  /* ==================================================================
     Bios tab — wrap the original renderer so that show blocks are
     auto-collapsed for shows already open. Everything else is untouched.
     ================================================================== */
  const _origRenderBiosTab = window.renderBiosTab;
  window.renderBiosTab = function (data) {
    _origRenderBiosTab(data);
    // Post-render collapse of open shows. Match by the header <h3> text.
    const blocks = document.querySelectorAll('#bios-container .season-show-block');
    blocks.forEach(function (block) {
      const h3 = block.querySelector('.season-show-title h3');
      if (!h3) return;
      if (!cbShowHasOpened(h3.textContent.trim())) return;
      const body = block.querySelector('.season-show-body');
      const icon = block.querySelector('.toggle-icon');
      if (body) body.classList.add('hidden');
      if (icon) icon.classList.add('collapsed');
    });
  };

  /* Same treatment for the Season / Production Teams tab. */
  const _origRenderFullSeason = window.renderFullSeason;
  window.renderFullSeason = function (data) {
    _origRenderFullSeason(data);
    const blocks = document.querySelectorAll('#season-container .season-show-block');
    blocks.forEach(function (block) {
      const h3 = block.querySelector('.season-show-title h3');
      if (!h3) return;
      if (!cbShowHasOpened(h3.textContent.trim())) return;
      const body = block.querySelector('.season-show-body');
      const icon = block.querySelector('.toggle-icon');
      if (body) body.classList.add('hidden');
      if (icon) icon.classList.add('collapsed');
    });
  };

  /* Sales tab: cards use their own inline-styled layout (not season-show-block).
     Wrap each card post-render into a collapsible structure by:
       - making the card's own top row (h3 + capacity %) the click target
       - moving everything below it into a hideable body wrapper
       - auto-collapsing when the show has opened
  */
  const _origRenderSales = window.renderSales;
  window.renderSales = function (data) {
    _origRenderSales(data);
    const cards = document.querySelectorAll('#sales-container > div');
    cards.forEach(function (card) {
      const header = card.querySelector('div[style*="justify-content:space-between"]');
      if (!header) return;
      const h3 = header.querySelector('h3');
      if (!h3) return;
      const showName = h3.textContent.trim();
      // Wrap all siblings AFTER the header row into a single body div so we can hide them.
      let body = card.querySelector('.sv-sales-body');
      if (!body) {
        body = document.createElement('div');
        body.className = 'sv-sales-body';
        // Move everything that comes after `header` inside `card` into `body`.
        let next = header.nextSibling;
        while (next) {
          const toMove = next; next = next.nextSibling;
          body.appendChild(toMove);
        }
        card.appendChild(body);
        // Make the header clickable.
        header.style.cursor = 'pointer';
        header.style.userSelect = 'none';
        header.addEventListener('click', function () {
          body.style.display = (body.style.display === 'none') ? '' : 'none';
        });
      }
      if (cbShowHasOpened(showName)) body.style.display = 'none';
    });
  };

  /* ==================================================================
     renderActors REWRITE — grouped by show as collapsible cards, matching
     the Contracts/Bios/Season layout. Auto-collapses opened shows.
     ================================================================== */
  window.renderActors = function (data) {
    if (!data) return;
    const showFilter = (document.getElementById('actors-show-filter') || {}).value || '';
    const container  = document.getElementById('actors-container');
    if (!container) return;
    container.innerHTML = '';

    const filtered = showFilter ? data.filter(function (a) { return a.show === showFilter; }) : data;
    if (filtered.length === 0) {
      container.innerHTML = '<div class="loading">No actors found.</div>';
      return;
    }

    // Preserve first-appearance order.
    const showOrder = [];
    data.forEach(function (a) { if (a.show && showOrder.indexOf(a.show) === -1) showOrder.push(a.show); });

    const byShow = {};
    filtered.forEach(function (a) { (byShow[a.show] = byShow[a.show] || []).push(a); });

    showOrder.forEach(function (show) {
      const list = byShow[show];
      if (!list || !list.length) return;
      const opened = cbShowHasOpened(show);

      const signedCount = list.filter(function (a) { return a.contractStatus === 'Signed'; }).length;
      const pct = list.length > 0 ? Math.round(signedCount / list.length * 100) : 0;

      const block = document.createElement('div');
      block.className = 'season-show-block';
      block.innerHTML =
        '<div class="season-show-header" onclick="toggleSeasonShow(this)">' +
          '<div class="season-show-title">' +
            '<h3>' + calEscape(show) + '</h3>' +
            '<span style="font-size:13px; color:' + (opened ? '#aaa' : '#888') + ';">' +
              list.length + ' cast · ' + signedCount + ' signed' + (opened ? ' · open' : '') +
            '</span>' +
          '</div>' +
          '<div class="season-show-meta">' +
            '<div style="width:80px; background:#f0f0f0; border-radius:99px; height:6px; overflow:hidden;">' +
              '<div style="width:' + pct + '%; height:100%; background:#4caf50; border-radius:99px;"></div>' +
            '</div>' +
            '<span class="toggle-icon' + (opened ? ' collapsed' : '') + '">▼</span>' +
          '</div>' +
        '</div>' +
        '<div class="season-show-body' + (opened ? ' hidden' : '') + '"></div>';

      const body = block.querySelector('.season-show-body');
      const table = document.createElement('table');
      table.className = 'contracts-table';
      table.style.marginBottom = '4px';
      table.innerHTML =
        '<thead><tr>' +
          '<th>Character</th><th>Name</th><th>Phone</th><th>Email</th>' +
          '<th>Contract</th><th>Bio</th><th>Emergency</th><th>Actions</th>' +
        '</tr></thead><tbody></tbody>';
      const tbody = table.querySelector('tbody');

      list.forEach(function (actor) {
        const fullName = [actor.firstName, actor.middleName, actor.lastName, actor.suffix].filter(Boolean).join(' ');
        const statusClass = actor.contractStatus === 'Signed' ? 'signed'
          : actor.contractStatus === 'Sent for Signature' ? 'sent' : 'not-started';
        const bioClass = actor.bioStatus === 'Submitted' ? 'submitted' : 'pending';
        const bioLabel = actor.bioStatus === 'Submitted' ? 'Submitted' : 'Awaiting';
        const emClass  = actor.emergencyInfoStatus === 'Submitted' ? 'submitted' : 'pending';
        const emLabel  = actor.emergencyInfoStatus === 'Submitted' ? 'Submitted' : 'Awaiting';
        const tr = document.createElement('tr');
        tr.innerHTML =
          '<td>' + calEscape(actor.character || '') + '</td>' +
          '<td>' + calEscape(fullName) + '</td>' +
          '<td>' + (actor.phone ? calEscape(formatPhone(actor.phone)) : '') + '</td>' +
          '<td>' + calEscape(actor.email || '') + '</td>' +
          '<td><span class="status-badge ' + statusClass + '">' + calEscape(actor.contractStatus || '') + '</span></td>' +
          '<td><span class="bio-badge ' + bioClass + '">' + bioLabel + '</span></td>' +
          '<td><span class="emergency-badge ' + emClass + '">' + emLabel + '</span></td>' +
          '<td><div class="actor-actions"></div></td>';
        const rm = document.createElement('button');
        rm.className = 'btn btn-danger';
        rm.style.cssText = 'font-size:11px; padding:4px 10px;';
        rm.textContent = 'Remove';
        rm.title = 'Remove this actor';
        rm.addEventListener('click', function () { askRemoveActor(actor); });
        tr.querySelector('.actor-actions').appendChild(rm);
        tbody.appendChild(tr);
      });
      body.appendChild(table);
      container.appendChild(block);
    });
  };
'''

# Inject the module — anchor to the closing </script> of the main script block
# so it runs AFTER every function declaration and can safely override them.
# The source Index.html has no `boot();` (that's the placeholder frontend's
# name), so the previous anchor was a silent no-op — assert to catch that.
assert '</script>' in html, 'no </script> tag found — file structure unexpected'
html = html.replace('</script>', SHOW_VISIBILITY_MODULE + '\n</script>', 1)

# -----------------------------------------------------------------------------
# 4b. Contact sheet View/Regenerate flow.
#
# The GAS viewContactSheet blindly called getContactSheetLink which silently
# generated on first call and returned the URL — but if you'd already
# generated and then changed the cast, it would just open the STALE doc.
#
# New behavior:
#   - Click Contact Sheet → GET /contact-sheet-link
#   - If exists → modal: View | Regenerate | Cancel
#       View      → open the URL
#       Regenerate → POST /contact-sheet-regenerate → open new URL
#   - If missing → POST /contact-sheet-generate → open URL
#
# We inject a JS override module that redefines viewContactSheet AFTER the
# original function's declaration runs. Same anchor pattern as 4a — after
# the previous injection, the same </script> is still the one that closes
# the main script block, so this appends right after SHOW_VISIBILITY_MODULE.
# -----------------------------------------------------------------------------
CONTACT_SHEET_MODULE = '''
  /* =====  Beefy error alert override — long messages get a scrollable
     pre + Copy button so the whole error is copyable. Original showAlert
     used innerText in a 420px-wide dialog with no scroll; anything longer
     than a paragraph got clipped and unselectable.
     Only kicks in for LONG messages (>240 chars) or ones containing "{"
     — short "Sent 3 emails" alerts still render the old compact way. ==== */
  (function () {
    var originalShowAlert = window.showAlert;
    window.showAlert = function (message, onOk) {
      var msg = String(message == null ? '' : message);
      var isLong = msg.length > 240 || msg.indexOf('{') !== -1 || msg.indexOf('\\n') !== -1;
      if (!isLong) return originalShowAlert(msg, onOk);

      // Build our own richer overlay so we don't fight the compact styles.
      var prior = document.getElementById('cb-err-modal');
      if (prior) prior.remove();
      var overlay = document.createElement('div');
      overlay.id = 'cb-err-modal';
      overlay.style.cssText = 'position:fixed; inset:0; background:rgba(0,0,0,0.55); z-index:99999; display:flex; align-items:center; justify-content:center; padding:20px;';
      overlay.innerHTML =
        '<div style="background:#fff; border-radius:10px; padding:22px 24px; max-width:820px; width:100%; max-height:80vh; display:flex; flex-direction:column; box-shadow:0 8px 32px rgba(0,0,0,0.22);">' +
          '<div style="display:flex; justify-content:space-between; align-items:baseline; margin-bottom:12px;">' +
            '<h3 style="margin:0; font-size:17px; color:#a2242a;">Error</h3>' +
            '<button id="cb-err-copy" class="btn btn-secondary" style="font-size:12px; padding:4px 12px;">Copy</button>' +
          '</div>' +
          '<pre id="cb-err-body" style="flex:1; overflow:auto; margin:0 0 16px; padding:14px; background:#fafafa; border:1px solid #eee; border-radius:6px; font-family:Consolas,Monaco,monospace; font-size:12px; line-height:1.5; white-space:pre-wrap; user-select:text; -webkit-user-select:text; cursor:text; color:#222;"></pre>' +
          '<div style="display:flex; justify-content:flex-end; gap:10px;">' +
            '<button id="cb-err-close" class="btn btn-primary" style="font-size:13px; padding:6px 14px;">Close</button>' +
          '</div>' +
        '</div>';
      document.body.appendChild(overlay);
      // Set the message via textContent so raw JSON / HTML doesn't render.
      document.getElementById('cb-err-body').textContent = msg;
      var close = function () { overlay.remove(); if (typeof onOk === 'function') onOk(); };
      document.getElementById('cb-err-close').onclick = close;
      document.getElementById('cb-err-copy').onclick = function () {
        var btn = document.getElementById('cb-err-copy');
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(msg).then(function () {
            btn.textContent = 'Copied ✓';
            setTimeout(function () { btn.textContent = 'Copy'; }, 1500);
          });
        } else {
          // Fallback: select the pre so Ctrl+C works.
          var pre = document.getElementById('cb-err-body');
          var range = document.createRange();
          range.selectNodeContents(pre);
          var sel = window.getSelection();
          sel.removeAllRanges();
          sel.addRange(range);
          btn.textContent = 'Selected — Ctrl+C';
        }
      };
      overlay.addEventListener('click', function (ev) { if (ev.target === overlay) close(); });
      // Also close on Escape.
      var esc = function (ev) { if (ev.key === 'Escape') { close(); document.removeEventListener('keydown', esc); } };
      document.addEventListener('keydown', esc);
    };
  })();

  /* =====  Contact Sheet: View / Regenerate modal (overrides viewContactSheet)
     Runs after the original declaration so this wins. ================== */
  window.viewContactSheet = function (e, showName) {
    if (e && e.stopPropagation) e.stopPropagation();
    showLoadingModal('Contact Sheet', 'Checking for ' + showName + '…');
    google.script.run
      .withSuccessHandler(function (info) {
        hideLoadingModal();
        // Shim now returns { url, exists, source }. If exists → modal.
        if (info && info.exists && info.url) {
          cbShowContactSheetModal(showName, info.url);
        } else {
          cbGenerateContactSheet(showName, false);
        }
      })
      .withFailureHandler(function (err) {
        hideLoadingModal();
        showAlert('Error: ' + (err && err.message ? err.message : 'lookup failed'));
      })
      .getContactSheetLink(showName);
  };

  function cbShowContactSheetModal(showName, url) {
    // Remove any leftover modal from a previous open so double-clicking the
    // Contact Sheet button doesn't stack overlays.
    var prior = document.getElementById('cb-cs-modal');
    if (prior) prior.remove();

    var overlay = document.createElement('div');
    overlay.id = 'cb-cs-modal';
    overlay.style.cssText = 'position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:99998; display:flex; align-items:center; justify-content:center;';
    overlay.innerHTML =
      '<div style="background:#fff; border-radius:10px; padding:24px; max-width:460px; width:calc(100% - 40px); box-shadow:0 4px 24px rgba(0,0,0,0.15);">' +
        '<h3 style="margin:0 0 6px; font-size:18px; font-weight:600;">Contact Sheet</h3>' +
        '<p style="margin:0 0 6px; color:#333; font-size:14px;"><strong>' + calEscape(showName) + '</strong> already has a contact sheet.</p>' +
        '<p style="margin:0 0 14px; color:#666; font-size:13px;">Regenerate to pick up any recent cast or crew changes. The old one moves to trash.</p>' +
        '<p style="margin:0 0 20px; color:#666; font-size:13px;">Once cast + team are locked in, drop a PDF snapshot into the show\\'s <em>General</em> folder. The working Google Doc stays here; only the PDF gets distributed. Regenerating later auto-refreshes the PDF too.</p>' +
        '<div style="display:flex; gap:8px; justify-content:flex-end; flex-wrap:wrap;">' +
          '<button id="cb-cs-cancel" class="btn btn-secondary" style="font-size:13px; padding:6px 14px;">Cancel</button>' +
          '<button id="cb-cs-regen"  class="btn btn-secondary" style="font-size:13px; padding:6px 14px;">Regenerate</button>' +
          '<button id="cb-cs-pdf"    class="btn btn-secondary" style="font-size:13px; padding:6px 14px;">Add PDF to show Drive</button>' +
          '<button id="cb-cs-view"   class="btn btn-primary"   style="font-size:13px; padding:6px 14px;">View</button>' +
        '</div>' +
      '</div>';
    document.body.appendChild(overlay);
    var close = function () { overlay.remove(); };
    // Click outside the panel = cancel.
    overlay.addEventListener('click', function (ev) { if (ev.target === overlay) close(); });
    document.getElementById('cb-cs-cancel').onclick = close;
    document.getElementById('cb-cs-view').onclick   = function () { close(); window.open(url, '_blank'); };
    document.getElementById('cb-cs-regen').onclick  = function () { close(); cbGenerateContactSheet(showName, true); };
    document.getElementById('cb-cs-pdf').onclick = function () {
      var btn = document.getElementById('cb-cs-pdf');
      btn.disabled = true; btn.textContent = 'Adding PDF…';
      google.script.run
        .withSuccessHandler(function (r) {
          close();
          // Open the show's General folder (the destination), not the source doc.
          cbShowDocReady('PDF added to ' + (r && r.folder ? r.folder : (showName + ' / General')), showName, (r && r.folder_url) || url);
        })
        .withFailureHandler(function (err) {
          btn.disabled = false; btn.textContent = 'Add PDF to show Drive';
          showAlert('Could not add PDF to show Drive: ' + (err && err.message ? err.message : 'unknown error'));
        })
        .addContactSheetPdfToShow(showName);
    };
  }

  function cbGenerateContactSheet(showName, regenerate) {
    showLoadingModal(
      regenerate ? 'Regenerating Contact Sheet' : 'Generating Contact Sheet',
      showName + ' · This usually takes 15–30 seconds…'
    );
    var method = regenerate ? 'regenerateContactSheet' : 'generateContactSheet';
    google.script.run
      .withSuccessHandler(function (result) {
        hideLoadingModal();
        if (result && result.url) cbShowDocReady('Contact Sheet ready', showName, result.url);
        else showAlert('Could not generate contact sheet for ' + showName);
      })
      .withFailureHandler(function (err) {
        hideLoadingModal();
        showAlert('Error: ' + (err && err.message ? err.message : 'generation failed'));
      })
      [method](showName);
  }

  /* Post-generation confirmation with click-to-open. Popup blockers reject
     window.open fired from async callbacks; a user-clicked <a target=_blank>
     is treated as a user gesture and always opens. Auto-clicks the link
     immediately AND leaves the modal visible for a second so if the popup
     WAS blocked, the user can still click "Open" manually. */
  function cbShowDocReady(title, subject, url) {
    var prior = document.getElementById('cb-doc-ready');
    if (prior) prior.remove();
    var overlay = document.createElement('div');
    overlay.id = 'cb-doc-ready';
    overlay.style.cssText = 'position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:99998; display:flex; align-items:center; justify-content:center;';
    overlay.innerHTML =
      '<div style="background:#fff; border-radius:10px; padding:24px; max-width:400px; width:calc(100% - 40px); box-shadow:0 4px 24px rgba(0,0,0,0.15); text-align:center;">' +
        '<h3 style="margin:0 0 6px; font-size:18px; font-weight:600; color:#188038;">✓ ' + calEscape(title) + '</h3>' +
        '<p style="margin:0 0 18px; color:#333; font-size:14px;">' + calEscape(subject) + '</p>' +
        '<div style="display:flex; gap:8px; justify-content:center;">' +
          '<button id="cb-doc-ready-close" class="btn btn-secondary" style="font-size:13px; padding:6px 14px;">Close</button>' +
          '<a id="cb-doc-ready-open" href="' + url + '" target="_blank" rel="noopener" class="btn btn-primary" style="font-size:13px; padding:6px 14px; text-decoration:none; display:inline-block;">Open ↗</a>' +
        '</div>' +
      '</div>';
    document.body.appendChild(overlay);
    var close = function () { overlay.remove(); };
    document.getElementById('cb-doc-ready-close').onclick = close;
    document.getElementById('cb-doc-ready-open').addEventListener('click', function () { setTimeout(close, 100); });
    overlay.addEventListener('click', function (ev) { if (ev.target === overlay) close(); });
    // Try to auto-open too — works in most browsers, silently no-ops if blocked.
    // The user still has the button as a fallback.
    try {
      var w = window.open(url, '_blank', 'noopener');
      if (w) setTimeout(close, 400); // popup worked — dismiss confirmation quickly
    } catch (_) { /* no-op */ }
  }

  /* =====  Tech Schedule: View / Regenerate (parallel to contact sheet) ==== */
  window.viewSchedule = function (e, showName) {
    if (e && e.stopPropagation) e.stopPropagation();
    showLoadingModal('Tech Schedule', 'Checking for ' + showName + '…');
    google.script.run
      .withSuccessHandler(function (info) {
        hideLoadingModal();
        if (info && info.exists && info.url) cbShowScheduleModal(showName, info.url);
        else cbGenerateSchedule(showName);
      })
      .withFailureHandler(function (err) {
        hideLoadingModal();
        showAlert('Error: ' + (err && err.message ? err.message : 'lookup failed'));
      })
      .getScheduleLink(showName);
  };

  function cbShowScheduleModal(showName, url) {
    var prior = document.getElementById('cb-sched-modal');
    if (prior) prior.remove();
    var overlay = document.createElement('div');
    overlay.id = 'cb-sched-modal';
    overlay.style.cssText = 'position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:99998; display:flex; align-items:center; justify-content:center;';
    overlay.innerHTML =
      '<div style="background:#fff; border-radius:10px; padding:24px; max-width:460px; width:calc(100% - 40px); box-shadow:0 4px 24px rgba(0,0,0,0.15);">' +
        '<h3 style="margin:0 0 6px; font-size:18px; font-weight:600;">Tech Schedule</h3>' +
        '<p style="margin:0 0 6px; color:#333; font-size:14px;"><strong>' + calEscape(showName) + '</strong> already has a tech schedule.</p>' +
        '<p style="margin:0 0 14px; color:#666; font-size:13px;">Regenerate to pull the latest dates from the sheet. The old schedule moves to trash.</p>' +
        '<p style="margin:0 0 20px; color:#666; font-size:13px;">Once tech dates are finalized, drop a PDF snapshot into the show\\'s <em>General</em> folder. The working Google Doc stays in Tech Packets; only the PDF gets distributed. Regenerating later auto-refreshes the PDF too.</p>' +
        '<div style="display:flex; gap:8px; justify-content:flex-end; flex-wrap:wrap;">' +
          '<button id="cb-sched-cancel" class="btn btn-secondary" style="font-size:13px; padding:6px 14px;">Cancel</button>' +
          '<button id="cb-sched-regen"  class="btn btn-secondary" style="font-size:13px; padding:6px 14px;">Regenerate</button>' +
          '<button id="cb-sched-pdf"    class="btn btn-secondary" style="font-size:13px; padding:6px 14px;">Add PDF to show Drive</button>' +
          '<button id="cb-sched-view"   class="btn btn-primary"   style="font-size:13px; padding:6px 14px;">View</button>' +
        '</div>' +
      '</div>';
    document.body.appendChild(overlay);
    var close = function () { overlay.remove(); };
    overlay.addEventListener('click', function (ev) { if (ev.target === overlay) close(); });
    document.getElementById('cb-sched-cancel').onclick = close;
    document.getElementById('cb-sched-view').onclick   = function () { close(); window.open(url, '_blank'); };
    document.getElementById('cb-sched-regen').onclick  = function () { close(); cbGenerateSchedule(showName); };
    document.getElementById('cb-sched-pdf').onclick = function () {
      var btn = document.getElementById('cb-sched-pdf');
      btn.disabled = true; btn.textContent = 'Adding PDF…';
      google.script.run
        .withSuccessHandler(function (r) {
          close();
          // Open the show's General folder (the destination), not the source doc.
          cbShowDocReady('PDF added to ' + (r && r.folder ? r.folder : (showName + ' / General')), showName, (r && r.folder_url) || url);
        })
        .withFailureHandler(function (err) {
          btn.disabled = false; btn.textContent = 'Add PDF to show Drive';
          showAlert('Could not add PDF to show Drive: ' + (err && err.message ? err.message : 'unknown error'));
        })
        .addTechSchedulePdfToShow(showName);
    };
  }

  /* =====  Add a real "Resend" button to Sent-for-Signature rows.
     Works via a new server endpoint that looks up the doc in Drive by
     expected name, so it succeeds even when col L was overwritten with
     an OpenSign ID by the older send code path. Also relabels the
     source's "Send" button to "Resend" when it's rendered (contracts
     where col L is still a doc URL). ==== */
  (function () {
    function findRowMeta(tr) {
      // Read data-* attributes from either checkbox — Blake's frontend has an
      // override renderContracts that only emits .ok-to-send-checkbox with
      // data-* attrs; the source's row-selector .contract-checkbox may not
      // exist at all. Try both.
      var cb = tr.querySelector('.contract-checkbox, .ok-to-send-checkbox');
      if (!cb) { console.warn('[callboard] Resend UI: no checkbox in row', tr); return null; }
      var show = cb.getAttribute('data-show') || '';
      var role = cb.getAttribute('data-role') || '';
      var firstName = cb.getAttribute('data-firstname') || '';
      if (!show || !role || !firstName) {
        console.warn('[callboard] Resend UI: incomplete data-* on checkbox', {
          show: show, role: role, firstName: firstName
        });
        return null;
      }
      return { show: show, role: role, firstName: firstName };
    }

    function ensureResendUI() {
      var container = document.getElementById('contracts-container');
      if (!container) return;
      // Blake's frontend has multiple tables (per-show blocks). Iterate all.
      container.querySelectorAll('tbody tr').forEach(function (tr) {
        var badge = tr.querySelector('.status-badge');
        if (!badge) return;
        var status = (badge.textContent || '').trim();
        // "Generated" (just-generated, not yet sent) gets Send button.
        // "Sent for Signature" (already gone once) gets Resend.
        var isGenerated = status === 'Generated';
        var isSent      = status === 'Sent for Signature';
        if (!isGenerated && !isSent) return;

        var actionsCell = tr.querySelector('.contract-actions');
        if (!actionsCell) return;

        // Always inject so it shows up regardless of whether col L was a
        // doc URL or overwritten with OpenSign ID. Idempotent via class.
        if (actionsCell.querySelector('.cb-resend-btn')) return;
        var meta = findRowMeta(tr);
        if (!meta) return;
        var btn = document.createElement('button');
        btn.className = 'btn btn-primary cb-resend-btn';
        btn.style.cssText = 'font-size:11px; padding:4px 10px;';
        btn.textContent = isSent ? 'Resend' : 'Send';
        btn.title = 'Look up the generated doc in Drive and ' + (isSent ? 're' : '') + 'send via OpenSign + welcome email';
        btn.addEventListener('click', function () {
          var okCheckbox = tr.querySelector('.ok-to-send-checkbox');
          if (!okCheckbox || !okCheckbox.checked) {
            showAlert('Please get approval from AAD before sending.');
            return;
          }
          requireApproval(function () {
            btn.disabled = true;
            btn.textContent = 'Sending…';
            google.script.run
              .withSuccessHandler(function (result) {
                if (result && result.success) {
                  btn.textContent = '✓ Sent';
                  setTimeout(function () {
                    google.script.run
                      .withSuccessHandler(function (data) { allContractsData = data; renderContracts(data); })
                      .getContractsData();
                  }, 800);
                } else {
                  btn.disabled = false;
                  btn.textContent = isSent ? 'Resend' : 'Send';
                  showAlert('Send failed: ' + ((result && result.error) || 'unknown error'));
                }
              })
              .withFailureHandler(function (err) {
                btn.disabled = false;
                btn.textContent = isSent ? 'Resend' : 'Send';
                showAlert('Error: ' + (err && err.message ? err.message : 'send failed'));
              })
              .resendContractFromWebapp(meta.show, meta.role, meta.firstName);
          });
        });
        // Insert before the Regenerate button so Resend sits leftmost of the "still-active" actions.
        var regenBtn = Array.from(actionsCell.querySelectorAll('button')).find(function (b) { return b.textContent.trim() === 'Regenerate'; });
        if (regenBtn) actionsCell.insertBefore(btn, regenBtn);
        else actionsCell.appendChild(btn);
      });
    }
    // Belt-and-suspenders: run on tab renders, on data refreshes, and on a
    // slow poll. All idempotent (each row checked for existing .cb-resend-btn
    // before adding).
    setInterval(ensureResendUI, 800);
    ensureResendUI();
  })();

  function cbGenerateSchedule(showName) {
    showLoadingModal('Generating Tech Schedule', showName + ' · This usually takes 10–20 seconds…');
    google.script.run
      .withSuccessHandler(function (result) {
        hideLoadingModal();
        if (result && result.url) cbShowDocReady('Tech Schedule ready', showName, result.url);
        else showAlert('Could not generate tech schedule for ' + showName);
      })
      .withFailureHandler(function (err) {
        hideLoadingModal();
        showAlert('Error: ' + (err && err.message ? err.message : 'generation failed'));
      })
      .generateTechSchedule(showName);
  }
'''

# Same trick as SHOW_VISIBILITY_MODULE — the previous replace put a fresh
# </script> at the same spot, so this replaces THAT </script> in turn and
# appends our contact-sheet module right after the previous injection.
html = html.replace('</script>', CONTACT_SHEET_MODULE + '\n</script>', 1)

# -----------------------------------------------------------------------------
# 4e. Bulk-select Generate/Send override.
#
# The GAS-source `generateSelected` calls `generateSingleContract` per row,
# which fires an async getCombinableShows check. If two of the selected rows
# have combinable-shows candidates, both fire openCombineModal in parallel —
# the modal uses shared module-level state (combineModalContract, ...Tr, etc.)
# so the second call clobbers the first, only one modal is visible, and the
# other row is stuck showing "Waiting…" forever.
#
# Fix: when the user has explicitly checked N rows and clicked "Generate
# Selected", skip the combine check and generate each row individually. If
# they want a combined contract they use the per-row "Combine" button.
# -----------------------------------------------------------------------------
CONTRACT_BULK_MODULE = '''
  /* ==================================================================
     Bulk Generate — skip the auto-combine modal.
     ================================================================== */
  window.generateSelected = function () {
    var checked = Array.from(document.querySelectorAll('.contract-checkbox:checked'));
    if (checked.length === 0) {
      showAlert('Please select at least one contract.');
      return;
    }
    checked.forEach(function (cb) {
      var tr = cb.closest('tr');
      var contract = {
        show:      cb.dataset.show,
        role:      cb.dataset.role,
        firstName: cb.dataset.firstname,
        lastName:  cb.dataset.lastname,
        character: cb.dataset.character || '',
      };
      var key = contract.show + '|' + contract.role + '|' + contract.firstName;
      if (!generatedDocs[key]) {
        _doGenerateSingle(contract, tr);  // bypass combinable-shows check
      }
    });
  };

  /* ==================================================================
     Immediate-after-generate button set — original _renderPreviewSendButtons
     only rendered Preview + Send. The full Preview + Send + Regenerate +
     Delete set only appeared after a re-render (page refresh, or another
     action that re-called renderContracts). Override to render all four
     right when the doc lands so users don't have to refresh to Delete.
     ================================================================== */
  window._renderPreviewSendButtons = function (actionsCell, tr, contract, result, isCombined) {
    cbBtn(actionsCell, 'Preview', 'btn-secondary', function () { window.open(result.docUrl, '_blank'); });
    cbBtn(actionsCell, isCombined ? 'Send Combined' : 'Send', 'btn-primary', function () {
      var okCheckbox = tr.querySelector('.ok-to-send-checkbox');
      if (!okCheckbox || !okCheckbox.checked) { showAlert('Please get approval from AAD before sending.'); return; }
      requireApproval(function () { sendSingleContract(contract, result, tr); });
    });
    cbBtn(actionsCell, 'Regenerate', 'btn-secondary', function () { generateSingleContract(contract, tr); });
    cbBuildDeleteButton(actionsCell, tr, contract);
  };
'''
html = html.replace('</script>', CONTRACT_BULK_MODULE + '\n</script>', 1)

# -----------------------------------------------------------------------------
# 4c. Calendar conflicts — REMOVED: Blake now maintains the calendar
#     conflict fetching and rendering directly in the source Index.html
#     (added in the Callboard Crossover pull). Source has proper .cal-day-conflict
#     CSS + inline rendering in renderMonthBlock + modal detail view.
# -----------------------------------------------------------------------------

# -----------------------------------------------------------------------------
# 4d. Roster/Actor bio+emergency columns and calendar conflict restyle.
#
# The source Index.html snapshot has been modified in place with these changes,
# but that directory is gitignored, so if someone ever refreshes the snapshot
# from a fresh GAS pull the substitutions below reapply the changes. Each
# substitution is a no-op when the modified form is already present, so the
# script stays idempotent whether run against a fresh source or a modified one.
#
# Changes:
#  - Prod Teams / Season / Actors tables get Bio + Emergency status columns
#    (adds <th>s and <td> badges; server exposes bioStatus / emergencyInfoStatus)
#  - Calendar day conflict badges: no red fill, red text with 🚫 prefix so
#    they don't read like show status badges
#  - Related CSS: .bio-badge, .emergency-badge, revised .cal-day-conflict
# -----------------------------------------------------------------------------
def _apply_if_present(html_in, old, new, label):
    if new in html_in:
        return html_in  # already modified (running against post-edit snapshot)
    assert old in html_in, f'{label}: neither old nor new snippet found — source structure changed'
    return html_in.replace(old, new, 1)

# CSS: replace filled-red conflict badge with text-only red + no fill, add
# .bio-badge/.emergency-badge classes.
html = _apply_if_present(
    html,
    '''    /* Calendar day conflict badges */
    .cal-day-conflict {
      font-size: 10px;
      font-weight: 600;
      padding: 1px 5px;
      border-radius: 3px;
      background: #a2242a;
      color: #fff;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      margin-top: 1px;
    }
    .cal-day.past .cal-day-conflict { opacity: 0.55; }''',
    '''    /* Calendar day conflict badges — red text + prohibition symbol, no fill,
       so they don't get mistaken for the solid-red show badges next to them. */
    .cal-day-conflict {
      font-size: 10px;
      font-weight: 600;
      padding: 1px 5px;
      color: #a2242a;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      margin-top: 1px;
    }
    .cal-day.past .cal-day-conflict { opacity: 0.55; }

    /* Bio + Emergency status badges — mirror the .status-badge look. */
    .bio-badge, .emergency-badge {
      display: inline-block;
      font-size: 11px;
      font-weight: 600;
      padding: 2px 8px;
      border-radius: 99px;
      white-space: nowrap;
    }
    .bio-badge.submitted, .emergency-badge.submitted { background: #e8f5e9; color: #2e7d32; }
    .bio-badge.pending,   .emergency-badge.pending   { background: #f5f5f5; color: #999; }''',
    'calendar conflict + status badge CSS',
)

# Calendar conflict badge JS — prepend 🚫 symbol.
html = _apply_if_present(
    html,
    '''return '<div class="cal-day-conflict" title="' + calEscape(title) + '">' + calEscape(nameLabel) + '</div>';''',
    '''return '<div class="cal-day-conflict" title="' + calEscape(title) + '">🚫 ' + calEscape(nameLabel) + '</div>';''',
    'calendar conflict JS symbol',
)

# Production Teams single-show table header.
html = _apply_if_present(
    html,
    '''    <table>
      <thead>
        <tr>
          <th>Role</th>
          <th>Name</th>
          <th>Email</th>
          <th>Phone</th>
          <th>Contract Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody id="roster-body"></tbody>
    </table>''',
    '''    <table>
      <thead>
        <tr>
          <th>Role</th>
          <th>Name</th>
          <th>Email</th>
          <th>Phone</th>
          <th>Contract</th>
          <th>Bio</th>
          <th>Emergency</th>
          <th></th>
        </tr>
      </thead>
      <tbody id="roster-body"></tbody>
    </table>''',
    'production teams header',
)

# renderRoster filled row: add Bio + Emergency badges.
html = _apply_if_present(
    html,
    '''        tr.innerHTML = `
          <td>${person.role}</td>
          <td>${name}</td>
          <td><a href="mailto:${person.email}">${person.email}</a></td>
          <td>${formatPhone(person.phone)}</td>
          <td><span class="status-badge ${statusClass}">${person.contractStatus || 'Not Started'}</span></td>
          <td><div class="row-actions"></div></td>
        `;

        const actionsCell = tr.querySelector('.row-actions');
        actionsCell.appendChild(editBtn);
        actionsCell.appendChild(removeBtn);
        actionsCell.appendChild(deleteBtn);

      } else {
        tr.innerHTML = `
          <td>${person.role}</td>
          <td class="assign-cell">
            <div class="inline-assign">
              <input type="text" placeholder="Search contacts..." autocomplete="off" class="inline-search-input">
              <div class="inline-dropdown" style="display:none;"></div>
            </div>
          </td>
          <td></td>
          <td></td>
          <td><span class="status-badge not-started">Unfilled</span></td>
          <td><div class="row-actions"></div></td>
        `;''',
    '''        const bioClass = person.bioStatus === 'Submitted' ? 'submitted' : 'pending';
        const bioLabel = person.bioStatus === 'Submitted' ? 'Submitted' : 'Awaiting';
        const emClass  = person.emergencyInfoStatus === 'Submitted' ? 'submitted' : 'pending';
        const emLabel  = person.emergencyInfoStatus === 'Submitted' ? 'Submitted' : 'Awaiting';
        tr.innerHTML = `
          <td>${person.role}</td>
          <td>${name}</td>
          <td><a href="mailto:${person.email}">${person.email}</a></td>
          <td>${formatPhone(person.phone)}</td>
          <td><span class="status-badge ${statusClass}">${person.contractStatus || 'Not Started'}</span></td>
          <td><span class="bio-badge ${bioClass}">${bioLabel}</span></td>
          <td><span class="emergency-badge ${emClass}">${emLabel}</span></td>
          <td><div class="row-actions"></div></td>
        `;

        const actionsCell = tr.querySelector('.row-actions');
        actionsCell.appendChild(editBtn);
        actionsCell.appendChild(removeBtn);
        actionsCell.appendChild(deleteBtn);

      } else {
        tr.innerHTML = `
          <td>${person.role}</td>
          <td class="assign-cell">
            <div class="inline-assign">
              <input type="text" placeholder="Search contacts..." autocomplete="off" class="inline-search-input">
              <div class="inline-dropdown" style="display:none;"></div>
            </div>
          </td>
          <td></td>
          <td></td>
          <td><span class="status-badge not-started">Unfilled</span></td>
          <td></td>
          <td></td>
          <td><div class="row-actions"></div></td>
        `;''',
    'renderRoster rows',
)

# Season view roster table header. Both `old` and `new` are anchored just
# below the <table> line so that the separate overflow-visible substitution
# on the same <table> line doesn't invalidate this match.
html = _apply_if_present(
    html,
    '''            <thead>
              <tr>
                <th>Role</th>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Contract Status</th>
                <th></th>
              </tr>
            </thead>''',
    '''            <thead>
              <tr>
                <th>Role</th>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Contract</th>
                <th>Bio</th>
                <th>Emergency</th>
                <th></th>
              </tr>
            </thead>''',
    'season roster header',
)

# renderSeasonRoster filled row: add badges + bump conflict-detail colspan to 8.
html = _apply_if_present(
    html,
    '''        tr.innerHTML = `
          <td>${person.role}</td>
          <td>${name} ${conflictPill}</td>
          <td><a href="mailto:${person.email}">${person.email}</a></td>
          <td>${formatPhone(person.phone)}</td>
          <td><span class="status-badge ${statusClass}">${person.contractStatus || 'Not Started'}</span></td>
          <td><div class="row-actions"></div></td>
        `;''',
    '''        const bioClass = person.bioStatus === 'Submitted' ? 'submitted' : 'pending';
        const bioLabel = person.bioStatus === 'Submitted' ? 'Submitted' : 'Awaiting';
        const emClass  = person.emergencyInfoStatus === 'Submitted' ? 'submitted' : 'pending';
        const emLabel  = person.emergencyInfoStatus === 'Submitted' ? 'Submitted' : 'Awaiting';
        tr.innerHTML = `
          <td>${person.role}</td>
          <td>${name} ${conflictPill}</td>
          <td><a href="mailto:${person.email}">${person.email}</a></td>
          <td>${formatPhone(person.phone)}</td>
          <td><span class="status-badge ${statusClass}">${person.contractStatus || 'Not Started'}</span></td>
          <td><span class="bio-badge ${bioClass}">${bioLabel}</span></td>
          <td><span class="emergency-badge ${emClass}">${emLabel}</span></td>
          <td><div class="row-actions"></div></td>
        `;''',
    'renderSeasonRoster filled row',
)
html = _apply_if_present(
    html,
    '''detailTr.innerHTML = `<td colspan="6" style="background:#faf5f5; padding:12px 20px; border-top:1px solid #f0d5d5;"><div class="conflict-detail-list">${detailHtml}</div></td>`;''',
    '''detailTr.innerHTML = `<td colspan="8" style="background:#faf5f5; padding:12px 20px; border-top:1px solid #f0d5d5;"><div class="conflict-detail-list">${detailHtml}</div></td>`;''',
    'season conflict detail colspan',
)
html = _apply_if_present(
    html,
    '''      } else {
        tr.innerHTML = `
          <td>${person.role}</td>
          <td class="assign-cell">
            <div class="inline-assign">
              <input type="text" placeholder="Search contacts..." autocomplete="off" class="inline-search-input">
              <div class="inline-dropdown" style="display:none;"></div>
            </div>
          </td>
          <td></td>
          <td></td>
          <td><span class="status-badge not-started">Unfilled</span></td>
          <td><div class="row-actions"></div></td>''',
    '''      } else {
        tr.innerHTML = `
          <td>${person.role}</td>
          <td class="assign-cell">
            <div class="inline-assign">
              <input type="text" placeholder="Search contacts..." autocomplete="off" class="inline-search-input">
              <div class="inline-dropdown" style="display:none;"></div>
            </div>
          </td>
          <td></td>
          <td></td>
          <td><span class="status-badge not-started">Unfilled</span></td>
          <td></td>
          <td></td>
          <td><div class="row-actions"></div></td>''',
    'renderSeasonRoster unfilled row',
)

# renderRosterActors header + body.
html = _apply_if_present(
    html,
    '''    const table = document.createElement('table');
    table.className = 'contracts-table';
    table.innerHTML = `
      <thead>
        <tr>
          <th>Character</th>
          <th>Name</th>
          <th>Phone</th>
          <th>Email</th>
          <th>Contract Status</th>
        </tr>
      </thead>
      <tbody></tbody>
    `;

    const tbody = table.querySelector('tbody');
    actors.forEach(actor => {
      const fullName = [actor.firstName, actor.middleName, actor.lastName, actor.suffix].filter(Boolean).join(' ');
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${actor.character || ''}</td>
        <td>${fullName}</td>
        <td>${actor.phone ? formatPhone(actor.phone) : ''}</td>
        <td>${actor.email || ''}</td>
        <td><span class="status-badge ${actor.contractStatus === 'Signed' ? 'signed' : actor.contractStatus === 'Sent for Signature' ? 'sent' : actor.contractStatus === 'Generated' ? 'generated' : 'not-started'}">${actor.contractStatus}</span></td>
      `;
      tbody.appendChild(tr);
    });''',
    '''    const table = document.createElement('table');
    table.className = 'contracts-table';
    table.innerHTML = `
      <thead>
        <tr>
          <th>Character</th>
          <th>Name</th>
          <th>Phone</th>
          <th>Email</th>
          <th>Contract</th>
          <th>Bio</th>
          <th>Emergency</th>
        </tr>
      </thead>
      <tbody></tbody>
    `;

    const tbody = table.querySelector('tbody');
    actors.forEach(actor => {
      const fullName = [actor.firstName, actor.middleName, actor.lastName, actor.suffix].filter(Boolean).join(' ');
      const bioClass = actor.bioStatus === 'Submitted' ? 'submitted' : 'pending';
      const bioLabel = actor.bioStatus === 'Submitted' ? 'Submitted' : 'Awaiting';
      const emClass  = actor.emergencyInfoStatus === 'Submitted' ? 'submitted' : 'pending';
      const emLabel  = actor.emergencyInfoStatus === 'Submitted' ? 'Submitted' : 'Awaiting';
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${actor.character || ''}</td>
        <td>${fullName}</td>
        <td>${actor.phone ? formatPhone(actor.phone) : ''}</td>
        <td>${actor.email || ''}</td>
        <td><span class="status-badge ${actor.contractStatus === 'Signed' ? 'signed' : actor.contractStatus === 'Sent for Signature' ? 'sent' : actor.contractStatus === 'Generated' ? 'generated' : 'not-started'}">${actor.contractStatus}</span></td>
        <td><span class="bio-badge ${bioClass}">${bioLabel}</span></td>
        <td><span class="emergency-badge ${emClass}">${emLabel}</span></td>
      `;
      tbody.appendChild(tr);
    });''',
    'renderRosterActors',
)

# .inline-assign needs its own stacking context so the autocomplete dropdown
# (z-index:1000) reliably paints above the "+ Add Role" row that sits after
# the table inside the season-show-block. Without an explicit z-index here,
# position:relative alone doesn't create a stacking context, and the
# dropdown paints in DOM order and gets covered.
html = _apply_if_present(
    html,
    '''    .inline-assign { position: relative; }''',
    '''    /* z-index promotes .inline-assign into its own stacking context so the
       autocomplete dropdown (z-index:1000 below) lifts above the "+ Add Role"
       row that sits after the table in the season-show-block. */
    .inline-assign { position: relative; z-index: 100; }''',
    'inline-assign stacking context',
)

# The base `table` selector has overflow:hidden (for rounded corners on
# free-standing tables). Inside a season-show-block the table's border-radius
# is already 0'd inline, so overflow:hidden buys nothing but clips the
# autocomplete dropdown when it extends past the last row. Override to visible.
html = _apply_if_present(
    html,
    '''        <div class="season-show-body">
          <table style="border-radius:0; box-shadow:none;">''',
    '''        <div class="season-show-body">
          <table style="border-radius:0; box-shadow:none; overflow:visible;">''',
    'season-view table overflow',
)

# .season-show-block clipped the "Search contacts" dropdown when it extended
# past the card's bottom edge. Table + header don't have backgrounds that
# need clipping to the rounded corners, so visible is fine.
html = _apply_if_present(
    html,
    '''    .season-show-block {
      background: #fff;
      border-radius: 10px;
      box-shadow: 0 1px 4px rgba(0,0,0,0.08);
      border: 1px solid #eee;
      margin-bottom: 20px;
      overflow: hidden;
    }''',
    '''    .season-show-block {
      background: #fff;
      border-radius: 10px;
      box-shadow: 0 1px 4px rgba(0,0,0,0.08);
      border: 1px solid #eee;
      margin-bottom: 20px;
      /* overflow was hidden — clipped the contact-search dropdown when it
         extended past the card's bottom edge. Table + header don't have
         backgrounds that need clipping to the rounded corners, so visible
         is fine visually. */
      overflow: visible;
      position: relative;
    }''',
    'season-show-block overflow',
)

# renderActors (standalone Actors tab) header + row.
html = _apply_if_present(
    html,
    '''    const table = document.createElement('table');
    table.className = 'contracts-table';
    table.innerHTML = `
      <thead>
        <tr>
          <th>Show</th>
          <th>Character</th>
          <th>Name</th>
          <th>Phone</th>
          <th>Email</th>
          <th>Contract Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody></tbody>
    `;

    const tbody = table.querySelector('tbody');
    filtered.forEach(actor => {
      const fullName = [actor.firstName, actor.middleName, actor.lastName, actor.suffix].filter(Boolean).join(' ');
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${actor.show}</td>
        <td>${actor.character}</td>
        <td>${fullName}</td>
        <td>${actor.phone ? formatPhone(actor.phone) : ''}</td>
        <td>${actor.email || ''}</td>
        <td><span class="status-badge ${actor.contractStatus === 'Signed' ? 'signed' : actor.contractStatus === 'Sent for Signature' ? 'sent' : 'not-started'}">${actor.contractStatus}</span></td>
        <td><div class="actor-actions"></div></td>
      `;''',
    '''    const table = document.createElement('table');
    table.className = 'contracts-table';
    table.innerHTML = `
      <thead>
        <tr>
          <th>Show</th>
          <th>Character</th>
          <th>Name</th>
          <th>Phone</th>
          <th>Email</th>
          <th>Contract</th>
          <th>Bio</th>
          <th>Emergency</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody></tbody>
    `;

    const tbody = table.querySelector('tbody');
    filtered.forEach(actor => {
      const fullName = [actor.firstName, actor.middleName, actor.lastName, actor.suffix].filter(Boolean).join(' ');
      const bioClass = actor.bioStatus === 'Submitted' ? 'submitted' : 'pending';
      const bioLabel = actor.bioStatus === 'Submitted' ? 'Submitted' : 'Awaiting';
      const emClass  = actor.emergencyInfoStatus === 'Submitted' ? 'submitted' : 'pending';
      const emLabel  = actor.emergencyInfoStatus === 'Submitted' ? 'Submitted' : 'Awaiting';
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${actor.show}</td>
        <td>${actor.character}</td>
        <td>${fullName}</td>
        <td>${actor.phone ? formatPhone(actor.phone) : ''}</td>
        <td>${actor.email || ''}</td>
        <td><span class="status-badge ${actor.contractStatus === 'Signed' ? 'signed' : actor.contractStatus === 'Sent for Signature' ? 'sent' : 'not-started'}">${actor.contractStatus}</span></td>
        <td><span class="bio-badge ${bioClass}">${bioLabel}</span></td>
        <td><span class="emergency-badge ${emClass}">${emLabel}</span></td>
        <td><div class="actor-actions"></div></td>
      `;''',
    'renderActors',
)

# -----------------------------------------------------------------------------
# 5. Disable the "restore last tab on refresh" behavior so load timing is
#    predictable (dashboard first every time).
# -----------------------------------------------------------------------------
old_boot = '''      const last = getLastTab();
      if (last && last !== 'dashboard') {
        switchTab(last);
      } else {
        renderDashboard(init.dashboard);
      }'''
new_boot = '''      // Always land on dashboard so refresh load timing is predictable.
      renderDashboard(init.dashboard);'''
assert old_boot in html, 'boot dispatch block not found — file structure may have changed'
html = html.replace(old_boot, new_boot, 1)

# -----------------------------------------------------------------------------
DST.parent.mkdir(parents=True, exist_ok=True)
DST.write_text(html, encoding='utf-8')
print(f'Wrote {DST}')
print(f'  {DST.stat().st_size // 1024} KB, {len(html.splitlines())} lines')
