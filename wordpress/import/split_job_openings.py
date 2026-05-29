"""
Split /job-openings/ into a listing page + an individual posting page,
mirroring the old Squarespace pattern (summary blocks linking to full
posts).

  /job-openings/                       — listing of cards (image + title + excerpt + read more)
  /job-openings/2627-production-team/  — full posting page

This way adding a new posting = create a new child page + add a card on the
listing page. Both pages use plain WP page slugs (no custom post type).
"""
import pymysql

# === Listing page content (cards with thumbnail + excerpt + link) ===
LISTING_CONTENT = """<style>
.jobs-intro { font-size: 1.05rem; line-height: 1.6; color: var(--color-text); margin: 0 0 2rem; }

.job-list { display: grid; gap: 1.5rem; }
.job-list-card {
  display: grid; grid-template-columns: 240px 1fr; gap: 1.5rem;
  background: #fff; border: 1px solid var(--color-line); border-radius: 6px;
  overflow: hidden; transition: border-color 0.15s, box-shadow 0.15s;
}
.job-list-card:hover { border-color: var(--color-accent); box-shadow: 0 4px 16px rgba(0,0,0,0.04); }
@media (max-width: 640px) { .job-list-card { grid-template-columns: 1fr; } }
.job-list-card__thumb {
  background: var(--color-soft); display: block; overflow: hidden;
}
.job-list-card__thumb img {
  width: 100%; height: 100%; object-fit: cover; aspect-ratio: 1 / 1;
  display: block;
}
@media (max-width: 640px) { .job-list-card__thumb img { aspect-ratio: 16/9; } }
.job-list-card__body { padding: 1.5rem 1.75rem 1.5rem 0; }
@media (max-width: 640px) { .job-list-card__body { padding: 0 1.5rem 1.5rem; } }
.job-list-card__eyebrow { display: inline-block; background: var(--color-accent); color: #fff; font-size: 0.7rem; letter-spacing: 0.1em; text-transform: uppercase; padding: 0.2rem 0.6rem; border-radius: 999px; margin-bottom: 0.6rem; }
.job-list-card__title { font-size: 1.25rem; margin: 0 0 0.35rem; }
.job-list-card__title a { color: var(--color-text); text-decoration: none; }
.job-list-card__title a:hover { color: var(--color-accent); }
.job-list-card__meta { margin: 0 0 0.6rem; font-size: 0.85rem; color: var(--color-muted); }
.job-list-card__excerpt { margin: 0 0 1rem; line-height: 1.55; font-size: 0.95rem; }
.job-list-card__more {
  font-size: 0.85rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em;
  color: var(--color-accent); text-decoration: none;
}
.job-list-card__more:hover { text-decoration: underline; }

.jobs-empty { text-align: center; padding: 2.5rem; background: var(--color-soft); border-radius: 6px; }
</style>

<p class="jobs-intro">Tacoma Little Theatre is always looking for talented people to join our team. Check below for current openings, and feel free to send a resume to <a href="mailto:jobs@tacomalittletheatre.com">jobs@tacomalittletheatre.com</a> any time — even if nothing's posted, we keep applications on file.</p>

<div class="job-list">
  <article class="job-list-card">
    <a class="job-list-card__thumb" href="/job-openings/2627-production-team/">
      <img src="/wp-content/uploads/migrated/orange-and-peach-simple-now-hiring-announcement-instagram-post.png" alt="">
    </a>
    <div class="job-list-card__body">
      <span class="job-list-card__eyebrow">Now Hiring</span>
      <h2 class="job-list-card__title"><a href="/job-openings/2627-production-team/">Production Team Members for 2026–2027</a></h2>
      <p class="job-list-card__meta">Posted June 11, 2024 · Letters reviewed on a rolling basis · Positions filled by May 31, 2026</p>
      <p class="job-list-card__excerpt">TLT is seeking designers — stage managers, properties, sound, costume, and a scenic artist apprentice — for our 2026–2027 season. Collaborative artists with a passion for the craft are encouraged to apply.</p>
      <a class="job-list-card__more" href="/job-openings/2627-production-team/">Read full posting &rarr;</a>
    </div>
  </article>
</div>"""

