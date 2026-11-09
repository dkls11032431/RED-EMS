<?php

session_start();

if (!isset($_SESSION['user']))
{
    header("Location: login.php");
    exit;
}

require 'config/db.php';

$alarms = $conn->query("
    SELECT *
    FROM alarms
    WHERE status = 'ACTIVE'
    ORDER BY alarm_time DESC
");

$alarmCount = $alarms->rowCount();

include 'includes/header.php';
include 'includes/sidebar.php';

?>

<div class="content-wrapper">

    <div class="container-fluid mt-4">

        <h2 class="mb-4">
            🚨 Active Alarms
            <span class="badge bg-danger">
                <?= $alarmCount ?>
            </span>
        </h2>

        <div class="card shadow">

            <div class="card-body">

                <div class="table-responsive">

                    <table class="table table-bordered table-striped table-hover">

                        <thead class="table-dark">

                            <tr>
                                <th>ID</th>
                                <th>Site</th>
                                <th>Branch</th>
                                <th>Alarm</th>
                                <th>Severity</th>
                                <th>Actual Value</th>
                                <th>Alarm Time</th>
                                <th>Duration</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>

                        </thead>

                        <tbody>

                        <?php if ($alarmCount > 0): ?>

                            <?php foreach ($alarms as $row): ?>

                                <?php
                                $duration = max(
                                    0,
                                    round(
                                        (time() - strtotime($row['alarm_time'])) / 60
                                    )
                                );
                                ?>

                                <tr class="<?=
                                    ($row['severity'] === 'CRITICAL')
                                        ? 'table-danger'
                                        : '';
                                ?>">

                                    <td>
                                        <?= htmlspecialchars($row['id']); ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($row['site_name']); ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($row['branch_name']); ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($row['alarm_message']); ?>
                                    </td>

                                    <td>

                                        <?php if ($row['severity'] === 'EMERGENCY'): ?>

                                            <span class="badge bg-dark">
                                                EMERGENCY
                                            </span>

                                        <?php elseif ($row['severity'] === 'CRITICAL'): ?>

                                            <span class="badge bg-danger">
                                                CRITICAL
                                            </span>

                                        <?php elseif ($row['severity'] === 'WARNING'): ?>

                                            <span class="badge bg-warning text-dark">
                                                WARNING
                                            </span>

                                        <?php else: ?>

                                            <span class="badge bg-info">
                                                INFO
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                    <td>
                                        <?= htmlspecialchars($row['actual_value']); ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($row['alarm_time']); ?>
                                    </td>

                                    <td>
                                        <?= $duration; ?> Min
                                    </td>

                                    <td>

                                        <?php if ($row['status'] === 'ACTIVE'): ?>

                                            <span class="badge bg-danger">
                                                ACTIVE
                                            </span>

                                        <?php elseif ($row['status'] === 'ACK'): ?>

                                            <span class="badge bg-warning text-dark">
                                                ACK
                                            </span>

                                        <?php else: ?>

                                            <span class="badge bg-success">
                                                <?= htmlspecialchars($row['status']); ?>
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                    <td>

                                        <a
                                            href="ack_alarm.php?id=<?= urlencode($row['id']); ?>"
                                            class="btn btn-warning btn-sm"
                                        >
                                            ACK
                                        </a>

                                        <a
                                            href="clear_alarm.php?id=<?= urlencode($row['id']); ?>"
                                            class="btn btn-success btn-sm"
                                            onclick="return confirm('Clear this alarm?');"
                                        >
                                            CLEAR
                                        </a>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="10" class="text-center text-muted py-4">
                                    No active alarms.
                                </td>
                            </tr>

                        <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </div>

        </div>

    </div>

</div>

<?php include 'includes/footer.php'; ?>
