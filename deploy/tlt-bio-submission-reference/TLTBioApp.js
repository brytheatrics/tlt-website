// ============================================================
//  TLTBioApp.gs — Pure JSON REST API
//  Uses GET parameters for all requests (CORS compatible)
//
//  Contactbook column map:
//    A (0)  = Contact ID       K (10) = Bio Token
//    B (1)  = First Name       L (11) = Token Sent Date
//    C (2)  = Middle Name      M (12) = Last Bio App Login
//    D (3)  = Last Name        N (13) = Date Added
//    E (4)  = Suffix           O (14) = Added By
//    F (5)  = Pronouns
//    G (6)  = Phone
//    H (7)  = Email
//    I (8)  = Notes
//    J (9)  = Skills
//
//  Bios tab column map:
//    A (0) = Contact ID
//    B (1) = Actor Bio
//    C (2) = Actor Bio Updated
//    D (3) = Director Bio
//    E (4) = Director Bio Updated
//    F (5) = Production Team Bio
//    G (6) = Production Team Bio Updated
// ============================================================

const CONTACTBOOK_ID = '1qQkqa8_v1Ie3FIPkevH5AUh1DDIY8WTxwL5O-KOf32o';
const MAIN_SHEET_ID  = '1jMhG2QgyLU_rHQoA2xFALIeAJDNOxXyNHUzMWkT-3ss';

const BIO_TYPES = {
  actor:    { label: 'Actor Bio',           bioCol: 1, updatedCol: 2 },
  director: { label: 'Director Bio',        bioCol: 3, updatedCol: 4 },
  designer: { label: 'Production Team Bio', bioCol: 5, updatedCol: 6 }
};

const ROLE_TO_BIO_TYPE = {
  'Director':                'director',
  'Choreographer':           'director',
  'Fight Choreographer':     'director',
  'Music Director':          'director',
  'Intimacy Director':       'director',
  'Lighting Designer':       'designer',
  'Sound Designer':          'designer',
  'Scenic Designer':         'designer',
  'Costume Designer':        'designer',
  'Properties Manager':      'designer',
  'Scenic Artist':           'designer',
  'Stage Manager':           'designer',
  'Assistant Stage Manager': 'designer',
  'Dialect Coach':           'designer',
  'Dramaturg':               'designer'
};

// ── Response Helper ───────────────────────────────────────────

function jsonResponse(data) {
  return ContentService
    .createTextOutput(JSON.stringify(data))
    .setMimeType(ContentService.MimeType.JSON);
}

// ── doGet — Handles ALL requests via GET parameters ───────────
//
//  Actions:
//    ?action=getContact&token=XXX           — load contact/bio/show data
//    ?action=submitBio&token=XXX&...        — submit a bio
//    ?action=updateContact&token=XXX&...    — update contact info
//    ?action=getEmergencyInfo&token=XXX     — load emergency info + contact
//    (submitEmergencyInfo is POST only — payload too large for GET)

function doGet(e) {
  try {
    const p      = e && e.parameter ? e.parameter : {};
    const action = p.action || 'getContact';

    if (action === 'getContact') {
      if (!p.token) return jsonResponse({ error: 'No token provided.' });
      return jsonResponse(getContactByToken(p.token));

    } else if (action === 'submitBio') {
      return jsonResponse(submitBio({
        token:       p.token       || '',
        bioType:     p.bioType     || '',
        bioText:     p.bioText     || '',
        show:        p.show        || '',
        role:        p.role        || '',
        action_type: p.action_type || 'use'
      }));

    } else if (action === 'updateContact') {
      return jsonResponse(updateContactInfo({
        token:    p.token    || '',
        pronouns: p.pronouns !== undefined ? p.pronouns : null,
        phone:    p.phone    !== undefined ? p.phone    : null
      }));

    } else if (action === 'getEmergencyInfo') {
      if (!p.token) return jsonResponse({ error: 'No token provided.' });
      return jsonResponse(getEmergencyInfoAction(p.token));

    } else if (action === 'getConflictForm') {
      if (!p.token) return jsonResponse({ error: 'No token provided.' });
      return jsonResponse(getConflictFormAction(p.token, p.show || ''));

    } else {
      return jsonResponse({ error: 'Unknown action: ' + action });
    }

  } catch(err) {
    Logger.log('doGet error: ' + err.message);
    return jsonResponse({ error: 'Server error: ' + err.message });
  }
}

// ── doPost — Handles POST requests ────────────────────────────

function doPost(e) {
  try {
    const data   = JSON.parse(e.postData.contents);
    const action = data.action || '';

    if (action === 'submitBio') {
      return jsonResponse(submitBio(data));
    } else if (action === 'updateContact') {
      return jsonResponse(updateContactInfo({
        token:    data.token,
        pronouns: data.pronouns !== undefined ? data.pronouns : null,
        phone:    data.phone    !== undefined ? data.phone    : null
      }));
    } else if (action === 'submitEmergencyInfo') {
      return jsonResponse(submitEmergencyInfo(data.token, data.data || {}));
    } else if (action === 'saveConflicts') {
      return jsonResponse(saveConflicts(data.token, data.show, data.role, data.conflicts));
    } else {
      return jsonResponse({ error: 'Unknown action: ' + action });
    }
  } catch(err) {
    Logger.log('doPost error: ' + err.message);
    return jsonResponse({ error: 'Server error: ' + err.message });
  }
}

// ── Contact Lookup ─────────────────────────────────────────────

function getContactByToken(token) {
  try {
    const ss    = SpreadsheetApp.openById(CONTACTBOOK_ID);
    const sheet = ss.getSheetByName('Contactbook');
    const data  = sheet.getDataRange().getValues();

    let contactRow = -1;
    for (let i = 1; i < data.length; i++) {
      if (String(data[i][10]).trim() === String(token).trim()) {
        contactRow = i;
        break;
      }
    }

    if (contactRow === -1) return { error: 'Link not found or expired. Please contact TLT staff.' };

    const row = data[contactRow];
    sheet.getRange(contactRow + 1, 13).setValue(new Date());

    const contactId = String(row[0]).trim();
    const contact = {
      contactId,
      firstName:  String(row[1]).trim(),
      middleName: String(row[2] || ''),
      lastName:   String(row[3]).trim(),
      suffix:     String(row[4] || ''),
      pronouns:   String(row[5] || ''),
      phone:      String(row[6] || ''),
      email:      String(row[7] || ''),
      notes:      String(row[8] || ''),
      rowIndex:   contactRow + 1
    };

    const biosSheet = ss.getSheetByName('Bios');
    if (!biosSheet) return { error: 'Bios tab not found. Please contact TLT staff.' };

    const biosData = biosSheet.getDataRange().getValues();
    const biosRow  = biosData.find(r => String(r[0]).trim() === contactId);

    const bios = {};
    Object.entries(BIO_TYPES).forEach(([key, cfg]) => {
      const updatedVal = biosRow ? biosRow[cfg.updatedCol] : '';
      bios[key] = {
        text:    biosRow ? String(biosRow[cfg.bioCol] || '') : '',
        updated: updatedVal instanceof Date ? updatedVal.toISOString() : String(updatedVal || '')
      };
    });

    const shows = getShowsForContact(contact.firstName, contact.lastName, contactId);

    // Enrich each show with conflicts data:
    //   Production team → available events (from Duties+Dates) + existing production-team conflicts
    //   Actor           → read-only CM conflicts (from Conflicts tab, populated by CM sync)
    const enriched = shows.map(s => {
      const bioWordLimit = _readBioWordLimitForShow(s.show, s.bioType);
      try {
        if (s.isActor) {
          return { ...s, bioWordLimit, cmConflicts: getCmConflictsForActor(s.show, contact.firstName, contact.lastName) };
        }
        const firstRole = String(s.role).split(' / ')[0];
        return {
          ...s,
          bioWordLimit,
          events:            getEventsForShowRole(s.show, firstRole),
          existingConflicts: getConflictsForContactShow(contactId, s.show)
        };
      } catch(e) {
        Logger.log('Enrich show ' + s.show + ' failed: ' + e.message);
        return { ...s, bioWordLimit };
      }
    });

    return { contact, bios, shows: enriched };

  } catch(err) {
    Logger.log('getContactByToken error: ' + err.message);
    return { error: 'Error loading form: ' + err.message };
  }
}

