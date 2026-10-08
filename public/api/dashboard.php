<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/streaks.php';
require_method('GET');

$userId = require_auth();
$pdo    = get_db();

// Accepts ?month=YYYY-MM, defaults to the current month.
$month = $_GET['month'] ?? (new DateTime('today'))->format('Y-m');
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
    json_response(['success' => false, 'error' => 'Invalid month. Use YYYY-MM.'], 422);
}
$monthStart = DateTime::createFromFormat('Y-m-d', $month . '-01');
$monthEnd   = (clone $monthStart)->modify('first day of next month');

// --- Budget -------------------------------------------------------
$stmt = $pdo->prepare('SELECT period_type, total_amount, needs_pct, wants_pct, savings_pct
                        FROM budgets WHERE user_id = :user_id');
$stmt->execute(['user_id' => $userId]);
$budget = $stmt->fetch();

// --- Spend per pillar for the selected month -----------------------
$stmt = $pdo->prepare('
    SELECT pillar, COALESCE(SUM(amount), 0) AS total_spent
    FROM transactions
    WHERE user_id = :user_id AND txn_date >= :start AND txn_date < :end
    GROUP BY pillar
');
$stmt->execute([
    'user_id' => $userId,
    'start'   => $monthStart->format('Y-m-d'),
    'end'     => $monthEnd->format('Y-m-d'),
]);
$spentByPillar = ['NEEDS' => 0.0, 'WANTS' => 0.0, 'SAVINGS' => 0.0];
foreach ($stmt->fetchAll() as $row) {
    $spentByPillar[$row['pillar']] = (float) $row['total_spent'];
}

// --- Build per-pillar cards ----------------------------------------
$pillars = [];
foreach (['NEEDS', 'WANTS', 'SAVINGS'] as $pillar) {
    $allocated = 0.0;
    if ($budget) {
        $pctKey    = strtolower($pillar) . '_pct';
        $allocated = round(((float) $budget['total_amount']) * ((int) $budget[$pctKey]) / 100, 2);
    }
    $spent     = $spentByPillar[$pillar];
    $remaining = $allocated - $spent;
    $pctUsed   = $allocated > 0 ? round(($spent / $allocated) * 100, 1) : ($spent > 0 ? 100.0 : 0.0);

    $status = 'green';
    if ($pctUsed >= 100) {
        $status = 'red';
    } elseif ($pctUsed >= 80) {
        $status = 'amber';
    }

    $pillars[] = [
        'pillar'        => $pillar,
        'allocated'     => $allocated,
        'spent'         => $spent,
        'remaining'     => $remaining,
        'percent_used'  => $pctUsed,
        'status'        => $status,
    ];
}

// --- Streak ----------------------------------------------------------
$streak = get_streak($pdo, $userId);

json_response([
    'success'      => true,
    'month'        => $month,
    'has_budget'   => (bool) $budget,
    'total_budget' => $budget ? (float) $budget['total_amount'] : 0,
    'pillars'      => $pillars,
    'streak'       => $streak,
]);
