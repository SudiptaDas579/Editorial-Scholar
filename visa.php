<?php

session_start();

require_once __DIR__ . '/config/db.php';

$pdo = getDB();

$success = '';
$error = '';

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

    $allowed = [
        'pdf',
        'jpg',
        'jpeg',
        'png'
    ];

    $extension = strtolower(
        pathinfo(
            $file['name'],
            PATHINFO_EXTENSION
        )
    );

    if (!in_array($extension, $allowed)) {
        return null;
    }

    if ($file['size'] > 10485760) {
        return null;
    }

    if (!is_dir($folder)) {
        mkdir($folder, 0777, true);
    }

    $newName =
        uniqid() .
        '_' .
        time() .
        '.' .
        $extension;

    move_uploaded_file(
        $file['tmp_name'],
        $folder . '/' . $newName
    );

    return $newName;
}

<?php if(isset($_GET['admin'])): ?>

<h1>Visa Admin Panel</h1>

<!-- Applications -->

<?php

$applications = $pdo->query("
SELECT *
FROM visa_applications
ORDER BY id DESC
")->fetchAll();

?>

<table>

<tr>
<th>ID</th>
<th>Code</th>
<th>Name</th>
<th>Status</th>
<th>Update</th>
</tr>

<?php foreach($applications as $app): ?>

<tr>

<td><?= $app['id'] ?></td>

<td><?= $app['application_code'] ?></td>

<td><?= htmlspecialchars($app['full_name']) ?></td>

<td><?= $app['visa_status'] ?></td>

<td>

<form method="POST">

<input
type="hidden"
name="application_id"
value="<?= $app['id'] ?>">

<select name="visa_status">

<option>submitted</option>
<option>documents_pending</option>
<option>under_review</option>
<option>interview</option>
<option>processing</option>
<option>approved</option>
<option>rejected</option>

</select>

<button
name="update_status">

Update

</button>

</form>

</td>

</tr>

<?php endforeach; ?>

</table>


if(isset($_POST['update_status']))
{
    $stmt = $pdo->prepare("
    UPDATE visa_applications
    SET visa_status=?
    WHERE id=?
    ");

    $stmt->execute([
        $_POST['visa_status'],
        $_POST['application_id']
    ]);
}


<h2>Audit Requests</h2>

<?php

$audits = $pdo->query("
SELECT *
FROM visa_audit_requests
ORDER BY id DESC
")->fetchAll();

foreach($audits as $audit):

?>

<div class="audit-card">

<h3>
<?= htmlspecialchars($audit['issue_type']) ?>
</h3>

<p>
<?= htmlspecialchars($audit['description']) ?>
</p>

</div>

<?php endforeach; ?>

<form method="POST">

<input
type="text"
name="question"
placeholder="Question">

<textarea
name="answer"
placeholder="Answer"></textarea>

<button
name="add_faq">

Add FAQ

</button>

</form>

if(isset($_POST['add_faq']))
{
    $stmt = $pdo->prepare("
    INSERT INTO visa_faq
    (
        question,
        answer
    )
    VALUES
    (
        ?,?
    )
    ");

    $stmt->execute([
        $_POST['question'],
        $_POST['answer']
    ]);
}







if (
    isset($_POST['submit_application'])
) {

    try {

        $applicationCode =
            generateApplicationCode();

        $fullName =
            clean($_POST['full_name']);

        $email =
            clean($_POST['email']);

        $phone =
            clean($_POST['phone']);

        $dob =
            $_POST['dob'];

        $gender =
            $_POST['gender'];

        $nationality =
            clean($_POST['nationality']);

        $passportNumber =
            clean($_POST['passport_number']);

        $passportExpiry =
            $_POST['passport_expiry'];

        $degree =
            clean($_POST['degree']);

        $institution =
            clean($_POST['institution']);

        $cgpa =
            clean($_POST['cgpa']);

        $passingYear =
            $_POST['passing_year'];

        $country =
            clean($_POST['destination_country']);

        $university =
            clean($_POST['university_name']);

        $course =
            clean($_POST['course_name']);

        $intake =
            clean($_POST['intake']);

        $sponsorName =
            clean($_POST['sponsor_name']);

        $sponsorRelation =
            clean($_POST['sponsor_relation']);

        $bankBalance =
            $_POST['bank_balance'];

        $ielts =
            $_POST['ielts_score'];

        $stmt =
            $pdo->prepare("
            INSERT INTO visa_applications
            (
                application_code,
                full_name,
                email,
                phone,
                dob,
                gender,
                nationality,
                passport_number,
                passport_expiry,
                degree,
                institution,
                cgpa,
                passing_year,
                destination_country,
                university_name,
                course_name,
                intake,
                sponsor_name,
                sponsor_relation,
                bank_balance,
                ielts_score
            )
            VALUES
            (
                ?,?,?,?,?,?,?,?,?,?,
                ?,?,?,?,?,?,?,?,?,?,
                ?
            )
        ");

        $stmt->execute([
            $applicationCode,
            $fullName,
            $email,
            $phone,
            $dob,
            $gender,
            $nationality,
            $passportNumber,
            $passportExpiry,
            $degree,
            $institution,
            $cgpa,
            $passingYear,
            $country,
            $university,
            $course,
            $intake,
            $sponsorName,
            $sponsorRelation,
            $bankBalance,
            $ielts
        ]);

        $applicationId =
            $pdo->lastInsertId();

        $success =
            "Application Submitted. Tracking ID: "
            . $applicationCode;

    } catch (Exception $e) {

        $error =
            $e->getMessage();
    }
}



$documents = [

    'passport',
    'photo',
    'transcript',
    'certificate',
    'bank_statement',
    'offer_letter',
    'sop',
    'ielts'

];

foreach ($documents as $doc) {

    if (
        !empty($_FILES[$doc]['name'])
    ) {

        $stored =
            uploadDocument(
                $_FILES[$doc],
                'uploads/' . $doc
            );

        if ($stored) {

            $insert =
                $pdo->prepare("
                INSERT INTO
                visa_documents
                (
                    application_id,
                    document_type,
                    original_name,
                    stored_name,
                    file_path
                )
                VALUES
                (
                    ?,?,?,?,?
                )
            ");

            $insert->execute([
                $applicationId,
                $doc,
                $_FILES[$doc]['name'],
                $stored,
                'uploads/' .
                $doc .
                '/' .
                $stored
            ]);
        }
    }
}



$tracker = null;

if (
    isset($_GET['track'])
) {

    $code =
        clean($_GET['track']);

    $stmt =
        $pdo->prepare("
        SELECT *
        FROM visa_applications
        WHERE application_code = ?
    ");

    $stmt->execute([
        $code
    ]);

    $tracker =
        $stmt->fetch();
}



$faqQuery =
    $pdo->query("
    SELECT *
    FROM visa_faq
    ORDER BY display_order
");

$faqs =
    $faqQuery->fetchAll();


    <!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1.0">

<title>Editorial Scholar Visa Portal</title>

<link
rel="stylesheet"
href="visa.css">

<link
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
rel="stylesheet">

</head>

<body>

<header class="navbar">

    <div class="logo">
        Editorial Scholar
    </div>

    <nav>

        <a href="#apply">
            Apply
        </a>

        <a href="#tracker">
            Track
        </a>

        <a href="#faq">
            FAQ
        </a>

    </nav>

</header>

<section class="hero">

    <div class="hero-content">

        <h1>
            Global Student Visa Assistance
        </h1>

        <p>
            Submit your visa application,
            upload required documents,
            and track progress in real time.
        </p>

        <a href="#apply" class="btn">
            Start Application
        </a>

    </div>

</section>

<section class="eligibility">

    <h2>
        Visa Eligibility Checker
    </h2>

    <div class="eligibility-grid">

        <input
        type="number"
        id="ielts"
        placeholder="IELTS Score">

        <input
        type="number"
        id="balance"
        placeholder="Bank Balance">

        <select id="country">

            <option>
                UK
            </option>

            <option>
                Canada
            </option>

            <option>
                Australia
            </option>

            <option>
                USA
            </option>

        </select>

        <button
        onclick="checkEligibility()">

            Check

        </button>

    </div>

    <div id="result"></div>

</section>

<section
id="apply"
class="application-section">

<h2>

Visa Application

</h2>

<?php if($success): ?>

<div class="success">

<?= $success ?>

</div>

<?php endif; ?>

<?php if($error): ?>

<div class="error">

<?= $error ?>

</div>

<?php endif; ?>

<form
method="POST"
enctype="multipart/form-data">

<div class="form-grid">

<input
type="text"
name="full_name"
placeholder="Full Name"
required>

<input
type="email"
name="email"
placeholder="Email"
required>

<input
type="text"
name="phone"
placeholder="Phone"
required>

<input
type="date"
name="dob">

<select name="gender">

<option>Male</option>
<option>Female</option>
<option>Other</option>

</select>

<input
type="text"
name="nationality"
placeholder="Nationality">

<input
type="text"
name="passport_number"
placeholder="Passport Number">

<input
type="date"
name="passport_expiry">

<input
type="text"
name="degree"
placeholder="Degree">

<input
type="text"
name="institution"
placeholder="Institution">

<input
type="text"
name="cgpa"
placeholder="CGPA">

<input
type="number"
name="passing_year"
placeholder="Passing Year">

<input
type="text"
name="destination_country"
placeholder="Destination Country">

<input
type="text"
name="university_name"
placeholder="University">

<input
type="text"
name="course_name"
placeholder="Course">

<input
type="text"
name="intake"
placeholder="Intake">

<input
type="text"
name="sponsor_name"
placeholder="Sponsor Name">

<input
type="text"
name="sponsor_relation"
placeholder="Sponsor Relation">

<input
type="number"
step="0.01"
name="bank_balance"
placeholder="Bank Balance">

<input
type="number"
step="0.5"
name="ielts_score"
placeholder="IELTS Score">

</div>


<h3>

Required Documents

</h3>

<div class="upload-grid">

<div>

<label>

Passport

</label>

<input
type="file"
name="passport">

</div>

<div>

<label>

Photo

</label>

<input
type="file"
name="photo">

</div>

<div>

<label>

Transcript

</label>

<input
type="file"
name="transcript">

</div>

<div>

<label>

Certificate

</label>

<input
type="file"
name="certificate">

</div>

<div>

<label>

Bank Statement

</label>

<input
type="file"
name="bank_statement">

</div>

<div>

<label>

Offer Letter

</label>

<input
type="file"
name="offer_letter">

</div>

<div>

<label>

SOP

</label>

<input
type="file"
name="sop">

</div>

<div>

<label>

IELTS

</label>

<input
type="file"
name="ielts">

</div>

</div>

<button
type="submit"
name="submit_application"
class="submit-btn">

Submit Application

</button>

</form>

</section>

<section
id="tracker"
class="tracker-section">

<h2>

Track Application

</h2>

<form method="GET">

<input
type="text"
name="track"
placeholder="Tracking ID">

<button>

Track

</button>

</form>

<?php if($tracker): ?>

<div class="tracker-card">

<h3>

<?= $tracker['application_code']; ?>

</h3>

<p>

Status:

<strong>

<?= $tracker['visa_status']; ?>

</strong>

</p>

</div>

<?php endif; ?>

</section>

<section class="audit-section">

<h2>

Request Audit

</h2>

<form method="POST">

<input
type="text"
name="audit_application_code"
placeholder="Application Code">

<input
type="text"
name="issue_type"
placeholder="Issue Type">

<select name="priority">

<option value="low">
Low
</option>

<option value="medium">
Medium
</option>

<option value="high">
High
</option>

</select>

<textarea
name="description"
placeholder="Describe Issue">

</textarea>

<button
name="submit_audit">

Submit Audit

</button>

</form>

</section>


<section
id="faq"
class="faq-section">

<h2>

Frequently Asked Questions

</h2>

<?php foreach($faqs as $faq): ?>

<div class="faq-item">

<button class="faq-question">

<?= htmlspecialchars(
$faq['question']
) ?>

</button>

<div class="faq-answer">

<?= htmlspecialchars(
$faq['answer']
) ?>

</div>

</div>

<?php endforeach; ?>

</section>


<footer>

<p>

© 2026 Editorial Scholar

</p>

</footer>

<script>

function checkEligibility()
{
    let ielts =
    parseFloat(
    document.getElementById(
    'ielts'
    ).value
    );

    let balance =
    parseFloat(
    document.getElementById(
    'balance'
    ).value
    );

    let result =
    document.getElementById(
    'result'
    );

    if(
    ielts >= 6 &&
    balance >= 10000
    )
    {
        result.innerHTML =
        "Eligible";
    }
    else
    {
        result.innerHTML =
        "Need More Requirements";
    }
}

document
.querySelectorAll(
'.faq-question'
)
.forEach(btn =>
{
    btn.onclick =
    function()
    {
        this
        .nextElementSibling
        .classList
        .toggle(
        'show'
        );
    };
});

</script>

</body>
</html>



