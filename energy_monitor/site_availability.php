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
    $rows = $conn->query('SELECT * FROM site_availability ORDER BY availability_percent DESC')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $error = 'The site availability table is not available on this database.';
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="content">
    <h1 class="page-title">Site availability</h1>
    <p class="page-lead">Uptime recorded in the existing site availability table.</p>
    <?php if ($error !== '') { ?>
    <div class="alert alert-warning"><?= htmlspecialchars($error) ?></div>
    <?php } elseif ($rows === []) { ?>
    <div class="empty-state">No availability rows are stored.</div>
    <?php } else { ?>
    <div class="table-card p-2">
    <table class="table table-sm mb-0">
        <thead>
            <tr><th>Site</th><th>Date</th><th>Uptime</th><th>Downtime</th><th>Availability</th></tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $row) { ?>
        <tr>
            <td><?= htmlspecialchars((string) ($row['site_name'] ?? '')) ?></td>
            <td><?= htmlspecialchars((string) ($row['report_date'] ?? '')) ?></td>
            <td><?= htmlspecialchars((string) ($row['uptime_minutes'] ?? '')) ?></td>
            <td><?= htmlspecialchars((string) ($row['downtime_minutes'] ?? '')) ?></td>
            <td><?= htmlspecialchars((string) ($row['availability_percent'] ?? '')) ?>%</td>
        </tr>
        <?php } ?>
        </tbody>
    </table>
    </div>
    <?php } ?>
</div>
<?php include 'includes/footer.php'; ?>
