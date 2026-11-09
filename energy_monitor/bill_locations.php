<?php

session_start();

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

require 'config/db.php';
require 'includes/auth_helpers.php';
require 'includes/bill_catalog.php';

bill_ensure($conn);
if (!sees_all_sites()) {
    header('Location: dashboard.php');
    exit;
}

$company = strtoupper($_GET['company'] ?? '');
$sql = "
    SELECT s.state_name, s.branch_name, s.company,
           b.broadcaster, b.transmitter, b.legal_name
    FROM bill_sites s
    LEFT JOIN bill_site_broadcasters b
        ON b.state_name = s.state_name AND b.branch_name = s.branch_name
";
$params = [];
if (in_array($company, ['KAL', 'SAFL'], true)) {
    $sql .= ' WHERE s.company = ?';
    $params[] = $company;
}
$sql .= ' ORDER BY s.company, s.state_name, s.branch_name, b.broadcaster';
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$siteCount = $conn->query('SELECT COUNT(*) FROM bill_sites')->fetchColumn();
$kalCount = $conn->query("SELECT COUNT(*) FROM bill_sites WHERE company = 'KAL'")->fetchColumn();
$saflCount = $conn->query("SELECT COUNT(*) FROM bill_sites WHERE company = 'SAFL'")->fetchColumn();

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="content">

<h2>Locations</h2>
<p class="text-muted">
    <?= (int) $siteCount ?> branches.
    South India (<?= (int) $kalCount ?>) is billed under KAL.
    The remaining branches (<?= (int) $saflCount ?>) are billed under SAFL.
</p>

<form method="get" class="mb-3">
    <select name="company" class="form-select" style="max-width:280px" onchange="this.form.submit()">
        <option value="">All companies</option>
        <option value="KAL" <?= $company === 'KAL' ? 'selected' : '' ?>>KAL Radio Limited — South</option>
        <option value="SAFL" <?= $company === 'SAFL' ? 'selected' : '' ?>>SAFL — North</option>
    </select>
</form>

<table class="table table-bordered table-sm bg-white">
    <thead>
        <tr>
            <th>Company</th>
            <th>State</th>
            <th>Branch</th>
            <th>Broadcaster</th>
            <th>Legal name on the worksheet</th>
            <th>Transmitter</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $row) { ?>
    <tr>
        <td><?= htmlspecialchars($row['company']) ?></td>
        <td><?= htmlspecialchars($row['state_name']) ?></td>
        <td><?= htmlspecialchars($row['branch_name']) ?></td>
        <td><?= htmlspecialchars((string) $row['broadcaster']) ?></td>
        <td><?= htmlspecialchars((string) $row['legal_name']) ?></td>
        <td><?= htmlspecialchars((string) $row['transmitter']) ?></td>
    </tr>
    <?php } ?>
    </tbody>
</table>

</div>

<?php include 'includes/footer.php'; ?>
