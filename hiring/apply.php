<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

$roles = [
    'moderator' => [
        'Moderator',
        'Reviews accounts, services, payment proofs, and disputes.',
        40,
        'Community moderation, customer support, conflict resolution, or trust-and-safety work.',
    ],
    'finance_admin' => [
        'Finance admin',
        'Handles withdrawals, cash-ins, and wallet reconciliation.',
        80,
        'Bookkeeping, payment operations, reconciliation, finance support, or handling confidential records.',
    ],
];
$errors = [];
$submitted = false;
$trackingToken = '';
$trackingReference = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim((string) ($_POST['full_name'] ?? ''));
    $contactEmail = strtolower(trim((string) ($_POST['contact_email'] ?? '')));
    $dateOfBirth = trim((string) ($_POST['date_of_birth'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $location = trim((string) ($_POST['location'] ?? ''));
    $education = trim((string) ($_POST['education'] ?? ''));
    $workHistory = trim((string) ($_POST['work_history'] ?? ''));
    $skills = trim((string) ($_POST['skills'] ?? ''));
    $availability = (string) ($_POST['availability'] ?? '');
    $referenceName = trim((string) ($_POST['reference_name'] ?? ''));
    $referenceContact = trim((string) ($_POST['reference_contact'] ?? ''));
    $coverLetter = trim((string) ($_POST['cover_letter'] ?? ''));
    $applicantDeclaration = isset($_POST['applicant_declaration']) ? 1 : 0;
    $role = (string) ($_POST['requested_role'] ?? '');
    $experience = trim((string) ($_POST['experience'] ?? ''));
    $motivation = trim((string) ($_POST['motivation'] ?? ''));
    $resume = upload_file($_FILES['resume_file'] ?? [], 'admin-resumes', ['pdf', 'doc', 'docx']);
    $proof = upload_file($_FILES['verification_file'] ?? [], 'admin-verification', ['jpg', 'jpeg', 'png', 'pdf']);
    $birthDate = DateTimeImmutable::createFromFormat('!Y-m-d', $dateOfBirth);
    $birthDateErrors = DateTimeImmutable::getLastErrors();
    $birthDateValid = $birthDate !== false
        && ($birthDateErrors === false || ($birthDateErrors['warning_count'] === 0 && $birthDateErrors['error_count'] === 0))
        && $birthDate->format('Y-m-d') === $dateOfBirth;

    if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
        $errors[] = 'Enter your full name.';
    } elseif (!filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid Gmail or other contact email.';
    } elseif (mb_strlen($contactEmail) > 190) {
        $errors[] = 'Use an email address shorter than 190 characters.';
    } elseif (!$birthDateValid) {
        $errors[] = 'Enter a valid date of birth.';
    } elseif ($birthDate > new DateTimeImmutable('today') || (int) $birthDate->diff(new DateTimeImmutable('today'))->y < 20) {
        $errors[] = 'Nerasky operations work is limited to applicants aged 20 or older.';
    } elseif (!preg_match('/^[0-9+().\-\s]{7,40}$/', $phone) || mb_strlen($location) < 2 || mb_strlen($location) > 120) {
        $errors[] = 'Enter a reachable phone number and location.';
    } elseif (mb_strlen($education) < 3 || mb_strlen($workHistory) < 30 || mb_strlen($skills) < 10) {
        $errors[] = 'Complete your education, work history, and skills so the hiring team can assess your application.';
    } elseif (!in_array($availability, ['full_time', 'part_time', 'weekends'], true)) {
        $errors[] = 'Choose your work availability.';
    } elseif (mb_strlen($referenceName) < 2 || mb_strlen($referenceContact) < 5) {
        $errors[] = 'Provide one professional or academic reference we can contact.';
    } elseif (!$resume[0]) {
        $errors[] = 'Resume: ' . $resume[1];
    } elseif (mb_strlen($coverLetter) < 80) {
        $errors[] = 'Write a cover letter of at least 80 characters.';
    } elseif (!isset($roles[$role])) {
        $errors[] = 'Choose an admin role.';
    } elseif (mb_strlen($experience) < (int) $roles[$role][2] || mb_strlen($motivation) < 40) {
        $errors[] = $roles[$role][0] . ' applicants must provide at least ' . $roles[$role][2] . ' characters of role-specific experience and 40 characters explaining their motivation.';
    } elseif (!$proof[0]) {
        $errors[] = 'Verification proof: ' . $proof[1];
    } elseif ($applicantDeclaration !== 1) {
        $errors[] = 'Confirm that the information in your application is accurate and that Nerasky may verify it.';
    }

    $existingUser = $errors === [] ? row('SELECT id FROM users WHERE email = ?', [$contactEmail]) : null;
    if ($errors === [] && $existingUser) {
        $errors[] = 'That email is already in use. Please provide a different email for hiring contact.';
    } elseif ($errors === [] && row("SELECT id FROM admin_applications WHERE contact_email = ? AND status IN ('submitted', 'pending', 'reviewing', 'interview')", [$contactEmail])) {
        $errors[] = 'You already have an active application for this email.';
    }

    if ($errors !== []) {
        foreach ([$resume, $proof] as $upload) {
            if ($upload[0]) {
                $storedFile = MARKET_APP_ROOT . '/' . ltrim((string) $upload[1], '/');
                if (is_file($storedFile) && !unlink($storedFile)) {
                    error_log('Nerasky hiring upload cleanup failed: ' . $storedFile);
                }
            }
        }
    }

    if ($errors === []) {
        $trackingToken = bin2hex(random_bytes(32));
        $trackingReference = 'NRK-' . strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));
        insert(
            'INSERT INTO admin_applications (full_name, email, contact_email, date_of_birth, password_hash, phone, location, education, work_history, skills, availability, reference_name, reference_contact, resume_file, cover_letter, requested_role, experience, motivation, verification_file, applicant_declaration, status, tracking_reference, tracking_token_hash) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [$name, $contactEmail, $contactEmail, $dateOfBirth, null, $phone, $location, $education, $workHistory, $skills, $availability, $referenceName, $referenceContact, $resume[1], $coverLetter, $role, $experience, $motivation, $proof[1], $applicantDeclaration, 'submitted', $trackingReference, hash('sha256', $trackingToken)]
        );
        $submitted = true;
    }
}

