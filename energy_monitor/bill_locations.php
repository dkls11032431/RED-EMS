<?php

session_start();

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

require 'config/db.php';
require 'includes/auth_helpers.php';
require 'includes/bill_catalog.php';
require 'includes/activity_log.php';

bill_ensure($conn);
if (!sees_all_sites()) {
    header('Location: dashboard.php');
    exit;
}
if (empty($_SESSION['ems_csrf'])) {
    $_SESSION['ems_csrf'] = bin2hex(random_bytes(16));
}

$notice = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_admin()) {
        $error = 'Only an administrator can change locations.';
    } elseif (!hash_equals($_SESSION['ems_csrf'], (string) ($_POST['csrf'] ?? ''))) {
        $error = 'The form expired. Reload the page and try again.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        try {
            if ($action === 'add_site') {
                $state = trim((string) ($_POST['state_name'] ?? ''));
                $branch = trim((string) ($_POST['branch_name'] ?? ''));
                $company = strtoupper((string) ($_POST['company'] ?? ''));
                if ($state === '' || $branch === '' || !in_array($company, ['KAL', 'SAFL'], true)) {
                    $error = 'State, location and company are required.';
                } else {
                    $conn->prepare('INSERT INTO bill_sites (state_name, branch_name, company) VALUES (?, ?, ?)')
                        ->execute([substr($state, 0, 80), substr($branch, 0, 80), $company]);
                    ems_log_event($conn, (string) $_SESSION['user'], 'location added', 'branch_name', $branch);
                    $notice = 'Location added.';
                }
            } elseif ($action === 'save_site') {
                $id = (int) ($_POST['id'] ?? 0);
                $state = trim((string) ($_POST['state_name'] ?? ''));
                $branch = trim((string) ($_POST['branch_name'] ?? ''));
                $company = strtoupper((string) ($_POST['company'] ?? ''));
                $current = $conn->prepare('SELECT * FROM bill_sites WHERE id = ?');
                $current->execute([$id]);
                $old = $current->fetch(PDO::FETCH_ASSOC);
                if (!$old || $state === '' || $branch === '' || !in_array($company, ['KAL', 'SAFL'], true)) {
                    $error = 'That location could not be updated.';
                } else {
                    $conn->prepare('UPDATE bill_sites SET state_name = ?, branch_name = ?, company = ? WHERE id = ?')
                        ->execute([substr($state, 0, 80), substr($branch, 0, 80), $company, $id]);
                    $conn->prepare('UPDATE bill_site_broadcasters SET state_name = ?, branch_name = ? WHERE state_name = ? AND branch_name = ?')
                        ->execute([$state, $branch, $old['state_name'], $old['branch_name']]);
                    $conn->prepare('UPDATE eb_worksheets SET state_name = ?, branch_name = ?, company = ? WHERE state_name = ? AND branch_name = ?')
                        ->execute([$state, $branch, $company, $old['state_name'], $old['branch_name']]);
                    ems_log_event($conn, (string) $_SESSION['user'], 'location updated', 'branch_name', $old['branch_name'] . ' -> ' . $branch);
                    $notice = 'Location updated. Saved worksheets for that location were renamed with it.';
                }
            } elseif ($action === 'delete_site') {
                $id = (int) ($_POST['id'] ?? 0);
                $current = $conn->prepare('SELECT * FROM bill_sites WHERE id = ?');
                $current->execute([$id]);
                $old = $current->fetch(PDO::FETCH_ASSOC);
                if (!$old) {
                    $error = 'That location is already gone.';
                } else {
                    $conn->prepare('DELETE FROM bill_site_broadcasters WHERE state_name = ? AND branch_name = ?')
                        ->execute([$old['state_name'], $old['branch_name']]);
                    $conn->prepare('DELETE FROM bill_sites WHERE id = ?')->execute([$id]);
                    ems_log_event($conn, (string) $_SESSION['user'], 'location deleted', 'branch_name', $old['branch_name']);
                    $notice = $old['branch_name'] . ' was removed. Worksheets already saved for it were kept.';
                }
            } elseif ($action === 'add_broadcaster') {
                $state = trim((string) ($_POST['state_name'] ?? ''));
                $branch = trim((string) ($_POST['branch_name'] ?? ''));
                $name = trim((string) ($_POST['broadcaster'] ?? ''));
                $transmitter = trim((string) ($_POST['transmitter'] ?? ''));
                $legal = trim((string) ($_POST['legal_name'] ?? ''));
                if ($state === '' || $branch === '' || $name === '') {
                    $error = 'Choose a location and enter the broadcaster.';
                } else {
                    if ($legal === '') {
                        $legal = bill_legal_name($name, $state);
                    }
                    $conn->prepare('INSERT INTO bill_site_broadcasters (state_name, branch_name, broadcaster, transmitter, legal_name) VALUES (?, ?, ?, ?, ?)')
                        ->execute([substr($state, 0, 80), substr($branch, 0, 80), substr($name, 0, 120), substr($transmitter, 0, 120), substr($legal, 0, 180)]);
                    ems_log_event($conn, (string) $_SESSION['user'], 'broadcaster added', 'broadcaster', $branch . ' · ' . $name);
                    $notice = 'Broadcaster added.';
                }
            } elseif ($action === 'save_broadcaster') {
                $id = (int) ($_POST['id'] ?? 0);
                $name = trim((string) ($_POST['broadcaster'] ?? ''));
                $transmitter = trim((string) ($_POST['transmitter'] ?? ''));
                $legal = trim((string) ($_POST['legal_name'] ?? ''));
                if ($name === '') {
                    $error = 'The broadcaster name is required.';
                } else {
                    $conn->prepare('UPDATE bill_site_broadcasters SET broadcaster = ?, transmitter = ?, legal_name = ? WHERE id = ?')
                        ->execute([substr($name, 0, 120), substr($transmitter, 0, 120), substr($legal, 0, 180), $id]);
                    ems_log_event($conn, (string) $_SESSION['user'], 'broadcaster updated', 'transmitter', $name . ' · ' . $transmitter);
                    $notice = 'Broadcaster updated.';
                }
            } elseif ($action === 'delete_broadcaster') {
                $id = (int) ($_POST['id'] ?? 0);
                $conn->prepare('DELETE FROM bill_site_broadcasters WHERE id = ?')->execute([$id]);
                ems_log_event($conn, (string) $_SESSION['user'], 'broadcaster deleted', 'id', (string) $id);
                $notice = 'Broadcaster removed.';
            }
        } catch (Throwable $e) {
            $error = 'That change was not saved. A location or broadcaster with the same name may already exist.';
        }
    }
}

