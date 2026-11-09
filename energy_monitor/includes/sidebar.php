<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (!function_exists('sees_all_sites')) {
    require_once __DIR__ . '/auth_helpers.php';
}
$navAdmin = is_admin();
$navAll = sees_all_sites();
$navCompanies = user_companies();
?>
<div class="sidebar">
 <h3 class="logo">⚡ EMS</h3>
 <a href="dashboard.php">📊 Dashboard</a>
 <?php if ($navAdmin) { ?>
 <a href="parameters.php">⚙ Parameters</a>
 <a href="meters.php">🔌 Meters</a>
 <?php } ?>
 <a href="alarms.php">🚨 Active Alarms</a>
 <a href="alarm_history.php">📜 Alarm History</a>
 <?php if ($navAll) { ?>
 <a href="alarm_limits.php">📏 Limits</a>
 <?php } ?>
 <a href="site_map.php">🗺 Supply map</a>

 <a class="nav" data-bs-toggle="collapse" href="#reportsMenu" role="button" aria-expanded="false" aria-controls="reportsMenu">
    📄 Reports
 </a>

 <div class="collapse" id="reportsMenu">

    <div style="padding-left:20px;">

        <a href="consumption_report.php">
            └ Overview
        </a>

        <a href="consumption_report.php?fuel=EB&period=daily">
            └ EB Reports
        </a>

        <a href="consumption_report.php?fuel=DG&period=daily">
            └ DG Reports
        </a>

        <a href="bill_analysis.php">
            └ Bill comparison
        </a>

        <?php if ($navAll) { ?>
        <a href="master_report.php">
            └ Management summary
        </a>
        <?php } ?>

    </div>

 </div>

 <?php if ($navAdmin) { ?>
 <a href="users.php">👤 Users</a>
 <?php } ?>
 <?php if ($navAll) { ?>
 <a href="mail_settings.php">✉ Alarm mail</a>
 <?php } ?>
 <a href="bill_approvals.php">✔ Meter approvals</a>
 <a href="event_log.php">📝 Event Log</a>

 <?php if ($navAll) { ?>
 <a href="bill_locations.php">📍 Locations</a>
 <?php } ?>

 <a class="nav" data-bs-toggle="collapse" href="#ebBills" role="button" aria-expanded="false" aria-controls="ebBills">
    💰 EB Bills
 </a>

 <div class="collapse" id="ebBills">

    <div style="padding-left:20px;">

        <strong>BECIL</strong>

        <?php if (in_array('KAL', $navCompanies, true)) { ?>
        <a href="bill_entry.php?type=BECIL&company=KAL">
            └ KAL (South)
        </a>
        <?php } ?>

        <?php if (in_array('SAFL', $navCompanies, true)) { ?>
        <a href="bill_entry.php?type=BECIL&company=SAFL">
            └ SAFL (North)
        </a>
        <?php } ?>

        <strong>NON BECIL</strong>

        <?php if (in_array('KAL', $navCompanies, true)) { ?>
        <a href="bill_entry.php?type=NON_BECIL&company=KAL">
            └ KAL (South)
        </a>
        <?php } ?>

        <?php if (in_array('SAFL', $navCompanies, true)) { ?>
        <a href="bill_entry.php?type=NON_BECIL&company=SAFL">
            └ SAFL (North)
        </a>
        <?php } ?>

    </div>

 </div>

 <a class="nav" data-bs-toggle="collapse" href="#dgBills" role="button" aria-expanded="false" aria-controls="dgBills">
    ⚡ DG Bills
 </a>

 <div class="collapse" id="dgBills">

    <div style="padding-left:20px;">

        <?php if (in_array('KAL', $navCompanies, true)) { ?>
        <a href="bill_entry.php?type=DG&company=KAL">
            └ KAL (South)
        </a>
        <?php } ?>

        <?php if (in_array('SAFL', $navCompanies, true)) { ?>
        <a href="bill_entry.php?type=DG&company=SAFL">
            └ SAFL (North)
        </a>
        <?php } ?>

    </div>

 </div>

</div>
