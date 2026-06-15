<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_name('editorial_scholar_mock');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function scholarship_fixture(): array
{
    static $fixture = null;

    if ($fixture === null) {
        $fixture = require __DIR__ . '/scholarship_data.php';
    }

    return $fixture;
}

function scholarship_escape(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function scholarship_token(): string
{
    if (empty($_SESSION['scholarship_token'])) {
        $_SESSION['scholarship_token'] = bin2hex(random_bytes(32));
    }

    return (string)$_SESSION['scholarship_token'];
}

function scholarship_verify_token(): void
{
    $token = (string)($_POST['_token'] ?? '');
    if ($token === '' || !hash_equals(scholarship_token(), $token)) {
        scholarship_set_flash('Your page session expired. Please try again.', 'error');
        scholarship_redirect();
    }
}

function scholarship_set_flash(string $message, string $type = 'success'): void
{
    $_SESSION['scholarship_flash'] = ['message' => $message, 'type' => $type];
}

function scholarship_flash(): ?array
{
    $flash = $_SESSION['scholarship_flash'] ?? null;
    unset($_SESSION['scholarship_flash']);

    return is_array($flash) ? $flash : null;
}

function scholarship_redirect(array $query = []): never
{
    $baseQuery = $_GET;
    unset($baseQuery['eligibility'], $baseQuery['details']);
    $targetQuery = array_merge($baseQuery, $query);
    $url = '/scholarship.php';

    if ($targetQuery !== []) {
        $url .= '?' . http_build_query($targetQuery);
    }

    header('Location: ' . $url, true, 303);
    exit;
}

function scholarship_init_session_state(): void
{
    $fixture = scholarship_fixture();

    if (!isset($_SESSION['tracked_grants']) || !is_array($_SESSION['tracked_grants'])) {
        $_SESSION['tracked_grants'] = array_keys($fixture['tracked_grants']);
    }

    if (!isset($_SESSION['tracker_status']) || !is_array($_SESSION['tracker_status'])) {
        $_SESSION['tracker_status'] = $fixture['tracked_grants'];
    }
}

function scholarship_handle_post(): void
{
    scholarship_init_session_state();

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return;
    }

    scholarship_verify_token();

    $action = strtolower(trim((string)($_POST['action'] ?? '')));
    $scholarshipId = trim((string)($_POST['scholarship_id'] ?? ''));

    if ($scholarshipId !== '' && scholarship_find_by_id($scholarshipId) === null) {
        scholarship_set_flash('Scholarship not found.', 'error');
        scholarship_redirect();
    }

    if ($action === 'check_eligibility') {
        scholarship_redirect(['eligibility' => $scholarshipId]);
    }

    if ($action === 'track') {
        if (!in_array($scholarshipId, $_SESSION['tracked_grants'], true)) {
            $_SESSION['tracked_grants'][] = $scholarshipId;
        }
        $_SESSION['tracker_status'][$scholarshipId] = 'preparing';
        scholarship_set_flash('Grant added to your tracker.');
        scholarship_redirect();
    }

    if ($action === 'untrack') {
        $_SESSION['tracked_grants'] = array_values(array_filter(
            $_SESSION['tracked_grants'],
            fn (string $id): bool => $id !== $scholarshipId
        ));
        unset($_SESSION['tracker_status'][$scholarshipId]);
        scholarship_set_flash('Grant removed from your tracker.');
        scholarship_redirect();
    }

    if ($action === 'status') {
        $status = trim((string)($_POST['status'] ?? 'watching'));
        $allowed = ['watching', 'preparing', 'submitted', 'archived'];
        if (!in_array($status, $allowed, true)) {
            scholarship_set_flash('Unsupported tracker status.', 'error');
            scholarship_redirect();
        }

        if (!in_array($scholarshipId, $_SESSION['tracked_grants'], true)) {
            $_SESSION['tracked_grants'][] = $scholarshipId;
        }

        $_SESSION['tracker_status'][$scholarshipId] = $status;
        scholarship_set_flash('Tracker status updated.');
        scholarship_redirect();
    }

    scholarship_set_flash('Unknown action.', 'error');
    scholarship_redirect();
}

function scholarship_filters(): array
{
    return [
        'search' => trim((string)($_GET['search'] ?? '')),
        'region' => trim((string)($_GET['region'] ?? 'all')),
        'field' => trim((string)($_GET['field'] ?? 'all')),
        'funding_type' => trim((string)($_GET['funding_type'] ?? 'all')),
        'deadline' => trim((string)($_GET['deadline'] ?? 'all')),
    ];
}

