<?php
// visa.php — Editorial Scholar Visa Portal

session_start();

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth_helpers.php';

$pdo = getDB();

// ── Nav variables ─────────────────────────────────────────────────────────────
$base         = BASE_URL;
$activeNav    = 'visa';
$isLoggedIn   = is_logged_in();
$role         = $_SESSION['role']      ?? '';
$userName     = $_SESSION['full_name'] ?? '';
$parts        = array_filter(explode(' ', trim($userName)));
if (count($parts) >= 2) {
    $userInitials = strtoupper(substr($parts[0], 0, 1) . substr(end($parts), 0, 1));
} elseif (count($parts) === 1) {
    $userInitials = strtoupper(substr($parts[0], 0, 2));
} else {
    $userInitials = '?';
}
// ─────────────────────────────────────────────────────────────────────────────

$success = '';
$error   = '';

/* ─── helpers ─────────────────────────────────────────────── */

function clean($value)
{
    return htmlspecialchars(trim($value));
}

function generateApplicationCode()
{
    return 'VISA-' . date('Y') . '-' . strtoupper(substr(md5(uniqid()), 0, 8));
}

function uploadDocument($file, $folder)
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $allowed   = ['pdf', 'jpg', 'jpeg', 'png'];
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($extension, $allowed)) {
        return null;
    }

    if ($file['size'] > 10_485_760) {   // 10 MB
        return null;
    }

    if (!is_dir($folder)) {
        mkdir($folder, 0777, true);
    }

    $newName = uniqid() . '_' . time() . '.' . $extension;
    move_uploaded_file($file['tmp_name'], $folder . '/' . $newName);

    return $newName;
}

/* ─── POST: update application status (admin) ─────────────── */

