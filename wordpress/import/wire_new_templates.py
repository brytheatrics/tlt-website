"""
Wire up the new templates and pages we just built:

1. Create /styleguide/ page if it doesn't exist; assign 'page-styleguide.php' template
2. Assign templates to existing pages by slug:
   - /auditions/ -> page-auditions.php
   - /ticketinfo/, /season-tickets/, /parking-information/ -> page-ticketing.php
   - /flush/ -> page-campaign.php
   - /press/, /job-openings/ -> page-post-listing.php (with category meta)
   - /contact/ -> page-contact.php
   - /recorded-programs/ -> page-video-archive.php
   - /volunteer/, /donation-request/, /tickets/, /donate/, /visit/, /get-involved/, /about/ -> page-designed.php (optional, only if not already templated)
3. Set listing_category_slug meta on press and job-openings pages
4. Flush WordPress rewrite rules so /off-the-shelf/<slug>/ works

Idempotent.
"""
import os, time, pymysql

c = pymysql.connect(host='127.0.0.1', port=10005, user='root', password='root', database='local')
cur = c.cursor()
now = time.strftime('%Y-%m-%d %H:%M:%S')


def get_page_id(slug):
    cur.execute("SELECT ID FROM wp_posts WHERE post_name=%s AND post_type='page' AND post_status='publish'", (slug,))
    row = cur.fetchone()
    return row[0] if row else None


def set_meta(pid, key, value):
    cur.execute("DELETE FROM wp_postmeta WHERE post_id=%s AND meta_key=%s", (pid, key))
    if value:
        cur.execute("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (%s,%s,%s)",
                    (pid, key, value))


def assign_template(slug, template_file):
    pid = get_page_id(slug)
    if not pid:
        print(f"  [skip] /{slug}/ not found")
        return
    set_meta(pid, '_wp_page_template', template_file)
    print(f"  /{slug}/ (id={pid}) -> {template_file}")


# 1. Create /styleguide/ page if it doesn't exist
pid = get_page_id('styleguide')
if not pid:
    cur.execute("""INSERT INTO wp_posts SET
        post_author=1, post_date=%s, post_date_gmt=%s, post_modified=%s, post_modified_gmt=%s,
        post_content=%s, post_title=%s, post_excerpt=%s,
        post_status='publish', post_type='page', post_name='styleguide',
        comment_status='closed', ping_status='closed', post_parent=0, menu_order=0,
        post_password='', to_ping='', pinged='', post_content_filtered='',
        guid='http://tlt.local/?page_id=styleguide'""",
        (now, now, now, now, '', 'Theme Styleguide', 'Internal QA — every component on one page'))
    pid = cur.lastrowid
    print(f"Created /styleguide/ (id={pid})")
else:
    print(f"/styleguide/ already exists (id={pid})")
set_meta(pid, '_wp_page_template', 'page-styleguide.php')

# 2. Assign templates to existing pages
print("\nAssigning page templates:")
assign_template('auditions', 'page-auditions.php')
assign_template('ticketinfo', 'page-ticketing.php')
assign_template('season-tickets', 'page-ticketing.php')
assign_template('parking-information', 'page-ticketing.php')
assign_template('flush', 'page-campaign.php')
assign_template('press', 'page-post-listing.php')
assign_template('job-openings', 'page-post-listing.php')
assign_template('contact', 'page-contact.php')
assign_template('recorded-programs', 'page-video-archive.php')

# Pages best served by the Designed Page template (image + headline + body + CTAs)
for slug in ['volunteer', 'donation-request', 'tickets', 'donate', 'visit', 'get-involved', 'about']:
    cur.execute("SELECT meta_value FROM wp_postmeta WHERE post_id=(SELECT ID FROM wp_posts WHERE post_name=%s AND post_type='page' LIMIT 1) AND meta_key='_wp_page_template' LIMIT 1", (slug,))
    row = cur.fetchone()
    if row and row[0] and row[0] != 'default' and row[0] != 'page-designed.php':
        print(f"  [skip] /{slug}/ already has template '{row[0]}'")
        continue
    assign_template(slug, 'page-designed.php')

# 3. Set listing_category_slug meta on post-listing pages
print("\nSetting listing category slugs:")
pid = get_page_id('press')
if pid:
    set_meta(pid, 'listing_category_slug', 'press')
    set_meta(pid, 'listing_per_page', '12')
    print(f"  /press/ category=press")
pid = get_page_id('job-openings')
if pid:
    set_meta(pid, 'listing_category_slug', 'job-openings')
    set_meta(pid, 'listing_per_page', '20')
    set_meta(pid, 'listing_show_thumbs', '0')
    print(f"  /job-openings/ category=job-openings (no thumbs)")

# 4. Set off-the-shelf page template
pid = get_page_id('off-the-shelf')
if pid:
    set_meta(pid, '_wp_page_template', 'page-off-the-shelf.php')
    print(f"\n/off-the-shelf/ template assigned (id={pid})")

# 5. Flush WordPress rewrite rules so /off-the-shelf/<slug>/ resolves
print("\nFlushing rewrite rules (set wp_options.rewrite_rules to empty so they regenerate)...")
cur.execute("DELETE FROM wp_options WHERE option_name='rewrite_rules'")
print("  Done (rules will regenerate on next admin / template load).")

c.commit()
c.close()
print("\nAll done.")
