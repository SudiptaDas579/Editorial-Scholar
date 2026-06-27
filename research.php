<?php
declare(strict_types=1);

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth_helpers.php';

// ── Nav variables ─────────────────────────────────────────────────────────────
$base       = BASE_URL;
$activeNav  = 'research';
$isLoggedIn = is_logged_in();
$role       = $_SESSION['role']      ?? '';
$userName   = $_SESSION['full_name'] ?? '';
$parts      = array_filter(explode(' ', trim($userName)));
if (count($parts) >= 2) {
    $userInitials = strtoupper(substr($parts[0], 0, 1) . substr(end($parts), 0, 1));
} elseif (count($parts) === 1) {
    $userInitials = strtoupper(substr($parts[0], 0, 2));
} else {
    $userInitials = '?';
}
// ─────────────────────────────────────────────────────────────────────────────

// Initialize sidebar filter inputs dynamically from database
try {
    $pdo = getDB();

    $countriesStmt = $pdo->query('SELECT DISTINCT country FROM research_institutions ORDER BY country ASC');
    $countries = $countriesStmt->fetchAll(PDO::FETCH_COLUMN);

    $majorsStmt = $pdo->query('SELECT DISTINCT major FROM research_programs ORDER BY major ASC');
    $majors = $majorsStmt->fetchAll(PDO::FETCH_COLUMN);

    $maxFeeStmt = $pdo->query('SELECT MAX(tuition_fee) FROM research_programs');
    $maxFee = (int)($maxFeeStmt->fetchColumn() ?: 50000);
} catch (\Throwable $e) {
    $countries = ['United Kingdom', 'Switzerland', 'Singapore'];
    $majors    = ['Economics & Philosophy', 'Computer Science', 'Fine Arts', 'Biomedical Science'];
    $maxFee    = 50000;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Research Programs — The Editorial Scholar</title>

    <!-- Shared assets -->
    <link rel="stylesheet" href="<?= $base ?>/src/output.css">
    <link href="https://fonts.googleapis.com/css2?family=Newsreader:wght@400;600;700&family=Manrope:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet">

    <!-- Page-specific stylesheet (unchanged) -->
    <link rel="stylesheet" href="akibulStyle.css">

    <style>
        /* Push content below fixed nav */
        body { padding-top: 60px; }

        /* Nav dropdown animation */
        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-6px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in { animation: fadeInDown 0.15s ease; }

        /* ── All original page styles below (unchanged) ── */
        .actions button {
            cursor: pointer;
            transition: background 0.2s, transform 0.1s;
        }
        .actions button:hover {
            background: #8e6c32;
            transform: translateY(-1px);
        }
        .actions button:active { transform: translateY(0); }
        .actions a { transition: color 0.2s; }
        .actions a:hover { color: #b08a44; text-decoration: underline; }
        .buttons button {
            cursor: pointer;
            transition: all 0.2s;
        }
        .buttons button:hover { background: #0d1b2a; color: white; }
        .buttons button.active { background: #0d1b2a; color: white; border-color: #0d1b2a; }
        .pagination button {
            margin: 0 3px;
            padding: 6px 12px;
            border: 1px solid #ccc;
            background: white;
            cursor: pointer;
            transition: all 0.2s;
        }
        .pagination button:hover,
        .pagination button.active { background: #0d1b2a; color: white; border-color: #0d1b2a; }
        .pagination span { padding: 0 8px; color: gray; }
        input[type="range"] { accent-color: #b08a44; cursor: pointer; }
        .search {
            transition: border-color 0.2s, box-shadow 0.2s;
            outline: none;
        }
        .search:focus {
            border-color: #b08a44;
            box-shadow: 0 0 0 3px rgba(176,138,68,0.15);
        }
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(13, 27, 42, 0.6);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
        }
        .modal-overlay.active { opacity: 1; pointer-events: auto; }
        .modal-box {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);
            width: 100%;
            max-width: 480px;
            overflow: hidden;
            transform: scale(0.95);
            transition: transform 0.3s ease;
            font-family: system-ui, -apple-system, sans-serif;
        }
        .modal-overlay.active .modal-box { transform: scale(1); }
        .modal-header {
            background: #0d1b2a;
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h3 { margin: 0; font-size: 18px; font-weight: 600; }
        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 20px;
            cursor: pointer;
            opacity: 0.8;
            transition: opacity 0.2s;
        }
        .modal-close:hover { opacity: 1; }
        .modal-body { padding: 24px; color: #334155; line-height: 1.5; }
        .modal-body p { margin: 0 0 12px 0; }
        .modal-footer {
            padding: 16px 24px;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }
        .btn-modal-close {
            background: #0d1b2a;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-modal-close:hover { background: #1e293b; }
        .match-score {
            display: inline-block;
            background: #fef08a;
            color: #854d0e;
            padding: 4px 12px;
            border-radius: 999px;
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 12px;
        }
        .match-score.high { background: #bbf7d0; color: #166534; }
        .card { transition: transform 0.2s, box-shadow 0.2s; }
        .card:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05); }
        .skeleton {
            background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
            background-size: 200% 100%;
            animation: shimmer 1.5s infinite;
            border-radius: 4px;
        }
        @keyframes shimmer {
            0%   { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/includes/nav_helper.php'; ?>

<!-- HERO -->
<section class="hero">
    <div class="hero-left">
        <h1>Curating Your <span>Academic Destiny</span></h1>
        <p>
            Navigate the world's most prestigious institutions. Our intelligence-driven
            matcher aligns your intellectual profile with global opportunities and
            certified scholarships.
        </p>
    </div>
    <div class="hero-right">
        <p class="db-label">LIVE DATABASE</p>
        <h2 id="total-institutions-count">5+</h2>
        <p>Institutions Analyzed</p>
    </div>
</section>

<!-- MAIN -->
<div class="container">

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <h3>Refine Selection</h3>

        <div class="filter">
            <p class="label">TARGET COUNTRY</p>
            <div id="countries-checkbox-group" style="display:flex; flex-direction:column; gap:8px;">
                <?php foreach ($countries as $c): ?>
                    <label style="display:flex; align-items:center; gap:8px; font-size:14px; cursor:pointer;">
                        <input type="checkbox" name="countries[]" value="<?= htmlspecialchars($c) ?>" checked
                               style="accent-color:#b08a44; cursor:pointer;">
                        <?= htmlspecialchars($c) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="filter">
            <p class="label">ACADEMIC MAJOR</p>
            <select id="major-select" style="padding:8px 12px; border:1px solid #ccc; background:white; font-size:14px;">
                <option value="all">All Majors</option>
                <?php foreach ($majors as $m): ?>
                    <option value="<?= htmlspecialchars($m) ?>"><?= htmlspecialchars($m) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter">
            <p class="label">DEGREE LEVEL</p>
            <div class="buttons" id="degree-buttons-group">
                <button data-level="all" class="active">All</button>
                <button data-level="Undergraduate">Undergraduate</button>
                <button data-level="Postgraduate">Postgraduate</button>
                <button data-level="Doctorate">Doctorate</button>
            </div>
        </div>

        <div class="filter">
            <p class="label">ANNUAL TUITION LIMIT</p>
            <input type="range" id="budget-range" min="0" max="<?= $maxFee ?>" value="<?= $maxFee ?>" style="width:100%;">
            <div class="budget-value">
                <p>£0</p>
                <p id="budget-display">£<?= number_format($maxFee) ?></p>
            </div>
        </div>
    </aside>

    <!-- CONTENT -->
    <main class="content">
        <input class="search" type="text" id="search-input"
               placeholder="Search by University, Scholarship Name, or Research Topic...">

        <div id="cards-container"></div>

        <div class="results-container">
            <p class="results" id="results-count" style="font-size:14px; color:#555;">Showing 0-0 of 0 results</p>
            <div class="pagination" id="pagination-container"></div>
        </div>
    </main>
</div>

<!-- FOOTER (matches site-wide) -->
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
                <a href="#" class="font-manrope font-normal text-sm tracking-[0.35px] text-[#64748B] hover:text-[#0F172A] transition-colors no-underline">Research Papers</a>
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

<!-- INTERACTION MODAL (unchanged) -->
<div class="modal-overlay" id="interaction-modal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 id="modal-title">Opportunity Matching</h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body" id="modal-body-content"></div>
        <div class="modal-footer">
            <button class="btn-modal-close" onclick="closeModal()">Close</button>
        </div>
    </div>
</div>

<script>
    let selectedDegreeLevel = 'all';

    let searchTimeout = null;
    document.getElementById('search-input').addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => loadResearchPrograms(1), 300);
    });

    document.querySelectorAll('#countries-checkbox-group input[type="checkbox"]').forEach(box => {
        box.addEventListener('change', () => loadResearchPrograms(1));
    });

    document.getElementById('major-select').addEventListener('change', () => loadResearchPrograms(1));

    document.querySelectorAll('#degree-buttons-group button').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('#degree-buttons-group button').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            selectedDegreeLevel = btn.dataset.level;
            loadResearchPrograms(1);
        });
    });

    const budgetRange   = document.getElementById('budget-range');
    const budgetDisplay = document.getElementById('budget-display');
    budgetRange.addEventListener('input', () => {
        const formatted = new Intl.NumberFormat('en-GB', { style:'currency', currency:'GBP', maximumFractionDigits:0 }).format(budgetRange.value);
        budgetDisplay.textContent = formatted + (budgetRange.value == budgetRange.max ? '+' : '');
    });
    budgetRange.addEventListener('change', () => loadResearchPrograms(1));

    function escapeHtml(string) {
        return String(string).replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[s]));
    }

    async function loadResearchPrograms(page = 1) {
        const cardsContainer    = document.getElementById('cards-container');
        const resultsCount      = document.getElementById('results-count');
        const paginationContainer = document.getElementById('pagination-container');

        cardsContainer.innerHTML = Array(2).fill(0).map(() => `
            <div class="card" style="height:240px; display:flex; gap:20px; border:1px solid #eee; padding:20px; background:white; margin-bottom:40px;">
                <div class="skeleton" style="width:250px; height:100%;"></div>
                <div style="flex:1; display:flex; flex-direction:column; gap:12px;">
                    <div class="skeleton" style="width:80px; height:16px;"></div>
                    <div class="skeleton" style="width:70%; height:24px;"></div>
                    <div class="skeleton" style="width:100%; height:48px;"></div>
                    <div class="skeleton" style="width:50%; height:20px; margin-top:auto;"></div>
                </div>
            </div>
        `).join('');

        const search           = document.getElementById('search-input').value;
        const checkedCountries = Array.from(document.querySelectorAll('#countries-checkbox-group input[type="checkbox"]:checked')).map(b => b.value);
        const major            = document.getElementById('major-select').value;
        const budget           = budgetRange.value;

        const params = new URLSearchParams();
        if (search)                          params.append('search', search);
        if (checkedCountries.length > 0)     params.append('countries', checkedCountries.join(','));
        if (major && major !== 'all')        params.append('major', major);
        if (selectedDegreeLevel !== 'all')   params.append('degree_level', selectedDegreeLevel);
        if (budget)                          params.append('max_budget', budget);
        params.append('page', page.toString());
        params.append('limit', '3');

        try {
            const response = await fetch(`research_api.php?${params.toString()}`);
            const result   = await response.json();

            if (result.status !== 'success') throw new Error(result.message || 'API request failed');

            if (result.data.length === 0) {
                cardsContainer.innerHTML = `
                    <div style="padding:40px; text-align:center; background:white; border:1px solid #eee; margin-bottom:40px; color:#64748b; font-family:system-ui,sans-serif;">
                        <i class="ri-search-line" style="font-size:48px; color:#cbd5e1; display:block; margin-bottom:12px;"></i>
                        <strong style="font-size:16px; color:#1e293b;">No programs matched your selection</strong>
                        <p style="margin:6px 0 0 0; font-size:14px;">Try modifying your search queries or resetting the filters in the sidebar.</p>
                    </div>
                `;
            } else {
                cardsContainer.innerHTML = result.data.map(item => {
                    const formattedFee = new Intl.NumberFormat('en-GB', { style:'currency', currency:'GBP', maximumFractionDigits:0 }).format(item.tuition_fee);
                    return `
                        <div class="card">
                            <img src="${escapeHtml(item.image_url)}" alt="${escapeHtml(item.institution_name)}">
                            <div class="card-content">
                                <p class="rank">QS RANK: #${escapeHtml(String(item.qs_rank).padStart(2, '0'))}</p>
                                <h3>${escapeHtml(item.institution_name)}</h3>
                                <p>${escapeHtml(item.description)}</p>
                                <p style="font-size:14px; color:#b08a44; font-weight:bold; margin-top:10px; margin-bottom:4px;">
                                    ${escapeHtml(item.title)} — ${escapeHtml(item.major)}
                                </p>
                                <div class="tags" style="margin-top:8px;">
                                    <span style="background:#f1f5f9; padding:2px 8px; border-radius:4px; font-size:12px; font-weight:500;">${escapeHtml(item.scholarship_type)}</span>
                                    <span style="background:#f1f5f9; padding:2px 8px; border-radius:4px; font-size:12px; font-weight:500;">${escapeHtml(item.country)}</span>
                                    <span style="background:#f1f5f9; padding:2px 8px; border-radius:4px; font-size:12px; font-weight:500;">${escapeHtml(item.intake_info)}</span>
                                    <span style="background:#fef3c7; color:#d97706; padding:2px 8px; border-radius:4px; font-size:12px; font-weight:600;">Est. Fee: ${formattedFee}</span>
                                </div>
                                <div class="actions">
                                    <a href="${escapeHtml(item.prospectus_url)}" target="_blank">View Prospectus</a>
                                    <button onclick="handleAction(${item.id}, '${escapeHtml(item.title)}', '${escapeHtml(item.institution_name)}', '${escapeHtml(item.action_label)}')">${escapeHtml(item.action_label)}</button>
                                </div>
                            </div>
                        </div>
                    `;
                }).join('');
            }

            const pg = result.pagination;
            resultsCount.textContent = `Showing ${pg.showing_start}-${pg.showing_end} of ${pg.total_results} results`;
            document.getElementById('total-institutions-count').textContent = pg.total_results + '+';
            renderPagination(pg);

        } catch (error) {
            console.error(error);
            cardsContainer.innerHTML = `
                <div style="padding:30px; text-align:center; background:#fef2f2; border:1px solid #fecaca; border-radius:8px; color:#991b1b; margin-bottom:40px; font-family:system-ui,sans-serif;">
                    <strong>Error loading search results</strong>
                    <p style="margin:6px 0 0 0; font-size:13px;">Please verify your database connection is active or try reloading the page.</p>
                </div>
            `;
        }
    }

    function renderPagination(pg) {
        const container = document.getElementById('pagination-container');
        container.innerHTML = '';
        if (pg.total_pages <= 1) return;

        const current = pg.current_page;
        const total   = pg.total_pages;

        if (current > 1) {
            const btn = document.createElement('button');
            btn.innerHTML = '&lt;';
            btn.title = 'Previous Page';
            btn.onclick = () => loadResearchPrograms(current - 1);
            container.appendChild(btn);
        }

        for (let i = 1; i <= total; i++) {
            if (i === 1 || i === total || (i >= current - 1 && i <= current + 1)) {
                const btn = document.createElement('button');
                btn.textContent = i;
                if (i === current) btn.className = 'active';
                btn.onclick = () => loadResearchPrograms(i);
                container.appendChild(btn);
            } else if (i === current - 2 || i === current + 2) {
                const span = document.createElement('span');
                span.textContent = '...';
                container.appendChild(span);
            }
        }

        if (current < total) {
            const btn = document.createElement('button');
            btn.innerHTML = '&gt;';
            btn.title = 'Next Page';
            btn.onclick = () => loadResearchPrograms(current + 1);
            container.appendChild(btn);
        }
    }

    function handleAction(programId, title, institution, actionLabel) {
        const overlay    = document.getElementById('interaction-modal');
        const modalTitle = document.getElementById('modal-title');
        const modalBody  = document.getElementById('modal-body-content');

        modalTitle.textContent = actionLabel;

        let htmlContent = '';
        if (actionLabel === 'Match Eligibility') {
            htmlContent = `
                <div class="match-score high">92% Profile Fit</div>
                <p><strong>Program:</strong> ${escapeHtml(title)}</p>
                <p><strong>University:</strong> ${escapeHtml(institution)}</p>
                <hr style="margin:16px 0; border:0; border-top:1px solid #e2e8f0;">
                <p style="font-size:14px; color:#475569;">
                    ✅ Academic qualifications meet baseline GPA thresholds.<br>
                    ✅ Target country aligns with your regional visa preference.<br>
                    ⚠️ Action Required: Submit certified IELTS or TOEFL scores prior to application submission.
                </p>
            `;
        } else if (actionLabel === 'Join Webinar') {
            htmlContent = `
                <p>Register to join our live admissions discussion for <strong>${escapeHtml(institution)}</strong>.</p>
                <p>📅 <strong>Schedule:</strong> Thursday, June 18, 2026, 4:00 PM GMT</p>
                <div style="background:#f0fdf4; padding:12px; border-radius:6px; border:1px solid #bbf7d0; font-size:13px; color:#15803d; margin-top:16px;">
                    <i class="ri-checkbox-circle-line"></i> A calendar link has been sent to your registered profile email.
                </div>
            `;
        } else {
            htmlContent = `
                <p>Checking status pipeline for <strong>${escapeHtml(title)}</strong> at <strong>${escapeHtml(institution)}</strong>...</p>
                <p style="font-size:14px;">📝 Application status: <strong>Open / Receiving Files</strong>.</p>
                <p style="font-size:14px; color:#475569; margin-top:8px;">Ready to proceed? Click below to load your advisor review panel.</p>
            `;
        }

        modalBody.innerHTML = htmlContent;
        overlay.classList.add('active');
    }

    function closeModal() {
        document.getElementById('interaction-modal').classList.remove('active');
    }

    document.getElementById('interaction-modal').addEventListener('click', e => {
        if (e.target.id === 'interaction-modal') closeModal();
    });

    window.addEventListener('DOMContentLoaded', () => loadResearchPrograms(1));
</script>

</body>
</html>