# === Full posting page content (what was previously on /job-openings/) ===
POSTING_CONTENT = """<style>
.job-back { display: inline-block; margin-bottom: 1rem; font-size: 0.85rem; color: var(--color-muted); text-decoration: none; }
.job-back:hover { color: var(--color-accent); }

.job-hero { background: var(--color-soft); border-radius: 6px; padding: 2rem; margin-bottom: 2rem; display: grid; grid-template-columns: 200px 1fr; gap: 2rem; align-items: center; }
@media (max-width: 640px) { .job-hero { grid-template-columns: 1fr; text-align: center; } }
.job-hero img { width: 100%; max-width: 200px; height: auto; border-radius: 4px; }
.job-hero__eyebrow { display: inline-block; background: var(--color-accent); color: #fff; font-size: 0.7rem; letter-spacing: 0.1em; text-transform: uppercase; padding: 0.2rem 0.6rem; border-radius: 999px; margin-bottom: 0.6rem; }
.job-hero__title { font-size: 1.6rem; margin: 0 0 0.4rem; line-height: 1.2; }
.job-hero__meta { margin: 0; font-size: 0.88rem; color: var(--color-muted); line-height: 1.5; }

.job-body h2 { font-size: 0.95rem; text-transform: uppercase; letter-spacing: 0.08em; color: var(--color-accent); margin: 2rem 0 0.75rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--color-line); }
.job-body h2:first-child { margin-top: 0; }
.job-body p { line-height: 1.65; }

.job-positions { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 0.5rem; margin: 0; padding: 0; list-style: none; }
.job-positions li { background: var(--color-soft); padding: 0.65rem 1rem; border-radius: 4px; font-weight: 600; font-size: 0.95rem; }

.show-list { display: grid; gap: 1rem; }
.show-list .show { border-left: 3px solid var(--color-accent); padding: 0.5rem 0 0.5rem 1rem; }
.show-list .show-title { font-weight: 700; font-size: 1rem; margin: 0 0 0.15rem; }
.show-list .show-meta { font-size: 0.85rem; color: var(--color-muted); margin: 0 0 0.4rem; }
.show-list .show-blurb { font-size: 0.92rem; line-height: 1.5; margin: 0; }

.job-apply { background: linear-gradient(135deg, var(--color-soft) 0%, #fff 100%); border: 1px solid var(--color-line); border-radius: 6px; padding: 2rem; text-align: center; margin-top: 2rem; }
.job-apply h2 { color: var(--color-text); border: none; padding: 0; text-transform: none; letter-spacing: 0; font-size: 1.3rem; margin: 0 0 0.5rem; }
.job-apply p { margin: 0 0 1.25rem; color: var(--color-muted); font-size: 0.95rem; }
</style>

<a href="/job-openings/" class="job-back">&larr; All Job Openings</a>

<div class="job-hero">
  <img src="/wp-content/uploads/migrated/orange-and-peach-simple-now-hiring-announcement-instagram-post.png" alt="Now Hiring">
  <div>
    <span class="job-hero__eyebrow">Now Hiring</span>
    <h1 class="job-hero__title">Production Team Members for 2026–2027</h1>
    <p class="job-hero__meta">Posted June 11, 2024 · Letters reviewed on a rolling basis · Positions filled by May 31, 2026</p>
  </div>
</div>

<div class="job-body">
  <p>Tacoma Little Theatre is seeking designers for our 2026–2027 season. We are seeking collaborative designers with a passion for the arts and their craft.</p>

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
      <p class="show-title">The Importance of Being Earnest <span style="font-weight:400;font-size:0.85em;color:var(--color-muted)">(UWT Partner Project)</span></p>
      <p class="show-meta">By Oscar Wilde · May 21 – June 6, 2027</p>
      <p class="show-blurb">Jack lives a double life — dutiful guardian in the country, free spirit in town under a false identity. His friend Algernon takes on a similar facade. Unfortunately, double lives have drawbacks, especially in love.</p>
    </div>
    <div class="show">
      <p class="show-title">The Play That Goes Wrong</p>
      <p class="show-meta">By Henry Lewis, Jonathan Sayer &amp; Henry Shields · July 9 – 25, 2027</p>
      <p class="show-blurb">Welcome to opening night of the Cornley University Drama Society's newest production, where things go from bad to utterly disastrous. Part Monty Python, part Sherlock Holmes — guaranteed to leave you aching with laughter.</p>
    </div>
  </div>

  <div class="job-apply">
    <h2>How to Apply</h2>
    <p>Submit a current resume and a letter of interest indicating which show(s) you're applying for. You may apply for more than one production in a single email.</p>
    <a class="btn btn-primary" href="mailto:jobs@tacomalittletheatre.com?subject=2026-2027%20Production%20Team%20Application">Email Your Application</a>
  </div>
</div>"""


c = pymysql.connect(host='127.0.0.1', port=10005, user='root', password='root', database='local')
cur = c.cursor()

# 1) Update /job-openings/ to be the listing
cur.execute("SELECT ID FROM wp_posts WHERE post_name='job-openings' AND post_status='publish' AND post_type='page' LIMIT 1")
parent_id = cur.fetchone()[0]
cur.execute("UPDATE wp_posts SET post_content=%s WHERE ID=%s", (LISTING_CONTENT, parent_id))
print(f"Updated /job-openings/ (id={parent_id}) — now a listing page")

# 2) Create or update the individual posting page as a child
SLUG = '2627-production-team'
cur.execute(
    "SELECT ID FROM wp_posts WHERE post_name=%s AND post_type='page' LIMIT 1",
    (SLUG,)
)
r = cur.fetchone()
if r:
    posting_id = r[0]
    cur.execute(
        "UPDATE wp_posts SET post_title=%s, post_content=%s, post_parent=%s, post_status='publish' WHERE ID=%s",
        ('Production Team Members for 2026–2027', POSTING_CONTENT, parent_id, posting_id)
    )
    print(f"Updated existing /job-openings/{SLUG}/ (id={posting_id})")
else:
    cur.execute("""
        INSERT INTO wp_posts (
            post_author, post_date, post_date_gmt, post_content, post_title,
            post_excerpt, post_status, comment_status, ping_status, post_password,
            post_name, to_ping, pinged, post_modified, post_modified_gmt,
            post_content_filtered, post_parent, guid, menu_order, post_type, post_mime_type, comment_count
        ) VALUES (
            1, NOW(), UTC_TIMESTAMP(), %s, %s,
            '', 'publish', 'closed', 'closed', '',
            %s, '', '', NOW(), UTC_TIMESTAMP(),
            '', %s, '', 0, 'page', '', 0
        )
    """, (POSTING_CONTENT, 'Production Team Members for 2026–2027', SLUG, parent_id))
    posting_id = cur.lastrowid
    cur.execute("UPDATE wp_posts SET guid=%s WHERE ID=%s",
                (f'http://tlt.local/?page_id={posting_id}', posting_id))
    print(f"Created new /job-openings/{SLUG}/ (id={posting_id})")

c.commit()
c.close()

# Flush rewrite rules so the new permalink works immediately
import subprocess
print("\nNote: visit /wp-admin/options-permalink.php once to flush rewrite cache if links 404")
