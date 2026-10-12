<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$pageTitle = 'Nerasky Careers';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Apply for a Nerasky marketplace operations job. Review the requirements, choose a role, and submit a complete employment application.">
  <title><?= e($pageTitle) ?> · Nerasky</title>
  <link rel="icon" type="image/png" href="<?= e(base_url('assets/nerasky-logo.png')) ?>">
  <link rel="apple-touch-icon" href="<?= e(base_url('assets/nerasky-logo.png')) ?>">
  <link rel="stylesheet" href="<?= e(hiring_url('assets/css/style.css')) ?>">
  <style>
    .careers-page { background: var(--bg); min-height: 100vh; }
    .careers-nav { position:sticky; top:0; z-index:20; background:rgb(15 22 47 / .96); color:#fff; border-bottom:1px solid var(--dark-line); backdrop-filter:blur(14px); }
    .careers-nav-inner { min-height: 68px; display:flex; align-items:center; justify-content:space-between; gap:1rem; }
    .careers-brand { color:#fff; display:inline-flex; align-items:center; gap:.55rem; font-weight:800; font-size:1.1rem; }
    .careers-hero { padding:5.5rem 0 4.5rem; color:#fff; background:radial-gradient(circle at 80% 10%,#3730a3 0,#151e42 35%,var(--dark) 75%); }
    .careers-hero-grid { display:grid; grid-template-columns:minmax(0,1.1fr) minmax(280px,.9fr); align-items:center; gap:3rem; }
    .careers-hero h1 { max-width:720px; color:#fff; font-size:clamp(2.4rem,6vw,4.4rem); }
    .careers-hero p { max-width:650px; color:#c9d0ec; font-size:1.1rem; }
    .careers-hero-card { padding:1.4rem; border:1px solid #3c4775; border-radius:var(--r-lg); background:rgb(21 30 66 / .75); box-shadow:var(--sh-lg); }
    .careers-hero-card strong { display:block; color:#fff; font-size:1.15rem; }
    .careers-hero-card span { display:block; margin-top:.35rem; color:#b6c0e0; font-size:var(--fs-sm); }
    .careers-section { padding:4.5rem 0; }
    .careers-section-head { max-width:700px; margin:0 auto 2.2rem; text-align:center; }
    .careers-section-head p { color:var(--muted); }
    .careers-role-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:1rem; }
    .careers-role { padding:1.4rem; background:#fff; border:1px solid var(--line); border-radius:var(--r); box-shadow:var(--sh-sm); }
    @keyframes careers-rise { from { opacity:0; transform:translateY(16px); } to { opacity:1; transform:translateY(0); } }
    @keyframes careers-glow { 0%,100% { box-shadow:var(--sh-lg); } 50% { box-shadow:0 24px 60px rgb(99 102 241 / .22); } }
    @keyframes careers-cta-shimmer { from { transform:translateX(-120%) rotate(12deg); } to { transform:translateX(230%) rotate(12deg); } }
    .careers-hero-grid > * { opacity:0; animation:careers-rise .65s ease forwards; }
    .careers-hero-grid > aside { animation-delay:.15s; }
    .careers-role, .careers-step, .careers-cta { opacity:0; animation:careers-rise .55s ease forwards; }
    .careers-role:nth-child(2) { animation-delay:.08s; }
    .careers-role:nth-child(3) { animation-delay:.16s; }
    .careers-role:hover { transform:translateY(-5px); border-color:#c7d2fe; box-shadow:0 14px 30px rgb(30 37 82 / .1); }
    .careers-role, .careers-cta { transition:transform .22s ease, border-color .22s ease, box-shadow .22s ease; }
    .careers-cta { animation-delay:.12s; }
    .careers-cta { position:relative; isolation:isolate; overflow:hidden; }
    .careers-cta::before { content:""; position:absolute; inset:-45% auto -45% -35%; width:26%; pointer-events:none; background:linear-gradient(90deg,transparent,rgb(255 255 255 / .2),transparent); transform:translateX(-120%) rotate(12deg); animation:careers-cta-shimmer 5.5s ease-in-out 1.1s infinite; }
    .careers-cta > * { position:relative; z-index:1; }
    .careers-cta:hover { transform:translateY(-3px); box-shadow:0 24px 60px rgb(99 102 241 / .22); }
    .careers-role h3 { margin-bottom:.35rem; }
    .careers-role p { min-height:3.2rem; color:var(--muted); font-size:var(--fs-sm); }
    .careers-role ul { margin:1rem 0 0; padding-left:1.2rem; color:var(--ink-700); font-size:var(--fs-sm); }
    .careers-role li + li { margin-top:.35rem; }
    .careers-role-tag { display:inline-flex; margin-bottom:1rem; padding:.3rem .6rem; border-radius:var(--r-pill); color:var(--brand-700); background:#eef2ff; font-size:var(--fs-xs); font-weight:800; }
    .careers-process { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:1rem; }
    .careers-step { padding:1rem; border-top:3px solid var(--brand-500); }
    .careers-step b { display:block; margin-bottom:.35rem; }
    .careers-step span { color:var(--muted); font-size:var(--fs-sm); }
    .careers-cta { margin-top:2.5rem; padding:2rem; border-radius:var(--r-lg); color:#fff; background:var(--grad-brand); text-align:center; }
    .careers-cta h2 { color:#fff; }
    .careers-cta p { color:#e0e7ff; }
    .careers-cta .btn-light { color:var(--brand-700); background:#fff; border-color:#fff; }
    @media (max-width:800px) {
      .careers-hero-grid,.careers-role-grid { grid-template-columns:1fr; }
      .careers-hero { padding:4rem 0 3rem; }
      .careers-process { grid-template-columns:repeat(2,minmax(0,1fr)); }
    }
    @media (max-width:520px) {
      .careers-nav-inner { min-height:60px; }
      .careers-nav .btn-outline { display:none; }
      .careers-section { padding:3.2rem 0; }
      .careers-process { grid-template-columns:1fr; }
    }
    @media (prefers-reduced-motion: reduce) {
      .careers-hero-grid > *, .careers-role, .careers-step, .careers-cta { animation:none; opacity:1; transform:none; }
      .careers-role, .careers-cta { transition:none; }
      .careers-cta::before { animation:none; }
    }
  </style>
</head>
<body class="careers-page">
  <header class="careers-nav">
    <div class="container careers-nav-inner">
      <a class="careers-brand" href="<?= e(hiring_url('careers.php')) ?>"><?= logo_svg(30) ?> Nerasky Careers</a>
      <nav class="flex" aria-label="Recruitment navigation">
        <a class="btn btn-outline btn-sm" style="color:#fff;border-color:#5e6a9b" href="<?= e(market_url('')) ?>">Visit marketplace</a>
        <a class="btn btn-outline btn-sm" style="color:#fff;border-color:#5e6a9b" href="<?= e(hiring_url('status.php')) ?>">Check status</a>
        <a class="btn btn-primary btn-sm" href="<?= e(hiring_url('apply.php')) ?>">Apply now</a>
      </nav>
    </div>
  </header>

  <main>
    <section class="careers-hero">
      <div class="container careers-hero-grid">
        <div>
          <span class="eyebrow" style="color:#a5b4fc">Nerasky Careers · Separate hiring website</span>
          <h1>Apply for work that keeps a marketplace trustworthy.</h1>
          <p>This careers site is separate from the Nerasky marketplace. Apply for a focused operations job, submit your employment documents, and receive limited work access only after a manual hiring and verification review.</p>
          <div class="flex" style="margin-top:1.5rem;flex-wrap:wrap">
            <a class="btn btn-primary btn-lg" href="<?= e(hiring_url('apply.php')) ?>">View open roles</a>
            <a class="btn btn-ghost btn-lg" style="color:#fff" href="#roles">See the roles</a>
          </div>
        </div>
        <aside class="careers-hero-card">
          <strong>Built around least privilege</strong>
          <span>Every worker starts with only the tools needed for their approved role. Super admins review access and can remove it.</span>
          <hr style="border-color:#3c4775;margin:1.2rem 0">
          <strong>Who can apply?</strong>
          <span>Adults aged 20+, with a reachable email, relevant experience, identity proof, and a complete application.</span>
        </aside>
      </div>
    </section>

    <section class="careers-section" id="roles">
      <div class="container">
        <div class="careers-section-head">
          <span class="eyebrow">Choose your lane</span>
          <h2>Open operations roles</h2>
          <p>Applicants must be 20 or older. We recommend starting with a limited operating role; Super admin access is reserved for the owner or a highly trusted lead.</p>
        </div>
        <div class="careers-role-grid">
          <article class="careers-role">
            <span class="careers-role-tag">Trust and safety</span>
            <h3>Moderator</h3>
            <p>Protects the community and keeps listings and payment proofs compliant.</p>
            <ul>
              <li>Review member accounts and consent</li>
              <li>Review services and reported activity</li>
              <li>Recommended background: support, moderation, or dispute handling</li>
              <li>No withdrawals, cash-in approval, or staff management</li>
            </ul>
          </article>
          <article class="careers-role">
            <span class="careers-role-tag">Money operations</span>
            <h3>Finance admin</h3>
            <p>Tracks internal balances and handles the manual cash movement workflow.</p>
            <ul>
              <li>Recommended background: bookkeeping or payment operations</li>
              <li>Review payments, cash-ins, withdrawals, and reconciliation</li>
              <li>Handle confidential financial records carefully</li>
              <li>No account, service, or staff moderation</li>
            </ul>
          </article>
          <article class="careers-role">
            <span class="careers-role-tag">Leadership only</span>
            <h3>Super admin</h3>
            <p>Owns policy, fees, settings, access decisions, and operational oversight.</p>
            <ul>
              <li>Full access across marketplace operations</li>
              <li>Approve, change, or remove staff roles</li>
              <li>Control platform fees and settings</li>
              <li>Reserved for the platform owner or trusted lead</li>
            </ul>
          </article>
        </div>
      </div>
    </section>

    <section class="careers-section" id="requirements">
      <div class="container">
        <div class="careers-section-head">
          <span class="eyebrow">Before you apply</span>
          <h2>Employment application requirements</h2>
          <p>Prepare these items before starting. Incomplete applications cannot move to hiring review.</p>
        </div>
        <div class="careers-role-grid">
          <article class="careers-role">
            <span class="careers-role-tag">Eligibility</span>
            <h3>Basic requirements</h3>
            <ul>
              <li>Be at least 20 years old</li>
              <li>Provide a reachable email, phone number, and location</li>
              <li>Choose one role and state your availability</li>
              <li>Be ready to complete onboarding if selected</li>
            </ul>
          </article>
          <article class="careers-role">
            <span class="careers-role-tag">Documents</span>
            <h3>Application materials</h3>
            <ul>
              <li>Current resume or CV</li>
              <li>Education, certifications, and work history</li>
              <li>Role-specific experience and cover letter</li>
              <li>One professional or academic reference</li>
              <li>Identity or verification proof</li>
            </ul>
          </article>
          <article class="careers-role">
            <span class="careers-role-tag">Review standard</span>
            <h3>What happens next</h3>
            <ul>
              <li>Super admin checks completeness and identity</li>
              <li>Experience is assessed against the selected role</li>
              <li>References or an interview may be requested</li>
              <li>Approval creates only the assigned role's access</li>
            </ul>
          </article>
        </div>
      </div>
    </section>

    <section class="careers-section" style="background:#fff">
      <div class="container">
        <div class="careers-section-head">
          <span class="eyebrow">A clear path</span>
          <h2>How joining works</h2>
        </div>
        <div class="careers-process">
          <div class="careers-step"><b>1. Meet the requirements</b><span>Be 20 or older and provide a reachable email and identity proof.</span></div>
          <div class="careers-step"><b>2. Apply for one role</b><span>Tell us about your experience and upload identity proof.</span></div>
          <div class="careers-step"><b>3. Super admin review</b><span>We check identity, account status, experience, and role fit.</span></div>
          <div class="careers-step"><b>4. Start limited</b><span>Approved access is limited to the role you were given.</span></div>
        </div>
        <div class="careers-cta">
          <h2>Ready to help Nerasky grow safely?</h2>
          <p>Applications are reviewed manually. A Super admin creates access only after approval. Never send your password, OTP, PIN, or private payment credentials.</p>
          <a class="btn btn-light btn-lg" href="<?= e(hiring_url('apply.php')) ?>">Start your application</a>
        </div>
      </div>
    </section>
  </main>
</body>
</html>
