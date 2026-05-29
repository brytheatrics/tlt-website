"""
Rebuild the Donation Request form (CF7 #1313) and the /donation-request/
page to match the original Squarespace form (Title: "Request an Auction
Donation") with all 13 fields, instructions, and the Review Process notice.
"""
import pymysql

CF7_FORM = """<p><label>Organization Name (required)<br>
    [text* org-name]</label></p>

<p><label>Organization EIN / Tax ID#  (required)<br>
    [text* org-ein placeholder "12-3456789"]</label></p>

<p><label>Organization Address (required)<br>
    [text* org-address-line1 placeholder "Address Line 1"]<br>
    [text  org-address-line2 placeholder "Address Line 2"]<br>
    [text* org-address-city  placeholder "City"]
    [text* org-address-state placeholder "State"]
    [text* org-address-zip   placeholder "ZIP Code"]</label></p>

<p><label>Mailing Address (if different from above)<br>
    [text mail-address-line1 placeholder "Address Line 1"]<br>
    [text mail-address-line2 placeholder "Address Line 2"]<br>
    [text mail-address-city  placeholder "City"]
    [text mail-address-state placeholder "State"]
    [text mail-address-zip   placeholder "ZIP Code"]</label></p>

<p><label>Title of Event<br>
    [text event-title]</label></p>

<p><label>Date of Event<br>
    [date event-date]</label></p>

<p><label>Event Description<br>
    [textarea event-description]</label></p>

<p><label>Deadline for Donations<br>
    [date donation-deadline]</label></p>

<p><label>Organization Contact Name<br>
    [text contact-name]</label></p>

<p><label>Organization Phone (required)<br>
    [tel* contact-phone]</label></p>

<p><label>Contact Email (required)<br>
    [email* contact-email]</label></p>

<p><label>If you have a donation letter, please copy and paste the text of it here.<br>
    [textarea donation-letter]</label></p>

<p><label>Additional Comments<br>
    [textarea additional-comments]</label></p>

<h3>Review Process</h3>
<p>This request for a donation item must be submitted at least four weeks prior to the day your organization needs the item. Staff reviews submissions on an ongoing basis. Due to the high volume we receive, we are unable to honor every request. Good luck at your upcoming event!</p>

<p>[submit "Request Donation"]</p>
"""

# Mail template uses the new field names
MAIL_BODY = """From: [contact-name] <[contact-email]>
Organization: [org-name] (EIN [org-ein])
Phone: [contact-phone]

Org Address:
[org-address-line1]
[org-address-line2]
[org-address-city], [org-address-state] [org-address-zip]

Mailing Address (if different):
[mail-address-line1]
[mail-address-line2]
[mail-address-city], [mail-address-state] [mail-address-zip]

Event: [event-title]
Event date: [event-date]
Donation deadline: [donation-deadline]

Event Description:
[event-description]

Donation Letter:
[donation-letter]

Additional Comments:
[additional-comments]
"""

MAIL_META = (
    'a:8:{'
    's:7:"subject";s:55:"TLT donation request: [event-title] from [org-name]";'
    's:6:"sender";s:36:"[contact-name] <wordpress@tlt.local>";'
    's:4:"body";s:%d:"%s";'
    's:9:"recipient";s:32:"info@tacomalittletheatre.com";'
    's:18:"additional_headers";s:25:"Reply-To: [contact-email]";'
    's:11:"attachments";s:0:"";'
    's:8:"use_html";i:0;'
    's:13:"exclude_blank";i:0;'
    '}'
) % (len(MAIL_BODY), MAIL_BODY.replace('"', '\\"'))

# Replacement page content — intro, form, success notice
PAGE_CONTENT = """<p>Tacoma Little Theatre is always happy to give back to our wonderful and supportive community. Thank you for thinking of TLT as a way to support your organization. To ensure the timely review of your request, please complete the form below.</p>

[contact-form-7 id="1313" title="Donation Request"]"""

c = pymysql.connect(host='127.0.0.1', port=10005, user='root', password='root', database='local')
cur = c.cursor()

# 1) Update CF7 form #1313
cur.execute("UPDATE wp_postmeta SET meta_value=%s WHERE post_id=1313 AND meta_key='_form'", (CF7_FORM,))
cur.execute("UPDATE wp_postmeta SET meta_value=%s WHERE post_id=1313 AND meta_key='_mail'", (MAIL_META,))
# Bump title to match original
cur.execute("UPDATE wp_posts SET post_title=%s WHERE ID=1313", ('Request an Auction Donation',))
print("Updated CF7 #1313 (Donation Request form)")

# 2) Update the page
cur.execute("SELECT ID FROM wp_posts WHERE post_name='donation-request' AND post_status='publish' AND post_type='page' LIMIT 1")
pid = cur.fetchone()[0]
cur.execute("UPDATE wp_posts SET post_title=%s, post_content=%s WHERE ID=%s",
            ('Request an Auction Donation', PAGE_CONTENT, pid))
# Make sure the cf7_shortcode meta still points at the right form id
cur.execute("DELETE FROM wp_postmeta WHERE post_id=%s AND meta_key='cf7_shortcode'", (pid,))
cur.execute("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (%s, %s, %s)",
            (pid, 'cf7_shortcode', '[contact-form-7 id="1313" title="Donation Request"]'))
print(f"Updated /donation-request/ page (id={pid})")

c.commit()
c.close()
