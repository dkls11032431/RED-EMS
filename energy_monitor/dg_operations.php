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

if (empty($_SESSION['ems_csrf'])) {
    $_SESSION['ems_csrf'] = bin2hex(random_bytes(16));
}

function dg_hmm(string $out, string $in): ?float
{
    if (!preg_match('/^\d{2}:\d{2}$/', $out) || !preg_match('/^\d{2}:\d{2}$/', $in)) {
        return null;
    }
    [$oh, $om] = array_map('intval', explode(':', $out));
    [$ih, $im] = array_map('intval', explode(':', $in));
    $start = $oh * 60 + $om;
    $end = $ih * 60 + $im;
    if ($end < $start) {
        $end += 24 * 60;
    }
    $minutes = $end - $start;
    $hours = intdiv($minutes, 60);
    $mins = $minutes % 60;
    return (float) sprintf('%d.%02d', $hours, $mins);
}

function dg_num($value): ?float
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    if (!is_numeric($value)) {
        return null;
    }
    return round((float) $value, 2);
}

$branchSql = 'SELECT state_name, branch_name, company FROM bill_sites';
$branchParams = [];
if (!sees_all_sites()) {
    [$clause, $branchParams] = scope_branch_clause('branch_name');
    $branchSql .= ' WHERE 1=1' . $clause;
}
$branchSql .= ' ORDER BY branch_name';
$branchStmt = $conn->prepare($branchSql);
$branchStmt->execute($branchParams);
$branches = $branchStmt->fetchAll(PDO::FETCH_ASSOC);

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['ems_csrf'], (string) ($_POST['csrf'] ?? ''))) {
        $error = 'The form expired. Reload the page and try again.';
    } elseif (($_POST['action'] ?? '') === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $rowStmt = $conn->prepare('SELECT branch_name FROM dg_daily_log WHERE id = ?');
        $rowStmt->execute([$id]);
        $branch = (string) $rowStmt->fetchColumn();
        if ($branch === '' || !user_can_branch($branch)) {
            $error = 'That log row is not available for your account.';
        } else {
            $conn->prepare('DELETE FROM dg_daily_log WHERE id = ?')->execute([$id]);
            ems_log_event($conn, (string) $_SESSION['user'], 'dg log deleted', 'id', (string) $id);
            header('Location: dg_operations.php?saved=1');
            exit;
        }
    } else {
        $date = (string) ($_POST['log_date'] ?? '');
        $category = strtoupper((string) ($_POST['category'] ?? ''));
        $branch = trim((string) ($_POST['branch_name'] ?? ''));
        $opening = dg_num($_POST['opening_kwh'] ?? '');
        $closing = dg_num($_POST['closing_kwh'] ?? '');
        $out = trim((string) ($_POST['power_out'] ?? ''));
        $in = trim((string) ($_POST['power_in'] ?? ''));
        $tank = dg_num($_POST['tank_litres'] ?? '');
        $used = dg_num($_POST['consumption_litres'] ?? '');
        $hand = dg_num($_POST['hand_stock_litres'] ?? '');
        $operator = trim((string) ($_POST['fuel_operator'] ?? ''));
        $qty = dg_num($_POST['fuel_qty'] ?? '');
        $remarks = trim((string) ($_POST['remarks'] ?? ''));
        $known = false;
        $company = '';
        foreach ($branches as $site) {
            if (strcasecmp($site['branch_name'], $branch) === 0) {
                $known = true;
                $company = $site['company'];
                $branch = $site['branch_name'];
            }
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $error = 'Choose a valid date.';
        } elseif (!in_array($category, ['CTI', 'OFFICE'], true)) {
            $error = 'Category must be CTI or Office.';
        } elseif (!$known || !user_can_branch($branch)) {
            $error = 'Choose a location assigned to your account.';
        } elseif ($opening === null || $closing === null) {
            $error = 'Opening and closing kWh are required numbers.';
        } elseif ($closing < $opening) {
            $error = 'Closing kWh cannot be lower than the opening reading.';
        } elseif ($out !== '' && !preg_match('/^\d{2}:\d{2}$/', $out)) {
            $error = 'Power out time must be HH:MM.';
        } elseif ($in !== '' && !preg_match('/^\d{2}:\d{2}$/', $in)) {
            $error = 'Power in time must be HH:MM.';
        } elseif (($tank !== null && $tank < 0) || ($used !== null && $used < 0) || ($hand !== null && $hand < 0) || ($qty !== null && $qty < 0)) {
            $error = 'Fuel quantities cannot be negative.';
        } else {
            $kwh = round($closing - $opening, 2);
            $running = dg_hmm($out, $in);
            $conn->prepare("
                INSERT INTO dg_daily_log (
                    log_date, category, company, branch_name, opening_kwh, closing_kwh, kwh,
                    power_out, power_in, running_hmm, tank_litres, consumption_litres, hand_stock_litres,
                    fuel_operator, fuel_qty, remarks, created_by, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $date, $category, $company, $branch, $opening, $closing, $kwh,
                $out, $in, $running ?? 0, $tank, $used, $hand,
                substr($operator, 0, 120), $qty, substr($remarks, 0, 500),
                (string) $_SESSION['user'], date('Y-m-d H:i:s'), date('Y-m-d H:i:s'),
            ]);
            ems_log_event($conn, (string) $_SESSION['user'], 'dg log saved', $branch, $date . ' ' . $category);
            header('Location: dg_operations.php?saved=1&branch=' . urlencode($branch));
            exit;
        }
    }
}

