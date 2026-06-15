<?php
declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/config/db.php';

try {
    $pdo = getDB();

    // 1. Fetch metadata for filters (available countries, majors, and max fee)
    // This allows the frontend sidebar to adapt dynamically as database entries update.
    $countriesStmt = $pdo->query('SELECT DISTINCT country FROM research_institutions ORDER BY country ASC');
    $allCountries = $countriesStmt->fetchAll(PDO::FETCH_COLUMN);

    $majorsStmt = $pdo->query('SELECT DISTINCT major FROM research_programs ORDER BY major ASC');
    $allMajors = $majorsStmt->fetchAll(PDO::FETCH_COLUMN);

    $maxFeeStmt = $pdo->query('SELECT MAX(tuition_fee) FROM research_programs');
    $maxFee = (int)($maxFeeStmt->fetchColumn() ?: 50000);

    // 2. Parse request query parameters
    $search = trim($_GET['search'] ?? '');
    
    // Support either comma-separated string or array
    $countries = [];
    if (isset($_GET['countries'])) {
        if (is_array($_GET['countries'])) {
            $countries = array_map('trim', $_GET['countries']);
        } else {
            $countries = array_filter(array_map('trim', explode(',', $_GET['countries'])));
        }
    }

    $major = trim($_GET['major'] ?? '');
    $degreeLevel = trim($_GET['degree_level'] ?? '');
    $maxBudgetInput = isset($_GET['max_budget']) ? (int)$_GET['max_budget'] : null;

    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = max(1, (int)($_GET['limit'] ?? 3));
    $offset = ($page - 1) * $limit;

    // 3. Build SQL query dynamically
    $whereClauses = [];
    $params = [];

    if ($search !== '') {
        $whereClauses[] = '(ri.name LIKE :search_1 OR ri.description LIKE :search_2 OR rp.title LIKE :search_3 OR rp.major LIKE :search_4 OR rp.scholarship_type LIKE :search_5)';
        $params['search_1'] = '%' . $search . '%';
        $params['search_2'] = '%' . $search . '%';
        $params['search_3'] = '%' . $search . '%';
        $params['search_4'] = '%' . $search . '%';
        $params['search_5'] = '%' . $search . '%';
    }

    if (!empty($countries)) {
        $countryPlaceholders = [];
        foreach ($countries as $index => $c) {
            $key = 'country_' . $index;
            $countryPlaceholders[] = ':' . $key;
            $params[$key] = $c;
        }
        $whereClauses[] = 'ri.country IN (' . implode(', ', $countryPlaceholders) . ')';
    }

    if ($major !== '' && strtolower($major) !== 'all') {
        $whereClauses[] = 'rp.major = :major';
        $params['major'] = $major;
    }

    if ($degreeLevel !== '' && strtolower($degreeLevel) !== 'all') {
        $whereClauses[] = 'rp.degree_level = :degree_level';
        $params['degree_level'] = $degreeLevel;
    }

    if ($maxBudgetInput !== null) {
        $whereClauses[] = 'rp.tuition_fee <= :max_budget';
        $params['max_budget'] = $maxBudgetInput;
    }

    $whereSql = '';
    if (!empty($whereClauses)) {
        $whereSql = ' WHERE ' . implode(' AND ', $whereClauses);
    }

    // Query for total matching count
    $countSql = 'SELECT COUNT(*) FROM research_programs rp JOIN research_institutions ri ON rp.institution_id = ri.id' . $whereSql;
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalResults = (int)$countStmt->fetchColumn();

    // Query for page items
    $dataSql = 'SELECT rp.*, ri.name AS institution_name, ri.qs_rank, ri.country, ri.description AS institution_description, ri.image_url 
                FROM research_programs rp 
                JOIN research_institutions ri ON rp.institution_id = ri.id' 
                . $whereSql 
                . ' ORDER BY ri.qs_rank ASC, rp.id ASC LIMIT ' . $limit . ' OFFSET ' . $offset;
                
    $dataStmt = $pdo->prepare($dataSql);
    $dataStmt->execute($params);
    $programs = $dataStmt->fetchAll();

    // Format tuition fee as string for display if needed, but keep integer values for logic.
    $totalPages = (int)ceil($totalResults / $limit);

    echo json_encode([
        'status' => 'success',
        'filters' => [
            'available_countries' => $allCountries,
            'available_majors' => $allMajors,
            'max_tuition_fee_limit' => $maxFee,
        ],
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_results' => $totalResults,
            'limit' => $limit,
            'showing_start' => $totalResults > 0 ? $offset + 1 : 0,
            'showing_end' => min($offset + $limit, $totalResults),
        ],
        'data' => $programs,
    ]);

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'An error occurred while fetching research data.',
        'debug' => getenv('DEVELOPMENT') === 'true' ? $e->getMessage() : null
    ]);
}
