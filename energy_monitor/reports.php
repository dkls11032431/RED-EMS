<?php

session_start();

if(!isset($_SESSION['user']))
{
    header("Location: login.php");
    exit;
}

require 'config/db.php';

$data = [];

$reportError = '';
if(isset($_POST['search']))
{
    $from = (string) ($_POST['from_date'] ?? '');
    $to   = (string) ($_POST['to_date'] ?? '');

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        $reportError = 'Choose a valid from and to date.';
    } else {
        $stmt = $conn->prepare("
        SELECT
            parameter_name,
            value,
            reading_time
        FROM meter_readings
        WHERE DATE(reading_time)
        BETWEEN ? AND ?
        ORDER BY reading_time DESC
        LIMIT 500
        ");

        $stmt->execute([$from,$to]);

        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

include 'includes/header.php';
include 'includes/sidebar.php';

?>

<div class="content">

<h2>📄 Energy Reports</h2>

<form method="post" class="row mb-4">

    <div class="col-md-3">

        <input
            type="date"
            name="from_date"
            class="form-control"
            required>

    </div>

    <div class="col-md-3">

        <input
            type="date"
            name="to_date"
            class="form-control"
            required>

    </div>

    <div class="col-md-2">

        <button
            name="search"
            class="btn btn-primary">

            Search

        </button>

    </div>

</form>

<?php if ($reportError !== '') { ?>
<div class="alert alert-danger"><?= htmlspecialchars($reportError) ?></div>
<?php } elseif (isset($_POST['search']) && $data === []) { ?>
<div class="empty-state">No readings were stored between those dates.</div>
<?php } ?>

<table class="table table-bordered bg-white">

    <thead>

        <tr>

            <th>Parameter</th>

            <th>Value</th>

            <th>Reading Time</th>

        </tr>

    </thead>

    <tbody>

    <?php foreach($data as $row){ ?>

        <tr>

            <td><?= htmlspecialchars((string) $row['parameter_name']); ?></td>

            <td><?= htmlspecialchars((string) $row['value']); ?></td>

            <td><?= htmlspecialchars((string) $row['reading_time']); ?></td>

        </tr>

    <?php } ?>

    </tbody>

</table>

</div>

<?php include 'includes/footer.php'; ?>