$pageTitle = 'Nerasky job application';
require __DIR__ . '/header.php';
?>
<style>
  .hiring-apply-page { background: #f7f8ff; }
  @keyframes hiring-rise { from { opacity:0; transform:translateY(16px); } to { opacity:1; transform:translateY(0); } }
  @keyframes hiring-float { 0%,100% { transform:translateY(0); } 50% { transform:translateY(-10px); } }
  .hiring-apply-hero { position: relative; overflow: hidden; padding: 4.8rem 0 4rem; color: #fff; background: radial-gradient(circle at 85% 5%, #4f46e5 0, #1e2552 36%, #11162f 78%); }
  .hiring-apply-hero::after { content: ''; position: absolute; width: 20rem; height: 20rem; right: -7rem; bottom: -10rem; border: 1px solid rgb(165 180 252 / .25); border-radius: 50%; box-shadow: 0 0 0 2rem rgb(165 180 252 / .06), 0 0 0 5rem rgb(165 180 252 / .04); }
  .hiring-apply-hero::after { animation:hiring-float 8s ease-in-out infinite; }
  .hiring-apply-hero .container { position: relative; z-index: 1; }
  .hiring-apply-hero .container > * { opacity:0; animation:hiring-rise .6s ease forwards; }
  .hiring-apply-hero .container > *:nth-child(2) { animation-delay:.08s; }
  .hiring-apply-hero .container > *:nth-child(3) { animation-delay:.16s; }
  .hiring-apply-hero .container > *:nth-child(4) { animation-delay:.24s; }
  .hiring-back { display: inline-flex; align-items: center; gap: .45rem; color: #c7d2fe; font-weight: 700; font-size: .9rem; }
  .hiring-back:hover { color: #fff; transform: translateX(-2px); }
  .hiring-hero-kicker { display: inline-flex; margin: 1.5rem 0 1rem; padding: .35rem .7rem; border: 1px solid rgb(199 210 254 / .25); border-radius: 999px; color: #e0e7ff; background: rgb(99 102 241 / .2); font-size: .78rem; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; }
  .hiring-apply-hero h1 { max-width: 720px; margin-bottom: 1rem; color: #fff; font-size: clamp(2.4rem, 6vw, 4.5rem); letter-spacing: -.04em; }
  .hiring-apply-hero p { max-width: 680px; margin: 0; color: #cbd5f5; font-size: 1.1rem; line-height: 1.75; }
  .hiring-apply-section { padding: 3.5rem 0 5rem; }
  .hiring-apply-grid { display: grid; grid-template-columns: minmax(230px, .72fr) minmax(0, 1.28fr); gap: 2.2rem; align-items: start; }
  .hiring-apply-copy { position: sticky; top: 1rem; padding: 1rem 0; }
  .hiring-apply-copy h2 { margin-bottom: .5rem; font-size: 1.65rem; letter-spacing: -.02em; }
  .hiring-apply-copy > p { color: var(--muted); font-size: var(--fs-sm); line-height: 1.65; }
  .hiring-overview-head { display:flex; justify-content:space-between; gap:1rem; align-items:center; margin-bottom:1.2rem; }
  .hiring-overview-head .eyebrow { margin:0; }
  .hiring-overview-count { padding:.3rem .55rem; border:1px solid #c7d2fe; border-radius:999px; color:#4338ca; background:#eef2ff; font-size:.7rem; font-weight:800; white-space:nowrap; }
  .hiring-apply-steps { display: grid; gap: .65rem; margin: 1.3rem 0 0; padding: 0; list-style: none; counter-reset: hiring-step; }
  .hiring-apply-steps li { position:relative; display:block; min-width:0; padding:1rem 1rem 1rem 3.75rem; border:1px solid #e4e7f3; border-radius:.85rem; background:#fff; counter-increment:hiring-step; transition:transform .2s ease, border-color .2s ease, box-shadow .2s ease; }
  .hiring-apply-steps li:hover { border-color:#a5b4fc; box-shadow:0 8px 18px rgb(30 37 82 / .07); transform:translateY(-2px); }
  .hiring-apply-steps li::before { content:counter(hiring-step); position:absolute; top:1rem; left:1rem; display:grid; width:2rem; height:2rem; place-items:center; border-radius:.65rem; color:#fff; background:var(--grad-brand); font-size:.85rem; font-weight:800; box-shadow:0 4px 12px rgb(79 70 229 / .2); }
  .hiring-apply-steps b, .hiring-apply-steps span { display:block; }
  .hiring-apply-steps b { margin-top:.05rem; font-size:.9rem; }
  .hiring-apply-steps span { margin-top:.2rem; color:var(--muted); font-size:.78rem; line-height:1.5; }
  .hiring-apply-note { margin-top: 1rem; padding:1rem; border:1px solid #c7d2fe; border-radius:.85rem; color:#3730a3; background:#eef2ff; font-size:var(--fs-sm); line-height:1.55; }
  .hiring-apply-note a { color:#312e81; font-weight:800; text-decoration:underline; }
  .hiring-apply-card { padding: clamp(1.15rem, 3vw, 2rem); border: 1px solid #e1e5f5; border-radius: 1.25rem; background: #fff; box-shadow: 0 18px 45px rgb(30 37 82 / .09); }
  .hiring-apply-copy, .hiring-apply-card { animation:hiring-rise .65s ease both; }
  .hiring-apply-card { animation-delay:.12s; }
  .hiring-apply-card-head { display: flex; justify-content: space-between; gap: 1rem; align-items: flex-start; margin-bottom: 1.5rem; }
  .hiring-apply-card-head h2 { margin: 0; font-size: 1.55rem; }
  .hiring-progress { min-width: 100px; color: var(--muted); font-size: .75rem; font-weight: 800; text-align: right; }
  .hiring-progress-bar { height: .35rem; margin-top: .45rem; overflow: hidden; border-radius: 99px; background: #e8eaf4; }
  .hiring-progress-bar span { display: block; width: 0; height: 100%; border-radius: inherit; background: var(--grad-brand); transition: width .25s ease; }
  .hiring-section-heading { display: flex; gap: .65rem; align-items: center; margin: 2rem 0 .9rem; padding-top: 1.35rem; border-top: 1px solid var(--line); font-size: 1rem; }
  .hiring-section-heading::before { content: ''; width: .45rem; height: 1.2rem; border-radius: 99px; background: var(--grad-brand); }
  .hiring-apply-card .field { margin-bottom: 1rem; }
  .hiring-apply-card label { font-weight: 750; }
  .hiring-apply-card .input { transition: border-color .2s ease, box-shadow .2s ease, transform .2s ease; }
  .hiring-apply-card .input:focus { border-color: #818cf8; box-shadow: 0 0 0 4px rgb(99 102 241 / .13); transform: translateY(-1px); }
  .hiring-upload { padding: .75rem; border: 1px dashed #a5b4fc; border-radius: .75rem; background: #f8faff; }
  .hiring-consent { padding: .85rem 1rem; border-radius: .75rem; background: #f8faff; }
  .hiring-consent label { display: flex; gap: .65rem; align-items: flex-start; font-size: var(--fs-sm); line-height: 1.5; }
  .hiring-submit { margin-top: .5rem; box-shadow: 0 10px 22px rgb(79 70 229 / .2); transition: transform .2s ease, box-shadow .2s ease; }
  .hiring-submit:hover { transform: translateY(-2px); box-shadow: 0 14px 28px rgb(79 70 229 / .28); }
  @media (max-width: 820px) { .hiring-apply-grid { grid-template-columns: 1fr; } .hiring-apply-copy { position: static; } .hiring-apply-steps { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
  @media (max-width: 560px) { .hiring-apply-hero { padding: 3.5rem 0 3rem; } .hiring-apply-section { padding-top: 2rem; } .hiring-apply-steps { grid-template-columns: 1fr; } .hiring-apply-card-head { display: block; } .hiring-progress { margin-top: .75rem; text-align: left; } }
  @media (prefers-reduced-motion: reduce) { .hiring-apply-hero::after, .hiring-apply-hero .container > *, .hiring-apply-copy, .hiring-apply-card { animation:none; opacity:1; transform:none; } .hiring-apply-card .input, .hiring-submit, .hiring-back, .hiring-apply-steps li { transition: none; } }
</style>
<div class="hiring-apply-page">
<section class="hiring-apply-hero">
  <div class="container">
    <a class="hiring-back" href="<?= e(hiring_url('careers.php')) ?>">← Back to Nerasky Careers</a>
    <span class="hiring-hero-kicker">Candidate application portal</span>
    <h1>Bring your skills to a marketplace people trust.</h1>
    <p>Tell us how you can help Nerasky operate safely. This application is reviewed by our hiring team, not automatically approved. Successful candidates receive a separate, role-limited work account after onboarding.</p>
  </div>
</section>

<section class="hiring-apply-section">
  <div class="container hiring-apply-grid">
    <aside class="hiring-apply-copy">
      <div class="hiring-overview-head"><span class="eyebrow">Application overview</span><span class="hiring-overview-count">4 steps</span></div>
      <h2>A thoughtful application, not a quick sign-up.</h2>
      <p>Take a few minutes to share enough detail for a fair review. We protect your information and only create access after approval.</p>
      <ol class="hiring-apply-steps">
        <li><b>Check eligibility</b><span>You must be 20 or older and provide accurate contact and identity details.</span></li>
        <li><b>Select one role</b><span>Choose Moderator or Finance admin based on your real experience.</span></li>
        <li><b>Share your evidence</b><span>Upload a CV, explain your experience, and provide a reference.</span></li>
        <li><b>Wait for review</b><span>The hiring team may contact you before a decision is made.</span></li>
      </ol>
      <div class="hiring-apply-note"><b>Need help?</b><br>Contact <a href="mailto:<?= e(HIRING_CONTACT_EMAIL) ?>"><?= e(HIRING_CONTACT_EMAIL) ?></a> for application questions. Never include an OTP, payment PIN, or password.</div>
    </aside>

    <div class="hiring-apply-card">
      <?php if ($submitted): ?>
        <div class="empty-state">
          <div class="es-ic">✓</div>
          <b>Application received</b>
          <p class="muted">Thank you. Our hiring team will review your application and contact you if we need more information. Login access is issued only after approval.</p>
          <div class="banner banner-good"><b>Save your private tracking details</b><br>Reference: <code><?= e($trackingReference) ?></code><br>Token: <code><?= e($trackingToken) ?></code><br><a href="<?= e(hiring_url('status.php')) ?>">Open the applicant status page</a></div>
          <a class="btn btn-outline" href="<?= e(hiring_url('careers.php')) ?>">Return to careers</a>
        </div>
      <?php else: ?>
        <div class="hiring-apply-card-head">
          <div><span class="eyebrow">Nerasky operations</span><h2>Your application</h2><p class="muted" style="font-size:var(--fs-sm);margin:.35rem 0 0">Complete the required employment details. Login credentials are never collected on this form.</p></div>
          <div class="hiring-progress"><span id="application-progress-text">0% complete</span><div class="hiring-progress-bar"><span id="application-progress-bar"></span></div></div>
        </div>
        <?php foreach ($errors as $error): ?><div class="banner banner-bad mb-1"><span class="b-ic">✕</span><span><?= e($error) ?></span></div><?php endforeach; ?>
        <form method="post" enctype="multipart/form-data">
          <?= csrf_field() ?>
          <div class="field"><label for="full_name">Full name</label><input class="input" id="full_name" name="full_name" required value="<?= e($_POST['full_name'] ?? '') ?>"></div>
          <div class="field"><label for="contact_email">Best email for hiring contact</label><input class="input" type="email" id="contact_email" name="contact_email" required value="<?= e($_POST['contact_email'] ?? '') ?>" placeholder="you@gmail.com"><small class="hint">Use an inbox you check regularly. Nerasky will contact you here about your application.</small></div>
          <div class="field"><label for="date_of_birth">Date of birth</label><input class="input" type="date" id="date_of_birth" name="date_of_birth" required value="<?= e($_POST['date_of_birth'] ?? '') ?>"><small class="hint">Nerasky operations work is available only to applicants aged 20 or older.</small></div>
          <h3 class="hiring-section-heading">Role and motivation</h3>
          <div class="field">
            <label for="requested_role">Choose one worker role</label>
            <select class="input" id="requested_role" name="requested_role" required>
              <option value="">Choose a role…</option>
              <?php foreach ($roles as $value => [$label, $description, $minimumExperience, $experiencePrompt]): ?><option value="<?= e($value) ?>" <?= ($_POST['requested_role'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?> — <?= e($description) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="field"><label for="experience">Role-specific experience</label><textarea class="input" id="experience" name="experience" rows="4" required placeholder="For Moderator: describe moderation, support, conflict resolution, or trust work. For Finance admin: describe bookkeeping, payment operations, or reconciliation."><?= e($_POST['experience'] ?? '') ?></textarea><small class="hint">Your answer is reviewed against the worker track you select.</small></div>
          <div class="field"><label for="motivation">Why do you want this job?</label><textarea class="input" id="motivation" name="motivation" rows="4" required placeholder="Explain why you want to work with Nerasky and what you would contribute."><?= e($_POST['motivation'] ?? '') ?></textarea></div>
          <h3 class="hiring-section-heading">About you</h3>
          <div class="form-grid-2">
            <div class="field"><label for="phone">Phone number</label><input class="input" id="phone" name="phone" required value="<?= e($_POST['phone'] ?? '') ?>" placeholder="+63 9XX XXX XXXX"></div>
            <div class="field"><label for="location">City / location</label><input class="input" id="location" name="location" required value="<?= e($_POST['location'] ?? '') ?>" placeholder="Quezon City, Philippines"></div>
          </div>
          <div class="field"><label for="education">Education and certifications</label><textarea class="input" id="education" name="education" rows="3" required placeholder="Degree, school, certifications, or relevant training."><?= e($_POST['education'] ?? '') ?></textarea></div>
          <div class="field"><label for="work_history">Work history</label><textarea class="input" id="work_history" name="work_history" rows="4" required placeholder="Employers, volunteer work, dates, responsibilities, and achievements."><?= e($_POST['work_history'] ?? '') ?></textarea></div>
          <div class="field"><label for="skills">Skills</label><textarea class="input" id="skills" name="skills" rows="2" required placeholder="List the tools, systems, and professional skills relevant to this role."><?= e($_POST['skills'] ?? '') ?></textarea></div>
          <div class="field"><label for="availability">Availability</label><select class="input" id="availability" name="availability" required><option value="">Choose availability…</option><option value="full_time" <?= ($_POST['availability'] ?? '') === 'full_time' ? 'selected' : '' ?>>Full-time</option><option value="part_time" <?= ($_POST['availability'] ?? '') === 'part_time' ? 'selected' : '' ?>>Part-time</option><option value="weekends" <?= ($_POST['availability'] ?? '') === 'weekends' ? 'selected' : '' ?>>Weekends only</option></select></div>
          <h3 class="hiring-section-heading">Your supporting materials</h3>
          <div class="field hiring-upload"><label for="resume_file">Resume / CV</label><input class="input" type="file" id="resume_file" name="resume_file" accept=".pdf,.doc,.docx" required><small class="hint">PDF, DOC, or DOCX. Maximum upload size follows Nerasky’s upload limit.</small></div>
          <div class="field"><label for="cover_letter">Cover letter</label><textarea class="input" id="cover_letter" name="cover_letter" rows="5" required placeholder="Why do you want to work with Nerasky, and why are you a strong fit for this role?"><?= e($_POST['cover_letter'] ?? '') ?></textarea></div>
          <h3 class="hiring-section-heading">Reference and verification</h3>
          <div class="form-grid-2">
            <div class="field"><label for="reference_name">Reference name</label><input class="input" id="reference_name" name="reference_name" required value="<?= e($_POST['reference_name'] ?? '') ?>" placeholder="Former supervisor or instructor"></div>
            <div class="field"><label for="reference_contact">Reference contact</label><input class="input" id="reference_contact" name="reference_contact" required value="<?= e($_POST['reference_contact'] ?? '') ?>" placeholder="Email or phone"></div>
          </div>
          <div class="field hiring-upload"><label for="verification_file">Identity or verification proof</label><input class="input" type="file" id="verification_file" name="verification_file" accept=".jpg,.jpeg,.png,.pdf" required><small class="hint">JPG, PNG, or PDF. Never upload passwords or payment PINs.</small></div>
          <div class="field hiring-consent"><label><input type="checkbox" name="applicant_declaration" value="1" required <?= isset($_POST['applicant_declaration']) ? 'checked' : '' ?>> <span>I confirm that this application is accurate and authorize Nerasky to verify the information for hiring.</span></label></div>
          <button class="btn btn-primary btn-block hiring-submit" type="submit">Send application for review</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</section>
</div>
<script>
  (function () {
    const form = document.querySelector('form');
    const text = document.getElementById('application-progress-text');
    const bar = document.getElementById('application-progress-bar');
    if (!form || !text || !bar) return;
    const update = () => {
      const fields = Array.from(form.querySelectorAll('input[required], select[required], textarea[required]'));
      const complete = fields.filter((field) => field.type === 'checkbox' ? field.checked : field.value.trim() !== '').length;
      const percent = fields.length ? Math.round((complete / fields.length) * 100) : 0;
      text.textContent = percent + '% complete';
      bar.style.width = percent + '%';
    };
    form.addEventListener('input', update);
    form.addEventListener('change', update);
    update();
  }());
</script>
<?php require __DIR__ . '/footer.php'; ?>
