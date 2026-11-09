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

bill_ensure($conn);
ems_ensure_plant($conn);

$allowedTypes = ['BECIL', 'NON_BECIL', 'DG'];
$type = strtoupper($_GET['type'] ?? $_POST['bill_type'] ?? 'BECIL');
if (!in_array($type, $allowedTypes, true)) {
    $type = 'BECIL';
}
$company = strtoupper($_GET['company'] ?? $_POST['company'] ?? 'SAFL');
if (!in_array($company, ['KAL', 'SAFL'], true)) {
    $company = 'SAFL';
}

if (!sees_all_sites()) {
    $allowedBranches = user_branches();
    if ($allowedBranches === []) {
        $sites = [];
    } else {
        $placeholders = implode(',', array_fill(0, count($allowedBranches), '?'));
        $siteStmt = $conn->prepare("SELECT state_name, branch_name, company FROM bill_sites WHERE branch_name IN ($placeholders) ORDER BY state_name, branch_name");
        $siteStmt->execute($allowedBranches);
        $sites = $siteStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    $allowedCompanies = array_values(array_unique(array_column($sites, 'company')));
    if ($allowedCompanies !== [] && !in_array($company, $allowedCompanies, true)) {
        $company = $allowedCompanies[0];
    }
    $sites = array_values(array_filter($sites, static function ($site) use ($company) {
        return $site['company'] === $company;
    }));
} else {
    $sites = bill_sites($conn, $company);
}
$state = $_GET['state'] ?? $_POST['state_name'] ?? ($sites[0]['state_name'] ?? '');
$branch = $_GET['branch'] ?? $_POST['branch_name'] ?? '';
$month = $_GET['month'] ?? $_POST['bill_month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}

$states = [];
foreach ($sites as $site) {
    $states[$site['state_name']] = true;
}
if (!isset($states[$state])) {
    $state = $sites[0]['state_name'] ?? '';
}
$branches = [];
foreach ($sites as $site) {
    if ($site['state_name'] === $state) {
        $branches[] = $site['branch_name'];
    }
}
if ($branch === '' || !in_array($branch, $branches, true)) {
    $branch = $branches[0] ?? '';
}

$catalog = $branch !== '' ? bill_broadcasters($conn, $state, $branch) : [];
$errors = [];
$saved = isset($_GET['saved']);

$existing = ($branch !== '') ? bill_find_sheet($conn, $type, $company, $branch, $month) : null;
$existingLines = $existing ? bill_sheet_lines($conn, (int) $existing['id']) : [];
$existingByKey = [];
foreach ($existingLines as $line) {
    $existingByKey[$line['broadcaster'] . '|' . $line['transmitter']] = $line;
}

if (is_admin() && isset($_POST['upload_pdf']) && $existing) {
    $uploadError = '';
    $file = $_FILES['bill_pdf'] ?? null;
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $uploadError = 'Choose a PDF bill to upload.';
    } elseif (($file['size'] ?? 0) > 8 * 1024 * 1024) {
        $uploadError = 'The PDF must be 8 MB or smaller.';
    } else {
        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        $mime = '';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = (string) finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
            }
        }
        if ($ext !== 'pdf' || ($mime !== '' && $mime !== 'application/pdf')) {
            $uploadError = 'Only a PDF bill can be stored on this worksheet.';
        } else {
            $dir = __DIR__ . '/uploads/bills';
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            $stored = 'uploads/bills/bill_' . (int) $existing['id'] . '_' . date('YmdHis') . '.pdf';
            if (!move_uploaded_file($file['tmp_name'], __DIR__ . '/' . $stored)) {
                $uploadError = 'The PDF could not be saved.';
            } else {
                if (!empty($existing['bill_pdf'])) {
                    $old = __DIR__ . '/' . ltrim((string) $existing['bill_pdf'], '/');
                    if (is_file($old)) {
                        @unlink($old);
                    }
                }
                $conn->prepare('UPDATE eb_worksheets SET bill_pdf = ? WHERE id = ?')->execute([$stored, (int) $existing['id']]);
                ems_log_event($conn, (string) $_SESSION['user'], 'bill pdf uploaded', 'bill_pdf', $branch . ' · ' . $month . ' · ' . basename($stored));
                header('Location: bill_entry.php?' . http_build_query([
                    'type' => $type, 'company' => $company, 'state' => $state, 'branch' => $branch, 'month' => $month, 'pdf' => 1,
                ]));
                exit;
            }
        }
    }
    $errors[] = $uploadError;
}

