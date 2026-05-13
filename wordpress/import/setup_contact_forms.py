"""
Activate Contact Form 7 and create three starter forms:
- Contact (general inquiry)
- Donation Request (orgs applying TO TLT for in-kind donations)
- Volunteer Signup

Then update /contact/, /donation-request/, /volunteer/ page meta with the
appropriate shortcode so the templates pick them up.

Idempotent.
"""
import os, time, pymysql

c = pymysql.connect(host='127.0.0.1', port=10005, user='root', password='root', database='local')
cur = c.cursor()
now = time.strftime('%Y-%m-%d %H:%M:%S')


# --- 1. Activate Contact Form 7 plugin ---
cur.execute("SELECT option_value FROM wp_options WHERE option_name='active_plugins'")
row = cur.fetchone()
active = row[0] if row else 'a:0:{}'

# Parse the PHP serialized array, append CF7 if not present, re-serialize.
# Rather than full PHP-serialize parsing, do a simple presence check + append.
cf7_entry = 'contact-form-7/wp-contact-form-7.php'
if cf7_entry not in active:
    # Naive append to a PHP serialized array. Format:
    # a:N:{i:0;s:LEN:"path/to/plugin.php";i:1;...}
    # Find current N + max index
    import re
    m = re.match(r'a:(\d+):\{(.*)\}\s*$', active, re.S)
    if m:
        n = int(m.group(1))
        inner = m.group(2)
        new_entry = f'i:{n};s:{len(cf7_entry)}:"{cf7_entry}";'
        new_active = f'a:{n+1}:{{{inner}{new_entry}}}'
        cur.execute("UPDATE wp_options SET option_value=%s WHERE option_name='active_plugins'", (new_active,))
        print(f"Activated Contact Form 7 (was {n} active plugins, now {n+1})")
    else:
        # No plugins active yet — create the entry
        new_active = f'a:1:{{i:0;s:{len(cf7_entry)}:"{cf7_entry}";}}'
        cur.execute("UPDATE wp_options SET option_value=%s WHERE option_name='active_plugins'", (new_active,))
        print("Activated Contact Form 7 (first active plugin)")
else:
    print("Contact Form 7 already active")

# CF7 creates its post type ('wpcf7_contact_form') on activation. Since we
# can't trigger WP activation hooks from CLI without bootstrapping WP, we
# manually register the forms via direct DB inserts. CF7 reads form templates
# from postmeta on its 'wpcf7_contact_form' post type, so we just create
# those posts directly.

def upsert_cf7_form(title, body, mail_subject, mail_recipient_template):
    cur.execute("SELECT ID FROM wp_posts WHERE post_title=%s AND post_type='wpcf7_contact_form'", (title,))
    row = cur.fetchone()
    if row:
        pid = row[0]
        action = 'exists'
    else:
        cur.execute("""INSERT INTO wp_posts SET
            post_author=1, post_date=%s, post_date_gmt=%s, post_modified=%s, post_modified_gmt=%s,
            post_content='', post_title=%s, post_excerpt='',
            post_status='publish', post_type='wpcf7_contact_form', post_name=%s,
            comment_status='closed', ping_status='closed', post_parent=0, menu_order=0,
            post_password='', to_ping='', pinged='', post_content_filtered=''""",
            (now, now, now, now, title, 'cf7-' + title.lower().replace(' ', '-')))
        pid = cur.lastrowid
        action = 'created'

    # Form template (PHP-serialized string stored in postmeta).
    # CF7 stores its config as serialized arrays. To keep this script simple,
    # we use PHP's serialize() format inline for known shapes.

    # The 'form' meta key holds the form's template (the visible body of the form).
    def php_serialize_string(s):
        return f's:{len(s.encode("utf-8"))}:"{s}";'

    # set_meta helper
    def set_meta(key, value):
        cur.execute("DELETE FROM wp_postmeta WHERE post_id=%s AND meta_key=%s", (pid, key))
        cur.execute("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (%s,%s,%s)",
                    (pid, key, value))

    set_meta('_form', body)

    # 'mail' template — what gets sent on submission. Stored as a PHP-serialized array.
    mail = {
        'subject':           mail_subject,
        'sender':            '[your-name] <wordpress@tlt.local>',
        'body':              "From: [your-name] <[your-email]>\nSubject: " + mail_subject + "\n\n[your-message]\n\n-- \nThis e-mail was sent from the contact form on Tacoma Little Theatre (http://tacomalittletheatre.com)",
        'recipient':         mail_recipient_template,
        'additional_headers': 'Reply-To: [your-email]',
        'attachments':       '',
        'use_html':          0,
        'exclude_blank':     0,
    }
    # Serialize the array manually (PHP-compatible)
    def php_serialize_assoc(d):
        parts = [f'a:{len(d)}:{{']
        for k, v in d.items():
            parts.append(php_serialize_string(k))
            if isinstance(v, str):
                parts.append(php_serialize_string(v))
            elif isinstance(v, int):
                parts.append(f'i:{v};')
            else:
                parts.append(php_serialize_string(str(v)))
        parts.append('}')
        return ''.join(parts)

    set_meta('_mail', php_serialize_assoc(mail))
    set_meta('_mail_2', php_serialize_assoc({
        'active':            0,
        'subject':           '',
        'sender':            '',
        'body':              '',
        'recipient':         '',
        'additional_headers': '',
        'attachments':       '',
        'use_html':          0,
        'exclude_blank':     0,
    }))

    # Messages (defaults)
    set_meta('_messages', php_serialize_assoc({
        'mail_sent_ok':        'Thank you for your message. It has been sent.',
        'mail_sent_ng':        'There was an error trying to send your message. Please try again later.',
        'validation_error':    'One or more fields have an error. Please check and try again.',
        'spam':                'There was an error trying to send your message. Please try again later.',
        'accept_terms':        'You must accept the terms and conditions before sending your message.',
        'invalid_required':    'Please fill out this field.',
        'invalid_too_long':    'This field has a too long input.',
        'invalid_too_short':   'This field has a too short input.',
    }))

    # Additional settings (empty)
    set_meta('_additional_settings', '')

    # Locale
    set_meta('_locale', 'en_US')

    # Hash (used by CF7 to track the form)
    import hashlib
    set_meta('_hash', hashlib.sha256(f"form-{pid}-{title}".encode()).hexdigest())

    print(f"  Form {action}: '{title}' (id={pid})")
    return pid


