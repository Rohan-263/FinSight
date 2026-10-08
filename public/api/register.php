<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_method('POST');

$data = read_json_body();
require_fields($data, ['name', 'email', 'password']);

$name     = trim((string) $data['name']);
$email    = strtolower(trim((string) $data['email']));
$password = (string) $data['password'];

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(['success' => false, 'error' => 'Please enter a valid email address.'], 422);
}

if (strlen($password) < 8) {
    json_response(['success' => false, 'error' => 'Password must be at least 8 characters long.'], 422);
}

if ($name === '') {
    json_response(['success' => false, 'error' => 'Name cannot be empty.'], 422);
}

$pdo = get_db();

$stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email');
$stmt->execute(['email' => $email]);
if ($stmt->fetch()) {
    json_response(['success' => false, 'error' => 'An account with this email already exists.'], 409);
}

$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare(
        'INSERT INTO users (name, email, password_hash) VALUES (:name, :email, :hash) RETURNING id'
    );
    $stmt->execute(['name' => $name, 'email' => $email, 'hash' => $hash]);
    $userId = (int) $stmt->fetchColumn();

    // Every user gets a streak row from day one so later queries
    // never have to special-case a missing streak record.
    $pdo->prepare('INSERT INTO streaks (user_id) VALUES (:user_id)')
        ->execute(['user_id' => $userId]);

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    json_response(['success' => false, 'error' => 'Could not create account. Please try again.'], 500);
}

// Regenerate the session id on privilege change (login), then store
// the identity. session_regenerate_id(true) preserves $_SESSION data.
session_regenerate_id(true);
$_SESSION['user_id'] = $userId;
$_SESSION['name']    = $name;

json_response([
    'success' => true,
    'user'    => ['id' => $userId, 'name' => $name, 'email' => $email],
]);
