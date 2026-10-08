<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

$userId = require_auth();
$pdo    = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare('SELECT period_type, total_amount, needs_pct, wants_pct, savings_pct
                            FROM budgets WHERE user_id = :user_id');
    $stmt->execute(['user_id' => $userId]);
    $budget = $stmt->fetch();

    json_response(['success' => true, 'budget' => $budget ?: null]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = read_json_body();
    require_fields($data, ['total_amount', 'needs_pct', 'wants_pct', 'savings_pct']);

    $periodType   = ($data['period_type'] ?? 'MONTHLY') === 'YEARLY' ? 'YEARLY' : 'MONTHLY';
    $totalAmount  = (float) $data['total_amount'];
    $needsPct     = (int) $data['needs_pct'];
    $wantsPct     = (int) $data['wants_pct'];
    $savingsPct   = (int) $data['savings_pct'];

    if ($totalAmount <= 0) {
        json_response(['success' => false, 'error' => 'Total budget amount must be greater than zero.'], 422);
    }

    foreach (['needsPct' => $needsPct, 'wantsPct' => $wantsPct, 'savingsPct' => $savingsPct] as $label => $pct) {
        if ($pct < 0 || $pct > 100) {
            json_response(['success' => false, 'error' => "{$label} must be between 0 and 100."], 422);
        }
    }

    if ($needsPct + $wantsPct + $savingsPct !== 100) {
        json_response(['success' => false, 'error' => 'Needs, Wants and Savings percentages must add up to 100.'], 422);
    }

    // Upsert: one budget row per user for the MVP.
    $stmt = $pdo->prepare('
        INSERT INTO budgets (user_id, period_type, total_amount, needs_pct, wants_pct, savings_pct)
        VALUES (:user_id, :period_type, :total_amount, :needs_pct, :wants_pct, :savings_pct)
        ON CONFLICT (user_id) DO UPDATE SET
            period_type  = EXCLUDED.period_type,
            total_amount = EXCLUDED.total_amount,
            needs_pct    = EXCLUDED.needs_pct,
            wants_pct    = EXCLUDED.wants_pct,
            savings_pct  = EXCLUDED.savings_pct,
            updated_at   = now()
    ');
    $stmt->execute([
        'user_id'      => $userId,
        'period_type'  => $periodType,
        'total_amount' => $totalAmount,
        'needs_pct'    => $needsPct,
        'wants_pct'    => $wantsPct,
        'savings_pct'  => $savingsPct,
    ]);

    json_response(['success' => true]);
}

json_response(['success' => false, 'error' => 'Method not allowed.'], 405);
