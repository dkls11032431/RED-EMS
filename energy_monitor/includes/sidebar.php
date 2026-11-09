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
$emsScript = basename($_SERVER['SCRIPT_NAME'] ?? '');
$emsQuery = $_GET;

function ems_here(string $file, array $query = []): string
{
    global $emsScript, $emsQuery;
    if ($emsScript !== $file) {
        return '';
    }
    foreach ($query as $key => $value) {
        if ((string) ($emsQuery[$key] ?? '') !== (string) $value) {
            return '';
        }
    }
    return ' active';
}

$crumbMap = [
    'dashboard.php' => ['Operations', 'Dashboard'],
    'electrical.php' => ['Energy', 'Electrical'],
    'equipment.php' => ['Equipment', 'Monitoring'],
    'devices.php' => ['IoT', 'Devices'],
    'site_map.php' => ['Operations', 'Supply map'],
    'consumption_report.php' => ['Analytics', 'Consumption'],
    'dg_operations.php' => ['Analytics', 'DG operations'],
    'dg_analytics.php' => ['Analytics', 'DG analytics'],
    'dg_report.php' => ['Analytics', 'DG worksheets'],
    'eb_bill_report.php' => ['Analytics', 'EB worksheets'],
    'bill_analysis.php' => ['Analytics', 'Bill comparison'],
    'master_report.php' => ['Analytics', 'Management summary'],
    'reports.php' => ['Analytics', 'Register search'],
    'daily_report.php' => ['Analytics', 'Daily report'],
    'monthly_report.php' => ['Analytics', 'Monthly report'],
    'meter_report.php' => ['Analytics', 'Meter report'],
    'alarms.php' => ['Alerts', 'Active alarms'],
    'alarm_history.php' => ['Alerts', 'History'],
    'alarm_limits.php' => ['Alerts', 'Limits'],
    'incidents.php' => ['Alerts', 'Incidents'],
    'site_availability.php' => ['Alerts', 'Availability'],
    'parameters.php' => ['Administration', 'Parameters'],
    'meters.php' => ['Administration', 'Meters'],
    'users.php' => ['Administration', 'Users'],
    'mail_settings.php' => ['Administration', 'Alarm mail'],
    'bill_approvals.php' => ['Administration', 'Meter approvals'],
    'event_log.php' => ['Administration', 'Event log'],
    'bill_locations.php' => ['Administration', 'Locations'],
    'bill_entry.php' => ['Billing', 'Worksheet'],
];
$crumb = $crumbMap[$emsScript] ?? ['RED SURYAN', 'EMS'];
$userLabel = htmlspecialchars((string) ($_SESSION['user'] ?? ''));
?>
<div class="sidebar">
    <div class="brand">
        <div class="brand-mark">RS</div>
        <div>
            <div class="brand-name">RED SURYAN</div>
            <div class="brand-sub">Energy management</div>
        </div>
    </div>

    <div class="nav-label">Operations</div>
    <a class="<?= ems_here('dashboard.php') ?>" href="dashboard.php">Dashboard</a>
    <a class="<?= ems_here('site_map.php') ?>" href="site_map.php">Supply map</a>
    <a class="<?= ems_here('electrical.php') ?>" href="electrical.php">Electrical energy</a>
    <a class="<?= ems_here('equipment.php') ?>" href="equipment.php">Equipment</a>
    <a class="<?= ems_here('devices.php') ?>" href="devices.php">IoT devices</a>

    <div class="nav-label">Analytics</div>
    <a class="<?= ($emsScript === 'consumption_report.php' && !in_array(strtoupper((string) ($emsQuery['fuel'] ?? 'BOTH')), ['EB', 'DG'], true)) ? 'active' : '' ?>" href="consumption_report.php">Overview</a>
    <a class="sub<?= ems_here('consumption_report.php', ['fuel' => 'EB']) ?>" href="consumption_report.php?fuel=EB&period=daily">EB report</a>
    <a class="sub<?= ems_here('consumption_report.php', ['fuel' => 'DG']) ?>" href="consumption_report.php?fuel=DG&period=daily">DG registers</a>
    <a class="<?= ems_here('dg_operations.php') ?>" href="dg_operations.php">DG operations log</a>
    <a class="<?= ems_here('dg_analytics.php') ?>" href="dg_analytics.php">DG location analytics</a>
    <a class="<?= ems_here('bill_analysis.php') ?>" href="bill_analysis.php">Bill comparison</a>
    <?php if ($navAll) { ?>
    <a class="<?= ems_here('master_report.php') ?>" href="master_report.php">Management summary</a>
    <?php } ?>
    <a class="sub<?= ems_here('daily_report.php') ?>" href="daily_report.php">Daily report</a>
    <a class="sub<?= ems_here('monthly_report.php') ?>" href="monthly_report.php">Monthly report</a>
    <a class="sub<?= ems_here('reports.php') ?>" href="reports.php">Register search</a>

    <div class="nav-label">Alerts</div>
    <a class="<?= ems_here('alarms.php') ?>" href="alarms.php">Active alarms</a>
    <a class="<?= ems_here('alarm_history.php') ?>" href="alarm_history.php">Alarm history</a>
    <?php if ($navAll) { ?>
    <a class="<?= ems_here('alarm_limits.php') ?>" href="alarm_limits.php">Limits</a>
    <?php } ?>
    <a class="<?= ems_here('incidents.php') ?>" href="incidents.php">Incidents</a>
    <a class="<?= ems_here('site_availability.php') ?>" href="site_availability.php">Availability</a>

    <div class="nav-label">Billing</div>
    <a class="nav" data-bs-toggle="collapse" href="#ebBills" role="button" aria-expanded="false" aria-controls="ebBills">EB bills</a>
    <div class="collapse" id="ebBills">
        <strong>BECIL</strong>
        <?php if (in_array('KAL', $navCompanies, true)) { ?>
        <a class="sub" href="bill_entry.php?type=BECIL&company=KAL">KAL (South)</a>
        <?php } ?>
        <?php if (in_array('SAFL', $navCompanies, true)) { ?>
        <a class="sub" href="bill_entry.php?type=BECIL&company=SAFL">SAFL (North)</a>
        <?php } ?>
        <strong>NON BECIL</strong>
        <?php if (in_array('KAL', $navCompanies, true)) { ?>
        <a class="sub" href="bill_entry.php?type=NON_BECIL&company=KAL">KAL (South)</a>
        <?php } ?>
        <?php if (in_array('SAFL', $navCompanies, true)) { ?>
        <a class="sub" href="bill_entry.php?type=NON_BECIL&company=SAFL">SAFL (North)</a>
        <?php } ?>
    </div>
    <a class="nav" data-bs-toggle="collapse" href="#dgBills" role="button" aria-expanded="false" aria-controls="dgBills">DG bills</a>
    <div class="collapse" id="dgBills">
        <?php if (in_array('KAL', $navCompanies, true)) { ?>
        <a class="sub" href="bill_entry.php?type=DG&company=KAL">KAL (South)</a>
        <?php } ?>
        <?php if (in_array('SAFL', $navCompanies, true)) { ?>
        <a class="sub" href="bill_entry.php?type=DG&company=SAFL">SAFL (North)</a>
        <?php } ?>
    </div>

    <div class="nav-label">Administration</div>
    <?php if ($navAdmin) { ?>
    <a class="<?= ems_here('parameters.php') ?>" href="parameters.php">Parameters</a>
    <a class="<?= ems_here('meters.php') ?>" href="meters.php">Meters</a>
    <a class="<?= ems_here('users.php') ?>" href="users.php">Users</a>
    <?php } ?>
    <?php if ($navAll) { ?>
    <a class="<?= ems_here('mail_settings.php') ?>" href="mail_settings.php">Alarm mail</a>
    <a class="<?= ems_here('bill_locations.php') ?>" href="bill_locations.php">Locations</a>
    <?php } ?>
    <a class="<?= ems_here('bill_approvals.php') ?>" href="bill_approvals.php">Meter approvals</a>
    <a class="<?= ems_here('event_log.php') ?>" href="event_log.php">Event log</a>
    <a href="logout.php">Sign out</a>
</div>
<header class="appbar">
    <div class="crumbs">
        <button class="nav-toggle" type="button" onclick="toggleNav()" aria-label="Open navigation">Menu</button>
        <span><?= htmlspecialchars($crumb[0]) ?></span>
        <span aria-hidden="true">/</span>
        <b><?= htmlspecialchars($crumb[1]) ?></b>
    </div>
    <div class="appbar-actions">
        <span class="ops-sub"><?= $userLabel ?></span>
        <button class="btn btn-outline-secondary btn-sm" type="button" onclick="toggleTheme()">Display</button>
        <a class="btn btn-outline-danger btn-sm" href="logout.php">Sign out</a>
    </div>
</header>