$filterBranch = trim((string) ($_GET['branch'] ?? ''));
$filterCategory = strtoupper((string) ($_GET['category'] ?? ''));
$from = (string) ($_GET['from'] ?? '');
$to = (string) ($_GET['to'] ?? '');
if ($filterBranch !== '' && !user_can_branch($filterBranch)) {
    $filterBranch = '';
}
if (!in_array($filterCategory, ['', 'CTI', 'OFFICE'], true)) {
    $filterCategory = '';
}

$sql = 'SELECT * FROM dg_daily_log WHERE 1=1';
$params = [];
if (!sees_all_sites()) {
    [$clause, $scope] = scope_branch_clause('branch_name');
    $sql .= $clause;
    $params = array_merge($params, $scope);
}
if ($filterBranch !== '') {
    $sql .= ' AND branch_name = ?';
    $params[] = $filterBranch;
}
if ($filterCategory !== '') {
    $sql .= ' AND category = ?';
    $params[] = $filterCategory;
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $sql .= ' AND log_date >= ?';
    $params[] = $from;
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $sql .= ' AND log_date <= ?';
    $params[] = $to;
}
$sql .= ' ORDER BY log_date DESC, id DESC';
$list = $conn->prepare($sql);
$list->execute($params);
$rows = $list->fetchAll(PDO::FETCH_ASSOC);

if (isset($_GET['export']) && in_array($_GET['export'], ['excel', 'pdf'], true)) {
    $headers = ['Date', 'Category', 'Company', 'Location', 'DG opening kWh', 'DG closing kWh', 'kWh', 'Power out', 'Power in', 'DG running H.MM', 'Diesel stock in tank (L)', 'Diesel consumption (L)', 'Physical stock outside (L)', 'Fuel sharing operator', 'Fuel qty', 'Remarks'];
    if ($_GET['export'] === 'excel') {
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename=dg_operations.xls');
        echo implode("\t", $headers) . "\n";
        foreach ($rows as $row) {
            $line = [
                $row['log_date'], $row['category'], $row['company'], $row['branch_name'],
                $row['opening_kwh'], $row['closing_kwh'], $row['kwh'], $row['power_out'], $row['power_in'],
                $row['running_hmm'], $row['tank_litres'], $row['consumption_litres'], $row['hand_stock_litres'],
                $row['fuel_operator'], $row['fuel_qty'], str_replace(["\t", "\n", "\r"], ' ', $row['remarks']),
            ];
            echo implode("\t", $line) . "\n";
        }
        exit;
    }
    require 'fpdf/fpdf.php';
    $pdf = new FPDF('L', 'mm', 'A4');
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, 'RED SURYAN EMS  DG operations log', 0, 1);
    $pdf->SetFont('Arial', '', 7);
    foreach ($rows as $row) {
        $pdf->Cell(0, 5, sprintf(
            '%s  %s  %s  %s  open %s  close %s  kWh %s  %s-%s  run %s  tank %s  used %s  hand %s  %s %s  %s',
            $row['log_date'],
            $row['category'],
            $row['company'],
            $row['branch_name'],
            $row['opening_kwh'],
            $row['closing_kwh'],
            $row['kwh'],
            $row['power_out'],
            $row['power_in'],
            $row['running_hmm'],
            $row['tank_litres'],
            $row['consumption_litres'],
            $row['hand_stock_litres'],
            $row['fuel_operator'],
            $row['fuel_qty'],
            substr(str_replace(["\n", "\r"], ' ', $row['remarks']), 0, 80)
        ), 0, 1);
    }
    if ($rows === []) {
        $pdf->Cell(0, 8, 'No DG operations saved for this filter.', 0, 1);
    }
    $pdf->Output('I', 'dg_operations.pdf');
    exit;
}

