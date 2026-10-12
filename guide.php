<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

$pageTitle  = 'How Nerasky works — Guide';
$currentNav = 'guide';
$pageMeta   = 'Step-by-step guide: the order system, buyer & seller instructions, payments, wallet and Nerasky Pro.';
$guideUser = get_user();
require __DIR__ . '/includes/header.php';
?>

<section class="guide-hero">
  <div class="container guide-hero-inner">
    <div class="guide-hero-copy reveal">
      <span class="hero-badge"><?= ui_icon('file') ?> The Nerasky guide</span>
      <h1>Everything you need to make your next move.</h1>
      <p class="lead">A clear, practical walkthrough for joining the marketplace, finding help, offering your skills, and moving through every order stage with confidence.</p>
      <div class="hero-cta" style="justify-content:center">
        <a class="btn btn-primary btn-lg" href="#order-system">The order system ↓</a>
        <a class="btn btn-white btn-lg" href="#quick-start">Quick start</a>
      </div>
    </div>
    <div class="guide-hero-visual reveal reveal-delay-2" aria-hidden="true">
      <div class="guide-hero-orbit"></div>
      <div class="guide-hero-card"><span class="guide-hero-card-top"><i></i> YOUR JOURNEY</span><strong>Skill → trust → opportunity</strong><div><span>01</span><b>Start with a clear profile</b></div><div><span>02</span><b>Build a great order</b></div><div><span>03</span><b>Keep the momentum</b></div></div>
    </div>
  </div>
</section>

<section class="section-tight">
  <div class="container">
    <!-- quick jump -->
    <div class="guide-toc guide-toc-modern reveal" aria-label="Guide sections">
      <div class="guide-toc-group">
        <span class="guide-toc-label"><?= ui_icon('sparkle', 14) ?> Start here</span>
        <a href="#quick-start"><?= ui_icon('zap') ?> Quick start</a>
        <a href="#order-system"><?= ui_icon('trending') ?> Order system</a>
        <a href="#example"><?= ui_icon('zap') ?> Worked example</a>
      </div>
      <div class="guide-toc-group">
        <span class="guide-toc-label"><?= ui_icon('users', 14) ?> Use Nerasky</span>
        <a href="#buyers"><?= ui_icon('box') ?> Buyer's guide</a>
        <a href="#sellers"><?= ui_icon('presentation') ?> Seller's guide</a>
        <a href="#payments"><?= ui_icon('card') ?> Payments &amp; wallet</a>
      </div>
      <div class="guide-toc-group">
        <span class="guide-toc-label"><?= ui_icon('shield', 14) ?> Membership &amp; help</span>
        <a href="#pro">★ Nerasky Pro</a>
        <a href="#faq">❓ FAQ</a>
      </div>
    </div>

    <!-- ============ QUICK START ============ -->
    <div class="section-head center reveal mt-4" id="quick-start">
      <span class="eyebrow">Step zero</span>
      <h2>Quick start — 4 moves</h2>
      <p>Everything below takes about two minutes in total.</p>
    </div>

    <div class="grid-4 guide-start-grid" data-stagger="85">
      <div class="step-card reveal">
        <span class="step-num">1</span>
        <div class="s-ic"><?= ui_icon('pen') ?></div>
        <?php if ($guideUser): ?>
          <h4>Your account is ready</h4>
          <p>You're signed in. Keep your profile up to date and explore the marketplace whenever you're ready.</p>
        <?php else: ?>
          <h4>Create an account</h4>
          <p>Sign up with your full name, school email, and a password. Upload a photo of your <b>school ID</b> — every account is manually verified by a human.</p>
        <?php endif; ?>
      </div>
      <div class="step-card reveal reveal-delay-1">
        <span class="step-num">2</span>
        <div class="s-ic"><?= ui_icon('shield') ?></div>
        <h4>Get verified</h4>
        <p>A moderator reviews your account before you can buy or sell. Nerasky is open to students <b>15 and older</b> — you enter your date of birth at signup. Under 18? Upload a <b>signed parental consent form</b> too, and an admin reviews it before you can sell.</p>
      </div>
      <div class="step-card reveal reveal-delay-2">
        <span class="step-num">3</span>
        <div class="s-ic"><?= ui_icon('search') ?></div>
        <h4>Explore or offer</h4>
        <p><b>Browse</b> the marketplace to buy help, or click <b>+ Post a service</b> to sell what you already know (tutoring, editing, coding, design…).</p>
      </div>
      <div class="step-card reveal reveal-delay-3">
        <span class="step-num">4</span>
        <div class="s-ic"><?= ui_icon('chat') ?></div>
        <h4>Message &amp; order</h4>
        <p>Use the <b><?= ui_icon('chat') ?> Message</b> button on any profile or service to ask questions first, then hit <b>Order now</b> when you're ready. That starts the order flow below.</p>
      </div>
    </div>
  </div>