// ── Shows for Contact ──────────────────────────────────────────

function getShowsForContact(firstName, lastName, contactId) {
  const ss      = SpreadsheetApp.openById(MAIN_SHEET_ID);
  const results = [];

  const teamSheet = ss.getSheetByName('Production Teams');
  const teamData  = teamSheet.getDataRange().getValues();

  const showMap = {};
  teamData.slice(1).forEach(row => {
    if (!row[0] || !row[1] || !row[2]) return;
    if (String(row[2]).trim().toLowerCase() !== firstName.toLowerCase()) return;
    if (String(row[4]).trim().toLowerCase() !== lastName.toLowerCase()) return;

    const show    = String(row[0]).trim();
    const role    = String(row[1]).trim();
    const bioType = ROLE_TO_BIO_TYPE[role] || 'designer';

    if (!showMap[show]) {
      showMap[show] = { show, role, bioType, isActor: false };
    } else {
      if (bioType === 'director' && showMap[show].bioType !== 'director') {
        showMap[show].bioType = 'director';
        showMap[show].role    = role;
      } else if (showMap[show].bioType === bioType) {
        showMap[show].role += ' / ' + role;
      }
    }
  });
  Object.values(showMap).forEach(e => results.push(e));

  const actorSheet = ss.getSheetByName('Actors');
  const actorData  = actorSheet.getDataRange().getValues();
  actorData.slice(1).forEach(row => {
    if (!row[0] || !row[2] || !row[4]) return;
    if (String(row[2]).trim().toLowerCase() !== firstName.toLowerCase()) return;
    if (String(row[4]).trim().toLowerCase() !== lastName.toLowerCase()) return;
    results.push({
      show:      String(row[0]).trim(),
      role:      'Actor',
      character: String(row[1]).trim(),
      bioType:   'actor',
      isActor:   true
    });
  });

  const showOrder = {};
  let order = 0;
  results.forEach(r => {
    if (!(r.show in showOrder)) showOrder[r.show] = order++;
  });
  results.sort((a, b) => {
    const showDiff = showOrder[a.show] - showOrder[b.show];
    if (showDiff !== 0) return showDiff;
    if (a.isActor !== b.isActor) return a.isActor ? 1 : -1;
    return 0;
  });

  return results;
}

// ── Submit Bio ─────────────────────────────────────────────────

function submitBio(data) {
  try {
    const ss     = SpreadsheetApp.openById(CONTACTBOOK_ID);
    const sheet  = ss.getSheetByName('Contactbook');
    const cbData = sheet.getDataRange().getValues();

    let contactRow = -1;
    let contactId  = '';
    for (let i = 1; i < cbData.length; i++) {
      if (String(cbData[i][10]).trim() === String(data.token).trim()) {
        contactRow = i + 1;
        contactId  = String(cbData[i][0]).trim();
        break;
      }
    }
    if (contactRow === -1) return { success: false, error: 'Token not found.' };

    const cfg = BIO_TYPES[data.bioType];
    if (!cfg) return { success: false, error: 'Invalid bio type.' };

    const biosSheet = ss.getSheetByName('Bios');
    const biosData  = biosSheet.getDataRange().getValues();
    let biosRow     = -1;
    for (let i = 0; i < biosData.length; i++) {
      if (String(biosData[i][0]).trim() === contactId) { biosRow = i + 1; break; }
    }

    const now = new Date();

    if (data.action_type === 'update') {
      if (biosRow === -1) {
        const newRow = [contactId, '', '', '', '', '', ''];
        newRow[cfg.bioCol]     = data.bioText;
        newRow[cfg.updatedCol] = now;
        biosSheet.appendRow(newRow);
      } else {
        biosSheet.getRange(biosRow, cfg.bioCol + 1).setValue(data.bioText);
        biosSheet.getRange(biosRow, cfg.updatedCol + 1).setValue(now);
      }
    }

    updateBioStatus(data.show, data.role, cbData[contactRow - 1][1], 'Submitted', data.bioType);
    logBioAction(contactId, cbData[contactRow - 1], data);
    return { success: true };

  } catch(err) {
    Logger.log('submitBio error: ' + err.message);
    return { success: false, error: err.message };
  }
}

// ── Update Contact Info ────────────────────────────────────────

function updateContactInfo(data) {
  try {
    const ss     = SpreadsheetApp.openById(CONTACTBOOK_ID);
    const sheet  = ss.getSheetByName('Contactbook');
    const cbData = sheet.getDataRange().getValues();

    let contactRow = -1;
    for (let i = 1; i < cbData.length; i++) {
      if (String(cbData[i][10]).trim() === String(data.token).trim()) {
        contactRow = i + 1; break;
      }
    }
    if (contactRow === -1) return { success: false, error: 'Token not found.' };

    if (data.pronouns !== null) sheet.getRange(contactRow, 6).setValue(data.pronouns);
    if (data.phone    !== null) sheet.getRange(contactRow, 7).setValue(data.phone);
    return { success: true };
  } catch(err) {
    Logger.log('updateContactInfo error: ' + err.message);
    return { success: false, error: err.message };
  }
}

// ── Bio Status ─────────────────────────────────────────────────

function updateBioStatus(show, role, firstName, status, bioType) {
  try {
    const ss = SpreadsheetApp.openById(MAIN_SHEET_ID);
    if (role === 'Actor') {
      const sheet = ss.getSheetByName('Actors');
      const data  = sheet.getDataRange().getValues();
      for (let i = 0; i < data.length; i++) {
        if (String(data[i][0]).trim() === show &&
            String(data[i][2]).trim().toLowerCase() === String(firstName).trim().toLowerCase()) {
          sheet.getRange(i + 1, 14).setValue(status);
          sheet.getRange(i + 1, 15).setValue(bioType);
          return;
        }
      }
    } else {
      const sheet = ss.getSheetByName('Production Teams');
      const data  = sheet.getDataRange().getValues();
      for (let i = 0; i < data.length; i++) {
        if (String(data[i][0]).trim() === show &&
            String(data[i][2]).trim().toLowerCase() === String(firstName).trim().toLowerCase()) {
          sheet.getRange(i + 1, 14).setValue(status);
          sheet.getRange(i + 1, 15).setValue(bioType);
        }
      }
    }
  } catch(err) {
    Logger.log('updateBioStatus error: ' + err.message);
  }
}

// ── Bio Log ────────────────────────────────────────────────────

function logBioAction(contactId, contactRow, data) {
  try {
    const ss       = SpreadsheetApp.openById(CONTACTBOOK_ID);
    const logSheet = ss.getSheetByName('Bio Log');
    const logData  = logSheet.getDataRange().getValues();
    const nextId   = 'LOG-' + String(logData.length).padStart(4, '0');
    logSheet.appendRow([
      nextId, new Date(), contactId,
      contactRow[1], contactRow[3], contactRow[7],
      data.show,
      BIO_TYPES[data.bioType] ? BIO_TYPES[data.bioType].label : data.bioType,
      data.action_type === 'update' ? 'Updated & Submitted' : 'Used Existing',
      '', data.bioText || '', 'Bio App'
    ]);
  } catch(err) {
    Logger.log('logBioAction error: ' + err.message);
  }
}

