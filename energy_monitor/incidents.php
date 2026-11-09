<?php

session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

require 'config/db.php';

$rows = [];
$error = '';
try {
    $rows = $conn->query('SELECT * FROM incidents ORDER BY opened_at DESC')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $error = 'The incidents table is not available on this database.';
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="content">
    <h1 class="page-title">Incidents</h1>
    <p class="page-lead">Open and resolved site incidents recorded in the existing incidents table.</p>
    <?php if ($error !== '') { ?>
    <div class="alert alert-warning"><?= htmlspecialchars($error) ?></div>
    <?php } elseif ($rows === []) { ?>
    <div class="empty-state">No incidents are stored.</div>
    <?php } else { ?>
    <div class="table-card p-2">
    <div class="table-responsive">
    <table class="table table-sm mb-0">
        <thead>
            <tr>
                <th>ID</th><th>Site</th><th>Alarm</th><th>Severity</th><th>Assigned to</th><th>Status</th><th>Opened</th><th>Resolved</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $row) { ?>
        <tr>
            <td><?= htmlspecialchars((string) ($row['id'] ?? '')) ?></td>
            <td><?= htmlspecialchars((string) ($row['site_name'] ?? '')) ?></td>
            <td><?= htmlspecialchars((string) ($row['alarm_type'] ?? '')) ?></td>
            <td><?= htmlspecialchars((string) ($row['severity'] ?? '')) ?></td>
            <td><?= htmlspecialchars((string) ($row['assigned_to'] ?? '')) ?></td>
            <td><?= htmlspecialchars((string) ($row['status'] ?? '')) ?></td>
            <td><?= htmlspecialchars((string) ($row['opened_at'] ?? '')) ?></td>
            <td><?= htmlspecialchars((string) ($row['resolved_at'] ?? '')) ?></td>
        </tr>
        <?php } ?>
        </tbody>
    </table>
    </div>
    </div>
    <?php } ?>
</div>
<?php include 'includes/footer.php'; ?>