</section>
<!-- ============ ORDER SYSTEM ============ -->
<section class="section-tight" id="order-system">
  <div class="container">
    <div class="section-head center reveal">
      <span class="eyebrow">The heart of Nerasky</span>
      <h2>The order system — step by step</h2>
      <p>Every order follows the same 6-stage cycle. The status pill on any order page shows exactly where you are.</p>
    </div>

    <!-- lifecycle visual -->
    <div class="card card-pad guide-lifecycle reveal" style="margin-bottom:2rem">
      <div class="order-flow" style="justify-content:center;flex-wrap:wrap;margin:0">
        <span class="flow-step done"><span class="fs-n">1</span>Order placed</span>
        <span class="flow-arrow">→</span>
        <span class="flow-step now"><span class="fs-n">2</span>Payment</span>
        <span class="flow-arrow">→</span>
        <span class="flow-step"><span class="fs-n">3</span>Confirmation</span>
        <span class="flow-arrow">→</span>
        <span class="flow-step"><span class="fs-n">4</span>Seller delivers</span>
        <span class="flow-arrow">→</span>
        <span class="flow-step"><span class="fs-n">5</span>Buyer reviews</span>
        <span class="flow-arrow">→</span>
        <span class="flow-step"><span class="fs-n">6</span><?= ui_icon('cash') ?> Payout</span>
      </div>
      <p class="hint center mt-2" style="margin:0">Cancellation is possible from the buyer side while the order is still <i>awaiting payment</i> or <i>in progress</i>.</p>
    </div>

    <div class="grid-2" style="grid-template-columns:1fr 1fr;align-items:start">
      <?php
      $stages = [
        ['1', 'box', 'Order placed', 'The buyer hits "Order now" on a service, adds notes, and waits. The status is <b>Awaiting payment</b> until payment is confirmed.',
         'POV buyer: you see your order in Dashboard → Orders. POV seller: you get a notification + a new "in progress" queue item.'],
        ['2', 'card', 'Secure payment', 'The buyer pays from their Nerasky wallet, scans a PayMongo QR Ph code, or submits GCash proof for manual review.',
         'PayMongo confirms automatically. An admin checks the actual GCash transaction before wallet funds are credited or work starts.'],
        ['3', 'shield', 'Protected order starts', 'Once wallet or PayMongo payment succeeds, or an admin verifies GCash, the order becomes <b>In progress</b>. The seller can begin and both sides can follow the status trail.',
         'The buyer keeps control: the payout is released only after the deliverable is accepted.'],
        ['4', 'send', 'Seller delivers', 'The seller does the work and uploads the deliverable file (PDF, ZIP, DOCX…) with an optional note. Status becomes <b>Delivered</b>.',
         'The buyer is notified and can download the file straight from the order page.'],
        ['5', 'star', 'Buyer reviews', 'The buyer checks the deliverable, rates it 1–5 stars, and clicks <b>Accept · release payout</b>. Status becomes <b>Completed</b>.',
         'This is the trust layer — the review becomes public on the seller’s profile.'],
        ['6', 'cash', 'Payout to the seller', 'Nerasky instantly credits the seller’s wallet after acceptance. Free sellers keep 95%. Pro sellers keep 97% on their first 3 orders each day, then the regular 95% rate applies until the next daily reset.',
         'The seller can then request a withdrawal through the Wallet page.'],
      ];
      foreach ($stages as $i => $st): ?>
        <div class="card card-pad guide-stage reveal<?= $i % 2 === 1 ? ' reveal-delay-1' : '' ?>">
          <div class="guide-stage-head">
            <span class="guide-stage-num"><?= $st[0] ?></span>
            <span class="guide-stage-ic"><?= ui_icon($st[1], 20) ?></span>
          </div>
          <h3 style="display:flex;align-items:center;gap:.5rem;font-size:1.05rem"><?= e($st[2]) ?></h3>
          <p class="muted" style="font-size:var(--fs-sm)"><?= $st[3] ?></p>
          <div class="banner banner-info" style="font-size:var(--fs-xs);padding:.65rem .9rem">
            <span class="b-ic"><?= ui_icon('zap') ?></span><span><?= $st[4] ?></span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<!-- ============ WORKED EXAMPLE ============ -->
