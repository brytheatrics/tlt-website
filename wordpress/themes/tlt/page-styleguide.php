<?php
/**
 * Template Name: Styleguide (internal)
 *
 * Renders every template / flex block / component in one page for quick
 * design review. Not linked from public nav — internal QA tool.
 *
 * To use: create a page with slug "styleguide" and assign this template.
 */
get_header(); ?>

<div class="container page-content">
  <header class="page-header">
    <h1>Theme Styleguide</h1>
    <p class="page-subtitle">Every component, on one page. Internal use only — not linked from nav.</p>
  </header>

  <article class="page-body">
    <!-- ============== Typography ============== -->
    <section class="styleguide-section">
      <h2>Typography</h2>
      <div class="styleguide-demo">
        <h1>Heading 1 — display</h1>
        <h2>Heading 2</h2>
        <h3>Heading 3</h3>
        <p>Body paragraph. The quick brown fox jumps over the lazy dog. <a href="#">This is a link</a> within a paragraph. <strong>Bold text</strong> and <em>italics</em> both work.</p>
        <p class="page-subtitle">Subtitle / page-subtitle italic muted.</p>
        <ul>
          <li>Unordered list item one</li>
          <li>Unordered list item two with <a href="#">a link</a></li>
          <li>Third item to demonstrate spacing</li>
        </ul>
      </div>
    </section>

    <!-- ============== Buttons ============== -->
    <section class="styleguide-section">
      <h2>Buttons</h2>
      <div class="styleguide-demo">
        <p>
          <a class="btn btn-primary" href="#">Primary Button</a>
          <a class="btn btn-outline" href="#" style="margin-left:0.5rem">Outline Button</a>
        </p>
        <p style="margin-top:1rem"><code>flex-block: button</code> · <code>flex-block: cta-row</code></p>
      </div>
    </section>

    <!-- ============== Pull quote ============== -->
    <section class="styleguide-section">
      <h2>Pull Quote</h2>
      <div class="styleguide-demo">
        <blockquote class="pull-quote">
          Tacoma Little Theatre has been telling stories that move us since 1918.
          <cite>— Press Release, 2025</cite>
        </blockquote>
        <p><code>flex-block: pull-quote</code></p>
      </div>
    </section>

    <!-- ============== Image floats ============== -->
    <section class="styleguide-section">
      <h2>Image Floats</h2>
      <div class="styleguide-demo">
        <figure class="float-right">
          <img src="https://placehold.co/360x240/272727/ffffff?text=Float+Right" alt="Demo image">
          <figcaption>A right-floated image with caption.</figcaption>
        </figure>
        <p>Body text wraps around a floated figure on desktop. On mobile, the float is dropped and the image stacks above the text. The quick brown fox jumps over the lazy dog. The quick brown fox jumps over the lazy dog. The quick brown fox jumps over the lazy dog. The quick brown fox jumps over the lazy dog.</p>
        <p>The quick brown fox jumps over the lazy dog. The quick brown fox jumps over the lazy dog. The quick brown fox jumps over the lazy dog.</p>
        <p><code>flex-block: figure</code> with <code>align: right|left</code></p>
      </div>
    </section>

    <!-- ============== Image + Text 2-column ============== -->
    <section class="styleguide-section">
      <h2>Image + Text (2-column)</h2>
      <div class="styleguide-demo">
        <div class="image-text">
          <div class="image-text__image">
            <img src="https://placehold.co/600x400/b8252f/ffffff?text=Image" alt="">
          </div>
          <div class="image-text__body">
            <h3>Section heading</h3>
            <p>Body text alongside the image. Stacks vertically on mobile.</p>
            <p><a href="#">Call-to-action link</a></p>
          </div>
        </div>
        <p><code>flex-block: image-text</code></p>
      </div>
    </section>

    <!-- ============== Full-bleed banner ============== -->
    <section class="styleguide-section">
      <h2>Full-bleed Banner</h2>
      <div class="styleguide-demo">
        <div class="full-bleed">
          <img src="https://placehold.co/1600x600/272727/ffffff?text=Full-Bleed+Banner" alt="">
        </div>
        <p><code>flex-block: full-bleed</code></p>
      </div>
    </section>

    <!-- ============== Section heading ============== -->
    <section class="styleguide-section">
      <h2>Section Heading</h2>
      <div class="styleguide-demo">
        <h2 class="section-heading">A Section Break</h2>
        <p>Used to divide long pages into named sections.</p>
        <p><code>flex-block: section-heading</code></p>
      </div>
    </section>

    <!-- ============== PDF link list ============== -->
    <section class="styleguide-section">
      <h2>PDF Link List</h2>
      <div class="styleguide-demo">
        <ul class="pdf-list">
          <li><a href="#">2024-2025 Season Brochure</a></li>
          <li><a href="#">Mail-in Season Ticket Order Form</a></li>
          <li><a href="#">TLT History (2025 Edition)</a></li>
        </ul>
        <p><code>flex-block: pdf-link-list</code></p>
      </div>
    </section>

    <!-- ============== Two-column callout ============== -->
    <section class="styleguide-section">
      <h2>Callout Pair</h2>
      <div class="styleguide-demo">
        <div class="callout-pair">
          <div class="callout-pair__col">
            <h3>Address</h3>
            <p>Tacoma Little Theatre<br>210 N "I" Street<br>Tacoma, WA 98403</p>
          </div>
          <div class="callout-pair__col">
            <h3>Box Office</h3>
            <p>Phone: (253) 272-2281<br>Tue-Fri 12-5pm<br>Sat 12-4pm</p>
          </div>
        </div>
        <p><code>flex-block: callout-pair</code></p>
      </div>
    </section>

    <!-- ============== Sponsor row ============== -->
    <section class="styleguide-section">
      <h2>Sponsor / Logo Row</h2>
      <div class="styleguide-demo">
        <div class="logo-row">
          <img src="https://placehold.co/120x60/cccccc/272727?text=Logo+1" alt="Sponsor 1">
          <img src="https://placehold.co/140x60/cccccc/272727?text=Logo+2" alt="Sponsor 2">
          <img src="https://placehold.co/120x60/cccccc/272727?text=Logo+3" alt="Sponsor 3">
          <img src="https://placehold.co/160x60/cccccc/272727?text=Logo+4" alt="Sponsor 4">
        </div>
        <p><code>flex-block: logo-row</code></p>
      </div>
    </section>

    <!-- ============== Audition row ============== -->
    <section class="styleguide-section">
      <h2>Audition Row (page-auditions.php)</h2>
      <div class="styleguide-demo">
        <div class="audition-row audition-row--open">
          <div class="audition-row__logo">
            <img src="https://placehold.co/140x140/b8252f/ffffff?text=Show+Logo" alt="">
          </div>
          <div class="audition-row__info">
            <h3 class="audition-row__title"><a href="#">SAMPLE SHOW</a></h3>
            <p class="audition-row__dates">September 21-23, 2025</p>
            <p class="audition-row__director">Directed by Kathy Pingel</p>
            <p class="audition-row__location">Tacoma Little Theatre · 210 N "I" Street, Tacoma WA</p>
            <p class="audition-row__cta">
              <a class="btn btn-primary" href="#">Schedule an Audition</a>
              <a class="btn btn-outline" href="#">Audition Packet (PDF)</a>
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- ============== Ticketing tier ============== -->
    <section class="styleguide-section">
      <h2>Ticketing Tiers (page-ticketing.php)</h2>
      <div class="styleguide-demo">
        <div class="ticketing-tiers">
          <div class="ticketing-tier">
            <h3>Adult</h3>
            <p class="price">$32</p>
            <p class="price-note">All performances</p>
            <p>Standard single-ticket adult admission.</p>
          </div>
          <div class="ticketing-tier">
            <h3>Senior / Military / Student</h3>
            <p class="price">$28</p>
            <p class="price-note">All performances</p>
            <p>$4 off the adult price.</p>
          </div>
          <div class="ticketing-tier">
            <h3>Pay What You Can</h3>
            <p class="price">PWYC</p>
            <p class="price-note">Preview Thursday only</p>
            <p>The first Thursday of every run.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- ============== Campaign CTA band ============== -->
    <section class="styleguide-section">
      <h2>Campaign CTA Band (page-campaign.php)</h2>
      <div class="styleguide-demo">
        <section class="campaign-cta-band">
          <h2>Become Part of the Campaign</h2>
          <p style="max-width:600px;margin:0 auto 1.5rem">Your contribution at any level helps TLT continue providing live theatre to the South Sound community.</p>
          <a class="btn btn-primary" href="#">Donate Now</a>
        </section>
      </div>
    </section>

    <!-- ============== Post list ============== -->
    <section class="styleguide-section">
      <h2>Post Listing (page-post-listing.php)</h2>
      <div class="styleguide-demo">
        <div class="post-list">
          <article class="post-list__item">
            <div class="post-list__thumb">
              <img src="https://placehold.co/120x90/cccccc/272727?text=Photo" alt="">
            </div>
            <div class="post-list__body">
              <p class="post-list__meta">May 1, 2026</p>
              <h3 class="post-list__title"><a href="#">Sample Press Release Title</a></h3>
              <p class="post-list__excerpt">Excerpt text describing the article in a sentence or two. Probably around 24 words is the sweet spot.</p>
              <a class="post-list__more" href="#">Read more →</a>
            </div>
          </article>
          <article class="post-list__item">
            <div class="post-list__thumb">
              <img src="https://placehold.co/120x90/cccccc/272727?text=Photo" alt="">
            </div>
            <div class="post-list__body">
              <p class="post-list__meta">April 15, 2026</p>
              <h3 class="post-list__title"><a href="#">Another Sample Post</a></h3>
              <p class="post-list__excerpt">Excerpt text describing the article in a sentence or two.</p>
              <a class="post-list__more" href="#">Read more →</a>
            </div>
          </article>
        </div>
      </div>
    </section>

    <!-- ============== Search results ============== -->
    <section class="styleguide-section">
      <h2>Search Results (search.php)</h2>
      <div class="styleguide-demo">
        <section class="search-results__group">
          <h2>Shows (2)</h2>
          <div class="search-results__item">
            <span class="post-type-label">Show</span>
            <h3><a href="#">THE OUTSIDER</a></h3>
            <p style="margin:0 0 0.25rem;color:var(--color-muted)">Aug 28 – Sep 13, 2026 · Directed by TBD</p>
            <p style="margin:0;color:var(--color-muted)">A political comedy by Paul Slade Smith.</p>
          </div>
        </section>
      </div>
    </section>

    <!-- ============== Designed Page preview ============== -->
    <section class="styleguide-section">
      <h2>Designed Page Template</h2>
      <p>Image + headline + body + CTAs. Renders full-bleed (preview here is just the content area).</p>
      <div class="styleguide-demo">
        <h1 class="designed-page__headline" style="font-size:2rem">THE GIFT OF TLT</h1>
        <p class="designed-page__subhead">Give the gift of live theatre this holiday season.</p>
        <p>Gift cards are available in any amount and never expire. Use them for tickets, classes, or our merch shop.</p>
        <div class="designed-page__ctas" style="margin:1.5rem 0">
          <a class="btn btn-primary" href="#">Buy Gift Card</a>
          <a class="btn btn-outline" href="#">Shop Merch</a>
        </div>
      </div>
    </section>

    <!-- ============== 404 preview ============== -->
    <section class="styleguide-section">
      <h2>404 page (404.php)</h2>
      <div class="styleguide-demo" style="text-align:center;padding:2rem">
        <h1 style="font-size:5rem;color:var(--color-accent);margin:0;line-height:1">404</h1>
        <h2 style="margin-top:1rem">This page took an early curtain.</h2>
        <p style="color:var(--color-muted)">The page you're looking for might have moved, been renamed, or never existed at all.</p>
      </div>
    </section>

    <p style="margin-top:4rem;padding:1.5rem;background:var(--color-soft);border-left:4px solid var(--color-accent)">
      <strong>Note:</strong> This page is for internal review only. Don't link to it from the public navigation.
      Slug: <code>/styleguide/</code>
    </p>
  </article>
</div>

<?php get_footer(); ?>
