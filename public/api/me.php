<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_method('GET');

$userId = current_user_id();

if ($userId === null) {
    json_response(['success' => true, 'authenticated' => false]);
}

$pdo = get_db();
$stmt = $pdo->prepare('SELECT id, name, email FROM users WHERE id = :id');
$stmt->execute(['id' => $userId]);
$user = $stmt->fetch();

if (!$user) {
    // Session points at a user that no longer exists.
    json_response(['success' => true, 'authenticated' => false]);
}

json_response([
    'success'       => true,
    'authenticated' => true,
    'user'          => $user,
]);