<section class="section-tight" id="example" style="background:var(--surface)">
  <div class="container">
    <div class="section-head center reveal">
      <span class="eyebrow">Watch it happen</span>
      <h2>An illustrative order walkthrough</h2>
      <p>This fictional scenario explains the workflow. Names, prices, ratings, and events are examples—not real users, orders, or reviews.</p>
    </div>

    <div class="order-summary">
      <div class="stack">
        <div class="card card-pad reveal">
          <div class="flex-between mb-2">
            <h3 style="font-size:1.05rem;margin:0"><?= ui_icon('graduation') ?> The cast</h3>
            <span class="sv-cat">Guided example</span>
          </div>
          <div class="grid-2 gap-2">
            <div class="filebox" style="background:var(--surface-2)">
              <span class="f-ic"><?= ui_icon('users') ?></span>
              <div><b>Example buyer</b><small class="muted" style="display:block;font-size:var(--fs-xs)">Buyer · wants an algebra crash course</small></div>
            </div>
            <div class="filebox" style="background:var(--surface-2)">
              <span class="f-ic">★</span>
              <div><b>Example seller</b><span class="pro-badge" style="margin-left:.4rem">★ Pro example</span><small class="muted" style="display:block;font-size:var(--fs-xs)">Seller · tutoring service</small></div>
            </div>
          </div>
          <div class="fee-break mt-2">
            <dl class="kv"><dt>Service</dt><dd>Algebra 101 crash course — 1 hour</dd></dl>
            <?php if ($guideUser): ?>
              <dl class="kv"><dt>Price</dt><dd>₱250.00</dd></dl>
              <dl class="kv"><dt>Seller receives</dt><dd style="color:var(--green-600)">₱242.50 <span class="faint" style="font-weight:500">(97% — Pro order 1–3)</span></dd></dl>
              <dl class="kv" style="border-bottom:0"><dt>Nerasky platform fee</dt><dd>₱7.50 <span class="faint" style="font-weight:500">(3% — daily Pro allowance)</span></dd></dl>
            <?php else: ?>
              <dl class="kv"><dt class="price-guest-blur">Estimated price</dt><dd>Log in to view</dd></dl>
              <dl class="kv" style="border-bottom:0"><dt>Seller payout &amp; fee</dt><dd>Shown after log in</dd></dl>
            <?php endif; ?>
          </div>
        </div>

        <div class="card card-pad reveal">
          <h3 style="font-size:1.05rem;margin-bottom:1rem"><?= ui_icon('clock') ?> The full journey</h3>
          <div class="timeline">
            <div class="tl-item"><b>1 — Order placed.</b> <span class="muted" style="font-size:var(--fs-sm)">The buyer submits an order with instructions. The order is created as <b>Awaiting payment</b>.</span></div>
            <div class="tl-item"><b>2 — Payment confirmed.</b> <span class="muted" style="font-size:var(--fs-sm)">The buyer chooses an available payment method and pays the displayed amount. The order advances after the payment is confirmed.</span></div>
            <div class="tl-item"><b>3 — Work starts.</b> <span class="muted" style="font-size:var(--fs-sm)">The order changes to <b>In progress</b>. Both sides are notified and can continue in the order chat.</span></div>
            <div class="tl-item"><b>4 — Work is delivered.</b> <span class="muted" style="font-size:var(--fs-sm)">The seller completes the service and submits the agreed deliverable. Status → <b>Delivered</b>.</span></div>
            <div class="tl-item"><b>5 — Buyer reviews the delivery.</b> <span class="muted" style="font-size:var(--fs-sm)">The buyer checks the work and can accept it to complete the order. Any rating or review is written by the actual buyer.</span></div>
            <div class="tl-item"><b>6 — Payout.</b> <span class="muted" style="font-size:var(--fs-sm)"><?php if ($guideUser): ?>In this price example, ₱242.50 is credited to the seller's wallet after the buyer accepts the work (₱7.50 is the platform fee).<?php else: ?>After the buyer accepts the work, the seller payout is credited to the seller's wallet and the platform fee is recorded.<?php endif; ?> Eligible Pro orders may receive the lower fee described above. The seller can request a manual GCash withdrawal from <b>Wallet</b>.</span></div>
          </div>
        </div>

        <div class="card card-pad reveal">
          <h3 style="font-size:1.05rem;margin-bottom:.6rem"><?= ui_icon('presentation') ?> Try the example yourself</h3>
          <p class="muted" style="font-size:var(--fs-sm)">Ready to explore the workflow? Sign in to your account and open <b>Orders</b> to follow your active purchases, deliveries, reviews, and payouts in one place.</p>
          <div class="quick-actions">
            <a class="btn btn-primary btn-sm" href="<?= e(base_url('login.php')) ?>">Sign in to continue</a>
            <a class="btn btn-outline btn-sm" href="<?= e(base_url('orders.php')) ?>">See the orders</a>
          </div>
        </div>
      </div>

      <aside class="stack">
        <div class="card card-pad reveal">
          <h3 style="font-size:1rem;margin-bottom:.6rem">★ Where to find each stage</h3>
          <dl class="kv"><dt>Dashboard</dt><dd>Orders as buyer/seller, wallet, attention alerts</dd></dl>
          <dl class="kv"><dt>Orders page</dt><dd>Filter by status (awaiting → completed)</dd></dl>
          <dl class="kv"><dt>Order page</dt><dd>The cockpit — payment, deliverable, review, chat</dd></dl>
          <dl class="kv" style="border-bottom:0"><dt>Messaging</dt><dd>Direct chats via <?= ui_icon('chat') ?> buttons anywhere</dd></dl>
        </div>
        <div class="card card-pad reveal">
          <h3 style="font-size:1rem;margin-bottom:.6rem"><?= ui_icon('info') ?> Who does what</h3>
          <table class="tbl" style="min-width:0">
            <tr><td class="muted">Buyer</td><td>pays, reviews, releases</td></tr>
            <tr><td class="muted">Seller</td><td>delivers work, answers chat, earns</td></tr>
            <tr><td class="muted">Admin</td><td>monitors payments, approves services/accounts</td></tr>
          </table>
        </div>
      </aside>
    </div>
  </div>
