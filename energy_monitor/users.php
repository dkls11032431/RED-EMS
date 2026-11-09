<?php

session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}
require 'config/db.php';
require 'includes/auth_helpers.php';
require 'includes/plant_standards.php';
require 'includes/activity_log.php';
ems_ensure_plant($conn);

if (!is_admin()) {
    header('Location: dashboard.php');
    exit;
}

$notice = '';
$error = '';

$sites = $conn->query('SELECT state_name, branch_name, company FROM bill_sites ORDER BY company, state_name, branch_name')->fetchAll(PDO::FETCH_ASSOC);

if (isset($_POST['create_user'])) {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $role = ($_POST['role'] ?? '') === 'supervisor' ? 'supervisor' : 'user';
    $email = trim((string) ($_POST['email'] ?? ''));
    $picked = array_values(array_unique(array_filter(array_map('strval', $_POST['branches'] ?? []))));
    $known = array_column($sites, 'branch_name');
    $picked = array_values(array_filter($picked, static function ($name) use ($known) {
        return in_array($name, $known, true);
    }));

    if (!preg_match('/^[A-Za-z0-9._-]{3,40}$/', $username)) {
        $error = 'Username must be 3–40 letters, numbers, dots, or dashes.';
    } elseif (strlen($password) < 4) {
        $error = 'Password must be at least 4 characters.';
    } elseif ($role === 'user' && $picked === []) {
        $error = 'Choose at least one location for this user.';
    } else {
        $exists = $conn->prepare('SELECT id FROM users WHERE username = ?');
        $exists->execute([$username]);
        if ($exists->fetchColumn()) {
            $error = 'That username is already in use.';
        } else {
            $company = null;
            if ($picked !== []) {
                $companyStmt = $conn->prepare('SELECT company FROM bill_sites WHERE branch_name = ? LIMIT 1');
                $companyStmt->execute([$picked[0]]);
                $company = $companyStmt->fetchColumn() ?: null;
            }
            $insert = $conn->prepare('INSERT INTO users (username, password, role, company, branch_name, email) VALUES (?, ?, ?, ?, ?, ?)');
            $insert->execute([
                $username,
                md5($password),
                $role,
                $company,
                $picked[0] ?? null,
                $email !== '' ? $email : null,
            ]);
            $userId = (int) $conn->lastInsertId();
            $link = $conn->prepare('INSERT IGNORE INTO user_locations (user_id, branch_name) VALUES (?, ?)');
            foreach ($picked as $branch) {
                $link->execute([$userId, $branch]);
            }
            ems_log_event($conn, (string) $_SESSION['user'], 'user created', 'username', $username . ' · ' . $role . ' · ' . implode(', ', $picked));
            header('Location: users.php?saved=created');
            exit;
        }
    }
}

if (isset($_POST['reset_password'], $_POST['id'])) {
    $plain = (string) ($_POST['new_password'] ?? '');
    $id = (int) $_POST['id'];
    if (strlen($plain) < 4) {
        $error = 'New password must be at least 4 characters.';
    } else {
        $who = $conn->prepare('SELECT username FROM users WHERE id = ?');
        $who->execute([$id]);
        $name = (string) $who->fetchColumn();
        if ($name === '') {
            $error = 'That account was not found.';
        } else {
            $conn->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([md5($plain), $id]);
            ems_log_event($conn, (string) $_SESSION['user'], 'password reset', 'password', $name);
            header('Location: users.php?saved=password');
            exit;
        }
    }
}

if (isset($_POST['save_locations'], $_POST['id'])) {
    $id = (int) $_POST['id'];
    $picked = array_values(array_unique(array_filter(array_map('strval', $_POST['branches'] ?? []))));
    $known = array_column($sites, 'branch_name');
    $picked = array_values(array_filter($picked, static function ($name) use ($known) {
        return in_array($name, $known, true);
    }));
    $who = $conn->prepare('SELECT username, role FROM users WHERE id = ?');
    $who->execute([$id]);
    $account = $who->fetch(PDO::FETCH_ASSOC);
    if (!$account) {
        $error = 'That account was not found.';
    } elseif (($account['role'] ?? '') === 'user' && $picked === []) {
        $error = 'A location user needs at least one site.';
    } else {
        $conn->prepare('DELETE FROM user_locations WHERE user_id = ?')->execute([$id]);
        $link = $conn->prepare('INSERT INTO user_locations (user_id, branch_name) VALUES (?, ?)');
        foreach ($picked as $branch) {
            $link->execute([$id, $branch]);
        }
        $company = null;
        if ($picked !== []) {
            $companyStmt = $conn->prepare('SELECT company FROM bill_sites WHERE branch_name = ? LIMIT 1');
            $companyStmt->execute([$picked[0]]);
            $company = $companyStmt->fetchColumn() ?: null;
        }
        $conn->prepare('UPDATE users SET branch_name = ?, company = ? WHERE id = ?')->execute([
            $picked[0] ?? null,
            $company,
            $id,
        ]);
        ems_log_event($conn, (string) $_SESSION['user'], 'locations changed', 'branch_name', $account['username'] . ' · ' . implode(', ', $picked));
        header('Location: users.php?saved=locations');
        exit;
    }
}

