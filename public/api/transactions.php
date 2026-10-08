<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/streaks.php';

$userId = require_auth();
$pdo    = get_db();
$method = $_SERVER['REQUEST_METHOD'];

// ------------------------------------------------------------------
// GET /api/transactions.php — paginated, filterable list
//   Query params: pillar, from (date), to (date), page, per_page
// ------------------------------------------------------------------
if ($method === 'GET') {
    $page    = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = min(100, max(1, (int) ($_GET['per_page'] ?? 20)));
    $offset  = ($page - 1) * $perPage;

    $where  = ['t.user_id = :user_id'];
    $params = ['user_id' => $userId];

    if (!empty($_GET['pillar'])) {
        if (!is_valid_pillar($_GET['pillar'])) {
            json_response(['success' => false, 'error' => 'Invalid pillar filter.'], 422);
        }
        $where[]        = 't.pillar = :pillar';
        $params['pillar'] = $_GET['pillar'];
    }
    if (!empty($_GET['from'])) {
        if (!is_valid_date($_GET['from'])) {
            json_response(['success' => false, 'error' => 'Invalid "from" date.'], 422);
        }
        $where[]      = 't.txn_date >= :from';
        $params['from'] = $_GET['from'];
    }
    if (!empty($_GET['to'])) {
        if (!is_valid_date($_GET['to'])) {
            json_response(['success' => false, 'error' => 'Invalid "to" date.'], 422);
        }
        $where[]    = 't.txn_date <= :to';
        $params['to'] = $_GET['to'];
    }

    $whereSql = implode(' AND ', $where);

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM transactions t WHERE {$whereSql}");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT t.id, t.amount, t.pillar, t.note, t.payee, t.txn_date,
               s.id AS subcategory_id, s.label AS subcategory_label
        FROM transactions t
        LEFT JOIN subcategories s ON t.subcategory_id = s.id
        WHERE {$whereSql}
        ORDER BY t.txn_date DESC, t.created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    foreach ($params as $key => $value) {
        $stmt->bindValue(":{$key}", $value);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    json_response([
        'success'      => true,
        'transactions' => $stmt->fetchAll(),
        'pagination'   => [
            'page'        => $page,
            'per_page'    => $perPage,
            'total'       => $total,
            'total_pages' => (int) ceil($total / $perPage),
        ],
    ]);
}

// ------------------------------------------------------------------
// POST /api/transactions.php — create a transaction
// ------------------------------------------------------------------
if ($method === 'POST') {
    $data = read_json_body();
    require_fields($data, ['amount', 'pillar', 'txn_date']);

    $amount  = (float) $data['amount'];
    $pillar  = (string) $data['pillar'];
    $date    = (string) $data['txn_date'];
    $note    = isset($data['note']) ? trim((string) $data['note']) : null;
    $payee   = isset($data['payee']) ? trim((string) $data['payee']) : null;
    $subcatId = !empty($data['subcategory_id']) ? (int) $data['subcategory_id'] : null;

    if ($amount <= 0) {
        json_response(['success' => false, 'error' => 'Amount must be greater than zero.'], 422);
    }
    if (!is_valid_pillar($pillar)) {
        json_response(['success' => false, 'error' => 'Invalid pillar.'], 422);
    }
    if (!is_valid_date($date)) {
        json_response(['success' => false, 'error' => 'Invalid date. Use YYYY-MM-DD.'], 422);
    }

    if ($subcatId !== null) {
        $check = $pdo->prepare('SELECT id FROM subcategories WHERE id = :id AND pillar = :pillar');
        $check->execute(['id' => $subcatId, 'pillar' => $pillar]);
        if (!$check->fetch()) {
            json_response(['success' => false, 'error' => 'Sub-category does not belong to the selected pillar.'], 422);
        }
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('
            INSERT INTO transactions (user_id, amount, pillar, subcategory_id, note, payee, txn_date)
            VALUES (:user_id, :amount, :pillar, :subcategory_id, :note, :payee, :txn_date)
            RETURNING id
        ');
        $stmt->execute([
            'user_id'        => $userId,
            'amount'         => $amount,
            'pillar'         => $pillar,
            'subcategory_id' => $subcatId,
            'note'           => $note ?: null,
            'payee'          => $payee ?: null,
            'txn_date'       => $date,
        ]);
        $newId = (int) $stmt->fetchColumn();

        update_streak_on_log($pdo, $userId);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        json_response(['success' => false, 'error' => 'Could not save transaction.'], 500);
    }

    json_response(['success' => true, 'id' => $newId], 201);
}

// ------------------------------------------------------------------
// PUT /api/transactions.php?id=123 — update a transaction
// ------------------------------------------------------------------
if ($method === 'PUT') {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        json_response(['success' => false, 'error' => 'Missing or invalid transaction id.'], 422);
    }

    $owned = $pdo->prepare('SELECT id FROM transactions WHERE id = :id AND user_id = :user_id');
    $owned->execute(['id' => $id, 'user_id' => $userId]);
    if (!$owned->fetch()) {
        json_response(['success' => false, 'error' => 'Transaction not found.'], 404);
    }

    $data = read_json_body();
    require_fields($data, ['amount', 'pillar', 'txn_date']);

    $amount   = (float) $data['amount'];
    $pillar   = (string) $data['pillar'];
    $date     = (string) $data['txn_date'];
    $note     = isset($data['note']) ? trim((string) $data['note']) : null;
    $payee    = isset($data['payee']) ? trim((string) $data['payee']) : null;
    $subcatId = !empty($data['subcategory_id']) ? (int) $data['subcategory_id'] : null;

    if ($amount <= 0) {
        json_response(['success' => false, 'error' => 'Amount must be greater than zero.'], 422);
    }
    if (!is_valid_pillar($pillar)) {
        json_response(['success' => false, 'error' => 'Invalid pillar.'], 422);
    }
    if (!is_valid_date($date)) {
        json_response(['success' => false, 'error' => 'Invalid date. Use YYYY-MM-DD.'], 422);
    }
    if ($subcatId !== null) {
        $check = $pdo->prepare('SELECT id FROM subcategories WHERE id = :id AND pillar = :pillar');
        $check->execute(['id' => $subcatId, 'pillar' => $pillar]);
        if (!$check->fetch()) {
            json_response(['success' => false, 'error' => 'Sub-category does not belong to the selected pillar.'], 422);
        }
    }

    $stmt = $pdo->prepare('
        UPDATE transactions
        SET amount = :amount, pillar = :pillar, subcategory_id = :subcategory_id,
            note = :note, payee = :payee, txn_date = :txn_date, updated_at = now()
        WHERE id = :id AND user_id = :user_id
    ');
    $stmt->execute([
        'amount'         => $amount,
        'pillar'         => $pillar,
        'subcategory_id' => $subcatId,
        'note'           => $note ?: null,
        'payee'          => $payee ?: null,
        'txn_date'       => $date,
        'id'             => $id,
        'user_id'        => $userId,
    ]);

    json_response(['success' => true]);
}

// ------------------------------------------------------------------
// DELETE /api/transactions.php?id=123
// ------------------------------------------------------------------
if ($method === 'DELETE') {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        json_response(['success' => false, 'error' => 'Missing or invalid transaction id.'], 422);
    }

    $stmt = $pdo->prepare('DELETE FROM transactions WHERE id = :id AND user_id = :user_id');
    $stmt->execute(['id' => $id, 'user_id' => $userId]);

    if ($stmt->rowCount() === 0) {
        json_response(['success' => false, 'error' => 'Transaction not found.'], 404);
    }

    json_response(['success' => true]);
}

json_response(['success' => false, 'error' => 'Method not allowed.'], 405);
