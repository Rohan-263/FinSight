<?php
function json_response(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

function read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}


function require_fields(array $data, array $required): void
{
    $missing = [];
    foreach ($required as $field) {
        if (!array_key_exists($field, $data) || $data[$field] === '' || $data[$field] === null) {
            $missing[] = $field;
        }
    }
    if (!empty($missing)) {
        json_response([
            'success' => false,
            'error'   => 'Missing required field(s): ' . implode(', ', $missing),
        ], 422);
    }
}

function is_valid_date(string $date): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

/**
 * Validates a pillar value against the allowed enum.
 */
function is_valid_pillar(string $pillar): bool
{
    return in_array($pillar, ['NEEDS', 'WANTS', 'SAVINGS'], true);
}