if (isset($_POST['email'], $_POST['id']) && !isset($_POST['create_user']) && !isset($_POST['reset_password']) && !isset($_POST['save_locations'])) {
    $id = (int) $_POST['id'];
    $email = trim((string) $_POST['email']);
    $who = $conn->prepare('SELECT username, email FROM users WHERE id = ?');
    $who->execute([$id]);
    $account = $who->fetch(PDO::FETCH_ASSOC);
    $conn->prepare('UPDATE users SET email = ? WHERE id = ?')->execute([$email !== '' ? $email : null, $id]);
    if ($account && (string) ($account['email'] ?? '') !== $email) {
        ems_log_event($conn, (string) $_SESSION['user'], 'field changed', 'email', $account['username'] . ' · ' . (string) ($account['email'] ?? '') . ' → ' . $email);
    }
    header('Location: users.php?saved=email');
    exit;
}

$users = $conn->query('SELECT * FROM users ORDER BY role, username')->fetchAll(PDO::FETCH_ASSOC);
$assigned = [];
foreach ($conn->query('SELECT user_id, branch_name FROM user_locations ORDER BY branch_name') as $row) {
    $assigned[(int) $row['user_id']][] = $row['branch_name'];
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="content">
<h2>User management</h2>
<p class="text-muted">Create a sign-in for one site or several. Admin and supervisor accounts see every location. A location user sees only the sites checked here.</p>
<?php if (isset($_GET['saved'])) { ?>
<div class="alert alert-success">
    <?php
    $savedLabel = [
        'created' => 'User created.',
        'password' => 'Password reset.',
        'locations' => 'Location access updated. The user sees it on the next sign-in.',
        'email' => 'Alert email saved.',
    ];
    echo htmlspecialchars($savedLabel[$_GET['saved']] ?? 'Saved.');
    ?>
</div>
<?php } ?>
<?php if ($error !== '') { ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php } ?>

<div class="card mb-4">
<div class="card-body">
<h3 class="h5">New user</h3>
<form method="post">
    <div class="row g-2">
        <div class="col-md-3">
            <label class="form-label">Username</label>
            <input class="form-control" name="username" required value="<?= htmlspecialchars((string) ($_POST['username'] ?? '')) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Password</label>
            <input class="form-control" type="text" name="password" required>
        </div>
        <div class="col-md-2">
            <label class="form-label">Role</label>
            <select class="form-select" name="role">
                <option value="user">Location user</option>
                <option value="supervisor">Supervisor</option>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Alert email</label>
            <input class="form-control" type="email" name="email">
        </div>
    </div>
    <label class="form-label mt-3">Locations and sites</label>
    <div class="border rounded p-2 bg-white" style="max-height:220px;overflow:auto;columns:3;">
        <?php foreach ($sites as $site) { ?>
        <label class="d-block small">
            <input type="checkbox" name="branches[]" value="<?= htmlspecialchars($site['branch_name']) ?>">
            <?= htmlspecialchars($site['branch_name']) ?>
            <span class="text-muted"><?= htmlspecialchars($site['company'] . ' · ' . $site['state_name']) ?></span>
        </label>
        <?php } ?>
    </div>
    <button class="btn btn-primary mt-3" name="create_user" value="1">Create user</button>
</form>
</div>
</div>

<table class="table table-bordered bg-white align-middle">
<thead>
<tr>
    <th>Username</th>
    <th>Role</th>
    <th>Locations</th>
    <th>Alert email</th>
    <th>Reset password</th>
</tr>
</thead>
<tbody>
<?php foreach ($users as $user) {
    $id = (int) $user['id'];
    $locs = $assigned[$id] ?? [];
    if ($locs === [] && !empty($user['branch_name'])) {
        $locs = [$user['branch_name']];
    }
?>
<tr>
    <td><?= htmlspecialchars($user['username']) ?></td>
    <td><?= htmlspecialchars((string) ($user['role'] ?? '')) ?></td>
    <td style="min-width:240px;">
        <form method="post">
            <input type="hidden" name="id" value="<?= $id ?>">
            <div style="max-height:120px;overflow:auto;" class="mb-2">
                <?php foreach ($sites as $site) { ?>
                <label class="d-block small">
                    <input type="checkbox" name="branches[]" value="<?= htmlspecialchars($site['branch_name']) ?>" <?= in_array($site['branch_name'], $locs, true) ? 'checked' : '' ?>>
                    <?= htmlspecialchars($site['branch_name']) ?>
                </label>
                <?php } ?>
            </div>
            <button class="btn btn-sm btn-outline-primary" name="save_locations" value="1">Save locations</button>
        </form>
    </td>
    <td>
        <form method="post" class="d-flex gap-2">
            <input type="hidden" name="id" value="<?= $id ?>">
            <input class="form-control form-control-sm" name="email" value="<?= htmlspecialchars((string) ($user['email'] ?? '')) ?>">
            <button class="btn btn-sm btn-outline-primary">Save</button>
        </form>
    </td>
    <td>
        <form method="post" class="d-flex gap-2">
            <input type="hidden" name="id" value="<?= $id ?>">
            <input class="form-control form-control-sm" name="new_password" placeholder="New password" required>
            <button class="btn btn-sm btn-outline-danger" name="reset_password" value="1">Reset</button>
        </form>
    </td>
</tr>
<?php } ?>
</tbody>
</table>
</div>
<?php include 'includes/footer.php'; ?>
