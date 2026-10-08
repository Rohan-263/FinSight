<?php
function update_streak_on_log(PDO $pdo, int $userId): void
{
    $today = (new DateTime('today'))->format('Y-m-d');

    $stmt = $pdo->prepare('SELECT current_streak, longest_streak, last_logged_date
                            FROM streaks WHERE user_id = :user_id FOR UPDATE');
    $stmt->execute(['user_id' => $userId]);
    $streak = $stmt->fetch();

    if (!$streak) {
        // Defensive: should already exist from registration.
        $pdo->prepare('INSERT INTO streaks (user_id) VALUES (:user_id)')
            ->execute(['user_id' => $userId]);
        $streak = ['current_streak' => 0, 'longest_streak' => 0, 'last_logged_date' => null];
    }

    $lastLogged = $streak['last_logged_date'];

    if ($lastLogged === $today) {
        // Already logged today — no change to the streak count.
        return;
    }

    $yesterday = (new DateTime('yesterday'))->format('Y-m-d');
    $newCurrent = ($lastLogged === $yesterday) ? $streak['current_streak'] + 1 : 1;
    $newLongest = max($newCurrent, (int) $streak['longest_streak']);

    $pdo->prepare('
        UPDATE streaks
        SET current_streak = :current, longest_streak = :longest,
            last_logged_date = :today, updated_at = now()
        WHERE user_id = :user_id
    ')->execute([
        'current' => $newCurrent,
        'longest' => $newLongest,
        'today'   => $today,
        'user_id' => $userId,
    ]);
}

function get_streak(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('SELECT current_streak, longest_streak, last_logged_date
                            FROM streaks WHERE user_id = :user_id');
    $stmt->execute(['user_id' => $userId]);
    $streak = $stmt->fetch();

    if (!$streak) {
        return ['current_streak' => 0, 'longest_streak' => 0, 'last_logged_date' => null];
    }

    $today     = (new DateTime('today'))->format('Y-m-d');
    $yesterday = (new DateTime('yesterday'))->format('Y-m-d');

    if ($streak['last_logged_date'] !== null
        && $streak['last_logged_date'] !== $today
        && $streak['last_logged_date'] !== $yesterday) {
        // Streak has lapsed — reflect 0 without waiting for a cron job.
        $streak['current_streak'] = 0;
    }

    return $streak;
}