// ── Emergency Info ────────────────────────────────────────────
//
// Stored in the Contactbook spreadsheet on an "Emergency Info" tab
// (auto-created if missing). One row per person keyed by Contact ID.
//
// Column order matches EMERGENCY_INFO_HEADERS.

const EMERGENCY_INFO_TAB = 'Emergency Info';
const EMERGENCY_INFO_HEADERS = [
  'Contact ID',
  'First Name', 'Middle Name', 'Last Name',
  'DOB', 'Over 18',
  'Address', 'Home Phone', 'Mobile Phone',
  'Guardian Name', 'Guardian Address', 'Guardian Home Phone', 'Guardian Mobile Phone',
  'Emergency Contact 1 Name', 'Emergency Contact 1 Phone',
  'Emergency Contact 2 Name', 'Emergency Contact 2 Phone',
  'Food Allergy', 'Food Allergy Detail',
  'Costume Allergy', 'Costume Allergy Detail',
  'Other Allergy', 'Other Allergy Detail',
  'Medical Conditions', 'Insurance',
  'Physician Name', 'Physician Phone',
  'Hospital Preference', 'ER Care Preference',
  'Medical Signature', 'Medical Signed Date',
  'Aliases', 'Conviction', 'Conviction Detail',
  'WATCH Signature', 'WATCH Signed Date',
  'Submitted At', 'Last Updated'
];

function getOrCreateEmergencyInfoTab() {
  const ss = SpreadsheetApp.openById(CONTACTBOOK_ID);
  let sheet = ss.getSheetByName(EMERGENCY_INFO_TAB);
  if (!sheet) {
    sheet = ss.insertSheet(EMERGENCY_INFO_TAB);
    sheet.getRange(1, 1, 1, EMERGENCY_INFO_HEADERS.length).setValues([EMERGENCY_INFO_HEADERS]);
    sheet.setFrozenRows(1);
  }
  return sheet;
}

function _emergencyRowToObject(row, rowIndex) {
  const isTruthy = v => v === true || v === 'TRUE' || v === 'Yes' || v === 'yes';
  const dobVal = row[4];
  let dobStr = '';
  if (dobVal instanceof Date) dobStr = Utilities.formatDate(dobVal, Session.getScriptTimeZone(), 'yyyy-MM-dd');
  else if (dobVal) dobStr = String(dobVal);
  return {
    firstName:  String(row[1]  || ''),
    middleName: String(row[2]  || ''),
    lastName:   String(row[3]  || ''),
    dob:        dobStr,
    over18:     String(row[5]  || ''),
    address:    String(row[6]  || ''),
    homePhone:  String(row[7]  || ''),
    mobilePhone:String(row[8]  || ''),
    guardianName:        String(row[9]  || ''),
    guardianAddress:     String(row[10] || ''),
    guardianHomePhone:   String(row[11] || ''),
    guardianMobilePhone: String(row[12] || ''),
    ec1Name:  String(row[13] || ''),
    ec1Phone: String(row[14] || ''),
    ec2Name:  String(row[15] || ''),
    ec2Phone: String(row[16] || ''),
    foodAllergy:         isTruthy(row[17]),
    foodAllergyDetail:   String(row[18] || ''),
    costumeAllergy:      isTruthy(row[19]),
    costumeAllergyDetail:String(row[20] || ''),
    otherAllergy:        isTruthy(row[21]),
    otherAllergyDetail:  String(row[22] || ''),
    medicalConditions:   String(row[23] || ''),
    insurance:           String(row[24] || ''),
    physicianName:       String(row[25] || ''),
    physicianPhone:      String(row[26] || ''),
    hospitalPref:        String(row[27] || ''),
    ercarePref:          String(row[28] || ''),
    // signatures + dates NOT returned — talent must re-sign / re-date on each submission
    aliases:             String(row[31] || ''),
    conviction:          String(row[32] || ''),
    convictionDetail:    String(row[33] || ''),
    submittedAt:         row[36] instanceof Date ? row[36] : null,
    rowIndex:            rowIndex
  };
}

function getEmergencyInfoByContactId(contactId) {
  if (!contactId) return null;
  const sheet = getOrCreateEmergencyInfoTab();
  const data  = sheet.getDataRange().getValues();
  for (let i = 1; i < data.length; i++) {
    if (String(data[i][0]).trim() === String(contactId).trim()) {
      return _emergencyRowToObject(data[i], i + 1);
    }
  }
  return null;
}

function getEmergencyInfoAction(token) {
  try {
    const ss    = SpreadsheetApp.openById(CONTACTBOOK_ID);
    const sheet = ss.getSheetByName('Contactbook');
    const data  = sheet.getDataRange().getValues();

    let contactRow = -1;
    for (let i = 1; i < data.length; i++) {
      if (String(data[i][10]).trim() === String(token).trim()) { contactRow = i; break; }
    }
    if (contactRow === -1) return { error: 'Link not found or expired. Please contact TLT staff.' };

    const row = data[contactRow];
    sheet.getRange(contactRow + 1, 13).setValue(new Date());

    const contactId = String(row[0]).trim();
    const contact = {
      contactId,
      firstName:  String(row[1]).trim(),
      middleName: String(row[2] || ''),
      lastName:   String(row[3]).trim(),
      phone:      String(row[6] || ''),
      email:      String(row[7] || '')
    };
    return { contact, emergencyInfo: getEmergencyInfoByContactId(contactId) };
  } catch(err) {
    Logger.log('getEmergencyInfoAction error: ' + err.message);
    return { error: 'Server error: ' + err.message };
  }
}

function submitEmergencyInfo(token, data) {
  try {
    if (!token) return { error: 'No token provided.' };

    // FAST PATH: only validate the token synchronously. Everything else
    // (sheet writes, logging, PDF generation) runs in a background trigger
    // so the form response returns in ~3–5s instead of 30+.
    const ss = SpreadsheetApp.openById(CONTACTBOOK_ID);
    const cb = ss.getSheetByName('Contactbook');
    // Just read col A + col K (contact ID + token) to make token lookup fast.
    const lastRow = cb.getLastRow();
    if (lastRow < 2) return { error: 'Token not found.' };
    const tokenCol = cb.getRange(2, 11, lastRow - 1, 1).getValues();
    let contactRow = -1;
    for (let i = 0; i < tokenCol.length; i++) {
      if (String(tokenCol[i][0]).trim() === String(token).trim()) { contactRow = i + 1; break; }
    }
    if (contactRow === -1) return { error: 'Token not found.' };

    // Read the identifying columns for this contact (A–H)
    const contactRowData = cb.getRange(contactRow + 1, 1, 1, 8).getValues()[0];
    const contactId = String(contactRowData[0]).trim();

    // Queue the full submission job (writes + logging + PDF gen) for background
    _queueEmergencySubmissionJob(contactId, contactRowData, data);

    return { success: true };
  } catch(err) {
    Logger.log('submitEmergencyInfo error: ' + err.message);
    return { error: 'Server error: ' + err.message };
  }
}