</section>
<!-- ============ BUYERS ============ -->
<section class="section-tight" id="buyers">
  <div class="container">
    <div class="section-head reveal">
      <span class="eyebrow">Need help?</span>
      <h2><?= ui_icon('box') ?> The buyer's playbook</h2>
    </div>
    <div class="grid-3">
      <div class="feature-card reveal">
        <div class="f-ic fc-1"><?= ui_icon('search') ?></div>
        <h3>Find the right service</h3>
        <p>Search <b>Browse</b> by keyword, filter by category chips, and sort by newest or price. ★ Pro sellers are boosted to the top.</p>
      </div>
      <div class="feature-card reveal reveal-delay-1">
        <div class="f-ic fc-2"><?= ui_icon('chat') ?></div>
        <h3>Ask before you pay</h3>
        <p>Hit <b><?= ui_icon('chat') ?> Message</b> with any question — scope, deadline, revisions. Everything stays in Nerasky's chat.</p>
      </div>
      <div class="feature-card reveal reveal-delay-2">
        <div class="f-ic fc-3"><?= ui_icon('check') ?></div>
        <h3>Order &amp; pay safely</h3>
        <p>Order the service and pay from your wallet, with PayMongo QR Ph, or by submitting GCash proof for manual review. Work starts after payment is confirmed; the seller's wallet payout is released after you accept the deliverable.</p>
      </div>
      <div class="feature-card reveal">
        <div class="f-ic fc-4"><?= ui_icon('download') ?></div>
        <h3>Stay in charge</h3>
        <p>Download the deliverable, check it thoroughly, then rate honestly. Your review shapes the seller's reputation — be fair.</p>
      </div>
      <div class="feature-card reveal reveal-delay-1">
        <div class="f-ic fc-1"><?= ui_icon('file') ?></div>
        <h3>Every order is documented</h3>
        <p>Payment status, messages, and the final rating are visible on the order page — a full paper trail.</p>
      </div>
      <div class="feature-card reveal reveal-delay-2">
        <div class="f-ic fc-2"><?= ui_icon('x') ?></div>
        <h3>Keep it inside Nerasky</h3>
        <p>Don't move chat or payments off-platform. Anything risky happens outside, we can't help. Report issues to an admin.</p>
      </div>
    </div>
  </div>
