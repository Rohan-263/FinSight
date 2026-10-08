<?php

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => filter_var(getenv('COOKIE_SECURE') ?: 'true', FILTER_VALIDATE_BOOLEAN),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('finsight_session');
    session_start();
}

/**
 * Returns the logged-in user's id, or null if not authenticated.
 */
function current_user_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

/**
 * Ends the request with a 401 JSON response if no user is logged in.
 * Returns the user id when authenticated, so callers can do:
 *   $userId = require_auth();
 */
function require_auth(): int
{
    $userId = current_user_id();
    if ($userId === null) {
        json_response(['success' => false, 'error' => 'Not authenticated.'], 401);
    }
    return $userId;
}