// Persist the full submission for a background trigger to process.
function _queueEmergencySubmissionJob(contactId, contactRowData, data) {
  const jobKey = 'EMERGENCY_JOB_' + new Date().getTime() + '_' + Math.floor(Math.random() * 1000000);
  const jobPayload = {
    contactId:  contactId,
    contactRow: contactRowData,  // [id, first, middle, last, suffix, pronouns, phone, email]
    data:       data
  };
  PropertiesService.getScriptProperties().setProperty(jobKey, JSON.stringify(jobPayload));

  const pendingTriggers = ScriptApp.getProjectTriggers().filter(t =>
    t.getHandlerFunction() === '_processQueuedEmergencyJobs'
  );
  if (pendingTriggers.length === 0) {
    ScriptApp.newTrigger('_processQueuedEmergencyJobs')
      .timeBased()
      .after(1000)
      .create();
  }
}

function logEmergencyInfoSubmission(contactId, contactRow, isUpdate) {
  try {
    const ss = SpreadsheetApp.openById(CONTACTBOOK_ID);
    const logSheet = ss.getSheetByName('Bio Log');
    if (!logSheet) return;
    const logData = logSheet.getDataRange().getValues();
    const nextId  = 'LOG-' + String(logData.length).padStart(4, '0');
    logSheet.appendRow([
      nextId, new Date(), contactId,
      contactRow[1], contactRow[3], contactRow[7],
      '',
      'Emergency Info',
      isUpdate ? 'Updated' : 'Submitted',
      '', '', 'Bio App'
    ]);
  } catch(err) {
    Logger.log('logEmergencyInfoSubmission error: ' + err.message);
  }
}

// ── Emergency Info: PDF generation + Drive drop ───────────────
//
// Config (set via Script Properties → File → Project Settings → Script Properties):
//   EMERGENCY_SEASON_FOLDER_ID   — Drive folder ID for the current season root
//                                   (shared with tlt-ludus-sync@... as Editor)
//   EMERGENCY_MEDICAL_TEMPLATE_ID — Google Doc ID of the Medical template
//   EMERGENCY_WATCH_TEMPLATE_ID   — Google Doc ID of the WATCH template
//   EMERGENCY_WATCH_SHARE_EMAIL   — Optional; default: chris@tacomalittletheatre.com
//
// Run initializeEmergencyInfoTemplates('LETTERHEAD_DOC_ID') once from the
// Apps Script editor to auto-create the two templates from your letterhead Doc.
// The returned IDs get saved to Script Properties.

const EMERGENCY_DEFAULT_WATCH_SHARE = 'chris@tacomalittletheatre.com';

// Prefer the Callboard Season tab's "Season Drive" cell (URL or ID) so the
// season folder can be updated with a single spreadsheet edit each year.
// Falls back to Script Properties for backwards compat and setup helper.
// Per-show bio word limits, read from the Season tab's show row:
//   col F (index 5) = Actor Bio Word Count
//   col G (index 6) = Director Bio Word Count
//   col H (index 7) = Designer Bio Word Count
// Empty/missing values fall back to defaults so Chris can leave a column blank
// without breaking submissions.
const _BIO_LIMIT_DEFAULTS = { actor: 100, director: 200, designer: 150 };
const _BIO_LIMIT_COL_INDEX = { actor: 5, director: 6, designer: 7 };

function _readBioWordLimitForShow(showName, bioType) {
  try {
    const ss = SpreadsheetApp.openById(MAIN_SHEET_ID);
    const sheet = ss.getSheetByName('Season');
    if (!sheet) return _BIO_LIMIT_DEFAULTS[bioType] || 150;
    const data = sheet.getDataRange().getValues();
    const row = data.find(r => String(r[1] || '').trim() === String(showName).trim());
    if (!row) return _BIO_LIMIT_DEFAULTS[bioType] || 150;
    const colIdx = _BIO_LIMIT_COL_INDEX[bioType];
    if (colIdx == null) return _BIO_LIMIT_DEFAULTS[bioType] || 150;
    const n = parseInt(String(row[colIdx] || '').trim(), 10);
    return (isNaN(n) || n <= 0) ? (_BIO_LIMIT_DEFAULTS[bioType] || 150) : n;
  } catch(e) {
    Logger.log('_readBioWordLimitForShow failed: ' + e.message);
    return _BIO_LIMIT_DEFAULTS[bioType] || 150;
  }
}

function _readSeasonFolderIdFromSheet() {
  try {
    const ss = SpreadsheetApp.openById(MAIN_SHEET_ID);
    const sheet = ss.getSheetByName('Season');
    if (!sheet) return '';
    const data = sheet.getRange(1, 1, Math.min(sheet.getLastRow(), 20), 2).getValues();
    const labelPatterns = ['season drive', 'season drive folder id', 'season drive url', 'season folder'];
    const row = data.find(r => {
      const label = String(r[0] || '').trim().toLowerCase();
      return labelPatterns.indexOf(label) !== -1;
    });
    if (!row) return '';
    const raw = String(row[1] || '').trim();
    if (!raw) return '';
    const m = raw.match(/\/folders\/([a-zA-Z0-9_-]+)/);
    return m ? m[1] : raw;   // if it's a URL, extract the ID; else use as-is
  } catch(e) {
    Logger.log('_readSeasonFolderIdFromSheet failed: ' + e.message);
    return '';
  }
}

function _emergencyConfig() {
  const props = PropertiesService.getScriptProperties();
  const sheetSeasonId = _readSeasonFolderIdFromSheet();
  return {
    seasonFolderId:     sheetSeasonId || props.getProperty('EMERGENCY_SEASON_FOLDER_ID')     || '',
    medicalTemplateId:                    props.getProperty('EMERGENCY_MEDICAL_TEMPLATE_ID')  || '',
    watchTemplateId:                      props.getProperty('EMERGENCY_WATCH_TEMPLATE_ID')    || '',
    watchShareEmail:                      props.getProperty('EMERGENCY_WATCH_SHARE_EMAIL')    || EMERGENCY_DEFAULT_WATCH_SHARE
  };
}

// Run this once from the Apps Script editor to grant the trigger + mail scopes
// (needed for the background PDF-gen trigger and WATCH review notification emails).
function _grantScopes() {
  ScriptApp.getProjectTriggers();          // triggers scriptapp scope
  MailApp.getRemainingDailyQuota();        // triggers send_mail scope
  Logger.log('ScriptApp + Mail scopes granted.');
}

// Manual test: sends a fake WATCH review email to chris@ to confirm the
// email pipeline works end-to-end. Run from the editor.
function _testWatchReviewEmail() {
  const cfg = _emergencyConfig();
  const to  = cfg.watchShareEmail || EMERGENCY_DEFAULT_WATCH_SHARE;
  _sendWatchReviewFlag(
    to,
    'Test User (TEST — please ignore)',
    { conviction: 'yes', convictionDetail: 'Test detail — this is a test.', dob: '2000-01-01' },
    null
  );
  Logger.log('Test WATCH review email sent to ' + to);
}

// One-shot setup for the whole Emergency Info system.
// Sets season folder ID, creates both templates from the letterhead Doc,
// saves all IDs to Script Properties, and (optionally) sets a WATCH-folder
// share recipient other than the default chris@.
//
// Run this ONCE from the Apps Script editor.
function setupEmergencyInfoSystem(seasonFolderId, letterheadDocId, watchShareEmail) {
  if (!seasonFolderId)  throw new Error('Missing seasonFolderId.');
  if (!letterheadDocId) throw new Error('Missing letterheadDocId.');

  const props = PropertiesService.getScriptProperties();
  props.setProperty('EMERGENCY_SEASON_FOLDER_ID', seasonFolderId);
  if (watchShareEmail) props.setProperty('EMERGENCY_WATCH_SHARE_EMAIL', watchShareEmail);

  const templates = initializeEmergencyInfoTemplates(letterheadDocId);

  Logger.log('✓ Season folder ID set:      ' + seasonFolderId);
  Logger.log('✓ Medical template created:  ' + templates.medicalTemplateUrl);
  Logger.log('✓ WATCH template created:    ' + templates.watchTemplateUrl);
  Logger.log('✓ WATCH share:               ' + (watchShareEmail || EMERGENCY_DEFAULT_WATCH_SHARE));
  Logger.log('Setup complete. Submissions will now generate PDFs.');
  return { seasonFolderId, ...templates };
}

