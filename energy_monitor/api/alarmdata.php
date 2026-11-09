<?php

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode([]);
    exit;
}

require '../config/db.php';
require __DIR__ . '/../includes/auth_helpers.php';

$meter_id = isset($_GET['meter_id']) ? (int) $_GET['meter_id'] : 0;
if ($meter_id <= 0 || !meter_belongs_to_user($conn, $meter_id)) {
    echo json_encode([]);
    exit;
}

/*
 | parameter_master_princek2 rows all have min_value / max_value set to
 | NULL, so there's nothing real to check against yet. Set actual
 | thresholds per parameter in that table and swap the fallback ranges
 | below for pm.min_value / pm.max_value once you do.
*/
$fallbackRanges = [
    'voltage' => [200, 250],
    'current' => [0, 100],
    'pf'      => [0.85, 1.0],
];

function range_for($name)
{
    global $fallbackRanges;

    if (preg_match('/^V[a-z]{0,3}\.?$/i', $name)) return $fallbackRanges['voltage'];
    if (preg_match('/^I[a-z]{0,3}\.?$/i', $name) || stripos($name, 'amps') !== false) return $fallbackRanges['current'];
    if (stripos($name, 'pf') !== false || stripos($name, 'p.f') !== false) return $fallbackRanges['pf'];

    return null;
}

/*
 | Placeholder severity rule: how far past the limit the actual value
 | is, as a percentage of the limit itself. >15% over/under = CRITICAL,
 | otherwise WARNING. Adjust the 15% threshold (or replace this rule
 | entirely) once you know what actually matters for your meters.
*/
function severity_for($value, $limit)
{
    if ($limit == 0) return 'CRITICAL';
    $deviationPct = abs($value - $limit) / abs($limit) * 100;
    return $deviationPct > 15 ? 'CRITICAL' : 'WARNING';
}

// --- Step 1: detect new breaches and log them to the alarms table ---

$params = $conn->prepare("
    SELECT id, parameter_name, min_value, max_value
    FROM parameter_master_princek2
    WHERE isdisplayed = 1 AND meter_group_id = ?
");
$params->execute([$meter_id]);
$params = $params->fetchAll(PDO::FETCH_ASSOC);

$latestStmt = $conn->prepare("
    SELECT value FROM meter_readings
    WHERE parameter_id = ? AND meter_id = ?
    ORDER BY reading_time DESC LIMIT 1
");

$existingActiveStmt = $conn->prepare("
    SELECT id FROM alarms
    WHERE meter_id = ? AND parameter_name = ? AND status = 'ACTIVE'
    LIMIT 1
");

$insertAlarmStmt = $conn->prepare("
    INSERT INTO alarms (meter_id, parameter_name, alarm_value, actual_value, alarm_time, alarm_message, status, severity)
    VALUES (?, ?, ?, ?, NOW(), ?, 'ACTIVE', ?)
");

foreach ($params as $p) {
    $latestStmt->execute([$p['id'], $meter_id]);
    $value = $latestStmt->fetchColumn();
    if ($value === false) continue;
    $value = (float) $value;

    $min = $p['min_value'];
    $max = $p['max_value'];

    if ($min === null && $max === null) {
        $fallback = range_for($p['parameter_name']);
        if (!$fallback) continue;
        [$min, $max] = $fallback;
    }

    $breached = ($min !== null && $value < $min) || ($max !== null && $value > $max);
    if (!$breached) continue;

    // Don't log a duplicate while one for this parameter is still active
    $existingActiveStmt->execute([$meter_id, $p['parameter_name']]);
    if ($existingActiveStmt->fetchColumn()) continue;

    $limitBreached = ($value < $min) ? $min : $max;
    $message = ($value < $min)
        ? "Below minimum ({$min})"
        : "Above maximum ({$max})";

    $insertAlarmStmt->execute([
        $meter_id,
        $p['parameter_name'],
        $limitBreached,
        $value,
        $message,
        severity_for($value, $limitBreached),
    ]);
}

// --- Step 2: return currently active alarms for this meter ---

$activeStmt = $conn->prepare("
    SELECT id, parameter_name, alarm_value, actual_value, alarm_message, alarm_time, severity
    FROM alarms
    WHERE meter_id = ? AND status = 'ACTIVE'
    ORDER BY alarm_time DESC
");
$activeStmt->execute([$meter_id]);

echo json_encode($activeStmt->fetchAll(PDO::FETCH_ASSOC));
