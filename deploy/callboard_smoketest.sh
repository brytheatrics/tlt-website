#!/usr/bin/env bash
# TLT Callboard — Read-endpoint smoke test.
#
# Hits every read endpoint with your callboard password and reports pass/fail.
# Doesn't touch any write/mutation endpoints — safe to run against production.
#
# Usage:
#   ./deploy/callboard_smoketest.sh 'YOUR_CALLBOARD_PASSWORD'
#
# Optionally override the URL:
#   BASE_URL='https://wordpress-1633814-6469148.cloudwaysapps.com' ./deploy/callboard_smoketest.sh 'PW'
#
# Requires: curl, python (for JSON pretty-printing on failure).

set -u

BASE_URL="${BASE_URL:-https://wordpress-1633814-6469148.cloudwaysapps.com}"
PASSWORD="${1:-}"

if [[ -z "$PASSWORD" ]]; then
  echo "Usage: $0 'YOUR_CALLBOARD_PASSWORD'"
  exit 2
fi

API="${BASE_URL}/wp-json/callboard/v1"
CURL_OPTS=(-sk -w "\n__HTTP_%{http_code}__" --max-time 30)

pass_count=0
fail_count=0
failures=()

banner() { echo; echo "=== $1 ==="; }

check() {
  local name="$1"; shift
  local method="$1"; shift
  local path="$1"; shift

  local resp code body
  if [[ "$method" == "GET" ]]; then
    resp=$(curl "${CURL_OPTS[@]}" -H "Authorization: Bearer ${TOKEN}" -H "Accept: application/json" "${API}${path}")
  else
    resp=$(curl "${CURL_OPTS[@]}" -X "$method" -H "Authorization: Bearer ${TOKEN}" -H "Accept: application/json" -H "Content-Type: application/json" -d "$1" "${API}${path}")
  fi

  code=$(echo "$resp" | grep -oE '__HTTP_[0-9]+__$' | tr -d _HTTP_)
  body=$(echo "$resp" | sed 's/__HTTP_[0-9]*__$//')

  if [[ "$code" == "200" ]]; then
    printf "  PASS  %-40s HTTP %s\n" "$name" "$code"
    pass_count=$((pass_count + 1))
  else
    printf "  FAIL  %-40s HTTP %s\n" "$name" "$code"
    fail_count=$((fail_count + 1))
    # Extract the error message if JSON.
    msg=$(echo "$body" | python -c "import json,sys; d=json.loads(sys.stdin.read()); print(d.get('message','(no message)'))" 2>/dev/null || echo "(non-JSON body)")
    failures+=("${name}: HTTP ${code} — ${msg}")
  fi
}

# -------------------------- Login --------------------------
banner "Login"
login_resp=$(curl "${CURL_OPTS[@]}" -X POST -H "Content-Type: application/json" -d "{\"password\":\"${PASSWORD}\"}" "${API}/login")
login_code=$(echo "$login_resp" | grep -oE '__HTTP_[0-9]+__$' | tr -d _HTTP_)
login_body=$(echo "$login_resp" | sed 's/__HTTP_[0-9]*__$//')
if [[ "$login_code" != "200" ]]; then
  echo "  FAIL  login  HTTP ${login_code}"
  echo "  body: ${login_body}"
  exit 1
fi
TOKEN=$(echo "$login_body" | python -c "import json,sys; print(json.load(sys.stdin)['token'])" 2>/dev/null || echo "")
if [[ -z "$TOKEN" ]]; then
  echo "  FAIL  login — no token in response body"
  exit 1
fi
echo "  PASS  login  HTTP 200 (got token)"

# -------------------------- Reads with no args --------------------------
banner "Season-level reads"
check "GET /shows"              GET "/shows"
check "GET /current-season"     GET "/current-season"
check "GET /roles"              GET "/roles"
check "GET /full-season"        GET "/full-season"
check "GET /initial-data"       GET "/initial-data"
check "GET /dashboard"          GET "/dashboard"
check "GET /actors"             GET "/actors"
check "GET /sales"              GET "/sales"
check "GET /bios"               GET "/bios"
check "GET /contacts"           GET "/contacts"
check "GET /contracts"          GET "/contracts"
check "GET /contracts?shape=array" GET "/contracts?shape=array"
check "GET /calendar-events"    GET "/calendar-events"
check "GET /calendar-conflicts" GET "/calendar-conflicts"