// Called by setupEmergencyInfoSystem. Also callable directly if you already
// have the season folder ID set and just want to (re)create templates.
function initializeEmergencyInfoTemplates(letterheadDocId) {
  if (!letterheadDocId) throw new Error('Pass the letterhead Doc ID.');
  const letterhead = DriveApp.getFileById(letterheadDocId);
  const parent     = letterhead.getParents().hasNext() ? letterhead.getParents().next() : DriveApp.getRootFolder();

  const medicalCopy = letterhead.makeCopy('Emergency Medical Form Template', parent);
  const watchCopy   = letterhead.makeCopy('WATCH Background Check Release Template', parent);

  _populateMedicalTemplate(medicalCopy.getId());
  _populateWatchTemplate(watchCopy.getId());

  const props = PropertiesService.getScriptProperties();
  props.setProperty('EMERGENCY_MEDICAL_TEMPLATE_ID', medicalCopy.getId());
  props.setProperty('EMERGENCY_WATCH_TEMPLATE_ID',   watchCopy.getId());

  Logger.log('Medical template: ' + medicalCopy.getUrl());
  Logger.log('WATCH template:   ' + watchCopy.getUrl());
  return {
    medicalTemplateId: medicalCopy.getId(),
    watchTemplateId:   watchCopy.getId(),
    medicalTemplateUrl: medicalCopy.getUrl(),
    watchTemplateUrl:   watchCopy.getUrl()
  };
}

function _populateMedicalTemplate(docId) {
  const doc  = DocumentApp.openById(docId);
  const body = doc.getBody();
  body.appendParagraph('Emergency Medical Form').setHeading(DocumentApp.ParagraphHeading.HEADING1);
  body.appendParagraph('');
  const fields = [
    ['Name',                '<<FullName>>'],
    ['Date of Birth',       '<<DOB>>'],
    ['Address',             '<<Address>>'],
    ['Home Phone',          '<<HomePhone>>'],
    ['Mobile Phone',        '<<MobilePhone>>'],
    ['Age 18+',             '<<Over18>>'],
    ['Parent / Guardian',   '<<GuardianBlock>>'],
    ['Emergency Contact 1', '<<EC1Name>> — <<EC1Phone>>'],
    ['Emergency Contact 2', '<<EC2Name>> — <<EC2Phone>>'],
    ['Food Allergies',      '<<FoodAllergy>>'],
    ['Costume Allergies',   '<<CostumeAllergy>>'],
    ['Other Allergies',     '<<OtherAllergy>>'],
    ['Medical Conditions',  '<<MedicalConditions>>'],
    ['Insurance',           '<<Insurance>>'],
    ['Primary Physician',   '<<PhysicianName>> — <<PhysicianPhone>>'],
    ['Hospital Preference', '<<HospitalPref>>'],
    ['ER Care Preference',  '<<ERCarePref>>']
  ];
  fields.forEach(f => {
    const p = body.appendParagraph('');
    p.appendText(f[0] + ': ').setBold(true);
    p.appendText(f[1]).setBold(false);
  });
  body.appendParagraph('');
  body.appendParagraph('In the event of a physical injury or illness, I hereby release, discharge and/or otherwise indemnify Tacoma Little Theatre, their employees and associated personnel, including the owners and employees of offsite rehearsal spaces, from any and all liability regarding a claim of injury or illness as a result of my participation in this production at Tacoma Little Theatre. In the event of an emergency, I hereby give my consent for emergency medical care prescribed by a duly licensed Doctor of Medicine or Doctor of Dentistry.').setItalic(true);
  body.appendParagraph('');
  const sig = body.appendParagraph('');
  sig.appendText('Signed electronically by: ').setBold(true);
  sig.appendText('<<MedicalSignature>>').setBold(false);
  const dateP = body.appendParagraph('');
  dateP.appendText('Date: ').setBold(true);
  dateP.appendText('<<MedicalDate>>').setBold(false);
  doc.saveAndClose();
}

function _populateWatchTemplate(docId) {
  const doc  = DocumentApp.openById(docId);
  const body = doc.getBody();
  body.appendParagraph('Washington State Patrol Criminal Background Check Release Form').setHeading(DocumentApp.ParagraphHeading.HEADING1);
  body.appendParagraph('');
  body.appendParagraph('Tacoma Little Theatre will use this information and subsequent record only in making initial employment or engagement decisions. Further dissemination or use of this record is strictly prohibited without written permission from the applicant.').setItalic(true);
  body.appendParagraph('');
  const fields = [
    ['Applicant Name',            '<<FullName>>'],
    ['Alias / Maiden / Other',    '<<Aliases>>'],
    ['Date of Birth',             '<<DOB>>'],
    ['Convicted of a crime against children or other persons?', '<<Conviction>>'],
    ['If Yes, please specify',    '<<ConvictionDetail>>']
  ];
  fields.forEach(f => {
    const p = body.appendParagraph('');
    p.appendText(f[0] + ': ').setBold(true);
    p.appendText(f[1]).setBold(false);
  });
  body.appendParagraph('');
  body.appendParagraph('I declare that the above information is true and accurate. I grant Tacoma Little Theatre permission to conduct a Washington State Patrol criminal history background check using the above information.').setItalic(true);
  body.appendParagraph('');
  const sig = body.appendParagraph('');
  sig.appendText('Signed electronically by: ').setBold(true);
  sig.appendText('<<WatchSignature>>').setBold(false);
  const dateP = body.appendParagraph('');
  dateP.appendText('Date: ').setBold(true);
  dateP.appendText('<<WatchDate>>').setBold(false);
  doc.saveAndClose();
}

// Trigger handler: processes any queued submissions (sheet writes + PDF gen),
// then deletes itself.
function _processQueuedEmergencyJobs() {
  const props = PropertiesService.getScriptProperties();
  const keys  = props.getKeys().filter(k => k.indexOf('EMERGENCY_JOB_') === 0);

  keys.forEach(key => {
    let job;
    try { job = JSON.parse(props.getProperty(key)); } catch(e) { props.deleteProperty(key); return; }
    if (!job || !job.contactRow) { props.deleteProperty(key); return; }
    try {
      _writeEmergencyInfoToSheet(job.contactId, job.contactRow, job.data);
      processEmergencyInfoSubmission(job.contactId, job.contactRow, job.data);
    } catch(err) {
      Logger.log('Queued job ' + key + ' failed: ' + err.message);
    }
    props.deleteProperty(key);
  });

  ScriptApp.getProjectTriggers().forEach(t => {
    if (t.getHandlerFunction() === '_processQueuedEmergencyJobs') {
      try { ScriptApp.deleteTrigger(t); } catch(e) {}
    }
  });
}