if (isset($_POST['request_fault']) && $branch !== '' && user_can_branch($branch)) {
    $index = (int) $_POST['request_fault'];
    $item = $catalog[$index] ?? null;
    $reason = trim((string) ($_POST['fault_reason'][$index] ?? ''));
    if ($item && $reason !== '' && ems_fault_status($conn, $type, $company, $branch, $item['broadcaster'], $item['transmitter']) === null) {
        $conn->prepare("
            INSERT INTO bill_fault_requests
                (bill_type, company, branch_name, broadcaster, transmitter, reason, status, requested_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?)
        ")->execute([
            $type, $company, $branch, $item['broadcaster'], $item['transmitter'],
            $reason, $_SESSION['user'], date('Y-m-d H:i:s'),
        ]);
        ems_log_event(
            $conn,
            (string) $_SESSION['user'],
            'faulty meter requested',
            'units',
            $branch . ' · ' . $item['broadcaster'] . ' · ' . $item['transmitter']
        );
    }
    header('Location: bill_entry.php?' . http_build_query([
        'type' => $type, 'company' => $company, 'state' => $state, 'branch' => $branch, 'month' => $month,
    ]));
    exit;
}

if (isset($_POST['save']) && $branch !== '' && user_can_branch($branch)) {
    $billAmount = (float) ($_POST['bill_amount'] ?? 0);
    $management = (float) ($_POST['management_charge'] ?? 2250);
    $gstRate = 18.0;
    $category = strtoupper(substr((string) ($_POST['category'] ?? 'B'), 0, 1));
    if (!in_array($category, ['A', 'B', 'C'], true)) {
        $category = 'B';
    }
    $utility = trim((string) ($_POST['utility_name'] ?? ''));
    $billDate = $_POST['bill_date'] ?? '';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $billDate)) {
        $billDate = null;
    }
    $commonOldPosted = (float) ($_POST['common_old'] ?? 0);
    $commonNew = (float) ($_POST['common_new'] ?? 0);
    $previousCommon = bill_previous_common($conn, $type, $company, $branch, $month);
    $commonOld = $previousCommon !== null ? $previousCommon : $commonOldPosted;

    if ($billAmount < 0) {
        $errors[] = 'Bill amount cannot be negative.';
    }
    if ($commonNew < $commonOld) {
        $errors[] = 'Common-area new reading is below the opening reading.';
    }

    $posted = $_POST['lines'] ?? [];
    $ownUnits = [];
    $prepared = [];
    foreach ($catalog as $index => $item) {
        $key = $index;
        $row = $posted[$key] ?? [];
        $approved = ems_fault_status($conn, $type, $company, $branch, $item['broadcaster'], $item['transmitter']) === 'approved';
        $previous = bill_previous_reading($conn, $type, $company, $branch, $month, $item['broadcaster'], $item['transmitter']);
        $old = $previous !== null ? $previous : (float) ($row['old'] ?? 0);
        $new = (float) ($row['new'] ?? 0);
        if ($approved) {
            $direct = round((float) ($row['direct'] ?? 0), 2);
            if ($direct < 0) {
                $errors[] = $item['broadcaster'] . ' direct units cannot be negative.';
            }
            $ownUnits[] = $direct;
            $prepared[] = [
                'item' => $item,
                'old' => $old,
                'new' => $new,
                'mode' => 'manual',
            ];
            continue;
        }
        if ($new < $old) {
            $errors[] = $item['broadcaster'] . ' (' . $item['transmitter'] . ') new reading is below the opening reading.';
        }
        $ownUnits[] = round($new - $old, 2);
        $prepared[] = [
            'item' => $item,
            'old' => $old,
            'new' => $new,
            'mode' => 'meter',
        ];
    }

    if ($catalog === []) {
        $errors[] = 'This branch has no broadcasters on the master list.';
    }

    if ($errors === []) {
        $commonUnits = round($commonNew - $commonOld, 2);
        $apportioned = bill_apportion($billAmount, $commonUnits, $ownUnits, $management, $gstRate);
        $now = date('Y-m-d H:i:s');
        $conn->beginTransaction();
        try {
            if ($existing) {
                $sheetId = (int) $existing['id'];
                $update = $conn->prepare("
                    UPDATE eb_worksheets SET
                        state_name = ?, bill_date = ?, category = ?, utility_name = ?,
                        bill_amount = ?, management_charge = ?, gst_rate = ?,
                        common_old = ?, common_new = ?, common_units = ?,
                        broadcaster_count = ?, dg_capacity = ?, fuel_litres = ?,
                        runtime_hours = ?, remarks = ?, updated_at = ?
                    WHERE id = ?
                ");
                $update->execute([
                    $state, $billDate, $category, $utility,
                    $billAmount, $management, $gstRate,
                    $commonOld, $commonNew, $commonUnits,
                    count($prepared),
                    $_POST['dg_capacity'] ?? null,
                    $_POST['fuel_litres'] !== '' ? $_POST['fuel_litres'] : null,
                    $_POST['runtime_hours'] !== '' ? $_POST['runtime_hours'] : null,
                    trim((string) ($_POST['remarks'] ?? '')),
                    $now,
                    $sheetId,
                ]);
                $conn->prepare('DELETE FROM eb_worksheet_lines WHERE worksheet_id = ?')->execute([$sheetId]);
            } else {
                $insert = $conn->prepare("
                    INSERT INTO eb_worksheets (
                        bill_type, company, state_name, branch_name, bill_month, bill_date,
                        category, utility_name, bill_amount, management_charge, gst_rate,
                        common_old, common_new, common_units, broadcaster_count,
                        dg_capacity, fuel_litres, runtime_hours, remarks, created_at, updated_at
                    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                ");
                $insert->execute([
                    $type, $company, $state, $branch, $month, $billDate,
                    $category, $utility, $billAmount, $management, $gstRate,
                    $commonOld, $commonNew, $commonUnits, count($prepared),
                    $_POST['dg_capacity'] ?? null,
                    ($_POST['fuel_litres'] ?? '') !== '' ? $_POST['fuel_litres'] : null,
                    ($_POST['runtime_hours'] ?? '') !== '' ? $_POST['runtime_hours'] : null,
                    trim((string) ($_POST['remarks'] ?? '')),
                    $now, $now,
                ]);
                $sheetId = (int) $conn->lastInsertId();
            }

            $lineInsert = $conn->prepare("
                INSERT INTO eb_worksheet_lines (
                    worksheet_id, line_no, broadcaster, legal_name, transmitter,
                    old_reading, new_reading, units, common_area_units, total_units,
                    proportionate_amount, management_charges, gst_amount, invoice_amount, entry_mode
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ");
            foreach ($prepared as $i => $row) {
                $calc = $apportioned[$i];
                $lineInsert->execute([
                    $sheetId,
                    $i + 1,
                    $row['item']['broadcaster'],
                    $row['item']['legal_name'],
                    $row['item']['transmitter'],
                    $row['old'],
                    $row['new'],
                    $calc['units'],
                    $calc['common'],
                    $calc['total'],
                    $calc['proportionate'],
                    $calc['management'],
                    $calc['gst'],
                    $calc['invoice'],
                    $row['mode'],
                ]);
            }
            $conn->commit();
        } catch (Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $errors[] = 'The worksheet could not be saved.';
        }

        if ($errors === []) {
            ems_log_event(
                $conn,
                (string) $_SESSION['user'],
                'worksheet saved',
                'bill_amount',
                $type . ' · ' . $company . ' · ' . $branch . ' · ' . $month . ' · ' . $billAmount
            );
            $query = http_build_query([
                'type' => $type,
                'company' => $company,
                'state' => $state,
                'branch' => $branch,
                'month' => $month,
                'saved' => 1,
            ]);
            header('Location: bill_entry.php?' . $query);
            exit;
        }
    }
}

$previousCommon = $branch !== '' ? bill_previous_common($conn, $type, $company, $branch, $month) : null;
$commonOldValue = $previousCommon !== null
    ? $previousCommon
    : (float) ($existing['common_old'] ?? 0);
$commonLocked = $previousCommon !== null;

$form = [
    'bill_date' => $existing['bill_date'] ?? date('Y-m-d'),
    'category' => $existing['category'] ?? 'B',
    'utility_name' => $existing['utility_name'] ?? '',
    'bill_amount' => $existing['bill_amount'] ?? '',
    'management_charge' => $existing['management_charge'] ?? 2250,
    'common_new' => $existing['common_new'] ?? '',
    'dg_capacity' => $existing['dg_capacity'] ?? '',
    'fuel_litres' => $existing['fuel_litres'] ?? '',
    'runtime_hours' => $existing['runtime_hours'] ?? '',
    'remarks' => $existing['remarks'] ?? '',
];

$lineViews = [];
foreach ($catalog as $i => $item) {
    $key = $item['broadcaster'] . '|' . $item['transmitter'];
    $previous = $branch !== ''
        ? bill_previous_reading($conn, $type, $company, $branch, $month, $item['broadcaster'], $item['transmitter'])
        : null;
    $stored = $existingByKey[$key] ?? null;
    $old = $previous !== null ? $previous : (float) ($stored['old_reading'] ?? 0);
    $postedNew = $_POST['lines'][$i]['new'] ?? null;
    $lineViews[] = [
        'item' => $item,
        'fault' => $branch !== '' ? ems_fault_status($conn, $type, $company, $branch, $item['broadcaster'], $item['transmitter']) : null,
        'direct' => $_POST['lines'][$i]['direct'] ?? ($stored['units'] ?? ''),
        'old' => $old,
        'locked' => $previous !== null,
        'new' => $postedNew !== null ? $postedNew : ($stored['new_reading'] ?? ''),
        'calc' => $stored,
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (['bill_date', 'category', 'utility_name', 'bill_amount', 'management_charge', 'common_new', 'dg_capacity', 'fuel_litres', 'runtime_hours', 'remarks'] as $field) {
        if (isset($_POST[$field])) {
            $form[$field] = $_POST[$field];
        }
    }
}

$sheetTitle = $type === 'DG' ? 'DG WORK SHEET' : 'WORK SHEET';
$utilityLabel = $type === 'DG' ? 'DG bill received from' : 'Electric Bill received from';
$monthLabel = $month !== '' ? date('F, Y', strtotime($month . '-01')) : '';

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="content">

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2 class="mb-1"><?= $type === 'DG' ? 'DG bill' : 'EB bill' ?> — <?= htmlspecialchars($company) ?></h2>
        <div class="text-muted">
            <?= $company === 'KAL' ? 'South India locations' : 'North, east and north-east locations' ?>.
            Opening readings come from the previous month’s closing reading.
        </div>
    </div>
    <a class="btn btn-outline-secondary" href="bill_analysis.php?company=<?= urlencode($company) ?>&type=<?= urlencode($type) ?>">
        Management comparison
    </a>
</div>

<?php if ($saved) { ?>
<div class="alert alert-success">Worksheet saved. Next month will open from these new readings.</div>
<?php } ?>
<?php foreach ($errors as $error) { ?>
<div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php } ?>

<form method="get" class="row g-2 mb-3">
    <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">
    <input type="hidden" name="company" value="<?= htmlspecialchars($company) ?>">
    <div class="col-md-3">
        <label class="form-label">State</label>
        <select name="state" class="form-select" onchange="this.form.submit()">
            <?php
            $seenState = [];
            foreach ($sites as $site) {
                if (isset($seenState[$site['state_name']])) {
                    continue;
                }
                $seenState[$site['state_name']] = true;
            ?>
            <option value="<?= htmlspecialchars($site['state_name']) ?>" <?= $site['state_name'] === $state ? 'selected' : '' ?>>
                <?= htmlspecialchars($site['state_name']) ?>
            </option>
            <?php } ?>
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label">Branch</label>
        <select name="branch" class="form-select" onchange="this.form.submit()">
            <?php foreach ($branches as $name) { ?>
            <option value="<?= htmlspecialchars($name) ?>" <?= $name === $branch ? 'selected' : '' ?>>
                <?= htmlspecialchars($name) ?>
            </option>
            <?php } ?>
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label">Bill month</label>
        <input type="month" name="month" value="<?= htmlspecialchars($month) ?>" class="form-control" onchange="this.form.submit()">
    </div>
</form>

<?php if ($branch === '') { ?>
<div class="alert alert-warning">No branches are loaded for <?= htmlspecialchars($company) ?>.</div>
<?php } else { ?>

<form method="post" id="worksheet" class="becil-sheet">
    <input type="hidden" name="bill_type" value="<?= htmlspecialchars($type) ?>">
    <input type="hidden" name="company" value="<?= htmlspecialchars($company) ?>">
    <input type="hidden" name="state_name" value="<?= htmlspecialchars($state) ?>">
    <input type="hidden" name="branch_name" value="<?= htmlspecialchars($branch) ?>">
    <input type="hidden" name="bill_month" value="<?= htmlspecialchars($month) ?>">

    <div class="sheet-head">
        <div>
            <div class="sheet-box"><?= htmlspecialchars(strtoupper($branch)) ?></div>
            <div class="sheet-box mt-1" style="width:72px;">
                <select name="category" class="form-select form-select-sm border-0 text-center">
                    <?php foreach (['A', 'B', 'C'] as $cat) { ?>
                    <option value="<?= $cat ?>" <?= $form['category'] === $cat ? 'selected' : '' ?>><?= $cat ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="sheet-title"><?= $sheetTitle ?></div>
        <div class="text-end">
            <div>Dated:
                <input type="date" name="bill_date" value="<?= htmlspecialchars((string) $form['bill_date']) ?>" class="form-control form-control-sm d-inline-block" style="width:160px;">
            </div>
            <div class="sheet-box mt-2"><?= htmlspecialchars($monthLabel) ?></div>
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-md-4">
            <strong>No. of Broadcasters</strong>
            <span class="ms-3" id="broadcasterCount"><?= count($lineViews) ?></span>
        </div>
        <div class="col-md-5">
            <label><?= htmlspecialchars($utilityLabel) ?></label>
            <input type="text" name="utility_name" value="<?= htmlspecialchars((string) $form['utility_name']) ?>" class="form-control" placeholder="Distribution company and office">
        </div>
        <div class="col-md-3">
            <div>Period of bill <strong><?= htmlspecialchars($monthLabel) ?></strong></div>
            <div class="sheet-box mt-2">
                <div class="small text-muted">Bill Amount</div>
                <input type="number" step="0.01" min="0" name="bill_amount" id="billAmount" value="<?= htmlspecialchars((string) $form['bill_amount']) ?>" class="form-control text-end" required>
            </div>
        </div>
    </div>

    <?php if ($type === 'DG') { ?>
    <div class="row mt-3">
        <div class="col-md-4">
            <label>DG capacity</label>
            <input type="text" name="dg_capacity" value="<?= htmlspecialchars((string) $form['dg_capacity']) ?>" class="form-control">
        </div>
        <div class="col-md-4">
            <label>Fuel consumption (litres)</label>
            <input type="number" step="0.01" name="fuel_litres" value="<?= htmlspecialchars((string) $form['fuel_litres']) ?>" class="form-control">
        </div>
        <div class="col-md-4">
            <label>Runtime (hours)</label>
            <input type="number" step="0.01" name="runtime_hours" value="<?= htmlspecialchars((string) $form['runtime_hours']) ?>" class="form-control">
        </div>
    </div>
    <?php } else { ?>
        <input type="hidden" name="dg_capacity" value="">
        <input type="hidden" name="fuel_litres" value="">
        <input type="hidden" name="runtime_hours" value="">
    <?php } ?>

    <h6 class="mt-4">Common area meter</h6>
    <table class="table table-bordered sheet-table" style="max-width:520px;">
        <thead>
            <tr><th>Date</th><th>Old</th><th>New</th><th>Units</th></tr>
        </thead>
        <tbody>
            <tr>
                <td>Reading</td>
                <td>
                    <input type="number" step="0.001" name="common_old" id="commonOld" value="<?= htmlspecialchars((string) $commonOldValue) ?>" class="form-control" <?= $commonLocked ? 'readonly' : '' ?>>
                    <?php if ($commonLocked) { ?><div class="small text-muted">From last month</div><?php } ?>
                </td>
                <td><input type="number" step="0.001" name="common_new" id="commonNew" value="<?= htmlspecialchars((string) $form['common_new']) ?>" class="form-control" required></td>
                <td class="text-end fw-semibold" id="commonUnits">0</td>
            </tr>
        </tbody>
    </table>

    <div class="d-flex justify-content-between align-items-end mt-3">
        <h6 class="mb-0">Broadcasters</h6>
        <div style="width:180px;">
            <label class="small">Management charge each</label>
            <input type="number" step="0.01" min="0" name="management_charge" id="managementCharge" value="<?= htmlspecialchars((string) $form['management_charge']) ?>" class="form-control">
        </div>
    </div>

    <?php if ($lineViews === []) { ?>
    <div class="alert alert-warning mt-3">No broadcasters are mapped to this branch yet.</div>
    <?php } ?>

    <?php foreach ($lineViews as $i => $line) {
        $item = $line['item'];
        $calc = $line['calc'];
    ?>
    <div class="broadcaster-block" data-line="<?= $i ?>">
        <div class="d-flex justify-content-between">
            <strong>Broadcaster No. <?= $i + 1 ?> <?= htmlspecialchars($item['legal_name']) ?></strong>
            <span><?= htmlspecialchars($item['transmitter']) ?></span>
        </div>
        <table class="table table-bordered sheet-table mb-1">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Old</th>
                    <th>New</th>
                    <th>Units</th>
                    <th>Common area Units</th>
                    <th>Total Units</th>
                    <th>Proportionate amount</th>
                    <th>Management charges</th>
                    <th>GST @ 18%</th>
                    <th>Invoice</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Reading</td>
                    <td>
                        <input type="number" step="0.001" name="lines[<?= $i ?>][old]" class="form-control old-reading" value="<?= htmlspecialchars((string) $line['old']) ?>" <?= $line['locked'] ? 'readonly' : '' ?>>
                    </td>
                    <td>
                        <input type="number" step="0.001" name="lines[<?= $i ?>][new]" class="form-control new-reading" value="<?= htmlspecialchars((string) $line['new']) ?>" <?= ($line['fault'] ?? '') === 'approved' ? '' : 'required' ?>>
                    </td>
                    <td class="text-end units">
                        <?php if (($line['fault'] ?? '') === 'approved') { ?>
                        <input type="number" step="0.01" min="0" name="lines[<?= $i ?>][direct]" class="form-control direct-units" value="<?= htmlspecialchars((string) ($line['direct'] ?? '')) ?>" title="Approved direct units">
                        <?php } else { ?>0<?php } ?>
                    </td>
                    <td class="text-end common">0</td>
                    <td class="text-end total">0</td>
                    <td class="text-end proportionate">0</td>
                    <td class="text-end management">0</td>
                    <td class="text-end gst">0</td>
                    <td class="text-end invoice">0</td>
                </tr>
            </tbody>
        </table>
        <?php if (($line['fault'] ?? '') === 'approved') { ?>
        <div class="small text-success mb-2">Admin approved direct units. Enter them in the Units column.</div>
        <?php } elseif (($line['fault'] ?? '') === 'pending') { ?>
        <div class="small text-muted mb-2">Waiting for admin approval to enter units directly.</div>
        <?php } else { ?>
        <div class="row g-2 mb-2">
            <div class="col-md-8">
                <input class="form-control form-control-sm" name="fault_reason[<?= $i ?>]" placeholder="Meter faulty — reason for typing units">
            </div>
            <div class="col-md-4">
                <button class="btn btn-sm btn-outline-secondary" type="submit" name="request_fault" value="<?= $i ?>">Request direct units</button>
            </div>
        </div>
        <?php } ?>
    </div>
    <?php } ?>

    <table class="table table-bordered sheet-table mt-2" style="max-width:640px; margin-left:auto;">
        <tbody>
            <tr class="fw-semibold">
                <td id="sumUnits">0</td>
                <td id="sumCommon">0</td>
                <td id="sumTotal">0</td>
                <td id="sumProportion">0</td>
            </tr>
        </tbody>
    </table>

    <div class="mb-3">
        <label>Remarks</label>
        <textarea name="remarks" class="form-control" rows="2"><?= htmlspecialchars((string) $form['remarks']) ?></textarea>
    </div>

    <button type="submit" name="save" class="btn btn-primary">Save worksheet</button>
    <button type="button" class="btn btn-outline-dark" onclick="window.print()">Print</button>
</form>
<?php if ($existing) { ?>
<div class="becil-sheet mt-3">
    <h3 class="h6">Utility bill PDF</h3>
    <?php if (!empty($existing['bill_pdf'])) { ?>
    <p><a href="bill_pdf.php?id=<?= (int) $existing['id'] ?>">Open the uploaded bill</a></p>
    <?php } else { ?>
    <p class="text-muted mb-2">No PDF is stored on this month yet.</p>
    <?php } ?>
    <?php if (is_admin()) { ?>
    <form method="post" enctype="multipart/form-data" class="row g-2 align-items-end">
        <input type="hidden" name="bill_type" value="<?= htmlspecialchars($type) ?>">
        <input type="hidden" name="company" value="<?= htmlspecialchars($company) ?>">
        <input type="hidden" name="state_name" value="<?= htmlspecialchars($state) ?>">
        <input type="hidden" name="branch_name" value="<?= htmlspecialchars($branch) ?>">
        <input type="hidden" name="bill_month" value="<?= htmlspecialchars($month) ?>">
        <div class="col-md-6">
            <label class="form-label">Upload or replace the bill PDF</label>
            <input type="file" name="bill_pdf" accept="application/pdf" class="form-control" required>
        </div>
        <div class="col-md-3">
            <button class="btn btn-outline-primary" name="upload_pdf" value="1">Save PDF</button>
        </div>
    </form>
    <?php } ?>
</div>
<?php } ?>
<?php if (isset($_GET['pdf'])) { ?><div class="alert alert-success mt-3">Bill PDF saved on this worksheet.</div><?php } ?>
<?php } ?>

</div>

<script>
function inr(value) {
    const negative = value < 0;
    const fixed = Math.abs(value).toFixed(2);
    const [whole, dec] = fixed.split('.');
    let int = whole;
    if (int.length > 3) {
        const last3 = int.slice(-3);
        let rest = int.slice(0, -3);
        rest = rest.replace(/\B(?=(\d{2})+(?!\d))/g, ',');
        int = rest + ',' + last3;
    }
    return (negative ? '-' : '') + int + '.' + dec;
}

function splitUnits(total, count) {
    if (count <= 0) return [];
    const cents = Math.round(total * 100);
    const each = Math.floor(cents / count);
    const remainder = cents - each * count;
    const shares = [];
    for (let i = 0; i < count; i++) {
        shares.push((each + (i < remainder ? 1 : 0)) / 100);
    }
    return shares;
}

function recalc() {
    const blocks = [...document.querySelectorAll('.broadcaster-block')];
    const count = blocks.length;
    const commonOld = parseFloat(document.getElementById('commonOld').value) || 0;
    const commonNew = parseFloat(document.getElementById('commonNew').value) || 0;
    const commonUnits = Math.round((commonNew - commonOld) * 100) / 100;
    document.getElementById('commonUnits').textContent = commonUnits.toFixed(2);
    const shares = splitUnits(Math.max(commonUnits, 0), count);
    const bill = parseFloat(document.getElementById('billAmount').value) || 0;
    const management = parseFloat(document.getElementById('managementCharge').value) || 0;
    const gstEach = Math.round(management * 18) / 100;
    const own = blocks.map(block => {
        const direct = block.querySelector('.direct-units');
        if (direct) {
            return Math.round((parseFloat(direct.value) || 0) * 100) / 100;
        }
        const oldR = parseFloat(block.querySelector('.old-reading').value) || 0;
        const newR = parseFloat(block.querySelector('.new-reading').value) || 0;
        return Math.round((newR - oldR) * 100) / 100;
    });
    const totals = own.map((units, i) => Math.round((units + (shares[i] || 0)) * 100) / 100);
    const sumTotal = totals.reduce((a, b) => a + b, 0);
    let given = 0;
    const billCents = Math.round(bill * 100);
    const sumCents = Math.round(sumTotal * 100);
    let sumUnits = 0, sumCommon = 0, sumProp = 0;
    blocks.forEach((block, i) => {
        let proportionCents;
        if (i === count - 1) {
            proportionCents = billCents - given;
        } else if (sumCents === 0) {
            proportionCents = 0;
        } else {
            proportionCents = Math.round(billCents * Math.round(totals[i] * 100) / sumCents);
            given += proportionCents;
        }
        if (!block.querySelector('.direct-units')) {
            block.querySelector('.units').textContent = own[i].toFixed(2);
        }
        block.querySelector('.common').textContent = (shares[i] || 0).toFixed(2);
        block.querySelector('.total').textContent = totals[i].toFixed(2);
        block.querySelector('.proportionate').textContent = inr(proportionCents / 100);
        block.querySelector('.management').textContent = inr(management);
        block.querySelector('.gst').textContent = inr(gstEach);
        block.querySelector('.invoice').textContent = inr(management + gstEach);
        sumUnits += own[i];
        sumCommon += shares[i] || 0;
        sumProp += proportionCents / 100;
    });
    document.getElementById('sumUnits').textContent = sumUnits.toFixed(2);
    document.getElementById('sumCommon').textContent = sumCommon.toFixed(2);
    document.getElementById('sumTotal').textContent = (sumUnits + sumCommon).toFixed(2);
    document.getElementById('sumProportion').textContent = inr(sumProp);
}

document.getElementById('worksheet')?.addEventListener('input', recalc);
recalc();
</script>

<style>
.becil-sheet { background:#fff; border:1px solid #d7dde5; padding:18px; }
.sheet-head { display:flex; justify-content:space-between; align-items:flex-start; gap:16px; }
.sheet-title { border:2px solid #222; padding:8px 28px; font-weight:700; letter-spacing:1px; }
.sheet-box { border:1px solid #222; padding:6px 10px; min-width:140px; text-align:center; font-weight:600; }
.sheet-table th, .sheet-table td { font-size:13px; vertical-align:middle; }
.sheet-table input { min-width:90px; }
.broadcaster-block { margin-top:14px; }
@media print {
    .sidebar, .btn, form[method="get"] { display:none !important; }
    .content { margin:0; }
    .becil-sheet { border:none; }
}
</style>

<?php include 'includes/footer.php'; ?>
