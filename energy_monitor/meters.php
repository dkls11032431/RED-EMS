<?php

session_start();

if(!isset($_SESSION['user']))
{
    header("Location: login.php");
    exit;
}

require 'config/db.php';
require 'includes/auth_helpers.php';
if (!is_admin()) {
    header('Location: dashboard.php');
    exit;
}

$meters = $conn->query("
SELECT *
FROM meter_master
ORDER BY meter_id
");

// One grouped query for every meter's last reading, instead of
// querying meter_readings once per row inside the loop below.
$lastReadings = $conn->query("
    SELECT meter_id, MAX(reading_time) AS last_reading
    FROM meter_readings
    GROUP BY meter_id
")->fetchAll(PDO::FETCH_KEY_PAIR);

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="content">

    <h2>⚙️ Meter Management</h2>

    <table class="table table-bordered">

        <thead>

            <tr>
                <th>Meter ID</th>
                <th>Meter Name</th>
                <th>Last Reading</th>
                <th>Status</th>
            </tr>

        </thead>

        <tbody>

            <?php foreach($meters as $meter){

                $lastReading = $lastReadings[$meter['meter_id']] ?? null;

                $minutes = $lastReading
                    ? (time() - strtotime($lastReading)) / 60
                    : null;

                $status = ($minutes !== null && $minutes <= 5)
                    ? 'Online'
                    : 'Offline';

            ?>

            <tr>

                <td><?= $meter['meter_id']; ?></td>

                <td><?= $meter['meter_name']; ?></td>

                <td><?= $lastReading ?? '—'; ?></td>

                <td>
                    <?php if($status == 'Online'){ ?>
                        <span class="badge bg-success">Online</span>
                    <?php }else{ ?>
                        <span class="badge bg-danger">Offline</span>
                    <?php } ?>
                </td>

            </tr>

            <?php } ?>

        </tbody>

    </table>

</div>

<?php include 'includes/footer.php'; ?>