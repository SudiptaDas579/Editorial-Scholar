<?php

declare(strict_types=1);

require_once __DIR__ . '/config/scholarship_backend.php';

scholarship_handle_post();
$vm = scholarship_view_model();

function page_url(array $params = []): string
{
    $query = array_merge($_GET, $params);
    foreach ($query as $key => $value) {
        if ($value === null || $value === '') {
            unset($query[$key]);
        }
    }

    return '/scholarship.php' . ($query === [] ? '' : '?' . http_build_query($query));
}

function selected_attr(string $actual, string $expected): string
{
    return $actual === $expected ? ' selected' : '';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scholarship Portal - The Editorial Scholar</title>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@0,600;1,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/src/output.css">
    <style>
        :root {
            --primary-dark: #0A192F;
            --accent-gold: #8B6E30;
            --bg-light: #F4F7F9;
            --white: #FFFFFF;
            --text-gray: #64748B;
            --border-color: #E2E8F0;
            --success: #059669;
            --danger: #DC2626;
            --warning: #A16207;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-light);
            margin: 0;
            color: var(--primary-dark);
        }

        button, input, select {
            font: inherit;
        }

        button {
            cursor: pointer;
        }

        .app-container {
            display: grid;
            grid-template-columns: 240px 1fr;
            min-height: calc(100vh - 60px);
        }

        .portal-sidebar {
            background: var(--white);
            padding: 40px 20px;
            border-right: 1px solid var(--border-color);
        }

        .user-card {
            background: var(--bg-light);
            padding: 12px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 32px;
        }

        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 6px;
            background: var(--primary-dark);
            color: white;
            display: grid;
            place-items: center;
            font-weight: 800;
            font-size: 0.8rem;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            text-decoration: none;
            color: var(--text-gray);
            font-size: 0.9rem;
            margin-bottom: 5px;
            transition: 0.2s ease;
            border-radius: 4px;
        }

        .nav-item:hover,
        .nav-item.active {
            background: var(--white);
            color: var(--accent-gold);
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        main {
            padding: 40px;
            min-width: 0;
        }

        .portal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
        }

        .search-bar {
            background: #EDF2F7;
            border-radius: 999px;
            padding: 8px 18px;
            display: flex;
            align-items: center;
            width: min(360px, 100%);
        }

        .search-bar input {
            background: none;
            border: none;
            outline: none;
            font-size: 0.85rem;
            width: 100%;
        }

        .flash {
            padding: 14px 16px;
            border-radius: 8px;
            margin-bottom: 22px;
            border: 1px solid var(--border-color);
            background: #FFFFFF;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .flash.success { color: var(--success); }
        .flash.error { color: var(--danger); }

        .hero-banner {
            background: linear-gradient(135deg, #0A192F 0%, #1A2E44 100%);
            border-radius: 12px;
            padding: 56px;
            color: white;
            position: relative;
            overflow: hidden;
            margin-bottom: 28px;
            min-height: 330px;
            display: flex;
            align-items: flex-end;
        }

        .hero-banner::after {
            content: "";
            position: absolute;
            inset: 0;
            background:
                linear-gradient(90deg, rgba(10,25,47,0.95) 0%, rgba(10,25,47,0.78) 48%, rgba(10,25,47,0.25) 100%),
                var(--hero-image) center/cover;
            opacity: 0.58;
        }

        .hero-content {
            position: relative;
            z-index: 1;
            max-width: 680px;
        }

        .hero-banner h1 {
            font-family: 'Playfair Display', serif;
            font-size: clamp(2.5rem, 5vw, 3.7rem);
            margin: 0 0 18px 0;
            line-height: 1.08;
            letter-spacing: 0;
        }

        .hero-banner p {
            max-width: 560px;
            opacity: 0.9;
            font-size: 1.05rem;
            margin-bottom: 26px;
        }

        .hero-actions,
        .card-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }

        .btn-primary,
        .btn-secondary,
        .btn-link {
            min-height: 42px;
            border-radius: 5px;
            font-weight: 700;
            padding: 10px 16px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-primary {
            background: var(--accent-gold);
            color: white;
            border: 1px solid var(--accent-gold);
        }

        .btn-secondary {
            background: white;
            color: var(--primary-dark);
            border: 1px solid var(--border-color);
        }

        .btn-link {
            color: inherit;
            border: 1px solid rgba(255,255,255,0.45);
            background: rgba(255,255,255,0.08);
        }

        .filter-row {
            background: var(--white);
            padding: 20px;
            border-radius: 8px;
            display: grid;
            grid-template-columns: repeat(4, minmax(140px, 1fr)) auto;
            gap: 15px;
            margin-bottom: 34px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.02);
        }

        .filter-row label {
            display: block;
            font-size: 0.64rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            margin-bottom: 6px;
        }

        select,
        .field {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            background: var(--bg-light);
            color: var(--primary-dark);
        }

        .content-layout {
            display: grid;
            grid-template-columns: minmax(0, 2fr) minmax(300px, 1fr);
            gap: 30px;
            align-items: start;
        }

        .section-title {
            font-family: 'Playfair Display', serif;
            font-style: italic;
            font-size: 1.65rem;
            margin: 0 0 18px;
        }

        .scholarship-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
        }

        .scholarship-card,
        .widget,
        .result-panel {
            background: var(--white);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            overflow: hidden;
        }

        .scholarship-card {
            display: flex;
            flex-direction: column;
            min-height: 100%;
        }

        .card-img {
            height: 180px;
            background: #cbd5e1 center/cover;
            position: relative;
        }

        .tag {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(255,255,255,0.94);
            padding: 5px 10px;
            border-radius: 999px;
            font-size: 0.68rem;
            font-weight: 800;
            text-transform: uppercase;
        }

        .card-body {
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            flex: 1;
        }

        .card-body h3 {
            font-size: 1.18rem;
            margin: 0;
        }

        .card-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            font-size: 0.78rem;
            color: var(--text-gray);
        }

        .pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            border: 1px solid var(--border-color);
            border-radius: 999px;
            padding: 4px 8px;
            background: #F8FAFC;
        }

        .widget {
            padding: 24px;
            margin-bottom: 22px;
        }

        .widget h3 {
            font-size: 1rem;
            margin: 0 0 14px;
        }

        .track-item,
        .doc-item,
        .mentor-item {
            display: flex;
            gap: 12px;
            padding: 14px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .track-item:last-child,
        .doc-item:last-child,
        .mentor-item:last-child {
            border-bottom: none;
        }

        .icon-box {
            width: 36px;
            height: 36px;
            background: #F1F5F9;
            border-radius: 6px;
            display: grid;
            place-items: center;
            flex: 0 0 auto;
        }

        .muted {
            color: var(--text-gray);
            font-size: 0.78rem;
        }

        .status {
            font-size: 0.68rem;
            font-weight: 800;
            text-transform: uppercase;
        }

        .status.success { color: var(--success); }
        .status.warning { color: var(--warning); }
        .status.danger { color: var(--danger); }

        .tracker-form {
            display: flex;
            gap: 8px;
            margin-top: 10px;
        }

        .tracker-form select {
            min-width: 0;
        }

        .small-button {
            border: 1px solid var(--border-color);
            background: white;
            border-radius: 4px;
            padding: 8px 10px;
            font-size: 0.78rem;
            font-weight: 800;
        }

        .testimonial {
            background: var(--primary-dark);
            color: white;
            padding: 28px;
            border-radius: 12px;
            font-size: 0.9rem;
            line-height: 1.6;
            font-style: italic;
        }

        .result-panel {
            padding: 24px;
            margin-bottom: 28px;
        }

        .result-header {
            display: flex;
            justify-content: space-between;
            gap: 18px;
            align-items: flex-start;
            margin-bottom: 18px;
        }

        .score-ring {
            width: 76px;
            height: 76px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            background: conic-gradient(var(--accent-gold) calc(var(--score) * 1%), #E2E8F0 0);
            font-weight: 900;
            color: var(--primary-dark);
            flex: 0 0 auto;
        }

        .score-ring span {
            width: 58px;
            height: 58px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            background: white;
        }

        .criteria-list {
            display: grid;
            gap: 10px;
        }

        .criterion {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 12px;
            display: grid;
            gap: 4px;
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            margin-top: 16px;
        }

        .detail-box {
            background: #F8FAFC;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 12px;
        }

        .empty-state {
            background: white;
            border: 1px dashed var(--border-color);
            border-radius: 12px;
            padding: 30px;
            color: var(--text-gray);
        }

        @media (max-width: 1100px) {
            .app-container,
            .content-layout {
                grid-template-columns: 1fr;
            }

            .portal-sidebar {
                border-right: none;
                border-bottom: 1px solid var(--border-color);
                padding: 20px;
            }

            .portal-sidebar nav {
                display: grid;
                grid-template-columns: repeat(5, minmax(120px, 1fr));
                gap: 8px;
                overflow-x: auto;
            }
        }

        @media (max-width: 760px) {
            main {
                padding: 22px;
            }

            .portal-header {
                align-items: stretch;
                flex-direction: column;
            }

            .hero-banner {
                padding: 32px 24px;
                min-height: 380px;
            }

            .filter-row,
            .scholarship-grid,
            .details-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
  <nav class="top-0 left-0 right-0 z-50 bg-white/80 backdrop-blur-md border-b border-[#F1F5F9]">
    <div class="px-8 h-[60px] flex items-center justify-between gap-4">
      <span class="font-newsreader font-bold text-3xl text-[#0F172A] whitespace-nowrap">
        The Editorial Scholar
      </span>
      <div class="hidden md:flex items-center gap-8">
        <a href="index.html" class="font-newsreader font-semibold text-base tracking-[-0.4px] text-[#475569] pb-1">Programs</a>
        <a href="scholarship.php" class="font-newsreader font-semibold text-base tracking-[-0.4px] text-[#A16207] hover:text-[#0F172A] transition-colors border-b-2 border-[#A16207]">Scholarships</a>
        <a href="testPrep.html" class="font-newsreader font-semibold text-base tracking-[-0.4px] text-[#475569] hover:text-[#0F172A] transition-colors">Test Prep</a>
        <a href="visa.html" class="font-newsreader font-semibold text-base tracking-[-0.4px] text-[#475569] hover:text-[#0F172A] transition-colors">Visa Guide</a>
        <a href="research.html" class="font-newsreader font-semibold text-base tracking-[-0.4px] text-[#475569] hover:text-[#0F172A] transition-colors">Research</a>
      </div>
      <a href="index.html" class="font-newsreader font-semibold text-sm tracking-[0.35px] text-[#031632]">
        Back To Explore
      </a>
    </div>
  </nav>

<div class="app-container">
    <aside class="portal-sidebar">
        <div class="user-card">
            <div class="avatar"><?= scholarship_escape(scholarship_initials($vm['user']['name'])) ?></div>
            <div>
                <div style="font-weight: 800; font-size: 0.82rem;"><?= scholarship_escape($vm['user']['name']) ?></div>
                <div style="font-size: 0.72rem; color: var(--text-gray);">Scholar ID #<?= scholarship_escape($vm['user']['scholar_id']) ?></div>
            </div>
        </div>

        <nav>
            <a href="index.html" class="nav-item"><i data-lucide="layout-grid"></i> Dashboard</a>
            <a href="#search-portal" class="nav-item active"><i data-lucide="search"></i> Search Portal</a>
            <a href="#document-vault" class="nav-item"><i data-lucide="archive"></i> Document Vault</a>
            <a href="#deadlines" class="nav-item"><i data-lucide="calendar"></i> Deadlines</a>
            <a href="#mentorship" class="nav-item"><i data-lucide="users"></i> Mentorship</a>
        </nav>
    </aside>

    <main id="search-portal">
        <header class="portal-header">
            <div>
                <div style="font-weight: 900;">The Editorial Scholar</div>
                <div class="muted"><?= scholarship_escape($vm['user']['role']) ?> · <?= scholarship_escape($vm['user']['field']) ?> · GPA <?= scholarship_escape($vm['user']['gpa']) ?></div>
            </div>
            <form class="search-bar" method="get" action="/scholarship.php">
                <i data-lucide="search" style="width: 16px; margin-right: 10px; color: #94a3b8;"></i>
                <input type="text" name="search" value="<?= scholarship_escape($vm['filters']['search']) ?>" placeholder="Search funding...">
                <input type="hidden" name="region" value="<?= scholarship_escape($vm['filters']['region']) ?>">
                <input type="hidden" name="field" value="<?= scholarship_escape($vm['filters']['field']) ?>">
                <input type="hidden" name="funding_type" value="<?= scholarship_escape($vm['filters']['funding_type']) ?>">
                <input type="hidden" name="deadline" value="<?= scholarship_escape($vm['filters']['deadline']) ?>">
            </form>
        </header>

        <?php if ($vm['flash'] !== null): ?>
            <div class="flash <?= scholarship_escape($vm['flash']['type']) ?>"><?= scholarship_escape($vm['flash']['message']) ?></div>
        <?php endif; ?>

        <section class="hero-banner" style="--hero-image: url('<?= scholarship_escape($vm['featured']['image']) ?>');">
            <div class="hero-content">
                <span style="font-size: 0.72rem; letter-spacing: 1px; color: #EAB308; font-weight: 800;">FEATURED SELECTION 2026</span>
                <h1><?= scholarship_escape($vm['featured']['title']) ?></h1>
                <p><?= scholarship_escape($vm['featured']['description']) ?></p>
                <div class="hero-actions">
                    <form method="post" action="/scholarship.php">
                        <input type="hidden" name="_token" value="<?= scholarship_escape($vm['token']) ?>">
                        <input type="hidden" name="action" value="check_eligibility">
                        <input type="hidden" name="scholarship_id" value="<?= scholarship_escape($vm['featured']['id']) ?>">
                        <button class="btn-primary" type="submit"><i data-lucide="sparkles"></i> Check Eligibility</button>
                    </form>
                    <a class="btn-link" href="<?= scholarship_escape(page_url(['details' => $vm['featured']['id']])) ?>#grant-details">View Grant Details</a>
                </div>
            </div>
        </section>

        <form class="filter-row" method="get" action="/scholarship.php">
            <div>
                <label for="region">Region</label>
                <select id="region" name="region">
                    <option value="all">Global Selection</option>
                    <?php foreach ($vm['filter_options']['regions'] as $region): ?>
                        <option value="<?= scholarship_escape($region) ?>"<?= selected_attr($vm['filters']['region'], $region) ?>><?= scholarship_escape($region) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="field">Field of Study</label>
                <select id="field" name="field">
                    <option value="all">All Disciplines</option>
                    <?php foreach ($vm['filter_options']['fields'] as $field): ?>
                        <option value="<?= scholarship_escape($field) ?>"<?= selected_attr($vm['filters']['field'], $field) ?>><?= scholarship_escape($field) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="funding_type">Grant Type</label>
                <select id="funding_type" name="funding_type">
                    <option value="all">All Funding</option>
                    <?php foreach ($vm['filter_options']['funding_types'] as $fundingType): ?>
                        <option value="<?= scholarship_escape($fundingType) ?>"<?= selected_attr($vm['filters']['funding_type'], $fundingType) ?>><?= scholarship_escape($fundingType) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="deadline">Deadline</label>
                <select id="deadline" name="deadline">
                    <?php foreach ($vm['filter_options']['deadlines'] as $value => $label): ?>
                        <option value="<?= scholarship_escape($value) ?>"<?= selected_attr($vm['filters']['deadline'], $value) ?>><?= scholarship_escape($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display: flex; gap: 8px; align-items: end;">
                <input type="hidden" name="search" value="<?= scholarship_escape($vm['filters']['search']) ?>">
                <button class="btn-primary" type="submit" aria-label="Apply filters"><i data-lucide="sliders-horizontal"></i></button>
                <a class="btn-secondary" href="/scholarship.php" aria-label="Reset filters"><i data-lucide="rotate-ccw"></i></a>
            </div>
        </form>

        <?php if ($vm['selected_eligibility'] !== null): ?>
            <?php $eligibility = $vm['selected_eligibility']; ?>
            <section class="result-panel" id="eligibility-result">
                <div class="result-header">
                    <div>
                        <span class="status <?= $eligibility['eligible'] ? 'success' : 'warning' ?>"><?= $eligibility['eligible'] ? 'Strong profile match' : 'Needs preparation' ?></span>
                        <h2 class="section-title" style="margin-top: 6px;"><?= scholarship_escape($eligibility['scholarship']['title']) ?> Eligibility</h2>
                        <p class="muted"><?= scholarship_escape($eligibility['recommendation']) ?></p>
                    </div>
                    <div class="score-ring" style="--score: <?= scholarship_escape($eligibility['match_percent']) ?>;"><span><?= scholarship_escape($eligibility['match_percent']) ?>%</span></div>
                </div>
                <div class="criteria-list">
                    <?php foreach ($eligibility['criteria'] as $criterion): ?>
                        <div class="criterion">
                            <strong><?= $criterion['passed'] ? 'Pass' : 'Action Needed' ?> · <?= scholarship_escape($criterion['label']) ?></strong>
                            <span class="muted"><?= scholarship_escape($criterion['detail']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="card-actions" style="margin-top: 18px;">
                    <?php if (!scholarship_is_tracked($eligibility['scholarship']['id'])): ?>
                        <form method="post" action="/scholarship.php">
                            <input type="hidden" name="_token" value="<?= scholarship_escape($vm['token']) ?>">
                            <input type="hidden" name="action" value="track">
                            <input type="hidden" name="scholarship_id" value="<?= scholarship_escape($eligibility['scholarship']['id']) ?>">
                            <button class="btn-primary" type="submit"><i data-lucide="bookmark-plus"></i> Add to Tracker</button>
                        </form>
                    <?php endif; ?>
                    <a class="btn-secondary" href="<?= scholarship_escape(page_url(['eligibility' => null])) ?>">Close Result</a>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($vm['details'] !== null): ?>
            <?php $details = $vm['details']; ?>
            <section class="result-panel" id="grant-details">
                <div class="result-header">
                    <div>
                        <span class="status success"><?= scholarship_escape($details['program']) ?></span>
                        <h2 class="section-title" style="margin-top: 6px;"><?= scholarship_escape($details['title']) ?></h2>
                        <p class="muted"><?= scholarship_escape($details['description']) ?></p>
                    </div>
                    <a class="btn-secondary" href="<?= scholarship_escape(page_url(['details' => null])) ?>">Close</a>
                </div>
                <div class="details-grid">
                    <div class="detail-box"><strong>Institution</strong><br><span class="muted"><?= scholarship_escape($details['institutions']) ?></span></div>
                    <div class="detail-box"><strong>Funding</strong><br><span class="muted"><?= scholarship_escape($details['amount']) ?></span></div>
                    <div class="detail-box"><strong>Deadline</strong><br><span class="muted"><?= scholarship_escape($details['deadline_label']) ?></span></div>
                    <div class="detail-box"><strong>Duration</strong><br><span class="muted"><?= scholarship_escape($details['duration']) ?></span></div>
                    <div class="detail-box"><strong>Level</strong><br><span class="muted"><?= scholarship_escape($details['level']) ?></span></div>
                    <div class="detail-box"><strong>Documents</strong><br><span class="muted"><?= scholarship_escape(implode(', ', array_map('scholarship_document_label', $details['requirements']))) ?></span></div>
                </div>
            </section>
        <?php endif; ?>

        <div class="content-layout">
            <section>
                <h2 class="section-title">Open Applications</h2>

                <?php if ($vm['scholarships'] === []): ?>
                    <div class="empty-state">No scholarship matched the current filters. Try a broader region, funding type, or deadline.</div>
                <?php else: ?>
                    <div class="scholarship-grid">
                        <?php foreach ($vm['scholarships'] as $scholarship): ?>
                            <?php $isTracked = scholarship_is_tracked($scholarship['id']); ?>
                            <article class="scholarship-card">
                                <div class="card-img" style="background-image: url('<?= scholarship_escape($scholarship['image']) ?>');">
                                    <span class="tag"><?= scholarship_escape($scholarship['funding_type']) ?></span>
                                </div>
                                <div class="card-body">
                                    <span class="status success"><?= scholarship_escape($scholarship['program']) ?></span>
                                    <h3><?= scholarship_escape($scholarship['title']) ?></h3>
                                    <p class="muted"><?= scholarship_escape($scholarship['description']) ?></p>
                                    <div class="card-meta">
                                        <span class="pill"><i data-lucide="map-pin" style="width: 13px;"></i><?= scholarship_escape($scholarship['institutions']) ?></span>
                                        <span class="pill"><i data-lucide="calendar" style="width: 13px;"></i><?= scholarship_escape($scholarship['deadline_label']) ?></span>
                                        <span class="pill"><i data-lucide="graduation-cap" style="width: 13px;"></i><?= scholarship_escape($scholarship['level']) ?></span>
                                    </div>
                                    <div class="card-actions" style="margin-top: auto;">
                                        <form method="post" action="/scholarship.php">
                                            <input type="hidden" name="_token" value="<?= scholarship_escape($vm['token']) ?>">
                                            <input type="hidden" name="action" value="check_eligibility">
                                            <input type="hidden" name="scholarship_id" value="<?= scholarship_escape($scholarship['id']) ?>">
                                            <button class="btn-secondary" type="submit"><i data-lucide="badge-check"></i> Check Eligibility</button>
                                        </form>

                                        <?php if ($isTracked): ?>
                                            <form method="post" action="/scholarship.php">
                                                <input type="hidden" name="_token" value="<?= scholarship_escape($vm['token']) ?>">
                                                <input type="hidden" name="action" value="untrack">
                                                <input type="hidden" name="scholarship_id" value="<?= scholarship_escape($scholarship['id']) ?>">
                                                <button class="btn-secondary" type="submit"><i data-lucide="bookmark-check"></i> Tracked</button>
                                            </form>
                                        <?php else: ?>
                                            <form method="post" action="/scholarship.php">
                                                <input type="hidden" name="_token" value="<?= scholarship_escape($vm['token']) ?>">
                                                <input type="hidden" name="action" value="track">
                                                <input type="hidden" name="scholarship_id" value="<?= scholarship_escape($scholarship['id']) ?>">
                                                <button class="btn-primary" type="submit"><i data-lucide="bookmark-plus"></i> Track</button>
                                            </form>
                                        <?php endif; ?>

                                        <a class="btn-secondary" href="<?= scholarship_escape(page_url(['details' => $scholarship['id']])) ?>#grant-details"><i data-lucide="file-text"></i> Details</a>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <aside style="background: transparent; border: none; padding: 0;">
                <div class="widget" id="deadlines">
                    <h3>Tracked Grants</h3>
                    <?php if ($vm['tracked_items'] === []): ?>
                        <p class="muted">No tracked grants yet. Add one from the application cards.</p>
                    <?php endif; ?>
                    <?php foreach ($vm['tracked_items'] as $item): ?>
                        <?php $scholarship = $item['scholarship']; ?>
                        <div class="track-item">
                            <div class="icon-box"><i data-lucide="graduation-cap"></i></div>
                            <div style="min-width: 0; flex: 1;">
                                <div style="font-weight: 800; font-size: 0.84rem;"><?= scholarship_escape($scholarship['title']) ?></div>
                                <div class="status <?= $item['missing_documents'] === [] ? 'success' : 'danger' ?>">
                                    <?= scholarship_escape($item['match_percent']) ?>% Profile Match<?= $item['missing_documents'] === [] ? '' : ' · Missing Documents' ?>
                                </div>
                                <div class="muted">Deadline: <?= scholarship_escape($scholarship['deadline_label']) ?></div>
                                <form class="tracker-form" method="post" action="/scholarship.php">
                                    <input type="hidden" name="_token" value="<?= scholarship_escape($vm['token']) ?>">
                                    <input type="hidden" name="action" value="status">
                                    <input type="hidden" name="scholarship_id" value="<?= scholarship_escape($scholarship['id']) ?>">
                                    <select name="status" aria-label="Tracker status">
                                        <?php foreach (['watching', 'preparing', 'submitted', 'archived'] as $status): ?>
                                            <option value="<?= scholarship_escape($status) ?>"<?= selected_attr($item['status'], $status) ?>><?= scholarship_escape(scholarship_status_label($status)) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="small-button" type="submit">Update</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="widget" id="document-vault">
                    <h3>Document Vault</h3>
                    <?php foreach ($vm['documents'] as $document): ?>
                        <?php
                            $statusClass = match ($document['status']) {
                                'verified', 'ready' => 'success',
                                default => 'danger',
                            };
                        ?>
                        <div class="doc-item">
                            <div class="icon-box"><i data-lucide="file-check"></i></div>
                            <div>
                                <div style="font-weight: 800; font-size: 0.84rem;"><?= scholarship_escape($document['name']) ?></div>
                                <div class="status <?= scholarship_escape($statusClass) ?>"><?= scholarship_escape($document['status']) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="widget" id="mentorship">
                    <h3>Mentorship</h3>
                    <?php foreach ($vm['mentors'] as $mentor): ?>
                        <div class="mentor-item">
                            <div class="icon-box"><i data-lucide="users"></i></div>
                            <div>
                                <div style="font-weight: 800; font-size: 0.84rem;"><?= scholarship_escape($mentor['name']) ?></div>
                                <div class="muted"><?= scholarship_escape($mentor['focus']) ?></div>
                                <div class="status success"><?= scholarship_escape($mentor['availability']) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="testimonial">
                    <p>"<?= scholarship_escape($vm['testimonial']['quote']) ?>"</p>
                    <div style="display: flex; align-items: center; gap: 10px; margin-top: 20px;">
                        <div class="avatar" style="width: 34px; height: 34px; background: #334155;"><?= scholarship_escape(scholarship_initials($vm['testimonial']['name'])) ?></div>
                        <div>
                            <div style="font-size: 0.78rem; font-weight: 800; font-style: normal;"><?= scholarship_escape($vm['testimonial']['name']) ?></div>
                            <div style="font-size: 0.68rem; opacity: 0.75; font-style: normal; text-transform: uppercase;"><?= scholarship_escape($vm['testimonial']['title']) ?></div>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </main>
</div>

<footer class="w-full bg-[#F8FAFC] border-t border-[#E2E8F0] py-12">
    <div class="max-w-[1280px] mx-auto px-8">
      <div class="grid grid-cols-4 gap-8">
        <div class="flex flex-col gap-4">
          <p class="font-newsreader font-bold text-lg text-[#0F172A]">The Editorial Scholar</p>
          <p class="font-manrope font-normal text-sm leading-5 tracking-[0.35px] text-[#64748B]">
            &copy; 2026 The Editorial Scholar.<br/>Curating Global Futures.
          </p>
        </div>
        <div class="flex flex-col gap-3">
          <p class="font-manrope font-bold text-sm tracking-[1.4px] uppercase text-[#0F172A] mb-2">Company</p>
          <a href="#" class="font-manrope font-normal text-sm tracking-[0.35px] text-[#64748B] hover:text-[#0F172A] transition-colors">About Us</a>
          <a href="#" class="font-manrope font-normal text-sm tracking-[0.35px] text-[#64748B] hover:text-[#0F172A] transition-colors">Contact Support</a>
        </div>
        <div class="flex flex-col gap-3">
          <p class="font-manrope font-bold text-sm tracking-[1.4px] uppercase text-[#0F172A] mb-2">Legal</p>
          <a href="#" class="font-manrope font-normal text-sm tracking-[0.35px] text-[#64748B] hover:text-[#0F172A] transition-colors">Terms of Service</a>
          <a href="#" class="font-manrope font-normal text-sm tracking-[0.35px] text-[#64748B] hover:text-[#0F172A] transition-colors">Privacy Policy</a>
          <a href="#" class="font-manrope font-normal text-sm tracking-[0.35px] text-[#64748B] hover:text-[#0F172A] transition-colors">Academic Integrity</a>
        </div>
        <div class="flex flex-col gap-3">
          <p class="font-manrope font-bold text-sm tracking-[1.4px] uppercase text-[#0F172A] mb-2">Social</p>
          <div class="flex items-center gap-4 mt-1">
            <a href="#" class="text-[#64748B] hover:text-[#0F172A] transition-colors"><i data-lucide="twitter"></i></a>
            <a href="#" class="text-[#64748B] hover:text-[#0F172A] transition-colors"><i data-lucide="linkedin"></i></a>
            <a href="#" class="text-[#64748B] hover:text-[#0F172A] transition-colors"><i data-lucide="instagram"></i></a>
          </div>
        </div>
      </div>
    </div>
</footer>

<script>
    lucide.createIcons();
</script>
</body>
</html>

