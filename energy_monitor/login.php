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

if(isset($_POST['login']))
{
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

    if($stmt->rowCount() > 0)
    {
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

        header("Location: dashboard.php");
        exit;
    }
    else
    {
        ems_log_event($conn, $username !== '' ? $username : 'unknown', 'login failed', 'password', 'Rejected sign-in');
        $msg='Invalid Login';
    }
}

?>

<!DOCTYPE html>
<html>

<head>

<meta charset="utf-8">

<link rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">

</head>

<body>

<div class="container mt-5">

<div class="row justify-content-center">

<div class="col-md-4">

<div class="card shadow">

<div class="card-body">

<h3>⚡ EMS Login</h3>

<p class="text-danger">
<?= $msg ?>
</p>

<form method="post">

<input
type="text"
name="username"
class="form-control mb-3"
placeholder="Username">

<input
type="password"
name="password"
class="form-control mb-3"
placeholder="Password">

<button
name="login"
class="btn btn-primary w-100">

Login

</button>

</form>

</div>

</div>

</div>

</div>

</div>

</body>
</html>