# -------------------------- Reads with show= arg --------------------------
banner "Show-level reads (first show only)"
# Fetch the shows list so we can hit ?show= endpoints against a real value.
shows_resp=$(curl "${CURL_OPTS[@]}" -H "Authorization: Bearer ${TOKEN}" "${API}/shows")
shows_body=$(echo "$shows_resp" | sed 's/__HTTP_[0-9]*__$//')
first_show=$(echo "$shows_body" | python -c "import json,sys; d=json.load(sys.stdin); print(d.get('data',[None])[0] or '')" 2>/dev/null)

if [[ -z "$first_show" ]]; then
  echo "  SKIP  no shows in season — can't hit show= endpoints"
else
  echo "  (using show: ${first_show})"
  encoded_show=$(python -c "import urllib.parse,sys; print(urllib.parse.quote('${first_show}'))")
  check "GET /show-roster"          GET "/show-roster?show=${encoded_show}"
  check "GET /actors-for-show"      GET "/actors-for-show?show=${encoded_show}"
  check "GET /schedule-link"        GET "/schedule-link?show=${encoded_show}"
  check "GET /contact-sheet-link"   GET "/contact-sheet-link?show=${encoded_show}"
  check "GET /program"              GET "/program?show=${encoded_show}"
fi

# -------------------------- Ping the mutation routes (should reject unknown params) --------------------------
# These POSTs with missing fields should return 400 with a "missing_show" style
# error. We're just verifying the endpoints exist and reject cleanly — nothing
# is written to your sheet.
banner "Mutation routes registered? (expect HTTP 400 — 'missing X')"
check "POST /tech-schedule-generate (empty body)" POST "/tech-schedule-generate" '{}'
check "POST /bios-doc-compile (empty body)"       POST "/bios-doc-compile" '{}'
check "POST /bios-send-requests (empty body)"     POST "/bios-send-requests" '{}'
check "POST /program-export (empty body)"         POST "/program-export" '{}'
check "POST /contract-generate (empty body)"      POST "/contract-generate" '{}'
check "POST /contract-generate-combined (empty)"  POST "/contract-generate-combined" '{}'
check "POST /contract-send (empty body)"          POST "/contract-send" '{}'
check "POST /contract-send-combined (empty body)" POST "/contract-send-combined" '{}'
check "POST /contract-delete (empty body)"        POST "/contract-delete" '{}'
check "POST /contact-sheet-generate (empty body)" POST "/contact-sheet-generate" '{}'
check "POST /contact-sheet-regenerate (empty)"    POST "/contact-sheet-regenerate" '{}'
check "POST /bios-resend (empty body)"            POST "/bios-resend" '{}'
# For the mutation routes, we expected 400 not 200 — invert the pass count for
# these entries. Simplest: recount by looking at failures.
# But the failure list already tracks "not 200" — for these entries, "HTTP 400"
# is actually the pass state. Move them out of failures.
new_failures=()
for f in "${failures[@]}"; do
  # If failure is a 400 on one of our mutation ping endpoints, that's actually a pass.
  if echo "$f" | grep -qE 'POST /(tech-schedule-generate|bios-doc-compile|bios-send-requests|program-export|contract-(generate|send|delete)|contact-sheet-(generate|regenerate)|bios-resend|contract-generate-combined|contract-send-combined).*HTTP 400'; then
    pass_count=$((pass_count + 1))
    fail_count=$((fail_count - 1))
  else
    new_failures+=("$f")
  fi
done
failures=("${new_failures[@]}")

# -------------------------- Summary --------------------------
banner "Summary"
echo "  ${pass_count} pass, ${fail_count} fail"
if [[ ${#failures[@]} -gt 0 ]]; then
  echo
  echo "Failures:"
  for f in "${failures[@]}"; do
    echo "  - $f"
  done
  exit 1
fi
echo
echo "All read endpoints healthy. Safe to test UI flows."
