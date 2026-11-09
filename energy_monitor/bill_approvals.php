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

if (sees_all_sites() && isset($_POST['decision'], $_POST['id'])) {
    $decision = (string) $_POST['decision'];
    $status = 'rejected';
    if ($decision === 'approve') {
        $status = 'approved';
    } elseif ($decision === 'revoke' || $decision === 'close') {
        $status = 'revoked';
    }
    $stmt = $conn->prepare("
        UPDATE bill_fault_requests
        SET status = ?, reviewed_by = ?, reviewed_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$status, $_SESSION['user'], (int) $_POST['id']]);
    ems_log_event($conn, (string) $_SESSION['user'], 'fault request ' . $status, 'status', 'Request ' . (int) $_POST['id']);
    header('Location: bill_approvals.php');
    exit;
}

$sql = 'SELECT * FROM bill_fault_requests';
$params = [];
if (!sees_all_sites()) {
    [$clause, $params] = scope_branch_clause('branch_name');
    $sql .= ' WHERE 1=1' . $clause;
}
$sql .= ' ORDER BY id DESC';
$stmt = $conn->prepare($sql);
$stmt->execute($params);

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="content">
<h2>Faulty meter approvals</h2>
<p class="text-muted">Direct units stay off until a request is approved. Revoke turns that field off again. A new request is required before anyone can type units.</p>
<table class="table table-bordered bg-white">
<thead><tr><th>When</th><th>Location</th><th>Broadcaster</th><th>Transmitter</th><th>Reason</th><th>By</th><th>Status</th><th></th></tr></thead>
<tbody>
<?php foreach ($stmt as $row) { ?>
<tr>
    <td><?= htmlspecialchars($row['created_at']) ?></td>
    <td><?= htmlspecialchars($row['branch_name']) ?></td>
    <td><?= htmlspecialchars($row['broadcaster']) ?></td>
    <td><?= htmlspecialchars($row['transmitter']) ?></td>
    <td><?= htmlspecialchars($row['reason']) ?></td>
    <td><?= htmlspecialchars($row['requested_by']) ?></td>
    <td><?= htmlspecialchars($row['status']) ?></td>
    <td>
        <?php if (sees_all_sites() && $row['status'] === 'pending') { ?>
        <form method="post" class="d-inline">
            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
            <button class="btn btn-sm btn-success" name="decision" value="approve">Approve</button>
            <button class="btn btn-sm btn-outline-danger" name="decision" value="reject">Reject</button>
        </form>
        <?php } elseif (sees_all_sites() && $row['status'] === 'approved') { ?>
        <form method="post" class="d-inline">
            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
            <button class="btn btn-sm btn-outline-danger" name="decision" value="revoke">Revoke</button>
        </form>
        <?php } ?>
    </td>
</tr>
<?php } ?>
</tbody>
</table>
</div>
<?php include 'includes/footer.php'; ?>
