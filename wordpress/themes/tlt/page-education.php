<?php
/**
 * Template Name: Education
 *
 * /education/ — currently happening + program directory + philosophy + scholarships + policies
 */
get_header(); ?>

<style>
  /* Outer wrappers can be full-width; inner .edu-inner constrains content */
  .edu-page { padding: 0; margin: 0; }
  .edu-inner { max-width: 1100px; margin: 0 auto; padding: 0 var(--pad); }
  .edu-soft-band { background: var(--color-soft); }
  .edu-hero {
    text-align: center;
    padding: 4rem var(--pad) 2rem;
  }
  .edu-hero h1 { margin-bottom: 1rem; }
  .edu-hero .lead { max-width: 700px; margin: 0 auto 1.5rem; font-size: 1.1rem; line-height: 1.6; color: var(--color-text); }

  .edu-section { padding: 3rem 0; }
  .edu-section h2 { color: var(--color-accent); text-align: center; margin-bottom: 0.5rem; }
  .edu-section .lede { text-align: center; color: var(--color-muted); max-width: 720px; margin: 0 auto 2.5rem; }

  /* Currently happening cards (matches homepage feature-row image style) */
  .edu-current-grid {
    display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;
    max-width: 880px; margin: 0 auto;
  }
  @media (max-width: 700px) { .edu-current-grid { grid-template-columns: 1fr; } }
  .edu-current-card {
    background: #fff; border: 1px solid var(--color-line); border-radius: 4px;
    overflow: hidden; color: var(--color-text);
    display: flex; flex-direction: column;
    transition: transform 0.15s, box-shadow 0.15s;
  }
  .edu-current-card:hover { transform: translateY(-3px); box-shadow: 0 10px 24px rgba(0,0,0,0.08); text-decoration: none; }
  .edu-current-card .img-wrap { aspect-ratio: 16/10; background: var(--color-soft); }
  .edu-current-card .img-wrap img { width: 100%; height: 100%; object-fit: contain; }
  .edu-current-card .body { padding: 1.25rem; }
  .edu-current-card h3 { margin: 0 0 0.4rem; font-size: 1.1rem; }
  .edu-current-card p { color: var(--color-muted); font-size: 0.92rem; margin: 0; line-height: 1.5; }

  /* Programs directory (no images, just text in 2 columns) */
  .programs-dir {
    display: grid; grid-template-columns: 1fr 1fr; gap: 2.5rem 3rem;
  }
  @media (max-width: 800px) { .programs-dir { grid-template-columns: 1fr; gap: 2rem; } }
  .program-entry h3 {
    color: var(--color-accent);
    font-size: 1.05rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin: 0 0 0.6rem;
    display: flex; align-items: center; gap: 0.5rem;
  }
  .program-entry h3 a {
    display: inline-flex; align-items: center;
    color: inherit; text-decoration: none;
  }
  .program-entry h3 a:hover { color: var(--color-accent-dark); text-decoration: none; }
  .program-entry h3 .link-icon {
    width: 16px; height: 16px;
    opacity: 0.6;
    transition: opacity 0.15s, transform 0.15s;
  }
  .program-entry h3 a:hover .link-icon { opacity: 1; transform: translate(2px, -2px); }
  .program-entry p { line-height: 1.6; color: var(--color-text); margin: 0; font-size: 0.95rem; }

  .philosophy { background: var(--color-soft); padding: 4rem var(--pad); }
  .philosophy .inner { max-width: 800px; margin: 0 auto; text-align: center; }
  .philosophy h2 { color: var(--color-accent); }
  .philosophy p { line-height: 1.7; margin: 1rem 0; }

  .scholarship-section {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 3rem;
    align-items: center;
    padding: 4rem 0;
  }
  @media (max-width: 800px) { .scholarship-section { grid-template-columns: 1fr; } }
  .scholarship-section img { aspect-ratio: 4/3; object-fit: cover; border-radius: 4px; }

  .policies { padding: 3rem 0; }
  .policies-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 2rem;
    margin-top: 2rem;
  }
  .policies-grid > div {
    padding: 1.25rem;
    background: var(--color-soft);
    border-radius: 4px;
  }
  .policies-grid h3 {
    color: var(--color-accent);
    font-size: 0.95rem;
    margin-bottom: 0.5rem;
  }
  .policies-grid p { font-size: 0.9rem; line-height: 1.6; margin: 0; }
