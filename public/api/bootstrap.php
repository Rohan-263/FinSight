<?php
/**
 * Bootstrap included at the top of every api/*.php endpoint.
 * Loads config, starts the session, and sets common headers.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/helpers.php';

header('Content-Type: application/json');

// Basic method guard helper for endpoints that only support one verb.
function require_method(string $method): void
{
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        json_response(['success' => false, 'error' => 'Method not allowed.'], 405);
    }
}
