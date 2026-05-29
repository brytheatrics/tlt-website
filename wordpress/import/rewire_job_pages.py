"""
Assign new templates to job pages and move all visual scaffolding out of
post_content (where wpautop was mangling it). Post_content now holds just
the body text of each posting.
"""
import pymysql

# === /job-openings/ ===
# Listing page — template hardcodes the listing, content is unused.
LISTING_PAGE_CONTENT = ''

# === /job-openings/2627-production-team/ ===
# Body text only — hero, eyebrow, meta, apply panel come from the template
# via post meta. Keeps wpautop happy because there's no <style>/<a><img> mix.
POSTING_CONTENT = """<p>Tacoma Little Theatre is seeking designers for our 2026–2027 season. We are seeking collaborative designers with a passion for the arts and their craft.</p>

<h2>Available Positions</h2>
<ul class="job-positions">
<li>Stage Managers</li>
<li>Resident Properties Designer</li>
<li>Resident Sound Designer</li>
<li>Show Costume Designer</li>
<li>Scenic Artist Apprentice</li>
</ul>

<h2>Our Approach</h2>
<p>At TLT, we believe theatre is a space for connection, reflection, and community storytelling. We are committed to producing work that is artistically bold, inclusive, and reflective of the diverse voices that make up our region. We strongly encourage designers of all backgrounds, identities, and experience levels to apply.</p>

<h2>Compensation</h2>
<p>Honorariums range from <strong>$600.00 – $1,400.00 per show</strong>.</p>

<h2>The 2026–2027 Season</h2>
<div class="show-list">
<div class="show">
<p class="show-title">The Outsider</p>
<p class="show-meta">By Paul Slade Smith · August 28 – September 13, 2026</p>
<p class="show-blurb">Ned Newley doesn't even want to be governor. He's terrified of public speaking, and his poll numbers are impressively bad. A timely and hilarious comedy that skewers politics and celebrates democracy.</p>
</div>
<div class="show">
<p class="show-title">Arsenic and Old Lace</p>
<p class="show-meta">By Joseph Kesserling · October 16 – November 1, 2026</p>
<p class="show-blurb">Drama critic Mortimer Brewster's engagement announcement is upended when he discovers a corpse in his elderly aunts' window seat — only to learn the two women aren't just aware of him, they killed him!</p>
</div>
<div class="show">
<p class="show-title">Hallmarked</p>
<p class="show-meta">By Michael D. Fox · December 4 – 27, 2026</p>
<p class="show-blurb">It seems everyone on the planet is obsessed with Hallmark movies. Everyone except Julie. Packed with fabulous new pop songs, loads of laughter, and heartwarming delight, Hallmarked is a rom-com fever dream.</p>
</div>
<div class="show">
<p class="show-title">Dot</p>
<p class="show-meta">By Colman Domingo · February 5 – 21, 2027</p>
<p class="show-blurb">The holidays are always a wild family affair at the Shealy house. This twisted and hilarious new play grapples unflinchingly with aging parents, midlife crises, and the heart of a West Philly neighborhood.</p>
</div>
<div class="show">
<p class="show-title">Urinetown</p>
<p class="show-meta">By Greg Kotis · March 26 – April 18, 2027</p>
<p class="show-blurb">In this side-splitting satire, the young hero Bobby Strong leads his community in a fight against oppression — set in a dystopian world where water is scarce and citizens must pay a fee for "The Privilege to Pee."</p>
</div>
<div class="show">
<p class="show-title">The Importance of Being Earnest (UWT Partner Project)</p>
<p class="show-meta">By Oscar Wilde · May 21 – June 6, 2027</p>
<p class="show-blurb">Jack lives a double life — dutiful guardian in the country, free spirit in town under a false identity. His friend Algernon takes on a similar facade. Unfortunately, double lives have drawbacks, especially in love.</p>
</div>
<div class="show">
<p class="show-title">The Play That Goes Wrong</p>
<p class="show-meta">By Henry Lewis, Jonathan Sayer &amp; Henry Shields · July 9 – 25, 2027</p>
<p class="show-blurb">Welcome to opening night of the Cornley University Drama Society's newest production, where things go from bad to utterly disastrous. Part Monty Python, part Sherlock Holmes — guaranteed to leave you aching with laughter.</p>
</div>
</div>"""

POSTING_META = {
    'job_eyebrow':     'Now Hiring',
    'job_meta':        'Letters reviewed on a rolling basis · Positions filled by May 31, 2026',
    'job_thumb':       '/wp-content/uploads/migrated/orange-and-peach-simple-now-hiring-announcement-instagram-post.png',
    'job_apply_url':   'mailto:jobs@tacomalittletheatre.com?subject=2026-2027%20Production%20Team%20Application',
    'job_apply_intro': "Submit a current resume and a letter of interest indicating which show(s) you're applying for. You may apply for more than one production in a single email.",
}


def set_meta(cur, pid, key, value):
    cur.execute("DELETE FROM wp_postmeta WHERE post_id=%s AND meta_key=%s", (pid, key))
    cur.execute("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (%s, %s, %s)",
                (pid, key, value))


c = pymysql.connect(host='127.0.0.1', port=10005, user='root', password='root', database='local')
cur = c.cursor()

# --- Listing ---
cur.execute("SELECT ID FROM wp_posts WHERE post_name='job-openings' AND post_status='publish' AND post_type='page' LIMIT 1")
listing_id = cur.fetchone()[0]
cur.execute("UPDATE wp_posts SET post_content=%s WHERE ID=%s", (LISTING_PAGE_CONTENT, listing_id))
set_meta(cur, listing_id, '_wp_page_template', 'page-job-openings.php')
print(f"Listing /job-openings/ (id={listing_id}): assigned page-job-openings.php, cleared content")

# --- Posting ---
cur.execute("SELECT ID FROM wp_posts WHERE post_name='2627-production-team' AND post_status='publish' AND post_type='page' LIMIT 1")
posting_id = cur.fetchone()[0]
cur.execute("UPDATE wp_posts SET post_content=%s WHERE ID=%s", (POSTING_CONTENT, posting_id))
set_meta(cur, posting_id, '_wp_page_template', 'page-job-posting.php')
for k, v in POSTING_META.items():
    set_meta(cur, posting_id, k, v)
print(f"Posting /job-openings/2627-production-team/ (id={posting_id}): assigned page-job-posting.php, set meta")

c.commit()
c.close()
