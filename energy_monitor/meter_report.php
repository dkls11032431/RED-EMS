<?php

session_start();

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

require 'config/db.php';

$meter_id = $_GET['meter_id'] ?? '';
$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');

$meters = $conn->query("
    SELECT meter_id, meter_name
    FROM meter_master
");

$data = [];

if ($meter_id != '') {

    $stmt = $conn->prepare("
        SELECT
            parameter_name,
            value,
            reading_time
        FROM meter_readings
        WHERE meter_id = ?
        AND DATE(reading_time) BETWEEN ? AND ?
        ORDER BY reading_time DESC
        LIMIT 500
    ");

    $stmt->execute([$meter_id, $from, $to]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="content">

    <h2>🔌 Meter Report</h2>

    <form method="get">

        <select name="meter_id" class="form-control">

            <option value="">
                Select Meter
            </option>

            <?php foreach($meters as $m){ ?>

            <option
                value="<?= $m['meter_id']; ?>"
                <?= $meter_id == $m['meter_id'] ? 'selected' : ''; ?>>

                <?= $m['meter_name']; ?>

            </option>

            <?php } ?>

        </select>
        <div class="row g-2 mt-2">
            <div class="col-md-3"><label class="form-label">From</label><input type="date" name="from" value="<?= htmlspecialchars($from) ?>" class="form-control"></div>
            <div class="col-md-3"><label class="form-label">To</label><input type="date" name="to" value="<?= htmlspecialchars($to) ?>" class="form-control"></div>
        </div>
        <br>

        <button class="btn btn-primary">
            View Report
        </button>

    </form>

    <a href="export_pdf.php?report=meter&meter_id=<?= $meter_id; ?>"
   class="btn btn-danger">

    📄 Export PDF

</a>

    <br>

    <table class="table table-bordered">

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

                <td><?= $row['parameter_name']; ?></td>

                <td><?= $row['value']; ?></td>

                <td><?= $row['reading_time']; ?></td>

            </tr>

        <?php } ?>

        </tbody>

    </table>

</div>

<?php include 'includes/footer.php'; ?>