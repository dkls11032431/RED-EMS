<?php

session_start();

if(!isset($_SESSION['user']))
{
    header("Location: login.php");
    exit;
}

require 'config/db.php';

$data = [];

if(isset($_POST['search']))
{
    $from = $_POST['from_date'];
    $to   = $_POST['to_date'];

    $stmt = $conn->prepare("
    SELECT
        parameter_name,
        value,
        reading_time
    FROM meter_readings
    WHERE DATE(reading_time)
    BETWEEN ? AND ?
    ORDER BY reading_time DESC
    ");

    $stmt->execute([$from,$to]);

    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
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