$operators = [];
if ($filterBranch !== '') {
    $op = $conn->prepare('SELECT DISTINCT broadcaster FROM bill_site_broadcasters WHERE branch_name = ? ORDER BY broadcaster');
    $op->execute([$filterBranch]);
    $operators = $op->fetchAll(PDO::FETCH_COLUMN);
}

$exportQuery = $_GET;
unset($exportQuery['saved']);
$excelHref = 'dg_operations.php?' . http_build_query($exportQuery + ['export' => 'excel']);
$pdfHref = 'dg_operations.php?' . http_build_query($exportQuery + ['export' => 'pdf']);

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="content">
    <h1 class="page-title">DG operations log</h1>
    <p class="page-lead">Daily diesel generator runs for CTI and Office. Running time is stored as hours.minutes, so 0.35 means 35 minutes. Fuel-sharing names come from the broadcasters already loaded for that location.</p>
    <?php if (isset($_GET['saved'])) { ?><div class="alert alert-success">DG log saved.</div><?php } ?>
    <?php if ($error !== '') { ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php } ?>

    <form method="get" class="panel mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label" for="branch">Location</label>
                <select id="branch" name="branch" class="form-select">
                    <option value="">All assigned</option>
                    <?php foreach ($branches as $site) { ?>
                    <option value="<?= htmlspecialchars($site['branch_name']) ?>" <?= strcasecmp($filterBranch, $site['branch_name']) === 0 ? 'selected' : '' ?>>
                        <?= htmlspecialchars($site['branch_name'] . ' · ' . $site['company']) ?>
                    </option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="category">Category</label>
                <select id="category" name="category" class="form-select">
                    <option value="">CTI and Office</option>
                    <option value="CTI" <?= $filterCategory === 'CTI' ? 'selected' : '' ?>>CTI</option>
                    <option value="OFFICE" <?= $filterCategory === 'OFFICE' ? 'selected' : '' ?>>Office</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="from">From</label>
                <input id="from" type="date" name="from" value="<?= htmlspecialchars($from) ?>" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label" for="to">To</label>
                <input id="to" type="date" name="to" value="<?= htmlspecialchars($to) ?>" class="form-control">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-primary">Apply</button>
                <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($excelHref) ?>">Excel</a>
                <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($pdfHref) ?>">PDF</a>
            </div>
        </div>
    </form>

    <form method="post" class="panel mb-3">
        <h2>New run</h2>
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['ems_csrf']) ?>">
        <div class="row g-2">
            <div class="col-md-2"><label class="form-label">Date</label><input type="date" name="log_date" class="form-control" required value="<?= htmlspecialchars(date('Y-m-d')) ?>"></div>
            <div class="col-md-2">
                <label class="form-label">Category</label>
                <select name="category" class="form-select" required>
                    <option value="CTI">CTI</option>
                    <option value="OFFICE">Office</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Location</label>
                <select name="branch_name" class="form-select" required>
                    <?php foreach ($branches as $site) { ?>
                    <option value="<?= htmlspecialchars($site['branch_name']) ?>"><?= htmlspecialchars($site['branch_name']) ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-2"><label class="form-label">Opening kWh</label><input name="opening_kwh" class="form-control" inputmode="decimal" required></div>
            <div class="col-md-2"><label class="form-label">Closing kWh</label><input name="closing_kwh" class="form-control" inputmode="decimal" required></div>
            <div class="col-md-1"><label class="form-label">Out</label><input name="power_out" class="form-control" placeholder="06:30"></div>
            <div class="col-md-1"><label class="form-label">In</label><input name="power_in" class="form-control" placeholder="07:30"></div>
            <div class="col-md-2"><label class="form-label">Tank stock (L)</label><input name="tank_litres" class="form-control" inputmode="decimal"></div>
            <div class="col-md-2"><label class="form-label">Consumption (L)</label><input name="consumption_litres" class="form-control" inputmode="decimal"></div>
            <div class="col-md-2"><label class="form-label">Stock outside (L)</label><input name="hand_stock_litres" class="form-control" inputmode="decimal"></div>
            <div class="col-md-3">
                <label class="form-label">Fuel sharing operator</label>
                <input name="fuel_operator" class="form-control" list="operators" maxlength="120">
                <datalist id="operators">
                    <?php foreach ($operators as $name) { ?>
                    <option value="<?= htmlspecialchars($name) ?>"></option>
                    <?php } ?>
                </datalist>
            </div>
            <div class="col-md-2"><label class="form-label">Fuel qty (L)</label><input name="fuel_qty" class="form-control" inputmode="decimal"></div>
            <div class="col-md-6"><label class="form-label">Remarks</label><input name="remarks" class="form-control" maxlength="500"></div>
            <div class="col-12"><button class="btn btn-primary">Save run</button></div>
        </div>
        <?php if ($operators === [] && $filterBranch === '') { ?>
        <p class="small text-muted mb-0 mt-2">Filter by a location to list its broadcasters in the fuel-sharing field. The catalog is the one already stored for EB and DG bills.</p>
        <?php } ?>
    </form>

    <div class="table-card p-2">
        <div class="table-responsive">
        <table class="table table-sm table-bordered mb-0">
            <thead>
                <tr>
                    <th>Date</th><th>Category</th><th>Location</th><th>Opening</th><th>Closing</th><th>kWh</th>
                    <th>Out</th><th>In</th><th>Running</th><th>Tank</th><th>Used</th><th>Outside</th>
                    <th>Operator</th><th>Fuel qty</th><th>Remarks</th><th></th>
                </tr>
            </thead>
            <tbody>
            <?php if ($rows === []) { ?>
            <tr><td colspan="16">No DG operations saved for this filter. The sample worksheet is not loaded as live data.</td></tr>
            <?php } ?>
            <?php foreach ($rows as $row) { ?>
            <tr>
                <td><?= htmlspecialchars(date('l, j F, Y', strtotime($row['log_date']))) ?></td>
                <td><?= $row['category'] === 'OFFICE' ? 'Office' : 'CTI' ?></td>
                <td><?= htmlspecialchars($row['branch_name'] . ' · ' . $row['company']) ?></td>
                <td class="text-end"><?= number_format((float) $row['opening_kwh'], 2) ?></td>
                <td class="text-end"><?= number_format((float) $row['closing_kwh'], 2) ?></td>
                <td class="text-end"><?= number_format((float) $row['kwh'], 2) ?></td>
                <td><?= htmlspecialchars($row['power_out']) ?></td>
                <td><?= htmlspecialchars($row['power_in']) ?></td>
                <td class="text-end"><?= htmlspecialchars((string) $row['running_hmm']) ?></td>
                <td class="text-end"><?= $row['tank_litres'] === null ? '' : htmlspecialchars((string) $row['tank_litres']) ?></td>
                <td class="text-end"><?= $row['consumption_litres'] === null ? '' : htmlspecialchars((string) $row['consumption_litres']) ?></td>
                <td class="text-end"><?= $row['hand_stock_litres'] === null ? '' : htmlspecialchars((string) $row['hand_stock_litres']) ?></td>
                <td><?= htmlspecialchars($row['fuel_operator']) ?></td>
                <td class="text-end"><?= $row['fuel_qty'] === null ? '' : htmlspecialchars((string) $row['fuel_qty']) ?></td>
                <td><?= htmlspecialchars($row['remarks']) ?></td>
                <td>
                    <form method="post">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['ems_csrf']) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                        <button class="btn btn-sm btn-outline-danger">Delete</button>
                    </form>
                </td>
            </tr>
            <?php } ?>
            </tbody>
        </table>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
