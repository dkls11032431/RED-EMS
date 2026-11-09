<?php

function ems_log_event(PDO $conn, string $username, string $activity, ?string $field = null, ?string $detail = null, ?int $duration = null): void
{
    $ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64);
    $when = date('Y-m-d H:i:s');
    try {
        $stmt = $conn->prepare("
            INSERT INTO event_log
                (username, activity, field_name, detail, duration_seconds, ip_address, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            substr($username, 0, 80),
            substr($activity, 0, 160),
            $field !== null ? substr($field, 0, 120) : null,
            $detail !== null ? substr($detail, 0, 500) : null,
            $duration,
            $ip,
            $when,
        ]);
    } catch (Throwable $e) {
        try {
            $text = $activity;
            if ($field) {
                $text .= ' [' . $field . ']';
            }
            if ($detail) {
                $text .= ' ' . $detail;
            }
            $conn->prepare('INSERT INTO event_log (username, activity) VALUES (?, ?)')
                ->execute([substr($username, 0, 80), substr($text, 0, 160)]);
        } catch (Throwable $ignored) {
            // Logging must not block a login or a bill save.
        }
    }
}

function logEvent($conn, $username, $activity)
{
    if ($conn instanceof PDO) {
        ems_log_event($conn, (string) $username, (string) $activity);
    }
}

function ems_format_duration(?int $seconds): string
{
    if ($seconds === null || $seconds < 0) {
        return '';
    }
    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    $remain = $seconds % 60;
    if ($hours > 0) {
        return $hours . 'h ' . $minutes . 'm';
    }
    if ($minutes > 0) {
        return $minutes . 'm ' . $remain . 's';
    }
    return $remain . 's';
}