</style>

<div class="edu-page">

  <!-- Hero (full-width soft band) -->
  <div class="edu-soft-band">
    <div class="edu-inner edu-hero">
      <h1>Education at Tacoma Little Theatre</h1>
      <p class="lead">TLT's Theatre classes help students of all ages to grow to their full potential as performers and more importantly as people. TLT's vision is to bring together students in our community to learn about and practice the skills and techniques of performance art, building life skills in the process.</p>
      <p>
        <a href="https://tlt.ludus.com/index.php?sections=classes" target="_blank" rel="noopener" class="btn btn-primary">Camp &amp; Class Registration</a>
      </p>
    </div>
  </div>

  <!-- Why Theatre Education? (full-width soft band, sits right under hero) -->
  <div class="philosophy">
    <div class="inner">
      <h2>Why Theatre Education?</h2>
      <p>While TLT prides itself in educating students with extensive knowledge and powerful skills they need as performers, our courses and camps are also created to build confidence, team work, collaboration, self esteem, communication, innovative thinking and much, much more!</p>
      <p>Our classes are designed to enhance curriculums of study for both students attending public or private schools and those who are homeschooled, by providing opportunities for art to be part of the daily lives of our students.</p>
      <p>In addition to skill building courses, TLT also offers exciting avenues for performance through our drama camps and stage productions.</p>
      <p>Our instructors are trained theatre artists and bring a variety of experiences within the industry of theatre. Additionally, all instructors provide thorough curriculums for outstanding learning potential and must pass an extensive background check required by TLT.</p>
      <p>TLT is excited to further our mission of enriching our community with quality, live theater experiences. Come join the fun!</p>
    </div>
  </div>

  <!-- Currently Happening (white) — driven by Promotions with location=education -->
  <?php
  $edu_promos = function_exists( 'tlt_get_active_promotions' )
      ? tlt_get_active_promotions( 'education' )
      : [];
  if ( $edu_promos ) :
  ?>
  <div class="edu-inner edu-section">
    <h2>Currently Happening</h2>
    <p class="lede">What's running right now. Click for details and registration.</p>
    <div class="edu-current-grid">
      <?php foreach ( $edu_promos as $i => $p ) tlt_render_promo( $p, $i, 'edu-card' ); ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Our Programs (full-width soft band) -->
  <div class="edu-soft-band">
   <div class="edu-inner edu-section">
    <h2>Our Programs</h2>
    <p class="lede">A full menu of theatre education for every age and interest.</p>
    <div class="programs-dir">

      <div class="program-entry">
        <h3>After-School @ TLT</h3>
        <p>These wonderful six-week sessions are held twice weekly (Mondays &amp; Wednesdays or Tuesdays &amp; Thursdays) from 4:00pm-6:00pm. We'll offer classes in the fall, winter, and spring, and each class will culminate in a fully staged play or musical production for friends and family to come enjoy. Students can enroll in one or both sessions. Classes are open to students in grades 1-8.</p>
      </div>

      <div class="program-entry">
        <h3>Homeschool @ TLT</h3>
        <p>Modeled on our after school program, this class is designed for the homeschool families in our community. These classes meet twice weekly and take place over a six-week period, culminating in a fully staged production for friends and family to come and enjoy. Tacoma Little Theatre is a certified Community Based Instructor (CBI). Classes are open to students in grades 1-8.</p>
      </div>

      <div class="program-entry">
        <h3>Improv</h3>
        <p>Learn the skills necessary to think on your feet and say &ldquo;Yes, and&hellip;&rdquo;. These tools help young and adult actors with their onstage skills, as well as off stage in school and work. Classes vary from evening to weekend times; please be sure to check our website for the latest details. Classes are available for 12-18-year-olds and for adults.</p>
      </div>

      <div class="program-entry">
        <h3>Voice Lessons</h3>
        <p>TLT is not currently offering voice lessons. If you are interested, feel free to reach out to us and we may be able to put you in contact with a teaching artist in our community. You can email us at <a href="mailto:education@tacomalittletheatre.com">education@tacomalittletheatre.com</a>.</p>
      </div>

      <div class="program-entry">
        <h3>Dance</h3>
        <p>Getting ready for an audition or just wanting to build some skills? Come join us for these fast-paced classes. Classes are offered at a variety of levels and skill sets ranging from the basics of ballet, jazz and musical theater, to more intensive and specific styles of musical theater. Students will spend six weeks focused on a specific style or skill set. Classes are available for all ages.</p>
      </div>

      <div class="program-entry">
        <h3>Adult Classes @ TLT</h3>
        <p>TLT's education program includes our adult actors. These programs start for students 18 and up, they include classes like intro to acting, advanced acting, improv classes, and dance classes. We offer classes and workshops periodically. Check in to see what's available online or email us at <a href="mailto:education@tacomalittletheatre.com">education@tacomalittletheatre.com</a>.</p>
      </div>

      <div class="program-entry">
        <h3><a href="/clubtlt/">Club TLT
          <svg class="link-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17L17 7M7 7h10v10"/></svg>
        </a></h3>
        <p>A unique club that offers year-round education dedicated to middle and high school students, ages 13-19. Students will have opportunities to focus on their audition skills to help prepare for school and community auditions they are working on! Students will also have opportunities to learn more about writing and directing their first play. Students can work their improv skills to help build confidence to mold into a variety of characters for their theatrical endeavors. Workshops and master classes in performance, direction, design, stage management, and play writing have all been offered in the past. Special events and activities offer students opportunities to attend performances and volunteer at TLT. Students will work together to create performances specially suited for teenagers.</p>
      </div>

      <div class="program-entry">
        <h3>Winter Break Camp</h3>
        <p>Join us during winter break for a fun and exciting camp experience! Dates change year to year to avoid Holidays. This is a great chance for students to spend some time onstage preparing an exciting musical before jumping back into the school year! Camp is most Mondays-Thursdays 9:00am-4:00pm, with one to two performances the last weekend of camp. Open for grades 1-12.</p>
      </div>

      <div class="program-entry">
        <h3>Spring Break Camp</h3>
        <p>Join TLT for this lightning fast theater experience! Students will work hard to put together a fully staged musical in just one week! If you plan to stay home for spring break, come join us for this wonderful program! Monday-Friday 9:00am-4:00pm, performs on the weekend. In 2023, we will offer a skills break camp &ndash; featuring all of the skill building, learning, and fun of a theater workshop without the pressure of putting on a play. This immersive experience would be appropriate for students who are interested in exploring theater arts, or deepening their skills on stage. Open for grades 1-12.</p>
      </div>

      <div class="program-entry">
        <h3>Summer Break Camps</h3>
        <p>We have two summer break camps each year. Camps are four weeks long, and provide an in-depth experience of putting a fully staged musical together, learning about the technical aspects of a production, and learning new theater techniques. Camp meets for four weeks, Monday-Friday 9:00am-4:00pm.</p>
      </div>

      <div class="program-entry">
        <h3><a href="/students-on-stage/">Students On Stage
          <svg class="link-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17L17 7M7 7h10v10"/></svg>
        </a></h3>
        <p>Our outreach program is designed to bring the entire educational experience to your school! Our programs range from a variety of musical and non-musical options, all designed to bring the importance and value of art into students' learning. Please contact us for more details by emailing <a href="mailto:education@tacomalittletheatre.com">education@tacomalittletheatre.com</a>.</p>
      </div>

    </div>
   </div>
  </div>

  <section class="edu-inner scholarship-section">
    <div>
      <h2 style="color:var(--color-accent)">Scholarships</h2>
      <p>The following button will direct you to our online scholarship application. This application is in draft form, and is being used for beta testing. If you choose to submit an application with us and do not hear back in just a couple of days, please send us an email at <a href="mailto:education@tacomalittletheatre.com">education@tacomalittletheatre.com</a>, to make sure your application has been received and is being processed. We may reach out for additional information as well. Thank you for your patience, as we seek to make the application process easy and accessible!</p>
      <p style="margin-top:1.5rem">
        <a href="https://docs.google.com/forms/d/e/1FAIpQLSdEwJCTMI4GGxAXoBhfZhi1GrNk0DP5pFTkFFJd1qKc8TciDA/viewform?usp=header" target="_blank" rel="noopener" class="btn btn-primary">Scholarship Application</a>
      </p>
    </div>
    <img src="<?php echo get_template_directory_uri(); ?>/assets/edu-clubtlt.jpg" alt="">
  </section>

  <section class="edu-inner policies">
    <h2 style="color:var(--color-accent);text-align:center;margin-bottom:0">Registration &amp; Program Policies</h2>
    <p style="text-align:center;color:var(--color-muted);margin-bottom:2rem">A quick look at what to expect when enrolling.</p>
    <div class="policies-grid">
      <div>
        <h3>Registration</h3>
        <p>All registrations are processed in the order they are received. Only payment in full or a payment of the $50 registration fee will secure your spot. Registrations will be accepted until the class is full or until the end of the first week of class, whichever comes first. Once your registration is processed, you will receive confirmation and further class details via e-mail.</p>
      </div>
      <div>
        <h3>Payment</h3>
        <p>When you register for classes or camps online, you will be prompted to pay the full tuition amount at that time. If you would prefer to set up a payment plan, we can arrange that! Just contact us at <a href="mailto:education@tacomalittletheatre.com">education@tacomalittletheatre.com</a>. The minimum, nonrefundable fee for enrollment is $50. Some scholarship funding is available! Click the button above to apply now. A $35.00 service charge will be attached to any check returned by the bank due to insufficient funds.</p>
      </div>
      <div>
        <h3>Cancellations</h3>
        <p>If you cancel or withdraw from the class more than 14 days prior to the class start, TLT can refund tuition minus a $50.00 cancellation fee. If you cancel within 14 days of the class/camp start date TLT can refund up to half of the tuition. No refunds will be given after the first class or day of camp. In camps or classes where casting is done, no refunds will be offered after casting is complete. We reserve the right to cancel a class if enrollment is insufficient. In this instance, any tuition paid will be refunded in full.</p>
      </div>
      <div>
        <h3>Performances</h3>
        <p>Please check all performance dates and times for conflicts before enrolling. Typically, actors are called to the theater one to one and a half hours before the performance. Details will be provided for the individual camp or class.</p>
      </div>
      <div>
        <h3>Attendance</h3>
        <p>Please check all rehearsal and class dates/times for conflicts before enrolling. Theater is a team based activity, and absences can impact the whole group. We are often able to work around absences with enough advanced notice.</p>
      </div>
      <div>
        <h3>Participation</h3>
        <p>When students join our program, they will be expected to participate in a safe manner, demonstrating respect for others and for property. If a student violates any rules or creates an unsafe situation for staff or other students, we reserve the right to remove the student from the class. Tacoma Little Theatre is not responsible for any lost, damaged, or stolen personal belongings. All dates, times and programming are subject to change.</p>
      </div>
    </div>
  </section>

</div>

<?php get_footer(); ?>