</section>
<!-- ============ SELLERS ============ -->
<section class="section-tight" id="sellers" style="background:var(--surface)">
  <div class="container">
    <div class="section-head reveal">
      <span class="eyebrow">Got a skill?</span>
      <h2><?= ui_icon('presentation') ?> The seller's playbook</h2>
    </div>
    <div class="grid-2" style="align-items:start">
      <div class="card card-pad reveal">
        <h3 style="font-size:1.05rem;margin-bottom:.8rem">1. List it well</h3>
        <div class="stack gap-2" style="gap:.7rem">
          <div class="filebox"><span class="f-ic">★</span><div><b>Title the outcome</b><small class="muted" style="display:block;font-size:var(--fs-xs)">"Essay that reads confident" beats "I fix grammar".</small></div></div>
          <div class="filebox"><span class="f-ic"><?= ui_icon('file') ?></span><div><b>Scope the deliverable</b><small class="muted" style="display:block;font-size:var(--fs-xs)">"500 words", "1 hour session", "2 revisions".</small></div></div>
          <div class="filebox"><span class="f-ic">⏱</span><div><b>Promise a real timeline</b><small class="muted" style="display:block;font-size:var(--fs-xs)">Then over-deliver. Reviews are your currency.</small></div></div>
          <div class="filebox"><span class="f-ic"><?= ui_icon('cash') ?></span><div><b>Price with confidence</b><small class="muted" style="display:block;font-size:var(--fs-xs)">Min ₱50. You keep 95% (97% on the first 3 daily Pro orders).</small></div></div>
        </div>
      </div>
      <div class="card card-pad reveal reveal-delay-1">
        <h3 style="font-size:1.05rem;margin-bottom:.8rem">2. Run the order like a pro</h3>
        <div class="stack gap-2" style="gap:.7rem">
          <div class="filebox"><span class="f-ic"><?= ui_icon('chat') ?></span><div><b>Reply fast in chat</b><small class="muted" style="display:block;font-size:var(--fs-xs)">Buyers pick sellers who feel responsive.</small></div></div>
          <div class="filebox"><span class="f-ic"><?= ui_icon('box') ?></span><div><b>Upload the deliverable</b><small class="muted" style="display:block;font-size:var(--fs-xs)">PDF / ZIP / DOCX — with a helpful note.</small></div></div>
          <div class="filebox"><span class="f-ic"><?= ui_icon('star') ?></span><div><b>Invite a fair review</b><small class="muted" style="display:block;font-size:var(--fs-xs)">The buyer releases your payout when they accept.</small></div></div>
          <div class="filebox"><span class="f-ic"><?= ui_icon('cash') ?></span><div><b>Withdraw anytime</b><small class="muted" style="display:block;font-size:var(--fs-xs)">Request a manual payout of at least ₱100 to your selected payout account from Wallet.</small></div></div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============ PAYMENTS & WALLET ============ -->