if (isset($_POST['update_status'])) {
    $stmt = $pdo->prepare("
        UPDATE visa_applications
        SET    visa_status = ?
        WHERE  id          = ?
    ");
    $stmt->execute([
        $_POST['visa_status'],
        (int)$_POST['application_id'],
    ]);
}

/* ─── POST: add FAQ (admin) ───────────────────────────────── */

if (isset($_POST['add_faq'])) {
    $stmt = $pdo->prepare("
        INSERT INTO visa_faq (question, answer)
        VALUES (?, ?)
    ");
    $stmt->execute([
        clean($_POST['question'] ?? ''),
        clean($_POST['answer']   ?? ''),
    ]);
}

/* ─── POST: submit visa application ──────────────────────── */

if (isset($_POST['submit_application'])) {

    try {
        $applicationCode = generateApplicationCode();

        $fullName      = clean($_POST['full_name']          ?? '');
        $email         = clean($_POST['email']              ?? '');
        $phone         = clean($_POST['phone']              ?? '');
        $dob           = $_POST['dob']                      ?? null;
        $gender        = $_POST['gender']                   ?? 'Male';
        $nationality   = clean($_POST['nationality']        ?? '');
        $passportNumber= clean($_POST['passport_number']    ?? '');
        $passportExpiry= $_POST['passport_expiry']          ?? null;
        $degree        = clean($_POST['degree']             ?? '');
        $institution   = clean($_POST['institution']        ?? '');
        $cgpa          = clean($_POST['cgpa']               ?? '');
        $passingYear   = $_POST['passing_year']             ?? null;
        $country       = clean($_POST['destination_country']?? '');
        $university    = clean($_POST['university_name']    ?? '');
        $course        = clean($_POST['course_name']        ?? '');
        $intake        = clean($_POST['intake']             ?? '');
        $sponsorName   = clean($_POST['sponsor_name']       ?? '');
        $sponsorRelation=clean($_POST['sponsor_relation']   ?? '');
        $bankBalance   = $_POST['bank_balance']             ?? 0;
        $ielts         = $_POST['ielts_score']              ?? 0;

        $stmt = $pdo->prepare("
            INSERT INTO visa_applications (
                application_code, full_name, email, phone,
                dob, gender, nationality,
                passport_number, passport_expiry,
                degree, institution, cgpa, passing_year,
                destination_country, university_name, course_name, intake,
                sponsor_name, sponsor_relation,
                bank_balance, ielts_score
            ) VALUES (
                ?,?,?,?,
                ?,?,?,
                ?,?,
                ?,?,?,?,
                ?,?,?,?,
                ?,?,
                ?,?
            )
        ");

        $stmt->execute([
            $applicationCode, $fullName, $email, $phone,
            $dob, $gender, $nationality,
            $passportNumber, $passportExpiry,
            $degree, $institution, $cgpa, $passingYear,
            $country, $university, $course, $intake,
            $sponsorName, $sponsorRelation,
            $bankBalance, $ielts,
        ]);

        $applicationId = (int)$pdo->lastInsertId();

        /* ── document uploads ── */
        $documents = [
            'passport', 'photo', 'transcript', 'certificate',
            'bank_statement', 'offer_letter', 'sop', 'ielts',
        ];

        foreach ($documents as $doc) {
            if (!empty($_FILES[$doc]['name'])) {
                $stored = uploadDocument($_FILES[$doc], 'uploads/' . $doc);

                if ($stored) {
                    $insert = $pdo->prepare("
                        INSERT INTO visa_documents
                            (application_id, document_type, original_name, stored_name, file_path)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $insert->execute([
                        $applicationId,
                        $doc,
                        $_FILES[$doc]['name'],
                        $stored,
                        'uploads/' . $doc . '/' . $stored,
                    ]);
                }
            }
        }

        $success = 'Application submitted successfully. Your Tracking ID: <strong>' . $applicationCode . '</strong>';

    } catch (Exception $e) {
        $error = 'Submission failed: ' . $e->getMessage();
    }
}

/* ─── POST: submit audit request ─────────────────────────── */

if (isset($_POST['submit_audit'])) {
    try {
        $auditCode = clean($_POST['audit_application_code'] ?? '');

        $appStmt = $pdo->prepare("
            SELECT id FROM visa_applications WHERE application_code = ?
        ");
        $appStmt->execute([$auditCode]);
        $appRow = $appStmt->fetch(PDO::FETCH_ASSOC);

        if (!$appRow) {
            $error = 'No application found with that tracking code.';
        } else {
            $auditStmt = $pdo->prepare("
                INSERT INTO visa_audit_requests
                    (application_id, issue_type, description, priority)
                VALUES (?, ?, ?, ?)
            ");
            $auditStmt->execute([
                $appRow['id'],
                clean($_POST['issue_type']   ?? ''),
                clean($_POST['description']  ?? ''),
                $_POST['priority']           ?? 'medium',
            ]);
            $success = 'Audit request submitted successfully.';
        }
    } catch (Exception $e) {
        $error = 'Audit submission failed: ' . $e->getMessage();
    }
}

/* ─── GET: track application ─────────────────────────────── */

$tracker = null;

if (!empty($_GET['track'])) {
    $code  = clean($_GET['track']);
    $tStmt = $pdo->prepare("
        SELECT * FROM visa_applications WHERE application_code = ?
    ");
    $tStmt->execute([$code]);
    $tracker = $tStmt->fetch(PDO::FETCH_ASSOC);
}

/* ─── FAQs ───────────────────────────────────────────────── */

$faqs = $pdo->query("SELECT * FROM visa_faq ORDER BY display_order")->fetchAll(PDO::FETCH_ASSOC);

/* ─── Admin data (only when ?admin=1) ────────────────────── */

$isAdmin      = isset($_GET['admin']);
$applications = [];
$audits       = [];

if ($isAdmin) {
    $applications = $pdo->query("
        SELECT * FROM visa_applications ORDER BY id DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $audits = $pdo->query("
        SELECT * FROM visa_audit_requests ORDER BY id DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visa Portal — The Editorial Scholar</title>

    <!-- Shared styles -->
    <link rel="stylesheet" href="<?= $base ?>/src/output.css">
    <link href="https://fonts.googleapis.com/css2?family=Newsreader:wght@400;600;700&family=Manrope:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet">

    <!-- Page-specific styles (unchanged) -->
    <link rel="stylesheet" href="visa.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">

    <style>
        /* Push page content below fixed nav */
        body { padding-top: 60px; }

        /* Nav dropdown animation */
        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-6px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in { animation: fadeInDown 0.15s ease; }
    </style>
</head>
<body>

<?php include __DIR__ . '/includes/nav_helper.php'; ?>

<!-- ══ HERO ════════════════════════════════════════════════ -->
<section class="hero">
    <div class="hero-content">
        <h1>Global Student Visa Assistance</h1>
        <p>Submit your visa application, upload required documents, and track progress in real time.</p>
        <a href="#apply" class="btn">Start Application</a>
    </div>
</section>

<!-- ══ ELIGIBILITY CHECKER ═════════════════════════════════ -->
<section class="eligibility">
    <h2>Visa Eligibility Checker</h2>
    <div class="eligibility-grid">
        <input type="number" id="ielts" placeholder="IELTS Score (e.g. 6.5)">
        <input type="number" id="balance" placeholder="Bank Balance (USD)">
        <select id="country">
            <option>UK</option>
            <option>Canada</option>
            <option>Australia</option>
            <option>USA</option>
        </select>
        <button onclick="checkEligibility()">Check</button>
    </div>
    <div id="result"></div>
</section>

<!-- ══ APPLICATION FORM ════════════════════════════════════ -->
<section id="apply" class="application-section">
    <h2>Visa Application</h2>

    <?php if ($success): ?>
        <div class="success"><?= $success ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <div class="form-grid">
            <input type="text"   name="full_name"           placeholder="Full Name"           required>
            <input type="email"  name="email"               placeholder="Email"               required>
            <input type="text"   name="phone"               placeholder="Phone"               required>
            <input type="date"   name="dob">
            <select name="gender">
                <option>Male</option>
                <option>Female</option>
                <option>Other</option>
            </select>
            <input type="text"   name="nationality"         placeholder="Nationality">
            <input type="text"   name="passport_number"     placeholder="Passport Number">
            <input type="date"   name="passport_expiry">
            <input type="text"   name="degree"              placeholder="Degree">
            <input type="text"   name="institution"         placeholder="Institution">
            <input type="text"   name="cgpa"                placeholder="CGPA">
            <input type="number" name="passing_year"        placeholder="Passing Year">
            <input type="text"   name="destination_country" placeholder="Destination Country">
            <input type="text"   name="university_name"     placeholder="University">
            <input type="text"   name="course_name"         placeholder="Course">
            <input type="text"   name="intake"              placeholder="Intake">
            <input type="text"   name="sponsor_name"        placeholder="Sponsor Name">
            <input type="text"   name="sponsor_relation"    placeholder="Sponsor Relation">
            <input type="number" name="bank_balance"        placeholder="Bank Balance" step="0.01">
            <input type="number" name="ielts_score"         placeholder="IELTS Score"  step="0.5">
        </div>

        <h3>Required Documents</h3>
        <div class="upload-grid">
            <div><label>Passport</label>       <input type="file" name="passport"></div>
            <div><label>Photo</label>          <input type="file" name="photo"></div>
            <div><label>Transcript</label>     <input type="file" name="transcript"></div>
            <div><label>Certificate</label>    <input type="file" name="certificate"></div>
            <div><label>Bank Statement</label> <input type="file" name="bank_statement"></div>
            <div><label>Offer Letter</label>   <input type="file" name="offer_letter"></div>
            <div><label>SOP</label>            <input type="file" name="sop"></div>
            <div><label>IELTS</label>          <input type="file" name="ielts"></div>
        </div>

        <button type="submit" name="submit_application" class="submit-btn">
            Submit Application
        </button>
    </form>
</section>

<!-- ══ TRACKER ═════════════════════════════════════════════ -->
<section id="tracker" class="tracker-section">
    <h2>Track Application</h2>

    <form method="GET" action="">
        <input type="text" name="track" placeholder="Enter Tracking ID (e.g. VISA-2026-XXXXXXXX)"
               value="<?= htmlspecialchars($_GET['track'] ?? '') ?>">
        <button type="submit">Track</button>
    </form>

    <?php if (!empty($_GET['track'])): ?>
        <?php if ($tracker): ?>
            <div class="tracker-card">
                <h3><?= htmlspecialchars($tracker['application_code']) ?></h3>
                <p>Applicant: <strong><?= htmlspecialchars($tracker['full_name']) ?></strong></p>
                <p>Status: <strong><?= htmlspecialchars($tracker['visa_status']) ?></strong></p>
                <p>Submitted: <strong><?= htmlspecialchars($tracker['created_at']) ?></strong></p>
            </div>
        <?php else: ?>
            <p style="color:#721c24;margin-top:15px;">No application found for that tracking ID.</p>
        <?php endif; ?>
    <?php endif; ?>
</section>

<!-- ══ AUDIT REQUEST ════════════════════════════════════════ -->
<section class="audit-section">
    <h2>Request Audit</h2>
    <form method="POST" action="">
        <input type="text"   name="audit_application_code" placeholder="Application Tracking Code" required>
        <input type="text"   name="issue_type"             placeholder="Issue Type"                required>
        <select name="priority">
            <option value="low">Low</option>
            <option value="medium" selected>Medium</option>
            <option value="high">High</option>
        </select>
        <textarea name="description" placeholder="Describe the issue" required></textarea>
        <button name="submit_audit" type="submit">Submit Audit</button>
    </form>
</section>

<!-- ══ FAQ ══════════════════════════════════════════════════ -->
<section id="faq" class="faq-section">
    <h2>Frequently Asked Questions</h2>
    <?php foreach ($faqs as $faq): ?>
        <div class="faq-item">
            <button class="faq-question">
                <?= htmlspecialchars($faq['question']) ?>
            </button>
            <div class="faq-answer">
                <?= htmlspecialchars($faq['answer']) ?>
            </div>
        </div>
    <?php endforeach; ?>
</section>

<!-- ══ ADMIN PANEL (only when ?admin=1) ═════════════════════ -->
<?php if ($isAdmin): ?>
<div class="admin-wrapper">
    <h1>Visa Admin Panel</h1>

    <div class="admin-card">
        <h2>Applications</h2>
        <table>
            <tr>
                <th>ID</th>
                <th>Code</th>
                <th>Name</th>
                <th>Status</th>
                <th>Update</th>
            </tr>
            <?php foreach ($applications as $app): ?>
            <tr>
                <td><?= (int)$app['id'] ?></td>
                <td><?= htmlspecialchars($app['application_code']) ?></td>
                <td><?= htmlspecialchars($app['full_name']) ?></td>
                <td><?= htmlspecialchars($app['visa_status']) ?></td>
                <td>
                    <form method="POST" action="?admin=1">
                        <input type="hidden" name="application_id" value="<?= (int)$app['id'] ?>">
                        <select name="visa_status">
                            <?php
                            $statuses = [
                                'submitted','documents_pending','under_review',
                                'interview','processing','approved','rejected',
                            ];
                            foreach ($statuses as $s):
                            ?>
                            <option value="<?= $s ?>" <?= $s === $app['visa_status'] ? 'selected' : '' ?>>
                                <?= $s ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <button name="update_status" type="submit">Update</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <div class="admin-card">
        <h2>Audit Requests</h2>
        <?php foreach ($audits as $audit): ?>
            <div class="audit-card">
                <h3><?= htmlspecialchars($audit['issue_type']) ?></h3>
                <p><strong>Priority:</strong> <?= htmlspecialchars($audit['priority']) ?></p>
                <p><?= htmlspecialchars($audit['description']) ?></p>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="admin-card">
        <h2>Add FAQ</h2>
        <form method="POST" action="?admin=1" class="audit-section">
            <input    type="text" name="question" placeholder="Question" required>
            <textarea name="answer" placeholder="Answer" required></textarea>
            <button name="add_faq" type="submit">Add FAQ</button>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- ══ FOOTER (matches site-wide) ══════════════════════════ -->
<footer class="w-full bg-[#F8FAFC] border-t border-[#E2E8F0] py-12">
    <div class="max-w-[1280px] mx-auto px-8">
        <div class="grid grid-cols-4 gap-8">

            <div class="flex flex-col gap-4">
                <p class="font-newsreader font-bold text-lg text-[#0F172A]">The Editorial Scholar</p>
                <p class="font-manrope font-normal text-sm leading-5 tracking-[0.35px] text-[#64748B]">
                    &copy; <?= date('Y') ?> The Editorial Scholar.<br>Curating Global Futures.
                </p>
            </div>

            <div class="flex flex-col gap-3">
                <p class="font-manrope font-bold text-sm tracking-[1.4px] uppercase text-[#0F172A] mb-2">Company</p>
                <a href="#" class="font-manrope font-normal text-sm tracking-[0.35px] text-[#64748B] hover:text-[#0F172A] transition-colors no-underline">About Us</a>
                <a href="#" class="font-manrope font-normal text-sm tracking-[0.35px] text-[#64748B] hover:text-[#0F172A] transition-colors no-underline">Contact Support</a>
            </div>

            <div class="flex flex-col gap-3">
                <p class="font-manrope font-bold text-sm tracking-[1.4px] uppercase text-[#0F172A] mb-2">Legal</p>
                <a href="<?= $base ?>/terms.php"   class="font-manrope font-normal text-sm tracking-[0.35px] text-[#64748B] hover:text-[#0F172A] transition-colors no-underline">Terms of Service</a>
                <a href="<?= $base ?>/privacy.php" class="font-manrope font-normal text-sm tracking-[0.35px] text-[#64748B] hover:text-[#0F172A] transition-colors no-underline">Privacy Policy</a>
                <a href="#"                          class="font-manrope font-normal text-sm tracking-[0.35px] text-[#64748B] hover:text-[#0F172A] transition-colors no-underline">Academic Integrity</a>
            </div>

            <div class="flex flex-col gap-3">
                <p class="font-manrope font-bold text-sm tracking-[1.4px] uppercase text-[#0F172A] mb-2">Social</p>
                <div class="flex items-center gap-4">
                    <a href="#" class="text-[#64748B] hover:text-[#0F172A] transition-colors"><i class="ri-twitter-x-line text-xl"></i></a>
                    <a href="#" class="text-[#64748B] hover:text-[#0F172A] transition-colors"><i class="ri-linkedin-line text-xl"></i></a>
                    <a href="#" class="text-[#64748B] hover:text-[#0F172A] transition-colors"><i class="ri-instagram-line text-xl"></i></a>
                </div>
            </div>

        </div>
    </div>
</footer>

<script>
function checkEligibility() {
    const ielts   = parseFloat(document.getElementById('ielts').value);
    const balance = parseFloat(document.getElementById('balance').value);
    const result  = document.getElementById('result');

    if (isNaN(ielts) || isNaN(balance)) {
        result.innerHTML = 'Please enter both IELTS score and bank balance.';
        result.style.color = '#721c24';
        return;
    }

    if (ielts >= 6 && balance >= 10000) {
        result.innerHTML = '✓ You appear eligible to apply.';
        result.style.color = '#155724';
    } else {
        result.innerHTML = '✗ Requirements not met. IELTS ≥ 6.0 and bank balance ≥ $10,000 required.';
        result.style.color = '#721c24';
    }
}

document.querySelectorAll('.faq-question').forEach(btn => {
    btn.addEventListener('click', function () {
        this.nextElementSibling.classList.toggle('show');
    });
});
</script>

</body>
</html>