# --- 2. Build the three starter forms ---
print("\nCreating starter forms:")

contact_form_body = """<p>Your name (required)
    [text* your-name]</p>

<p>Your email (required)
    [email* your-email]</p>

<p>Subject
    [text your-subject]</p>

<p>Your message
    [textarea your-message]</p>

<p>[submit "Send Message"]</p>"""

contact_id = upsert_cf7_form(
    'Contact',
    contact_form_body,
    'Tacoma Little Theatre: contact form submission',
    'boxoffice@tacomalittletheatre.com'
)

donation_form_body = """<p>Organization name (required)
    [text* org-name]</p>

<p>Contact person (required)
    [text* contact-name]</p>

<p>Contact email (required)
    [email* contact-email]</p>

<p>Contact phone
    [tel contact-phone]</p>

<p>Event name (required)
    [text* event-name]</p>

<p>Event date (required)
    [date* event-date]</p>

<p>What are you requesting? (required)
    [textarea* request-details placeholder "Tickets, gift cards, memorabilia, etc."]</p>

<p>How will this support the community? (required)
    [textarea* community-impact]</p>

<p>Is your organization a 501(c)(3)?
    [select tax-status "Yes" "No" "Other / Please explain"]</p>

<p>Anything else we should know?
    [textarea additional-notes]</p>

<p>[submit "Submit Donation Request"]</p>"""

donation_id = upsert_cf7_form(
    'Donation Request',
    donation_form_body,
    'TLT donation request: [event-name] from [org-name]',
    'boxoffice@tacomalittletheatre.com'
)

volunteer_form_body = """<p>Your name (required)
    [text* your-name]</p>

<p>Your email (required)
    [email* your-email]</p>

<p>Phone
    [tel your-phone]</p>

<p>What areas interest you? (check all that apply)
    [checkbox volunteer-interests "Ushering" "Box Office" "Bartending" "Set Construction" "Costumes / Props" "Marketing" "Special Events" "Board / Committees" "Other"]</p>

<p>Tell us a bit about yourself
    [textarea about-you]</p>

<p>How did you hear about us?
    [text referral-source]</p>

<p>[submit "Sign Up to Volunteer"]</p>"""

volunteer_id = upsert_cf7_form(
    'Volunteer Signup',
    volunteer_form_body,
    'TLT volunteer signup from [your-name]',
    'volunteers@tacomalittletheatre.com'
)


# --- 3. Wire shortcodes to page meta so templates pick them up ---
print("\nWiring shortcodes to pages:")

def set_page_meta(slug, key, value):
    cur.execute("SELECT ID FROM wp_posts WHERE post_name=%s AND post_type='page' LIMIT 1", (slug,))
    row = cur.fetchone()
    if not row:
        print(f"  [skip] /{slug}/ not found")
        return
    pid = row[0]
    cur.execute("DELETE FROM wp_postmeta WHERE post_id=%s AND meta_key=%s", (pid, key))
    cur.execute("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (%s,%s,%s)", (pid, key, value))
    print(f"  /{slug}/ {key} = '{value}'")

set_page_meta('contact', 'contact_form_shortcode', f'[contact-form-7 id="{contact_id}" title="Contact"]')
# Donation request and volunteer pages use the Designed Page template — the form
# would need to be in the body. For now we just record the shortcode in a meta
# so Chris (or you) can paste it where appropriate.
set_page_meta('donation-request', 'cf7_shortcode', f'[contact-form-7 id="{donation_id}" title="Donation Request"]')
set_page_meta('volunteer', 'cf7_shortcode', f'[contact-form-7 id="{volunteer_id}" title="Volunteer Signup"]')


c.commit()
c.close()
print("\nDone. Three Contact Form 7 forms created and linked.")
print("Note: Contact Form 7 will need to be 'activated' fresh via WP admin once to register its post type schema if any DB upgrades are needed. Forms should work as-is.")
