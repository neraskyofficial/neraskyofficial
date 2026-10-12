<?php
declare(strict_types=1);
require __DIR__ . '/includes/auth.php';

$pageTitle = 'Terms of service';
$currentNav = '';
require __DIR__ . '/includes/header.php';
?>
<section class="privacy-hero">
  <div class="container privacy-hero-grid">
    <div class="privacy-hero-copy reveal">
      <span class="eyebrow">Trust &amp; safety</span>
      <h1>A thoughtful space to learn, earn, and grow.</h1>
      <p class="privacy-lead">These plain-language terms set expectations for students, families, and sellers using Nerasky. Please read them before creating an account or placing an order.</p>
      <div class="privacy-updated"><span class="privacy-status-dot"></span><span>Built around student safety</span><span class="privacy-divider">·</span><span>Clear rules, human review</span></div>
    </div>
    <div class="privacy-hero-card reveal reveal-delay-2">
      <div class="privacy-lock"><?= ui_icon('shield', 28) ?></div>
      <strong>Trust is a shared responsibility.</strong>
      <p>Our community works best when every member treats others fairly and follows the same safety-first standards.</p>
      <div class="privacy-card-line"><span>Student-first eligibility</span><b>01</b></div>
      <div class="privacy-card-line"><span>Verified accounts</span><b>02</b></div>
      <div class="privacy-card-line"><span>Fair, reviewed payments</span><b>03</b></div>
    </div>
  </div>
</section>

<section class="section privacy-content">
  <div class="container privacy-layout">
    <aside class="privacy-sidebar reveal">
      <span class="privacy-sidebar-label">On this page</span>
      <a href="#eligibility">Eligibility &amp; student safety</a>
      <a href="#accounts">Accounts &amp; verification</a>
      <a href="#payments">Payments &amp; fees</a>
      <a href="#data-privacy">Data privacy</a>
      <a href="#conduct">Prohibited conduct</a>
      <a href="#notice">Important notice</a>
      <div class="privacy-sidebar-note"><span><?= ui_icon('heart', 16) ?></span><p>Be kind, keep transactions honest, and help make Nerasky safer for students.</p></div>
    </aside>

    <article class="legal-page privacy-article" data-stagger="90">
      <div class="privacy-section reveal" id="eligibility">
        <span class="privacy-number">01</span>
        <div>
          <h2>Eligibility &amp; student safety</h2>
          <ul class="terms-list">
            <li>Nerasky complies with <b>RA 9231 (Anti-Child Labor Law)</b>. <b>Users below 15 are not permitted to transact</b> — sign-up is hard-blocked by date of birth.</li>
            <li>Students aged <b>15–17</b> may join only with a signed parental consent form, reviewed and approved by an admin before they can sell.</li>
            <li>Consistent with RA 9231, minors may work at most <b>8 hours a day</b> and <b>40 hours a week</b>, and may not work between <b>10:00 PM and 6:00 AM</b>. Nerasky does not schedule or monitor work hours, but students and parents are expected to observe these limits.</li>
          </ul>
        </div>
      </div>

      <div class="privacy-section reveal" id="accounts">
        <span class="privacy-number">02</span>
        <div>
          <h2>Accounts &amp; verification</h2>
          <ul class="terms-list">
            <li>Every account is manually verified by a human moderator before it can buy or sell.</li>
            <li>Ages 15–17 verify with a <b>school ID</b>; 18+ may use a school or government ID.</li>
            <li>One person, one account. Providing false identity or age information is grounds for permanent removal.</li>
          </ul>
        </div>
      </div>

      <div class="privacy-section reveal" id="payments">
        <span class="privacy-number">03</span>
        <div>
          <h2>Payments &amp; fees</h2>
          <ul class="terms-list">
            <li>PayMongo QR Ph is available for order payments, wallet top-ups, and Nerasky Pro purchases; those payments are applied after server-side PayMongo confirmation. Where configured, GCash is an optional manual payment method: a moderator verifies the actual transaction before starting an order or crediting a wallet. Seller withdrawals are reviewed and processed manually.</li>
            <li>Nerasky charges a flat <b>5% commission</b> on completed orders. ★ Pro members receive a 3% rate on their first <?= (int) PREMIUM_DAILY_DISCOUNTED_ORDERS ?> orders each day; the rate resets every day.</li>
            <li>Wallet credits and payouts reflect verified transactions and completed orders. Payment availability depends on the configured and authorized receiving account.</li>
          </ul>
        </div>
      </div>

      <div class="privacy-section reveal" id="data-privacy">
        <span class="privacy-number">04</span>
        <div>
          <h2>Data privacy — RA 10173, the Data Privacy Act</h2>
          <ul class="terms-list">
            <li>Verification documents (school/government IDs and parental consent forms) are used for manual eligibility and identity review. They are not shown to other marketplace users; authorized moderators may access them, and the selected hosting provider stores the uploaded files. Supabase provides sign-in and email confirmation. See the Privacy Policy for more detail.</li>
            <li>You may request deletion of documents or account data through the authenticated Support page. The operator should remove documents that are no longer needed, subject to records that must be retained for payment, safety, or legal purposes.</li>
            <li>The site operator selects the hosting and authentication providers used to operate the marketplace. PayMongo may process checkout payments, and GCash is used separately when a buyer selects manual GCash payment. Nerasky does not sell personal information or use tracking pixels.</li>
            <li>Chat messages stay inside the platform and are visible only to the students in the conversation and platform moderators acting on a safety report.</li>
          </ul>
        </div>
      </div>

      <div class="privacy-section reveal" id="conduct">
        <span class="privacy-number">05</span>
        <div>
          <h2>Prohibited conduct</h2>
          <ul class="terms-list">
            <li>Academic-dishonesty-for-hire (e.g., taking exams or graded work in another student's name) is banned.</li>
            <li>Harassment, moving payments off-platform to evade verification, and falsifying payment proofs or IDs.</li>
            <li>Anything that would put a minor at risk — violations end in removal and, where required, referral to school authorities.</li>
          </ul>
        </div>
      </div>

      <div class="privacy-section reveal" id="notice">
        <span class="privacy-number">06</span>
        <div>
          <h2>Important notice</h2>
          <p>Nerasky is an independent student marketplace project. It is not operated by or affiliated with any school, government agency, GCash, or PayMongo. Example names, prices, and workflows in the guide are illustrative; actual listings and account data are shown only in their respective marketplace pages. These terms are not legal advice. Account holders can contact the site team through the authenticated Support page.</p>
          <a class="btn btn-outline privacy-contact" href="<?= e(base_url('guide.php#faq')) ?>">Read the guide and FAQs <?= ui_icon('arrow-right', 15) ?></a>
        </div>
      </div>
    </article>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