<section class="section-tight" id="payments">
  <div class="container">
    <div class="section-head center reveal">
      <span class="eyebrow">Money in motion</span>
      <h2><?= ui_icon('card') ?> Payments, wallet &amp; withdrawals</h2>
    </div>
    <div class="grid-3">
      <div class="feature-card reveal">
        <div class="f-ic fc-1"><?= ui_icon('file') ?></div>
        <h3>How buyers "pay"</h3>
        <p>Pay with wallet funds or PayMongo QR Ph for automatic confirmation, or use GCash and submit the reference and screenshot for admin review. Work starts after payment confirmation.</p>
      </div>
      <div class="feature-card reveal reveal-delay-1">
        <div class="f-ic fc-2"><?= ui_icon('cash') ?></div>
        <h3>Your wallet</h3>
        <p>Completed orders credit your balance after the buyer accepts. A confirmed PayMongo QR Ph top-up can also add wallet funds. See your balance and lifetime earnings on the <b>Wallet</b> page.</p>
      </div>
      <div class="feature-card reveal reveal-delay-2">
        <div class="f-ic fc-3"><?= ui_icon('send') ?></div>
        <h3>Withdrawals</h3>
        <p>Request any amount over ₱100 to your selected payout account. An admin verifies the details and processes the payout manually.</p>
      </div>
    </div>
    <div class="banner banner-info reveal mt-3" style="max-width:760px;margin-inline:auto">
      <span class="b-ic"><?= ui_icon('search') ?></span>
      <span><b>Payment notice:</b> PayMongo QR Ph payments are confirmed automatically; GCash proof is reviewed manually by an admin. Contact support if a PayMongo charge is not reflected.</span>
    </div>
  </div>
</section>
<!-- ============ PRO ============ -->
<section class="dark-band section" id="pro">
  <div class="container">
    <div class="center reveal" style="max-width:720px;margin-inline:auto">
      <span class="eyebrow" style="color:#fcd34d">Membership</span>
      <h2 style="font-size:2.1rem">★ Nerasky Pro</h2>
      <p style="color:#a8b0d0;font-size:1.08rem"><?= price((float) PREMIUM_PRICE) ?> / <?= (int) PREMIUM_DAYS ?> days, paid from your wallet.</p>
      <div class="grid-3" style="margin-top:1.6rem">
        <div class="card card-pad" style="background:rgb(245 158 11/.08);border-color:rgb(245 158 11/.4)">
          <b style="font-size:1.2rem"><?= ui_icon('trending') ?> 3% daily allowance</b>
          <p class="muted" style="font-size:var(--fs-sm);margin:0">Keep 97% on your first <?= (int) PREMIUM_DAILY_DISCOUNTED_ORDERS ?> orders each day. Then the fee returns to 5% and resets tomorrow.</p>
        </div>
        <div class="card card-pad" style="background:rgb(245 158 11/.08);border-color:rgb(245 158 11/.4)">
          <b style="font-size:1.2rem"><?= ui_icon('zap') ?> Boosted</b>
          <p class="muted" style="font-size:var(--fs-sm);margin:0">Your listings rank first in Browse and the landing page.</p>
        </div>
        <div class="card card-pad" style="background:rgb(245 158 11/.08);border-color:rgb(245 158 11/.4)">
          <b style="font-size:1.2rem">★ Pro badge</b>
          <p class="muted" style="font-size:var(--fs-sm);margin:0">Stand out on every service card and your profile.</p>
        </div>
      </div>
      <a class="btn btn-primary btn-lg mt-3" href="<?= e(base_url('premium.php')) ?>">Check your membership</a>
    </div>
  </div>
</section>