function scholarship_filter_options(): array
{
    $fixture = scholarship_fixture();
    $regions = [];
    $fields = [];
    $fundingTypes = [];

    foreach ($fixture['scholarships'] as $scholarship) {
        $regions[$scholarship['region']] = true;
        $fundingTypes[$scholarship['funding_type']] = true;
        foreach ($scholarship['fields'] as $field) {
            $fields[$field] = true;
        }
    }

    $regions = array_keys($regions);
    $fields = array_keys($fields);
    $fundingTypes = array_keys($fundingTypes);
    sort($regions);
    sort($fields);
    sort($fundingTypes);

    return [
        'regions' => $regions,
        'fields' => $fields,
        'funding_types' => $fundingTypes,
        'deadlines' => [
            'all' => 'All Deadlines',
            'next-3-months' => 'Next 3 Months',
            'next-6-months' => 'Next 6 Months',
            'future' => 'Future Deadlines',
        ],
    ];
}

function scholarship_filtered(array $filters): array
{
    $fixture = scholarship_fixture();

    return array_values(array_filter($fixture['scholarships'], function (array $scholarship) use ($filters): bool {
        $search = strtolower($filters['search']);

        if ($search !== '') {
            $haystack = strtolower(implode(' ', [
                $scholarship['title'],
                $scholarship['program'],
                $scholarship['institutions'],
                $scholarship['description'],
                implode(' ', $scholarship['tags']),
            ]));

            if (!str_contains($haystack, $search)) {
                return false;
            }
        }

        if ($filters['region'] !== 'all' && $scholarship['region'] !== $filters['region']) {
            return false;
        }

        if (
            $filters['field'] !== 'all'
            && !in_array($filters['field'], $scholarship['fields'], true)
            && !in_array('All Disciplines', $scholarship['fields'], true)
        ) {
            return false;
        }

        if ($filters['funding_type'] !== 'all' && $scholarship['funding_type'] !== $filters['funding_type']) {
            return false;
        }

        return scholarship_deadline_matches($scholarship['deadline_date'], $filters['deadline']);
    }));
}

function scholarship_deadline_matches(string $deadlineDate, string $filter): bool
{
    if ($filter === 'all') {
        return true;
    }

    $deadline = new DateTimeImmutable($deadlineDate);
    $today = new DateTimeImmutable('today');

    return match ($filter) {
        'future' => $deadline >= $today,
        'next-3-months' => $deadline >= $today && $deadline <= $today->modify('+3 months'),
        'next-6-months' => $deadline >= $today && $deadline <= $today->modify('+6 months'),
        default => true,
    };
}

function scholarship_find_by_id(string $id): ?array
{
    foreach (scholarship_fixture()['scholarships'] as $scholarship) {
        if ($scholarship['id'] === $id) {
            return $scholarship;
        }
    }

    return null;
}

function scholarship_featured(): array
{
    foreach (scholarship_fixture()['scholarships'] as $scholarship) {
        if (!empty($scholarship['featured'])) {
            return $scholarship;
        }
    }

    return scholarship_fixture()['scholarships'][0];
}

function scholarship_is_tracked(string $id): bool
{
    scholarship_init_session_state();

    return in_array($id, $_SESSION['tracked_grants'], true);
}

function scholarship_tracker_status(string $id): string
{
    scholarship_init_session_state();

    return (string)($_SESSION['tracker_status'][$id] ?? 'watching');
}

function scholarship_document_label(string $type): string
{
    return match ($type) {
        'cv' => 'Academic CV',
        'statement' => 'Personal Statement',
        'transcript' => 'Transcript',
        'reference' => 'Reference Letters',
        default => ucwords(str_replace('_', ' ', $type)),
    };
}