$company = strtoupper($_GET['company'] ?? '');
$siteSql = 'SELECT id, state_name, branch_name, company FROM bill_sites';
$params = [];
if (in_array($company, ['KAL', 'SAFL'], true)) {
    $siteSql .= ' WHERE company = ?';
    $params[] = $company;
}
$siteSql .= ' ORDER BY company, state_name, branch_name';
$siteStmt = $conn->prepare($siteSql);
$siteStmt->execute($params);
$sites = $siteStmt->fetchAll(PDO::FETCH_ASSOC);

$txSql = 'SELECT id, state_name, branch_name, broadcaster, transmitter, legal_name FROM bill_site_broadcasters';
$txParams = [];
if (in_array($company, ['KAL', 'SAFL'], true)) {
    $txSql .= ' WHERE branch_name IN (SELECT branch_name FROM bill_sites WHERE company = ?)';
    $txParams[] = $company;
}
$txSql .= ' ORDER BY branch_name, broadcaster, transmitter';
$txStmt = $conn->prepare($txSql);
$txStmt->execute($txParams);
$broadcasters = $txStmt->fetchAll(PDO::FETCH_ASSOC);

$counts = [];
foreach ($broadcasters as $row) {
    $key = strtoupper($row['state_name'] . '|' . $row['branch_name']);
    $counts[$key][strtoupper($row['broadcaster'])] = true;
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="content">
<h1 class="page-title">Locations</h1>
<p class="page-lead">Broadcaster names follow the workbook. The transmitter is stored here and can be corrected by an administrator. The count is the number of broadcasters at that location.</p>
<?php if ($notice !== '') { ?><div class="alert alert-success"><?= htmlspecialchars($notice) ?></div><?php } ?>
<?php if ($error !== '') { ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php } ?>

<form method="get" class="mb-3">
    <label class="form-label" for="company">Company</label>
    <select id="company" name="company" class="form-select" style="max-width:280px" onchange="this.form.submit()">
        <option value="">All companies</option>
        <option value="KAL" <?= $company === 'KAL' ? 'selected' : '' ?>>KAL — South</option>
        <option value="SAFL" <?= $company === 'SAFL' ? 'selected' : '' ?>>SAFL</option>
    </select>
</form>

<div class="table-card p-2 mb-3">
<div class="table-responsive">
<table class="table table-sm mb-0">
<thead><tr><th>Company</th><th>State</th><th>Location</th><th class="text-end">Broadcasters</th><?php if (is_admin()) { ?><th></th><?php } ?></tr></thead>
<tbody>
<?php foreach ($sites as $site) {
    $count = count($counts[strtoupper($site['state_name'] . '|' . $site['branch_name'])] ?? []);
?>
<tr>
    <?php if (is_admin()) { ?>
    <td>
        <form method="post" id="site-<?= (int) $site['id'] ?>">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['ems_csrf']) ?>">
            <input type="hidden" name="id" value="<?= (int) $site['id'] ?>">
            <select form="site-<?= (int) $site['id'] ?>" name="company" class="form-select form-select-sm">
                <option value="KAL" <?= $site['company'] === 'KAL' ? 'selected' : '' ?>>KAL</option>
                <option value="SAFL" <?= $site['company'] === 'SAFL' ? 'selected' : '' ?>>SAFL</option>
            </select>
        </form>
    </td>
    <td><input form="site-<?= (int) $site['id'] ?>" class="form-control form-control-sm" name="state_name" value="<?= htmlspecialchars($site['state_name']) ?>" required></td>
    <td><input form="site-<?= (int) $site['id'] ?>" class="form-control form-control-sm" name="branch_name" value="<?= htmlspecialchars($site['branch_name']) ?>" required></td>
    <td class="text-end"><?= $count ?></td>
    <td class="text-nowrap">
        <button form="site-<?= (int) $site['id'] ?>" class="btn btn-sm btn-primary" name="action" value="save_site">Save</button>
        <button form="site-<?= (int) $site['id'] ?>" class="btn btn-sm btn-outline-danger" name="action" value="delete_site" onclick="return confirm('Remove this location from the master list?');">Delete</button>
    </td>
    <?php } else { ?>
        <td><?= htmlspecialchars($site['company']) ?></td>
        <td><?= htmlspecialchars($site['state_name']) ?></td>
        <td><?= htmlspecialchars($site['branch_name']) ?></td>
        <td class="text-end"><?= $count ?></td>
    <?php } ?>
</tr>
<?php } ?>
</tbody>
</table>
</div>
</div>

