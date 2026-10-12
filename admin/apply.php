<?php
declare(strict_types=1);

header('Location: /hiring/apply.php', true, 302);
exit;

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim((string) ($_POST['full_name'] ?? ''));
    $contactEmail = strtolower(trim((string) ($_POST['contact_email'] ?? '')));
    $dateOfBirth = trim((string) ($_POST['date_of_birth'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');
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

    if (mb_strlen($name) < 2) {
        $errors[] = 'Enter your full name.';
    } elseif (!filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid Gmail or other contact email.';
    } elseif (!$dateOfBirth || !($birthDate = date_create_immutable($dateOfBirth))) {
        $errors[] = 'Enter a valid date of birth.';
    } elseif ($birthDate > new DateTimeImmutable('today') || (int) $birthDate->diff(new DateTimeImmutable('today'))->y < 20) {
        $errors[] = 'Admin work is limited to applicants aged 20 or older.';
    } elseif (mb_strlen($password) < 10) {
        $errors[] = 'Choose an admin password with at least 10 characters.';
    } elseif ($password !== $passwordConfirmation) {
        $errors[] = 'The password confirmation does not match.';
    } elseif (mb_strlen($phone) < 7 || mb_strlen($location) < 2) {
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
        $errors[] = 'That email is already in use. Choose a separate email for your admin login.';
    } elseif ($errors === [] && row("SELECT id FROM admin_applications WHERE contact_email = ? AND status = 'pending'", [$contactEmail])) {
        $errors[] = 'You already have a pending admin application.';
    }

    if ($errors === []) {
        insert(
            'INSERT INTO admin_applications (full_name, email, contact_email, date_of_birth, password_hash, phone, location, education, work_history, skills, availability, reference_name, reference_contact, resume_file, cover_letter, requested_role, experience, motivation, verification_file, applicant_declaration) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [$name, $contactEmail, $contactEmail, $dateOfBirth, password_hash($password, PASSWORD_DEFAULT), $phone, $location, $education, $workHistory, $skills, $availability, $referenceName, $referenceContact, $resume[1], $coverLetter, $role, $experience, $motivation, $proof[1], $applicantDeclaration]
        );
        $submitted = true;
    }
}

$pageTitle = 'Nerasky job application';
require __DIR__ . '/header.php';
?>
<section class="admin-apply-hero">
  <div class="container">
    <p style="margin:0 0 1rem"><a href="<?= e(base_url('careers.php')) ?>" style="color:#c7d2fe">← Back to admin opportunities</a></p>
    <span class="eyebrow" style="color:#a5b4fc">Join the trust team</span>
    <h1>Apply for a Nerasky operations role.</h1>
    <p>This is a real worker application. Submit your resume, work history, references, and role-specific qualifications. Applicants must be 20 or older and pass a manual company-style review before an admin login is activated.</p>
  </div>
</section>

<section class="section-tight admin-apply-section">
  <div class="container admin-apply-grid">
    <div class="admin-apply-copy">
      <h2>How it works</h2>
      <ol class="admin-apply-steps">
        <li><b>Meet the adult work requirement.</b><span>Applicants must be 20 or older and submit identity proof and date of birth.</span></li>
        <li><b>Choose one worker track.</b><span>Moderator and Finance admin applications have different experience requirements.</span></li>
        <li><b>Submit a complete job application.</b><span>The hiring team reviews your resume, references, identity proof, and role-specific experience.</span></li>
        <li><b>Start with least privilege.</b><span>Access can be changed or removed as the marketplace grows.</span></li>
      </ol>
    </div>

    <div class="admin-card admin-apply-card">
      <?php if ($submitted): ?>
        <div class="empty-state">
          <div class="es-ic">✓</div>
          <b>Application submitted</b>
          <p class="muted">A Super admin will review your application. Do not share passwords or payment credentials.</p>
          <a class="btn btn-outline" href="<?= e(base_url('login.php')) ?>">Return to admin login</a>
        </div>
      <?php else: ?>
        <h2 style="font-size:1.35rem">Job application</h2>
        <p class="muted" style="font-size:var(--fs-sm)">Complete every section honestly. If approved, your email and password become a separate limited worker admin login.</p>
        <?php foreach ($errors as $error): ?><div class="banner banner-bad mb-1"><span class="b-ic">✕</span><span><?= e($error) ?></span></div><?php endforeach; ?>
        <form method="post" enctype="multipart/form-data">
          <?= csrf_field() ?>
          <div class="field"><label for="full_name">Full name</label><input class="input" id="full_name" name="full_name" required value="<?= e($_POST['full_name'] ?? '') ?>"></div>
          <div class="field"><label for="contact_email">Admin login and contact email</label><input class="input" type="email" id="contact_email" name="contact_email" required value="<?= e($_POST['contact_email'] ?? '') ?>" placeholder="you@gmail.com"><small class="hint">Use Gmail, Outlook, or another email you can access. This becomes your admin username after approval.</small></div>
          <div class="field"><label for="date_of_birth">Date of birth</label><input class="input" type="date" id="date_of_birth" name="date_of_birth" required value="<?= e($_POST['date_of_birth'] ?? '') ?>"><small class="hint">Admin work is available only to applicants aged 20 or older.</small></div>
          <div class="field"><label for="password">Create admin password</label><input class="input" type="password" id="password" name="password" required minlength="10" autocomplete="new-password"><small class="hint">Keep this password private. It is never shown to reviewers.</small></div>
          <div class="field"><label for="password_confirmation">Confirm admin password</label><input class="input" type="password" id="password_confirmation" name="password_confirmation" required minlength="10" autocomplete="new-password"></div>
          <h3 class="apply-form-heading">Job applied for</h3>
          <div class="field">
            <label for="requested_role">Choose one worker role</label>
            <select class="input" id="requested_role" name="requested_role" required>
              <option value="">Choose a role…</option>
              <?php foreach ($roles as $value => [$label, $description, $minimumExperience, $experiencePrompt]): ?><option value="<?= e($value) ?>" <?= ($_POST['requested_role'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?> — <?= e($description) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="field"><label for="experience">Role-specific experience</label><textarea class="input" id="experience" name="experience" rows="4" required placeholder="For Moderator: describe moderation, support, conflict resolution, or trust work. For Finance admin: describe bookkeeping, payment operations, or reconciliation."><?= e($_POST['experience'] ?? '') ?></textarea><small class="hint">Your answer is reviewed against the worker track you select.</small></div>
          <div class="field"><label for="motivation">Why do you want this job?</label><textarea class="input" id="motivation" name="motivation" rows="4" required placeholder="Explain why you want to work with Nerasky and what you would contribute."><?= e($_POST['motivation'] ?? '') ?></textarea></div>
          <h3 class="apply-form-heading">Candidate profile</h3>
          <div class="form-grid-2">
            <div class="field"><label for="phone">Phone number</label><input class="input" id="phone" name="phone" required value="<?= e($_POST['phone'] ?? '') ?>" placeholder="+63 9XX XXX XXXX"></div>
            <div class="field"><label for="location">City / location</label><input class="input" id="location" name="location" required value="<?= e($_POST['location'] ?? '') ?>" placeholder="Quezon City, Philippines"></div>
          </div>
          <div class="field"><label for="education">Education and certifications</label><textarea class="input" id="education" name="education" rows="3" required placeholder="Degree, school, certifications, or relevant training."><?= e($_POST['education'] ?? '') ?></textarea></div>
          <div class="field"><label for="work_history">Work history</label><textarea class="input" id="work_history" name="work_history" rows="4" required placeholder="Employers, volunteer work, dates, responsibilities, and achievements."><?= e($_POST['work_history'] ?? '') ?></textarea></div>
          <div class="field"><label for="skills">Skills</label><textarea class="input" id="skills" name="skills" rows="2" required placeholder="List the tools, systems, and professional skills relevant to this role."><?= e($_POST['skills'] ?? '') ?></textarea></div>
          <div class="field"><label for="availability">Availability</label><select class="input" id="availability" name="availability" required><option value="">Choose availability…</option><option value="full_time" <?= ($_POST['availability'] ?? '') === 'full_time' ? 'selected' : '' ?>>Full-time</option><option value="part_time" <?= ($_POST['availability'] ?? '') === 'part_time' ? 'selected' : '' ?>>Part-time</option><option value="weekends" <?= ($_POST['availability'] ?? '') === 'weekends' ? 'selected' : '' ?>>Weekends only</option></select></div>
          <h3 class="apply-form-heading">Application materials</h3>
          <div class="field"><label for="resume_file">Resume / CV</label><input class="input" type="file" id="resume_file" name="resume_file" accept=".pdf,.doc,.docx" required><small class="hint">PDF, DOC, or DOCX. Maximum upload size follows Nerasky’s upload limit.</small></div>
          <div class="field"><label for="cover_letter">Cover letter</label><textarea class="input" id="cover_letter" name="cover_letter" rows="5" required placeholder="Why do you want to work with Nerasky, and why are you a strong fit for this role?"><?= e($_POST['cover_letter'] ?? '') ?></textarea></div>
          <h3 class="apply-form-heading">Reference</h3>
          <div class="form-grid-2">
            <div class="field"><label for="reference_name">Reference name</label><input class="input" id="reference_name" name="reference_name" required value="<?= e($_POST['reference_name'] ?? '') ?>" placeholder="Former supervisor or instructor"></div>
            <div class="field"><label for="reference_contact">Reference contact</label><input class="input" id="reference_contact" name="reference_contact" required value="<?= e($_POST['reference_contact'] ?? '') ?>" placeholder="Email or phone"></div>
          </div>
          <div class="field"><label for="verification_file">Identity or verification proof</label><input class="input" type="file" id="verification_file" name="verification_file" accept=".jpg,.jpeg,.png,.pdf" required><small class="hint">JPG, PNG, or PDF. Never upload passwords or payment PINs.</small></div>
          <div class="field"><label><input type="checkbox" name="applicant_declaration" value="1" required <?= isset($_POST['applicant_declaration']) ? 'checked' : '' ?>> I confirm that the information and documents in this application are accurate, and I authorize Nerasky to verify them for hiring.</label></div>
          <button class="btn btn-primary btn-block" type="submit">Submit application</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php require __DIR__ . '/footer.php'; ?>
