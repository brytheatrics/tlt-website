"""
Polish the donation-request page:
- Assign the new page-donation-request.php template
- Reorganize the CF7 form into sections (Organization / Event / Contact) and
  drop the inline Review Process (now in sidebar)
- Slim the page content to just the form shortcode (intro is in template hero)
"""
import pymysql

CF7_FORM = """<h3 class="form-section-h">Organization</h3>

<p><label>Organization Name (required)
    [text* org-name]</label></p>

<p><label>Organization EIN / Tax ID# (required)
    [text* org-ein placeholder "12-3456789"]</label></p>

<p><label>Organization Address (required)
    [text* org-address-line1 placeholder "Address Line 1"]
    [text  org-address-line2 placeholder "Address Line 2"]</label></p>

<p class="addr-row">
    [text* org-address-city  placeholder "City"]
    [text* org-address-state placeholder "State"]
    [text* org-address-zip   placeholder "ZIP Code"]
</p>

<p><label>Mailing Address (if different from above)
    [text mail-address-line1 placeholder "Address Line 1"]
    [text mail-address-line2 placeholder "Address Line 2"]</label></p>

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

<p><label>Organization Contact Name
    [text contact-name]</label></p>

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

PAGE_CONTENT = '[contact-form-7 id="1313" title="Donation Request"]'

c = pymysql.connect(host='127.0.0.1', port=10005, user='root', password='root', database='local')
cur = c.cursor()

# 1) Update CF7 form
cur.execute("UPDATE wp_postmeta SET meta_value=%s WHERE post_id=1313 AND meta_key='_form'", (CF7_FORM,))
print("Updated CF7 #1313 form template (sectioned)")

# 2) Update page: assign new template + simplify content (intro is in template)
cur.execute("SELECT ID FROM wp_posts WHERE post_name='donation-request' AND post_status='publish' AND post_type='page' LIMIT 1")
pid = cur.fetchone()[0]
cur.execute("UPDATE wp_posts SET post_content=%s WHERE ID=%s", (PAGE_CONTENT, pid))
cur.execute("DELETE FROM wp_postmeta WHERE post_id=%s AND meta_key='_wp_page_template'", (pid,))
cur.execute("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (%s, %s, %s)",
            (pid, '_wp_page_template', 'page-donation-request.php'))
print(f"Assigned page-donation-request.php to /donation-request/ (id={pid})")

c.commit()
c.close()