<?php if (is_admin()) { ?>
<form method="post" class="panel mb-3">
    <h2>Add a location</h2>
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['ems_csrf']) ?>">
    <input type="hidden" name="action" value="add_site">
    <div class="row g-2">
        <div class="col-md-3"><label class="form-label">State</label><input class="form-control" name="state_name" required maxlength="80"></div>
        <div class="col-md-3"><label class="form-label">Location</label><input class="form-control" name="branch_name" required maxlength="80"></div>
        <div class="col-md-2">
            <label class="form-label">Company</label>
            <select class="form-select" name="company"><option value="SAFL">SAFL</option><option value="KAL">KAL</option></select>
        </div>
        <div class="col-md-2"><label class="form-label">&nbsp;</label><button class="btn btn-primary w-100">Add</button></div>
    </div>
</form>
<?php } ?>

<div class="table-card p-2 mb-3">
<div class="table-responsive">
<table class="table table-sm mb-0">
<thead><tr><th>Location</th><th>Broadcaster</th><th>Transmitter</th><th>Legal name</th><?php if (is_admin()) { ?><th></th><?php } ?></tr></thead>
<tbody>
<?php foreach ($broadcasters as $row) { ?>
<tr>
    <?php if (is_admin()) { ?>
    <td>
        <?= htmlspecialchars($row['branch_name']) ?>
        <form method="post" id="tx-<?= (int) $row['id'] ?>">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['ems_csrf']) ?>">
            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
        </form>
    </td>
    <td><input form="tx-<?= (int) $row['id'] ?>" class="form-control form-control-sm" name="broadcaster" value="<?= htmlspecialchars($row['broadcaster']) ?>" required></td>
    <td><input form="tx-<?= (int) $row['id'] ?>" class="form-control form-control-sm" name="transmitter" value="<?= htmlspecialchars((string) $row['transmitter']) ?>" placeholder="Transmitter name"></td>
    <td><input form="tx-<?= (int) $row['id'] ?>" class="form-control form-control-sm" name="legal_name" value="<?= htmlspecialchars((string) $row['legal_name']) ?>"></td>
    <td class="text-nowrap">
        <button form="tx-<?= (int) $row['id'] ?>" class="btn btn-sm btn-primary" name="action" value="save_broadcaster">Save</button>
        <button form="tx-<?= (int) $row['id'] ?>" class="btn btn-sm btn-outline-danger" name="action" value="delete_broadcaster" onclick="return confirm('Remove this broadcaster?');">Delete</button>
    </td>
    <?php } else { ?>
        <td><?= htmlspecialchars($row['branch_name']) ?></td>
        <td><?= htmlspecialchars($row['broadcaster']) ?></td>
        <td><?= htmlspecialchars((string) $row['transmitter']) ?></td>
        <td><?= htmlspecialchars((string) $row['legal_name']) ?></td>
    <?php } ?>