function scholarship_eligibility(array $scholarship): array
{
    $fixture = scholarship_fixture();
    $user = $fixture['current_user'];
    $documents = $fixture['documents'];
    $criteria = [];

    $levelPass = in_array($user['degree_level'], $scholarship['eligible_levels'], true);
    $criteria[] = [
        'label' => 'Degree level',
        'passed' => $levelPass,
        'detail' => $levelPass
            ? "{$user['degree_level']} applicants are eligible."
            : 'This opportunity is focused on ' . implode(', ', $scholarship['eligible_levels']) . '.',
    ];

    $fieldPass = in_array('All Disciplines', $scholarship['fields'], true)
        || in_array($user['field'], $scholarship['fields'], true)
        || count(array_intersect($user['interests'], $scholarship['fields'])) > 0;
    $criteria[] = [
        'label' => 'Academic fit',
        'passed' => $fieldPass,
        'detail' => $fieldPass
            ? 'Your academic profile aligns with the scholarship scope.'
            : 'Your field is not in the preferred list for this opportunity.',
    ];

    $gpaPass = (float)$user['gpa'] >= (float)$scholarship['minimum_gpa'];
    $criteria[] = [
        'label' => 'Academic record',
        'passed' => $gpaPass,
        'detail' => $gpaPass
            ? 'Your GPA meets the current benchmark.'
            : "A GPA near {$scholarship['minimum_gpa']} is recommended.",
    ];

    $readyTypes = [];
    foreach ($documents as $document) {
        if (in_array($document['status'], ['ready', 'verified'], true)) {
            $readyTypes[] = $document['type'];
        }
    }

    $missing = array_values(array_diff($scholarship['requirements'], $readyTypes));
    $documentPass = $missing === [];
    $criteria[] = [
        'label' => 'Document readiness',
        'passed' => $documentPass,
        'detail' => $documentPass
            ? 'All required document groups are ready.'
            : 'Missing: ' . implode(', ', array_map('scholarship_document_label', $missing)) . '.',
    ];

    $passed = count(array_filter($criteria, fn (array $criterion): bool => (bool)$criterion['passed']));
    $matchPercent = (int)round(($passed / count($criteria)) * 100);

    return [
        'scholarship' => $scholarship,
        'criteria' => $criteria,
        'match_percent' => $matchPercent,
        'eligible' => $matchPercent >= 75,
        'missing_documents' => array_map('scholarship_document_label', $missing),
        'recommendation' => $matchPercent >= 75
            ? 'Strong fit. Move this opportunity into active preparation.'
            : 'Promising, but close the gaps before investing in a full application.',
        'next_steps' => $documentPass
            ? ['Review grant details.', 'Start application timeline.', 'Book a mentor review.']
            : ['Prepare missing documents.', 'Review eligibility details.', 'Track this grant for follow-up.'],
    ];
}

function scholarship_tracked_items(): array
{
    scholarship_init_session_state();

    $items = [];
    foreach ($_SESSION['tracked_grants'] as $id) {
        $scholarship = scholarship_find_by_id((string)$id);
        if ($scholarship === null) {
            continue;
        }

        $eligibility = scholarship_eligibility($scholarship);
        $items[] = [
            'scholarship' => $scholarship,
            'status' => scholarship_tracker_status($scholarship['id']),
            'match_percent' => $eligibility['match_percent'],
            'missing_documents' => $eligibility['missing_documents'],
        ];
    }

    usort($items, fn (array $a, array $b): int => strcmp(
        $a['scholarship']['deadline_date'],
        $b['scholarship']['deadline_date']
    ));

    return $items;
}

function scholarship_initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $initials = '';

    foreach ($parts as $part) {
        $initials .= substr($part, 0, 1);
    }

    return strtoupper(substr($initials, 0, 2) ?: 'ES');
}

function scholarship_status_label(string $status): string
{
    return match ($status) {
        'watching' => 'Watching',
        'preparing' => 'Preparing',
        'submitted' => 'Submitted',
        'archived' => 'Archived',
        default => ucfirst($status),
    };
}

function scholarship_view_model(): array
{
    scholarship_init_session_state();

    $fixture = scholarship_fixture();
    $filters = scholarship_filters();
    $selectedEligibility = null;
    $details = null;

    $eligibilityId = trim((string)($_GET['eligibility'] ?? ''));
    if ($eligibilityId !== '') {
        $scholarship = scholarship_find_by_id($eligibilityId);
        if ($scholarship !== null) {
            $selectedEligibility = scholarship_eligibility($scholarship);
        }
    }

    $detailsId = trim((string)($_GET['details'] ?? ''));
    if ($detailsId !== '') {
        $details = scholarship_find_by_id($detailsId);
    }

    return [
        'user' => $fixture['current_user'],
        'documents' => $fixture['documents'],
        'mentors' => $fixture['mentors'],
        'testimonial' => $fixture['testimonial'],
        'filters' => $filters,
        'filter_options' => scholarship_filter_options(),
        'featured' => scholarship_featured(),
        'scholarships' => scholarship_filtered($filters),
        'tracked_items' => scholarship_tracked_items(),
        'selected_eligibility' => $selectedEligibility,
        'details' => $details,
        'flash' => scholarship_flash(),
        'token' => scholarship_token(),
    ];
}

