"""Triage app: click through every TLT page, decide keep/skip/maybe, leave notes.
Run: python app.py
Then open http://localhost:5174
"""
import os, json, webbrowser, threading, time
from flask import Flask, render_template, request, jsonify, send_from_directory, redirect

ROOT = os.path.dirname(os.path.abspath(__file__))
PROJECT = os.path.dirname(ROOT)
CANDIDATES_FILE = os.path.join(ROOT, "candidates.json")
DECISIONS_FILE = os.path.join(ROOT, "decisions.json")

app = Flask(__name__, static_folder=None)

def load_candidates():
    with open(CANDIDATES_FILE, "r", encoding="utf-8") as f:
        return json.load(f)

def load_decisions():
    if not os.path.exists(DECISIONS_FILE):
        return {}
    try:
        with open(DECISIONS_FILE, "r", encoding="utf-8") as f:
            return json.load(f)
    except: return {}

def save_decisions(d):
    with open(DECISIONS_FILE, "w", encoding="utf-8") as f:
        json.dump(d, f, indent=1)

@app.after_request
def no_cache(resp):
    resp.headers["Cache-Control"] = "no-store, no-cache, must-revalidate, max-age=0"
    resp.headers["Pragma"] = "no-cache"
    return resp

@app.route("/")
def index():
    return render_template("triage.html")

@app.route("/api/candidates")
def api_candidates():
    cands = load_candidates()
    decs = load_decisions()
    for c in cands:
        d = decs.get(c["url"], {})
        c["decision"] = d.get("decision", "")
        c["note"] = d.get("note", "")
    return jsonify(cands)

@app.route("/api/decision", methods=["POST"])
def api_decision():
    body = request.get_json()
    url = body["url"]
    decs = load_decisions()
    cur = decs.get(url, {})
    if "decision" in body: cur["decision"] = body["decision"]
    if "note" in body: cur["note"] = body["note"]
    cur["updated_at"] = int(time.time())
    decs[url] = cur
    save_decisions(decs)
    return jsonify(ok=True)

@app.route("/api/export")
def api_export():
    """Export decisions as CSV-able JSON for the migration script."""
    return jsonify(load_decisions())

@app.route("/preview/<path:rel>")
def preview(rel):
    """Serve scraped HTML files. rel is path relative to project root, e.g. scrape/pages_other_blog/foo.html"""
    full_path = os.path.join(PROJECT, rel)
    if not os.path.exists(full_path):
        return f"<h2>Not scraped locally</h2><p>{rel}</p>", 404
    directory = os.path.dirname(full_path)
    filename = os.path.basename(full_path)
    return send_from_directory(directory, filename)

@app.route("/health")
def health():
    return "ok"

def open_browser_when_ready(port):
    time.sleep(1.0)
    webbrowser.open(f"http://localhost:{port}/")

if __name__ == "__main__":
    PORT = 5174
    threading.Thread(target=open_browser_when_ready, args=(PORT,), daemon=True).start()
    print(f"\n  TLT Triage running at http://localhost:{PORT}")
    print(f"  Decisions saved live to: {DECISIONS_FILE}")
    print(f"  Press Ctrl+C to stop.\n")
    app.run(port=PORT, debug=False)
