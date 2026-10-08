<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_method('GET');

require_auth(); // must be logged in, though data returned isn't user-specific

$pdo    = get_db();
$pillar = $_GET['pillar'] ?? null;

if ($pillar !== null && !is_valid_pillar($pillar)) {
    json_response(['success' => false, 'error' => 'Invalid pillar filter.'], 422);
}

if ($pillar !== null) {
    $stmt = $pdo->prepare(
        'SELECT id, pillar, label FROM subcategories WHERE pillar = :pillar ORDER BY label'
    );
    $stmt->execute(['pillar' => $pillar]);
} else {
    $stmt = $pdo->query('SELECT id, pillar, label FROM subcategories ORDER BY pillar, label');
}

json_response(['success' => true, 'subcategories' => $stmt->fetchAll()]);