<!-- ============ FAQ ============ -->
<section class="section-tight" id="faq">
  <div class="container">
    <div class="section-head center reveal">
      <span class="eyebrow">Still curious?</span>
      <h2>❓ Quick answers</h2>
    </div>
    <div class="grid-2 faq-grid" data-stagger="75">
      <div class="card card-pad faq-card reveal">
        <span class="faq-number">01</span>
        <h4 style="font-size:.95rem">How do I pay for an order?</h4>
        <p class="muted" style="font-size:var(--fs-sm)">Use your Nerasky wallet, scan the PayMongo QR Ph code for automatic confirmation, or pay by GCash and submit the transaction reference and screenshot. The seller should wait until the order moves to In progress.</p>
      </div>
      <div class="card card-pad faq-card reveal">
        <span class="faq-number">02</span>
        <h4 style="font-size:.95rem">How do I add money to my wallet?</h4>
        <p class="muted" style="font-size:var(--fs-sm)">Add wallet funds through PayMongo QR Ph for automatic confirmation, or submit a GCash cash-in request for admin review. Your balance updates after the selected payment is verified.</p>
      </div>
      <div class="card card-pad faq-card reveal">
        <span class="faq-number">03</span>
        <h4 style="font-size:.95rem">Why does my order say "Awaiting payment"?</h4>
        <p class="muted" style="font-size:var(--fs-sm)">The buyer still needs to pay from their wallet, complete PayMongo QR Ph checkout, or submit GCash proof. An order moves to <b>In progress</b> only after payment is confirmed.</p>
      </div>
      <div class="card card-pad faq-card reveal">
        <span class="faq-number">04</span>
        <h4 style="font-size:.95rem">How are QR payments verified?</h4>
        <p class="muted" style="font-size:var(--fs-sm)">PayMongo confirms QR Ph payments to Nerasky automatically. The app checks the server-side payment status before adding wallet funds, activating Pro, or starting an order.</p>
      </div>
      <div class="card card-pad faq-card reveal">
        <span class="faq-number">05</span>
        <h4 style="font-size:.95rem">How can I buy Nerasky Pro?</h4>
        <p class="muted" style="font-size:var(--fs-sm)">You can pay for Pro with your wallet balance or use the PayMongo QR Ph checkout. Pro activates once payment is confirmed.</p>
      </div>
      <div class="card card-pad faq-card reveal">
        <span class="faq-number">06</span>
        <h4 style="font-size:.95rem">How do seller payouts and withdrawals work?</h4>
        <p class="muted" style="font-size:var(--fs-sm)">After the buyer accepts delivery, the seller's payout is credited to their Nerasky wallet. Sellers can request a withdrawal of at least ₱100 to their selected payout account; an admin processes and records the transfer.</p>
      </div>
      <div class="card card-pad faq-card reveal">
        <span class="faq-number">07</span>
        <h4 style="font-size:.95rem">My QR payment is delayed or failed. What now?</h4>
        <p class="muted" style="font-size:var(--fs-sm)">Keep the checkout page open while Nerasky checks with PayMongo. If it expires or fails, start a new checkout; contact support if PayMongo charged you but Nerasky did not update. Never share payment account passwords or one-time codes.</p>
      </div>
      <div class="card card-pad faq-card reveal">
        <span class="faq-number">08</span>
        <h4 style="font-size:.95rem">Why are seller names hidden when I'm logged out?</h4>
        <p class="muted" style="font-size:var(--fs-sm)">Privacy by design. Log in (even a free account) to see names, message sellers, and order.</p>
      </div>
      <div class="card card-pad faq-card reveal">
        <span class="faq-number">09</span>
        <h4 style="font-size:.95rem">Minors can use Nerasky?</h4>
        <p class="muted" style="font-size:var(--fs-sm)">Students 15 and up can join. Ages 15–17 sign up with a school ID plus a signed parental consent form that an admin approves before they can sell. Under 15 cannot create an account.</p>
      </div>
    </div>
  </div>
</section>

<!-- ============ CTA ============ -->
<section class="section">
  <div class="container center reveal">
    <span class="eyebrow" style="justify-content:center"><?= $guideUser ? 'Good to see you again' : 'Ready to try it?' ?></span>
    <h2><?= $guideUser ? 'Your next opportunity starts here.' : 'You now know everything.<br>Go make your first peso.' ?> <?= ui_icon('cash') ?></h2>
    <div class="flex" style="justify-content:center;margin-top:1.6rem">
      <?php if ($guideUser): ?>
        <a class="btn btn-primary btn-lg" href="<?= e(base_url('sell.php')) ?>">Post a service</a>
        <a class="btn btn-outline btn-lg" href="<?= e(base_url('browse.php')) ?>">Browse services</a>
        <a class="btn btn-ghost btn-lg" href="<?= e(base_url('wallet.php')) ?>">Open your wallet</a>
      <?php else: ?>
        <a class="btn btn-primary btn-lg" href="<?= e(base_url('register.php')) ?>">Create free account</a>
        <a class="btn btn-outline btn-lg" href="<?= e(base_url('browse.php')) ?>">Or just browse</a>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>