// Does the actual sheet writes: emergency info row, contactbook mobile phone,
// bio log. Called by the trigger handler, not from the doPost path.
function _writeEmergencyInfoToSheet(contactId, contactRowArr, data) {
  const ss = SpreadsheetApp.openById(CONTACTBOOK_ID);
  const cb = ss.getSheetByName('Contactbook');
  const sheet = getOrCreateEmergencyInfoTab();
  const existing = getEmergencyInfoByContactId(contactId);
  const now = new Date();
  const submittedAt = existing && existing.submittedAt instanceof Date ? existing.submittedAt : now;

  const rowValues = [
    contactId,
    String(data.firstName  || ''),
    String(data.middleName || ''),
    String(data.lastName   || ''),
    data.dob || '',
    String(data.over18 || ''),
    String(data.address     || ''),
    String(data.homePhone   || ''),
    String(data.mobilePhone || ''),
    String(data.guardianName        || ''),
    String(data.guardianAddress     || ''),
    String(data.guardianHomePhone   || ''),
    String(data.guardianMobilePhone || ''),
    String(data.ec1Name  || ''),
    String(data.ec1Phone || ''),
    String(data.ec2Name  || ''),
    String(data.ec2Phone || ''),
    data.foodAllergy    === true,
    String(data.foodAllergyDetail    || ''),
    data.costumeAllergy === true,
    String(data.costumeAllergyDetail || ''),
    data.otherAllergy   === true,
    String(data.otherAllergyDetail   || ''),
    String(data.medicalConditions || ''),
    String(data.insurance         || ''),
    String(data.physicianName     || ''),
    String(data.physicianPhone    || ''),
    String(data.hospitalPref      || ''),
    String(data.ercarePref        || ''),
    String(data.medicalSignature  || ''),
    String(data.medicalDate       || ''),
    String(data.aliases           || ''),
    String(data.conviction        || ''),
    String(data.convictionDetail  || ''),
    String(data.watchSignature    || ''),
    String(data.watchDate         || ''),
    submittedAt,
    now
  ];

  if (existing) {
    sheet.getRange(existing.rowIndex, 1, 1, rowValues.length).setValues([rowValues]);
  } else {
    sheet.appendRow(rowValues);
  }

  // Find the row again by contact ID (in case Contactbook has been re-sorted)
  const cbTokenCol = cb.getRange(2, 1, cb.getLastRow() - 1, 1).getValues();
  let cbRowNum = -1;
  for (let i = 0; i < cbTokenCol.length; i++) {
    if (String(cbTokenCol[i][0]).trim() === String(contactId).trim()) { cbRowNum = i + 2; break; }
  }
  if (cbRowNum > 0 && data.mobilePhone) {
    cb.getRange(cbRowNum, 7).setValue(data.mobilePhone);
  }

  logEmergencyInfoSubmission(contactId, contactRowArr, !!existing);
}

// Called by _processQueuedEmergencyJobs after the row is written. Generates
// PDFs and drops them in Drive. Failures here don't fail the submission.
function processEmergencyInfoSubmission(contactId, contactRow, data) {
  const cfg = _emergencyConfig();
  if (!cfg.seasonFolderId) { Logger.log('processEmergencyInfoSubmission: SEASON_FOLDER_ID not set — skipping PDF drop.'); return; }
  if (!cfg.medicalTemplateId || !cfg.watchTemplateId) {
    Logger.log('processEmergencyInfoSubmission: template IDs not set — skipping PDF drop.');
    return;
  }

  const firstName = String(contactRow[1]).trim();
  const lastName  = String(contactRow[3]).trim();
  const fullName  = [firstName, contactRow[2], lastName, contactRow[4]].filter(Boolean).join(' ');
  const fileName  = lastName + ', ' + firstName + '.pdf';

  const seasonFolder = DriveApp.getFolderById(cfg.seasonFolderId);

  // ─ MEDICAL PDF — one copy in each show's Stage Management/Medical Forms folder ─
  try {
    const medicalPdfBlob = _generateEmergencyPdf(cfg.medicalTemplateId, _medicalTagMap(fullName, data), fileName);
    const shows = getShowsForContact(firstName, lastName, contactId);
    const distinctShows = [...new Set(shows.map(s => s.show))];
    distinctShows.forEach(showName => {
      try {
        const showFolder = _findChildFolder(seasonFolder, showName);
        if (!showFolder) { Logger.log('Medical: show folder not found "' + showName + '" — skipping'); return; }
        const smFolder  = _findOrCreateChildFolder(showFolder, 'Stage Management');
        const medFolder = _findOrCreateChildFolder(smFolder,   'Medical Forms');
        _replaceFile(medFolder, fileName, medicalPdfBlob);
        _markEmergencyInfoSubmittedForShow(showName, firstName, lastName);
      } catch(showErr) {
        Logger.log('Medical drop for show "' + showName + '" failed: ' + showErr.message);
      }
    });
  } catch(err) {
    Logger.log('Medical PDF generation failed: ' + err.message);
  }

  // ─ WATCH PDF — one copy in season/WATCH/ folder, share with chris@ ─
  try {
    const watchPdfBlob = _generateEmergencyPdf(cfg.watchTemplateId, _watchTagMap(fullName, data), fileName);
    const watchFolder = _findOrCreateChildFolder(seasonFolder, 'WATCH');
    _ensureFolderSharedWith(watchFolder, cfg.watchShareEmail);
    const watchFile = _replaceFile(watchFolder, fileName, watchPdfBlob);

    // Auto-flag: if talent answered YES to the conviction question, email the
    // reviewer (defaults to chris@) so a background check gets run.
    if (String(data.conviction).toLowerCase() === 'yes' && cfg.watchShareEmail) {
      try {
        _sendWatchReviewFlag(cfg.watchShareEmail, fullName, data, watchFile);
      } catch(mailErr) {
        Logger.log('WATCH review flag email failed: ' + mailErr.message);
      }
    }
  } catch(err) {
    Logger.log('WATCH PDF generation failed: ' + err.message);
  }
}

function _sendWatchReviewFlag(toEmail, fullName, data, watchFile) {
  const fileUrl = watchFile ? watchFile.getUrl() : '';
  const detail  = String(data.convictionDetail || '(no detail provided)').trim();
  const dob     = String(data.dob || '(not provided)');

  const htmlBody = `
    <div style="font-family:'Segoe UI',Arial,sans-serif;max-width:560px;color:#333;">
      <div style="background:#a2242a;padding:16px 24px;border-radius:6px 6px 0 0;">
        <h1 style="color:#fff;font-size:16px;margin:0;font-weight:600;">TLT — WATCH Review Needed</h1>
      </div>
      <div style="background:#fff;padding:24px;border:1px solid #e0e0e0;border-top:none;border-radius:0 0 6px 6px;">
        <p style="margin:0 0 12px;">A new WATCH background check release has been submitted with a <strong style="color:#a2242a;">YES</strong> answer to the conviction question. Please review and run a WSP background check.</p>
        <table style="width:100%;border-collapse:collapse;margin:12px 0;font-size:14px;">
          <tr><td style="padding:6px 0;color:#666;width:130px;">Name:</td><td style="padding:6px 0;"><strong>${fullName}</strong></td></tr>
          <tr><td style="padding:6px 0;color:#666;">Date of birth:</td><td style="padding:6px 0;">${dob}</td></tr>
          <tr><td style="padding:6px 0;color:#666;vertical-align:top;">Disclosed detail:</td><td style="padding:6px 0;white-space:pre-wrap;">${detail}</td></tr>
        </table>
        ${fileUrl ? `<p style="margin:16px 0 0;"><a href="${fileUrl}" style="background:#a2242a;color:#fff;padding:10px 20px;border-radius:4px;text-decoration:none;font-weight:600;font-size:14px;">Open WATCH PDF in Drive</a></p>` : ''}
        <p style="margin:24px 0 0;font-size:12px;color:#999;border-top:1px solid #f0f0f0;padding-top:12px;">
          Automated notification from the TLT Callboard. The PDF is also stored in the season's WATCH folder.
        </p>
      </div>
    </div>
  `;
  MailApp.sendEmail({
    to:       toEmail,
    subject:  'WATCH Review Needed: ' + fullName,
    htmlBody: htmlBody,
    name:     'Tacoma Little Theatre'
  });
  Logger.log('WATCH review flag emailed to ' + toEmail + ' for ' + fullName);
}

