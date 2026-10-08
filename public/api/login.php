<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_method('POST');

$data = read_json_body();
require_fields($data, ['email', 'password']);

$email    = strtolower(trim((string) $data['email']));
$password = (string) $data['password'];

$pdo = get_db();
$stmt = $pdo->prepare('SELECT id, name, password_hash FROM users WHERE email = :email');
$stmt->execute(['email' => $email]);
$user = $stmt->fetch();

// Same error for "no such user" and "wrong password" — don't leak
// which one it was.
if (!$user || !password_verify($password, $user['password_hash'])) {
    json_response(['success' => false, 'error' => 'Invalid email or password.'], 401);
}

session_regenerate_id(true);
$_SESSION['user_id'] = (int) $user['id'];
$_SESSION['name']    = $user['name'];

json_response([
    'success' => true,
    'user'    => ['id' => (int) $user['id'], 'name' => $user['name'], 'email' => $email],
]);
