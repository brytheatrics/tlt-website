"""
Match the original Squarespace form more closely:
- Add Country dropdown above each address block (defaults to United States)
- Split Organization Contact Name into First + Last
"""
import pymysql

CF7_FORM = """<h3 class="form-section-h">Organization</h3>

<p><label>Organization Name (required)
    [text* org-name]</label></p>

<p><label>Organization EIN / Tax ID# (required)
    [text* org-ein placeholder "12-3456789"]</label></p>

<p><label>Organization Address (required)</label></p>
<p><label>Country
    [select org-address-country default:"United States" "United States" "Canada" "Mexico" "Other"]</label></p>

<p><label>Address Line 1 (required)
    [text* org-address-line1]</label></p>

<p><label>Address Line 2
    [text  org-address-line2]</label></p>

<p class="addr-row">
    [text* org-address-city  placeholder "City"]
    [text* org-address-state placeholder "State"]
    [text* org-address-zip   placeholder "ZIP Code"]
</p>

<p><label>Mailing Address (if different from above)</label></p>
<p><label>Country
    [select mail-address-country default:"United States" "United States" "Canada" "Mexico" "Other"]</label></p>

<p><label>Address Line 1
    [text mail-address-line1]</label></p>

<p><label>Address Line 2
    [text mail-address-line2]</label></p>

<p class="addr-row">
    [text mail-address-city  placeholder "City"]
    [text mail-address-state placeholder "State"]
    [text mail-address-zip   placeholder "ZIP Code"]
</p>

<h3 class="form-section-h">Event</h3>

<p><label>Title of Event
    [text event-title]</label></p>

<p><label>Date of Event
    [date event-date]</label></p>

<p><label>Event Description
    [textarea event-description]</label></p>

<p><label>Deadline for Donations
    [date donation-deadline]</label></p>

<h3 class="form-section-h">Contact</h3>

<p><label>Organization Contact Name</label></p>
<p class="name-row">
    [text contact-first-name placeholder "First Name"]
    [text contact-last-name  placeholder "Last Name"]
</p>

<p><label>Organization Phone (required)
    [tel* contact-phone]</label></p>

<p><label>Contact Email (required)
    [email* contact-email]</label></p>

<h3 class="form-section-h">Donation Letter &amp; Notes</h3>

<p><label>If you have a donation letter, please copy and paste the text of it here.
    [textarea donation-letter]</label></p>

<p><label>Additional Comments
    [textarea additional-comments]</label></p>

<p>[submit "Request Donation"]</p>
"""

# Update mail body for new field names
MAIL_BODY = """From: [contact-first-name] [contact-last-name] <[contact-email]>
Organization: [org-name] (EIN [org-ein])
Phone: [contact-phone]

Org Address:
[org-address-line1]
[org-address-line2]
[org-address-city], [org-address-state] [org-address-zip]
[org-address-country]

Mailing Address (if different):
[mail-address-line1]
[mail-address-line2]
[mail-address-city], [mail-address-state] [mail-address-zip]
[mail-address-country]

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
    's:6:"sender";s:53:"[contact-first-name] [contact-last-name] <wordpress@tlt.local>";'
    's:4:"body";s:%d:"%s";'
    's:9:"recipient";s:32:"info@tacomalittletheatre.com";'
    's:18:"additional_headers";s:25:"Reply-To: [contact-email]";'
    's:11:"attachments";s:0:"";'
    's:8:"use_html";i:0;'
    's:13:"exclude_blank";i:0;'
    '}'
) % (len(MAIL_BODY), MAIL_BODY.replace('"', '\\"'))

c = pymysql.connect(host='127.0.0.1', port=10005, user='root', password='root', database='local')
cur = c.cursor()
cur.execute("UPDATE wp_postmeta SET meta_value=%s WHERE post_id=1313 AND meta_key='_form'", (CF7_FORM,))
cur.execute("UPDATE wp_postmeta SET meta_value=%s WHERE post_id=1313 AND meta_key='_mail'", (MAIL_META,))
c.commit()
c.close()
print("Updated CF7 #1313: added country dropdowns + split contact name")