function _generateEmergencyPdf(templateId, tagMap, targetFileName) {
  const tempCopy = DriveApp.getFileById(templateId).makeCopy('__temp ' + targetFileName + ' ' + new Date().getTime());
  try {
    const doc  = DocumentApp.openById(tempCopy.getId());
    const body = doc.getBody();
    Object.keys(tagMap).forEach(tag => body.replaceText(tag, tagMap[tag]));
    doc.saveAndClose();
    const pdfBlob = tempCopy.getAs('application/pdf').setName(targetFileName);
    return pdfBlob;
  } finally {
    try { tempCopy.setTrashed(true); } catch(e) {}
  }
}

function _medicalTagMap(fullName, d) {
  const yn = v => v === true || v === 'true' ? 'Yes' : 'No';
  const guardian = (d.over18 === 'no')
    ? (d.guardianName || '') + (d.guardianAddress ? ' — ' + d.guardianAddress : '') +
      (d.guardianHomePhone ? ' — Home: ' + d.guardianHomePhone : '') +
      (d.guardianMobilePhone ? ' — Mobile: ' + d.guardianMobilePhone : '')
    : 'N/A (18+)';
  return {
    '<<FullName>>':          fullName,
    '<<DOB>>':               d.dob || '',
    '<<Address>>':           d.address || '',
    '<<HomePhone>>':         d.homePhone || '',
    '<<MobilePhone>>':       d.mobilePhone || '',
    '<<Over18>>':            d.over18 === 'yes' ? 'Yes' : (d.over18 === 'no' ? 'No' : ''),
    '<<GuardianBlock>>':     guardian,
    '<<EC1Name>>':           d.ec1Name || '',
    '<<EC1Phone>>':          d.ec1Phone || '',
    '<<EC2Name>>':           d.ec2Name || '',
    '<<EC2Phone>>':          d.ec2Phone || '',
    '<<FoodAllergy>>':       d.foodAllergy    ? (d.foodAllergyDetail    || 'Yes (no detail provided)') : 'None',
    '<<CostumeAllergy>>':    d.costumeAllergy ? (d.costumeAllergyDetail || 'Yes (no detail provided)') : 'None',
    '<<OtherAllergy>>':      d.otherAllergy   ? (d.otherAllergyDetail   || 'Yes (no detail provided)') : 'None',
    '<<MedicalConditions>>': d.medicalConditions || 'None reported',
    '<<Insurance>>':         d.insurance || '',
    '<<PhysicianName>>':     d.physicianName || '',
    '<<PhysicianPhone>>':    d.physicianPhone || '',
    '<<HospitalPref>>':      d.hospitalPref || '',
    '<<ERCarePref>>':        d.ercarePref || '',
    '<<MedicalSignature>>':  d.medicalSignature || '',
    '<<MedicalDate>>':       d.medicalDate || ''
  };
}

function _watchTagMap(fullName, d) {
  return {
    '<<FullName>>':         fullName,
    '<<Aliases>>':          d.aliases || 'None',
    '<<DOB>>':              d.dob || '',
    '<<Conviction>>':       d.conviction === 'yes' ? 'Yes' : (d.conviction === 'no' ? 'No' : ''),
    '<<ConvictionDetail>>': d.conviction === 'yes' ? (d.convictionDetail || '') : 'N/A',
    '<<WatchSignature>>':   d.watchSignature || '',
    '<<WatchDate>>':        d.watchDate || ''
  };
}

function _findChildFolder(parent, name) {
  const it = parent.getFoldersByName(name);
  return it.hasNext() ? it.next() : null;
}

function _findOrCreateChildFolder(parent, name) {
  const it = parent.getFoldersByName(name);
  return it.hasNext() ? it.next() : parent.createFolder(name);
}

function _replaceFile(folder, fileName, blob) {
  const existing = folder.getFilesByName(fileName);
  while (existing.hasNext()) {
    try { existing.next().setTrashed(true); } catch(e) {}
  }
  return folder.createFile(blob);
}

function _ensureFolderSharedWith(folder, email) {
  if (!email) return;
  try {
    const editors = folder.getEditors().map(u => u.getEmail().toLowerCase());
    if (editors.indexOf(email.toLowerCase()) === -1) folder.addEditor(email);
  } catch(err) {
    Logger.log('_ensureFolderSharedWith error for ' + email + ': ' + err.message);
  }
}

function _markEmergencyInfoSubmittedForShow(showName, firstName, lastName) {
  try {
    const ss  = SpreadsheetApp.openById(MAIN_SHEET_ID);
    const now = new Date();
    ['Production Teams', 'Actors'].forEach(tabName => {
      const sheet = ss.getSheetByName(tabName);
      if (!sheet) return;
      const data = sheet.getDataRange().getValues();
      // Column Q (index 16) = Emergency Info status, R (17) = Emergency Info date
      // (col P is already used for okToSend approval)
      for (let i = 1; i < data.length; i++) {
        if (String(data[i][0]).trim() !== showName) continue;
        if (String(data[i][2]).trim().toLowerCase() !== firstName.toLowerCase()) continue;
        if (String(data[i][4]).trim().toLowerCase() !== lastName.toLowerCase()) continue;
        sheet.getRange(i + 1, 17).setValue('Submitted');
        sheet.getRange(i + 1, 18).setValue(now);
      }
    });
  } catch(err) {
    Logger.log('_markEmergencyInfoSubmittedForShow error: ' + err.message);
  }
}

// ── Production Team Conflicts ─────────────────────────────────
//
// Storage: "Production Team Conflicts" tab on the Callboard sheet
// (auto-created if missing). One row per (contact, show, event).

const CONFLICTS_TAB = 'Production Team Conflicts';
const CONFLICTS_HEADERS = [
  'Show', 'Contact ID', 'First Name', 'Last Name', 'Role',
  'Event Type', 'Event Date', 'Notes',
  'Submitted At', 'Last Updated'
];

// Duties tab key-date column names → Dates tab event types.
// Skips deadlines (Design Packet due, Cues in Cuelist, etc.) — only includes
// events that people physically attend on a specific date.
const CONFLICT_COL_TO_EVENT = {
  'Opening Performance':               ['Opening Performance'],
  'Closing Performance':               ['Closing Performance'],
  'Rehearsal Start':                   ['Rehearsal Start'],
  'Production Meetings':               ['Production Meeting 1','Production Meeting 2','Production Meeting 3'],
  'Designer Run':                      ['Designer Run'],
  'Costume Parade':                    ['Costume/Prop Parade/Headshots'],
  'Prop Parade':                       ['Costume/Prop Parade/Headshots'],
  'Headshots':                         ['Costume/Prop Parade/Headshots'],
  'Costume/Prop Parade and Headshots': ['Costume/Prop Parade/Headshots'],
  'Dry Tech':                          ['Dry Tech'],
  'Cue to Cue':                        ['Cue to Cue'],
  'Tech Run':                          ['Tech Run'],
  'Cue to Cue and Tech Run':           ['Cue to Cue','Tech Run'],
  'Dress Rehearsals':                  ['Dress Rehearsal'],
  'Performance':                       ['Performance']
};

function _getOrCreateConflictsTab() {
  const ss = SpreadsheetApp.openById(MAIN_SHEET_ID);
  let sheet = ss.getSheetByName(CONFLICTS_TAB);
  if (!sheet) {
    sheet = ss.insertSheet(CONFLICTS_TAB);
    sheet.getRange(1, 1, 1, CONFLICTS_HEADERS.length).setValues([CONFLICTS_HEADERS]);
    sheet.setFrozenRows(1);
  }
  return sheet;
}

