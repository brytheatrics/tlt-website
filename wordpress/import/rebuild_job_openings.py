"""
Replace /job-openings/ post_content with a clean, structured job-posting
layout. Removes the Squarespace summary-block cruft (date displayed 3x,
huge thumbnail dominating the page) and replaces with one proper listing
card.

Also drops the listing_category_slug meta so the "No posts found" hint
doesn't render below.
"""
import pymysql

# Structured page body — uses a scoped <style> block + a job-card layout
PAGE_CONTENT = """<style>
.jobs-intro { font-size: 1.05rem; line-height: 1.6; color: var(--color-text); margin: 0 0 2rem; }

.job-card { background: #fff; border: 1px solid var(--color-line); border-radius: 6px; overflow: hidden; margin-bottom: 2rem; }
.job-card__head { background: var(--color-soft); padding: 1.5rem 1.75rem; border-bottom: 1px solid var(--color-line); }
.job-card__eyebrow { display: inline-block; background: var(--color-accent); color: #fff; font-size: 0.7rem; letter-spacing: 0.1em; text-transform: uppercase; padding: 0.2rem 0.6rem; border-radius: 999px; margin-bottom: 0.5rem; }
.job-card__title { font-size: 1.4rem; margin: 0 0 0.35rem; color: var(--color-text); }
.job-card__meta { margin: 0; font-size: 0.88rem; color: var(--color-muted); }
.job-card__body { padding: 1.75rem; }
.job-card__body h3 { font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.08em; color: var(--color-accent); margin: 1.5rem 0 0.5rem; }
.job-card__body h3:first-child { margin-top: 0; }
.job-card__body p { line-height: 1.6; }

.job-positions { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 0.5rem; margin: 0; padding: 0; list-style: none; }
.job-positions li { background: var(--color-soft); padding: 0.65rem 1rem; border-radius: 4px; font-weight: 600; font-size: 0.95rem; }

.show-list { display: grid; gap: 1rem; margin: 1rem 0 0; }
.show-list .show { border-left: 3px solid var(--color-accent); padding: 0.5rem 0 0.5rem 1rem; }
.show-list .show-title { font-weight: 700; font-size: 1rem; margin: 0 0 0.15rem; }
.show-list .show-meta { font-size: 0.85rem; color: var(--color-muted); margin: 0 0 0.4rem; }
.show-list .show-blurb { font-size: 0.92rem; line-height: 1.5; margin: 0; }

.job-apply { background: linear-gradient(135deg, var(--color-soft) 0%, #fff 100%); border: 1px solid var(--color-line); border-radius: 6px; padding: 2rem; text-align: center; margin-top: 2rem; }
.job-apply h3 { margin: 0 0 0.5rem; color: var(--color-text); }
.job-apply p { margin: 0 0 1.25rem; color: var(--color-muted); font-size: 0.95rem; }
</style>

<p class="jobs-intro">Tacoma Little Theatre is always looking for talented people to join our team. Check below for current openings, and feel free to send a resume to <a href="mailto:jobs@tacomalittletheatre.com">jobs@tacomalittletheatre.com</a> any time — even if nothing's posted, we keep applications on file.</p>

<div class="job-card">
  <div class="job-card__head">
    <span class="job-card__eyebrow">Now Hiring</span>
    <h2 class="job-card__title">Production Team Members for 2026–2027</h2>
    <p class="job-card__meta">Posted June 11, 2024 · Letters reviewed on a rolling basis · Positions filled by May 31, 2026</p>
  </div>

  <div class="job-card__body">
    <p>Tacoma Little Theatre is seeking designers for our 2026–2027 season. We are seeking collaborative designers with a passion for the arts and their craft.</p>

    <h3>Available Positions</h3>
    <ul class="job-positions">
      <li>Stage Managers</li>
      <li>Resident Properties Designer</li>
      <li>Resident Sound Designer</li>
      <li>Show Costume Designer</li>
      <li>Scenic Artist Apprentice</li>
    </ul>

    <h3>Our Approach</h3>
    <p>At TLT, we believe theatre is a space for connection, reflection, and community storytelling. We are committed to producing work that is artistically bold, inclusive, and reflective of the diverse voices that make up our region. We strongly encourage designers of all backgrounds, identities, and experience levels to apply.</p>

    <h3>Compensation</h3>
    <p>Honorariums range from <strong>$600.00 – $1,400.00 per show</strong>.</p>

    <h3>The 2026–2027 Season</h3>
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
      <h3>How to Apply</h3>
      <p>Submit a current resume and a letter of interest indicating which show(s) you're applying for. You may apply for more than one production in a single email.</p>
      <a class="btn btn-primary" href="mailto:jobs@tacomalittletheatre.com?subject=2026-2027%20Production%20Team%20Application">Email Your Application</a>
    </div>
  </div>
</div>"""

c = pymysql.connect(host='127.0.0.1', port=10005, user='root', password='root', database='local')
cur = c.cursor()
cur.execute("SELECT ID FROM wp_posts WHERE post_name='job-openings' AND post_status='publish' AND post_type='page' LIMIT 1")
pid = cur.fetchone()[0]
cur.execute("UPDATE wp_posts SET post_content=%s WHERE ID=%s", (PAGE_CONTENT, pid))
# Remove listing_category_slug so template doesn't show "No posts found" hint
cur.execute("DELETE FROM wp_postmeta WHERE post_id=%s AND meta_key='listing_category_slug'", (pid,))
c.commit()
c.close()
print(f"Updated /job-openings/ (id={pid}), removed listing_category_slug")
