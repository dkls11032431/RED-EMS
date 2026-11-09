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

$company = strtoupper($_GET['company'] ?? '');
$type = strtoupper($_GET['type'] ?? '');
$branch = $_GET['branch'] ?? '';

$sql = "SELECT * FROM eb_worksheets WHERE bill_type IN ('BECIL', 'NON_BECIL')";
$params = [];
if (in_array($company, ['KAL', 'SAFL'], true)) {
    $sql .= ' AND company = ?';
    $params[] = $company;
}
if (in_array($type, ['BECIL', 'NON_BECIL'], true)) {
    $sql .= ' AND bill_type = ?';
    $params[] = $type;
}
if (!sees_all_sites()) {
    if ($branch !== '' && !user_can_branch($branch)) {
        $branch = '';
    }
    if ($branch === '') {
        [$clause, $scope] = scope_branch_clause('branch_name');
        $sql .= $clause;
        $params = array_merge($params, $scope);
    }
}
if ($branch !== '') {
    $sql .= ' AND branch_name = ?';
    $params[] = $branch;
}
$sql .= ' ORDER BY bill_month DESC, branch_name';
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="content">

<h2>EB bill report</h2>

<form method="get" class="row g-2 mb-3">
    <div class="col-md-3">
        <label class="form-label">Bill type</label>
        <select name="type" class="form-select">
            <option value="">All</option>
            <option value="BECIL" <?= $type === 'BECIL' ? 'selected' : '' ?>>BECIL</option>
            <option value="NON_BECIL" <?= $type === 'NON_BECIL' ? 'selected' : '' ?>>Non-BECIL</option>
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label">Company</label>
        <select name="company" class="form-select">
            <option value="">All</option>
            <option value="KAL" <?= $company === 'KAL' ? 'selected' : '' ?>>KAL (South)</option>
            <option value="SAFL" <?= $company === 'SAFL' ? 'selected' : '' ?>>SAFL (North)</option>
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label">Branch</label>
        <input type="text" name="branch" value="<?= htmlspecialchars($branch) ?>" class="form-control">
    </div>
    <div class="col-md-2">
        <label class="form-label">&nbsp;</label>
        <button class="btn btn-primary form-control">Search</button>
    </div>
</form>

<table class="table table-bordered table-striped bg-white">
    <thead>
        <tr>
            <th>Month</th>
            <th>Type</th>
            <th>Company</th>
            <th>State</th>
            <th>Branch</th>
            <th>Broadcasters</th>
            <th>Common units</th>
            <th>Bill amount</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
    <?php if ($rows === []) { ?>
    <tr><td colspan="9">No EB worksheets saved yet.</td></tr>
    <?php } ?>
    <?php foreach ($rows as $row) {
        $open = 'bill_entry.php?' . http_build_query([
            'type' => $row['bill_type'],
            'company' => $row['company'],
            'state' => $row['state_name'],
            'branch' => $row['branch_name'],
            'month' => $row['bill_month'],
        ]);
    ?>
    <tr>
        <td><?= htmlspecialchars($row['bill_month']) ?></td>
        <td><?= htmlspecialchars($row['bill_type']) ?></td>
        <td><?= htmlspecialchars($row['company']) ?></td>
        <td><?= htmlspecialchars($row['state_name']) ?></td>
        <td><?= htmlspecialchars($row['branch_name']) ?></td>
        <td><?= (int) $row['broadcaster_count'] ?></td>
        <td class="text-end"><?= number_format((float) $row['common_units'], 2) ?></td>
        <td class="text-end"><?= bill_inr($row['bill_amount']) ?></td>
        <td><a href="<?= htmlspecialchars($open) ?>">Open</a></td>
    </tr>
    <?php } ?>
    </tbody>
</table>

<p><a href="bill_analysis.php?type=BECIL">Open the management comparison</a></p>

</div>

<?php include 'includes/footer.php'; ?>