// Read Duties tab: for the given role, which key-date columns are checked?
function _getCheckedKeyDateColsForRole(role) {
  const ss    = SpreadsheetApp.openById(MAIN_SHEET_ID);
  const sheet = ss.getSheetByName('Duties');
  if (!sheet) return [];
  const data = sheet.getDataRange().getValues();
  if (data.length < 2) return [];
  const headers = data[0];
  const row = data.find(r => String(r[0]).trim() === String(role).trim());
  if (!row) return [];
  const cols = [];
  for (let i = 6; i < headers.length; i++) {
    const v = row[i];
    if (v === true || v === 'TRUE') cols.push(String(headers[i]).trim());
  }
  return cols;
}

// For a show + role, return the events the role attends (with dates).
// Only includes events with a start time (skips pure deadlines).
function getEventsForShowRole(show, role) {
  const checkedCols = _getCheckedKeyDateColsForRole(role);
  if (checkedCols.length === 0) return [];

  const eventTypes = new Set();
  checkedCols.forEach(col => {
    const list = CONFLICT_COL_TO_EVENT[col];
    if (list) list.forEach(e => eventTypes.add(e));
  });
  if (eventTypes.size === 0) return [];

  const ss    = SpreadsheetApp.openById(MAIN_SHEET_ID);
  const sheet = ss.getSheetByName('Dates');
  if (!sheet) return [];
  const rows = sheet.getDataRange().getValues().slice(1);
  const tz   = Session.getScriptTimeZone();

  const out = [];
  rows.forEach(r => {
    if (String(r[0]).trim() !== show) return;
    const eventType = String(r[1]).trim();
    if (!eventTypes.has(eventType)) return;
    if (!r[4]) return;
    if (!r[5]) return;  // require a start time (filters out deadlines)
    const dateObj = r[4] instanceof Date ? r[4] : new Date(r[4]);
    if (isNaN(dateObj.getTime())) return;
    const iso = Utilities.formatDate(dateObj, tz, 'yyyy-MM-dd');
    out.push({
      eventType,
      date:      iso,
      dateLabel: Utilities.formatDate(dateObj, tz, 'EEE, MMM d')
    });
  });

  out.sort((a, b) => a.date === b.date ? a.eventType.localeCompare(b.eventType) : a.date.localeCompare(b.date));
  return out;
}

function getConflictsForContactShow(contactId, show) {
  if (!contactId || !show) return [];
  const sheet = _getOrCreateConflictsTab();
  const rows  = sheet.getDataRange().getValues().slice(1);
  const tz    = Session.getScriptTimeZone();
  return rows
    .filter(r => String(r[1]).trim() === String(contactId).trim() && String(r[0]).trim() === String(show).trim())
    .map(r => ({
      eventType: String(r[5]).trim(),
      date:      r[6] instanceof Date ? Utilities.formatDate(r[6], tz, 'yyyy-MM-dd') : String(r[6] || '').trim(),
      notes:     String(r[7] || '').trim()
    }));
}

// Read-only cast conflicts from the existing Conflicts tab (populated by CM sync)
function getCmConflictsForActor(showName, firstName, lastName) {
  const ss = SpreadsheetApp.openById(MAIN_SHEET_ID);
  const cSheet = ss.getSheetByName('Conflicts');
  if (!cSheet) return [];
  const rows = cSheet.getDataRange().getValues().slice(1);
  const tz = Session.getScriptTimeZone();
  const fullName = (firstName + ' ' + lastName).trim().toLowerCase();
  return rows
    .filter(r => {
      const rowFull = String(r[2] || '').trim().toLowerCase();
      return rowFull === fullName;
    })
    .map(r => {
      const d = r[3];
      const iso = d instanceof Date ? Utilities.formatDate(d, tz, 'yyyy-MM-dd') : String(d || '').trim();
      return {
        date:      iso,
        eventType: String(r[4] || '').trim() || 'Conflict',
        notes:     String(r[5] || '').trim()
      };
    });
}

// Save conflicts for a person+show. Wipes existing conflicts for that pair, then appends.
function saveConflicts(token, show, role, conflicts) {
  try {
    if (!token || !show) return { error: 'Missing token or show.' };
    conflicts = Array.isArray(conflicts) ? conflicts : [];

    const cbSheet = SpreadsheetApp.openById(CONTACTBOOK_ID).getSheetByName('Contactbook');
    const cbData  = cbSheet.getDataRange().getValues();
    let contactRow = -1;
    for (let i = 1; i < cbData.length; i++) {
      if (String(cbData[i][10]).trim() === String(token).trim()) { contactRow = i; break; }
    }
    if (contactRow === -1) return { error: 'Token not found.' };

    const contactId = String(cbData[contactRow][0]).trim();
    const firstName = String(cbData[contactRow][1]).trim();
    const lastName  = String(cbData[contactRow][3]).trim();

    const sheet = _getOrCreateConflictsTab();
    const data  = sheet.getDataRange().getValues();
    const now   = new Date();

    // Delete rows for this contact+show (walk from bottom to top so indices stay valid)
    for (let i = data.length - 1; i >= 1; i--) {
      if (String(data[i][1]).trim() === contactId && String(data[i][0]).trim() === show) {
        sheet.deleteRow(i + 1);
      }
    }

    // Append new conflicts
    conflicts.forEach(c => {
      if (!c || !c.date || !c.eventType) return;
      sheet.appendRow([
        show, contactId, firstName, lastName, String(role || ''),
        String(c.eventType).trim(),
        c.date,
        String(c.notes || '').trim(),
        now, now
      ]);
    });

    return { success: true, saved: conflicts.length };
  } catch(err) {
    Logger.log('saveConflicts error: ' + err.message);
    return { error: 'Server error: ' + err.message };
  }
}

// GET action handler
function getConflictFormAction(token, showParam) {
  try {
    if (!token) return { error: 'No token provided.' };
    const cbSheet = SpreadsheetApp.openById(CONTACTBOOK_ID).getSheetByName('Contactbook');
    const cbData  = cbSheet.getDataRange().getValues();
    let contactRow = -1;
    for (let i = 1; i < cbData.length; i++) {
      if (String(cbData[i][10]).trim() === String(token).trim()) { contactRow = i; break; }
    }
    if (contactRow === -1) return { error: 'Link not found or expired. Please contact TLT staff.' };

    const row = cbData[contactRow];
    const contactId = String(row[0]).trim();
    const firstName = String(row[1]).trim();
    const lastName  = String(row[3]).trim();

    const shows = getShowsForContact(firstName, lastName, contactId);
    // For each show, attach events (production team) or CM conflicts (actor)
    const enriched = shows.map(s => {
      if (s.isActor) {
        return { ...s, cmConflicts: getCmConflictsForActor(s.show, firstName, lastName) };
      }
      return {
        ...s,
        events:            getEventsForShowRole(s.show, s.role.split(' / ')[0]),  // use first role if merged
        existingConflicts: getConflictsForContactShow(contactId, s.show)
      };
    });

    return {
      contact: { contactId, firstName, lastName },
      shows:   enriched
    };
  } catch(err) {
    Logger.log('getConflictFormAction error: ' + err.message);
    return { error: 'Server error: ' + err.message };
  }
}

// ── Token Generation ──────────────────────────────────────────

function generateBioToken(seed) {
  return Utilities.base64Encode(
    Utilities.computeDigest(
      Utilities.DigestAlgorithm.SHA_256,
      seed + new Date().getTime() + Math.random()
    )
  ).replace(/[^a-zA-Z0-9]/g, '').substring(0, 32);
}