</tr>
<?php } ?>
</tbody>
</table>
</div>
</div>

<?php if (is_admin()) { ?>
<form method="post" class="panel">
    <h2>Add a broadcaster</h2>
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['ems_csrf']) ?>">
    <input type="hidden" name="action" value="add_broadcaster">
    <div class="row g-2">
        <div class="col-md-3">
            <label class="form-label">Location</label>
            <select class="form-select" name="branch_name" required onchange="this.form.state_name.value=this.selectedOptions[0].dataset.state">
                <?php foreach ($sites as $site) { ?>
                <option value="<?= htmlspecialchars($site['branch_name']) ?>" data-state="<?= htmlspecialchars($site['state_name']) ?>"><?= htmlspecialchars($site['branch_name']) ?></option>
                <?php } ?>
            </select>
            <input type="hidden" name="state_name" value="<?= htmlspecialchars($sites[0]['state_name'] ?? '') ?>">
        </div>
        <div class="col-md-3"><label class="form-label">Broadcaster</label><input class="form-control" name="broadcaster" required maxlength="120"></div>
        <div class="col-md-3"><label class="form-label">Transmitter</label><input class="form-control" name="transmitter" maxlength="120"></div>
        <div class="col-md-3"><label class="form-label">Legal name</label><input class="form-control" name="legal_name" maxlength="180"></div>
        <div class="col-12"><button class="btn btn-primary">Add broadcaster</button></div>
    </div>
</form>
<?php } ?>
</div>
<?php include 'includes/footer.php'; ?>
