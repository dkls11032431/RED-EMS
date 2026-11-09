<?php

session_start();

require 'config/db.php';
require 'includes/plant_standards.php';
require 'includes/activity_log.php';
try {
    ems_ensure_plant($conn);
} catch (Throwable $e) {
    // Login still works if the plant tables cannot be created yet.
}

$msg = '';

if (isset($_POST['login'])) {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = md5((string) ($_POST['password'] ?? ''));

    $stmt = $conn->prepare("
    SELECT *
    FROM users
    WHERE username=?
    AND password=?
    ");

    $stmt->execute([
        $username,
        $password
    ]);

    if ($stmt->rowCount() > 0) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $branches = [];
        if (!empty($user['id'])) {
            try {
                $loc = $conn->prepare('SELECT branch_name FROM user_locations WHERE user_id = ? ORDER BY branch_name');
                $loc->execute([(int) $user['id']]);
                $branches = $loc->fetchAll(PDO::FETCH_COLUMN);
            } catch (Throwable $e) {
                $branches = [];
            }
        }
        if ($branches === [] && !empty($user['branch_name'])) {
            $branches = [$user['branch_name']];
        }

        $companies = [];
        if ($branches !== []) {
            $placeholders = implode(',', array_fill(0, count($branches), '?'));
            try {
                $companyStmt = $conn->prepare("SELECT DISTINCT company FROM bill_sites WHERE branch_name IN ($placeholders)");
                $companyStmt->execute($branches);
                $companies = $companyStmt->fetchAll(PDO::FETCH_COLUMN);
            } catch (Throwable $e) {
                $companies = [];
            }
        }
        if ($companies === [] && !empty($user['company'])) {
            $companies = [$user['company']];
        }

        $_SESSION['user'] = $user['username'];
        $_SESSION['role'] = $user['role'] ?? 'user';
        $_SESSION['company'] = $companies[0] ?? ($user['company'] ?? null);
        $_SESSION['companies'] = array_values($companies);
        $_SESSION['branch'] = $branches[0] ?? ($user['branch_name'] ?? '');
        $_SESSION['branches'] = array_values($branches);
        $_SESSION['login_at'] = time();

        ems_log_event($conn, $user['username'], 'login', 'session', 'Signed in');

        header('Location: dashboard.php');
        exit;
    }

    ems_log_event($conn, $username !== '' ? $username : 'unknown', 'login failed', 'password', 'Rejected sign-in');
    $msg = 'The username or password was not accepted.';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in · RED SURYAN EMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;600;700&family=IBM+Plex+Sans+Condensed:wght@600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="asset/css/style.css?v=5">
</head>
<body>
<div class="login-screen">
    <section class="login-hero">
        <div class="brand-mark">RS</div>
        <h1>RED SURYAN EMS</h1>
        <p>Electrical supply, diesel generation, billing and alarms for the sites you are assigned.</p>
    </section>
    <section class="login-panel">
        <div class="login-card card p-4">
            <h2 class="h4 mb-3">Sign in</h2>
            <?php if ($msg !== '') { ?>
            <div class="alert alert-danger" role="alert"><?= htmlspecialchars($msg) ?></div>
            <?php } ?>
            <form method="post">
                <label class="form-label" for="username">Username</label>
                <input id="username" type="text" name="username" class="form-control mb-3" autocomplete="username" required>
                <label class="form-label" for="password">Password</label>
                <input id="password" type="password" name="password" class="form-control mb-3" autocomplete="current-password" required>
                <button name="login" class="btn btn-primary w-100">Sign in</button>
            </form>
        </div>
    </section>
</div>
</body>
</html>
