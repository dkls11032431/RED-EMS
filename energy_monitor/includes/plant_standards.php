<?php

require_once __DIR__ . '/bill_catalog.php';

/**
 * IS 12360 style window for a 230 V phase: 230 V ± 10%.
 * Power factor follows the utility penalty line the sites are billed on.
 */
function ems_default_limits(): array
{
    return [
        'Vr' => ['low' => 207, 'high' => 253, 'unit' => 'V', 'note' => 'Phase R, 230 V ± 10%'],
        'Vy' => ['low' => 207, 'high' => 253, 'unit' => 'V', 'note' => 'Phase Y, 230 V ± 10%'],
        'Vb' => ['low' => 207, 'high' => 253, 'unit' => 'V', 'note' => 'Phase B, 230 V ± 10%'],
        'Ir' => ['low' => 0, 'high' => 100, 'unit' => 'A', 'note' => 'Phase R. Set the high limit to the feeder rating. 0 disables the low alarm.'],
        'Iy' => ['low' => 0, 'high' => 100, 'unit' => 'A', 'note' => 'Phase Y. Set the high limit to the feeder rating.'],
        'Ib' => ['low' => 0, 'high' => 100, 'unit' => 'A', 'note' => 'Phase B. Set the high limit to the feeder rating.'],
        'PF' => ['low' => 0.85, 'high' => 1, 'unit' => 'pf', 'note' => 'Alarm when power factor is below 0.85'],
        'DAILY-KWH' => ['low' => 75, 'high' => 125, 'unit' => '%', 'note' => 'Yesterday versus the previous 7-day average'],
    ];
}

function ems_classify_supply(float $avgVoltage, float $kw, float $ebDelta, float $dgDelta): string
{
    $gridUp = $avgVoltage >= 180 || ($kw >= 0.3 && $ebDelta >= $dgDelta);
    $onDg = $dgDelta > 0.05 && $dgDelta > $ebDelta;
    if ($onDg && $avgVoltage < 180) {
        return 'DG';
    }
    if ($onDg && !$gridUp) {
        return 'DG';
    }
    if ($gridUp && !$onDg) {
        return 'EB';
    }
    if ($onDg) {
        return 'DG';
    }
    if ($avgVoltage < 50 && $kw < 0.2) {
        return 'NONE';
    }
    if ($gridUp) {
        return 'EB';
    }
    return 'NONE';
}

function ems_limit_alarm(float $value, float $low, float $high): ?string
{
    if ($high > 0 && $value > $high) {
        return 'HIGH';
    }
    if ($low > 0 && $value < $low) {
        return 'LOW';
    }
    return null;
}

function ems_pf_alarm(?float $pf, float $minimum = 0.85): ?string
{
    if ($pf === null || $pf <= 0 || $pf > 1.5) {
        return null;
    }
    return $pf < $minimum ? 'LOW' : null;
}

function ems_consumption_alarm(float $dayKwh, float $averageKwh, float $lowPercent, float $highPercent): ?string
{
    if ($averageKwh <= 0) {
        return null;
    }
    $ratio = ($dayKwh / $averageKwh) * 100;
    if ($ratio > $highPercent) {
        return 'HIGH';
    }
    if ($ratio < $lowPercent) {
        return 'LOW';
    }
    return null;
}

function ems_branch_coordinates(): array
{
    return [
        'AHMEDABAD' => [23.0225, 72.5714],
        'Rajkot' => [22.3039, 70.8022],
        'Vadodara' => [22.3072, 73.1812],
        'SURAT' => [21.1702, 72.8311],
        'PUNE' => [18.5204, 73.8567],
        'NAGPUR' => [21.1458, 79.0882],
        'DHULE' => [20.9042, 74.7749],
        'NANDED' => [19.1383, 77.3210],
        'BHOPAL' => [23.2599, 77.4126],
        'JABALPUR' => [23.1815, 79.9864],
        'INDORE' => [22.7196, 75.8577],
        'JAIPUR' => [26.9124, 75.7873],
        'KANPUR' => [26.4499, 80.3319],
        'ALLAHABAD' => [25.4358, 81.8463],
        'VARANASI' => [25.3176, 82.9739],
        'LUCKNOW' => [26.8467, 80.9462],
        'JHANSI' => [25.4484, 78.5685],
        'PATNA' => [25.5941, 85.1376],
        'MUZAFFARPUR' => [26.1209, 85.3647],
        'CHANDIGARH' => [30.7333, 76.7794],
        'AMRITSAR' => [31.6340, 74.8723],
        'JAMMU' => [32.7266, 74.8570],
        'LEH' => [34.1526, 77.5771],
        'SILIGURI' => [26.7271, 88.3953],
        'GANGTOK' => [27.3389, 88.6065],
        'ASANSOL' => [23.6739, 86.9524],
        'AGARTALA' => [23.8315, 91.2868],
        'VIJAYAWADA' => [16.5062, 80.6480],
        'RAJAMUNDRY' => [17.0005, 81.8040],
        'WARANGAL' => [17.9689, 79.5941],
        'HYDERABAD' => [17.3850, 78.4867],
        'Vizag' => [17.6868, 83.2185],
        'BANGALORE' => [12.9716, 77.5946],
        'MANGALORE' => [12.9141, 74.8560],
        'Mysore' => [12.2958, 76.6394],
        'Trivandrum' => [8.5241, 76.9366],
        'Thrissur' => [10.5276, 76.2144],
        'KANNUR' => [11.8745, 75.3704],
        'KOZHIKODE' => [11.2588, 75.7804],
        'MADURAI' => [9.9252, 78.1198],
        'Pondychery' => [11.9416, 79.8083],
        'TRICHY' => [10.7905, 78.7047],
        'Tuticorin' => [8.7642, 78.1348],
        'Coimbatore' => [11.0168, 76.9558],
        'Nellai' => [8.7139, 77.7567],
    ];
}

function ems_table_columns(PDO $conn, string $table): array
{
    try {
        $columns = [];
        foreach ($conn->query('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '`') as $column) {
            $columns[strtolower($column['Field'])] = $column['Field'];
        }
        return $columns;
    } catch (Throwable $e) {
        return [];
    }
}

function ems_add_column(PDO $conn, string $table, string $column, string $definition): void
{
    $columns = ems_table_columns($conn, $table);
    if ($columns === [] || isset($columns[strtolower($column)])) {
        return;
    }
    $conn->exec('ALTER TABLE `' . str_replace('`', '', $table) . '` ADD COLUMN `' . $column . '` ' . $definition);
}

function ems_ensure_plant(PDO $conn): void
{
    bill_ensure($conn);

    $conn->exec("
        CREATE TABLE IF NOT EXISTS electrical_limits (
            parameter_name VARCHAR(40) PRIMARY KEY,
            low_limit DECIMAL(12,3) NOT NULL,
            high_limit DECIMAL(12,3) NOT NULL,
            unit_label VARCHAR(16) NOT NULL,
            note VARCHAR(255) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $limit = $conn->prepare("
        INSERT IGNORE INTO electrical_limits (parameter_name, low_limit, high_limit, unit_label, note)
        VALUES (?, ?, ?, ?, ?)
    ");
    foreach (ems_default_limits() as $name => $row) {
        $limit->execute([$name, $row['low'], $row['high'], $row['unit'], $row['note']]);
    }

    $conn->exec("
        CREATE TABLE IF NOT EXISTS site_geo (
            id INT AUTO_INCREMENT PRIMARY KEY,
            state_name VARCHAR(80) NOT NULL,
            branch_name VARCHAR(80) NOT NULL,
            latitude DECIMAL(9,6) NULL,
            longitude DECIMAL(9,6) NULL,
            UNIQUE KEY uniq_geo (state_name, branch_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $coords = ems_branch_coordinates();
    $geo = $conn->prepare("
        INSERT IGNORE INTO site_geo (state_name, branch_name, latitude, longitude)
        VALUES (?, ?, ?, ?)
    ");
    $sites = $conn->query('SELECT state_name, branch_name FROM bill_sites');
    foreach ($sites as $site) {
        $point = $coords[$site['branch_name']] ?? [null, null];
        $geo->execute([$site['state_name'], $site['branch_name'], $point[0], $point[1]]);
    }

    $conn->exec("
        CREATE TABLE IF NOT EXISTS meter_branches (
            meter_id INT PRIMARY KEY,
            branch_name VARCHAR(80) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    try {
        $conn->exec("
            INSERT IGNORE INTO meter_branches (meter_id, branch_name)
            SELECT meter_id, 'TRICHY' FROM meter_master WHERE meter_name LIKE '%Trichy%'
        ");
        $conn->exec("
            INSERT IGNORE INTO meter_branches (meter_id, branch_name)
            SELECT meter_id, 'PUNE' FROM meter_master WHERE meter_name LIKE '%Pune%' OR meter_name LIKE '%Main Incomer%'
        ");
    } catch (Throwable $e) {
        // meter_master is created by the existing collector, not by this page.
    }

    $conn->exec("
        CREATE TABLE IF NOT EXISTS mail_settings (
            id TINYINT PRIMARY KEY,
            enabled TINYINT NOT NULL DEFAULT 0,
            host VARCHAR(160) NOT NULL DEFAULT '',
            port INT NOT NULL DEFAULT 587,
            username VARCHAR(160) NOT NULL DEFAULT '',
            password VARCHAR(160) NOT NULL DEFAULT '',
            encryption VARCHAR(8) NOT NULL DEFAULT 'tls',
            from_email VARCHAR(160) NOT NULL DEFAULT '',
            from_name VARCHAR(80) NOT NULL DEFAULT 'EMS alarms',
            admin_email VARCHAR(160) NOT NULL DEFAULT '',
            supervisor_email VARCHAR(160) NOT NULL DEFAULT ''
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $conn->exec("INSERT IGNORE INTO mail_settings (id) VALUES (1)");

    $conn->exec("
        CREATE TABLE IF NOT EXISTS bill_fault_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            bill_type VARCHAR(20) NOT NULL,
            company VARCHAR(10) NOT NULL,
            branch_name VARCHAR(80) NOT NULL,
            broadcaster VARCHAR(120) NOT NULL,
            transmitter VARCHAR(120) NOT NULL,
            reason VARCHAR(500) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            requested_by VARCHAR(80) NOT NULL,
            reviewed_by VARCHAR(80) NULL,
            created_at DATETIME NOT NULL,
            reviewed_at DATETIME NULL,
            KEY idx_fault (branch_name, broadcaster, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    ems_add_column($conn, 'eb_worksheet_lines', 'entry_mode', "VARCHAR(16) NOT NULL DEFAULT 'meter'");
    ems_add_column($conn, 'eb_worksheets', 'bill_pdf', 'VARCHAR(255) NULL');
    ems_add_column($conn, 'users', 'role', "VARCHAR(20) NOT NULL DEFAULT 'user'");
    ems_add_column($conn, 'users', 'company', 'VARCHAR(20) NULL');
    ems_add_column($conn, 'users', 'branch_name', 'VARCHAR(80) NULL');
    ems_add_column($conn, 'users', 'email', 'VARCHAR(160) NULL');

    $conn->exec("
        CREATE TABLE IF NOT EXISTS user_locations (
            user_id INT NOT NULL,
            branch_name VARCHAR(80) NOT NULL,
            PRIMARY KEY (user_id, branch_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $conn->exec("
        CREATE TABLE IF NOT EXISTS event_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(80) NOT NULL,
            activity VARCHAR(160) NOT NULL,
            field_name VARCHAR(120) NULL,
            detail VARCHAR(500) NULL,
            duration_seconds INT NULL,
            ip_address VARCHAR(64) NULL,
            created_at DATETIME NULL,
            KEY idx_event_time (created_at),
            KEY idx_event_user (username)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    ems_add_column($conn, 'event_log', 'field_name', 'VARCHAR(120) NULL');
    ems_add_column($conn, 'event_log', 'detail', 'VARCHAR(500) NULL');
    ems_add_column($conn, 'event_log', 'duration_seconds', 'INT NULL');
    ems_add_column($conn, 'event_log', 'ip_address', 'VARCHAR(64) NULL');
    ems_add_column($conn, 'event_log', 'created_at', 'DATETIME NULL');

    $conn->exec("
        CREATE TABLE IF NOT EXISTS dg_daily_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            log_date DATE NOT NULL,
            category VARCHAR(16) NOT NULL,
            company VARCHAR(10) NOT NULL DEFAULT '',
            branch_name VARCHAR(80) NOT NULL,
            opening_kwh DECIMAL(14,2) NOT NULL DEFAULT 0,
            closing_kwh DECIMAL(14,2) NOT NULL DEFAULT 0,
            kwh DECIMAL(14,2) NOT NULL DEFAULT 0,
            power_out CHAR(5) NOT NULL DEFAULT '',
            power_in CHAR(5) NOT NULL DEFAULT '',
            running_hmm DECIMAL(8,2) NOT NULL DEFAULT 0,
            tank_litres DECIMAL(12,2) NULL,
            consumption_litres DECIMAL(12,2) NULL,
            hand_stock_litres DECIMAL(12,2) NULL,
            fuel_operator VARCHAR(120) NOT NULL DEFAULT '',
            fuel_qty DECIMAL(12,2) NULL,
            remarks VARCHAR(500) NOT NULL DEFAULT '',
            created_by VARCHAR(80) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            KEY idx_dg_day (log_date, branch_name, category)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $conn->exec("
        CREATE TABLE IF NOT EXISTS iot_devices (
            id INT AUTO_INCREMENT PRIMARY KEY,
            meter_id INT NOT NULL,
            device_name VARCHAR(120) NOT NULL,
            protocol VARCHAR(24) NOT NULL,
            host VARCHAR(160) NOT NULL DEFAULT '',
            port INT NULL,
            unit_id INT NULL,
            notes VARCHAR(500) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL,
            KEY idx_iot_meter (meter_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    ems_add_column($conn, 'iot_devices', 'branch_name', "VARCHAR(80) NOT NULL DEFAULT ''");
    ems_add_column($conn, 'iot_devices', 'last_check_at', 'DATETIME NULL');
    ems_add_column($conn, 'iot_devices', 'last_check_ok', 'TINYINT NULL');
    ems_add_column($conn, 'iot_devices', 'last_check_detail', 'VARCHAR(255) NULL');

    ems_seed_accounts($conn);
    ems_seed_user_locations($conn);
}

function ems_seed_user_locations(PDO $conn): void
{
    try {
        $conn->exec("
            INSERT IGNORE INTO user_locations (user_id, branch_name)
            SELECT id, branch_name FROM users
            WHERE branch_name IS NOT NULL AND branch_name <> ''
        ");
    } catch (Throwable $e) {
        // Older user tables without an id column keep the single branch_name column.
    }
}

function ems_match_branch(string $label): string
{
    $label = trim($label);
    if ($label === '') {
        return '';
    }
    foreach (array_keys(ems_branch_coordinates()) as $name) {
        if (strcasecmp($name, $label) === 0) {
            return $name;
        }
    }
    foreach (array_keys(ems_branch_coordinates()) as $name) {
        if (stripos($label, $name) !== false || stripos($name, $label) !== false) {
            return $name;
        }
    }
    return $label;
}

function ems_seed_accounts(PDO $conn): void
{
    $columns = ems_table_columns($conn, 'users');
    if (!isset($columns['username'], $columns['password'])) {
        return;
    }
    $hash = md5('Sfm@93.5');
    $hasRole = isset($columns['role']);
    $hasCompany = isset($columns['company']);
    $hasBranch = isset($columns['branch_name']);

    $exists = $conn->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
    $exists->execute(['supervisor']);
    if (!$exists->fetchColumn()) {
        $fields = ['username', 'password'];
        $values = ['supervisor', $hash];
        if ($hasRole) {
            $fields[] = 'role';
            $values[] = 'supervisor';
        }
        if ($hasCompany) {
            $fields[] = 'company';
            $values[] = null;
        }
        if ($hasBranch) {
            $fields[] = 'branch_name';
            $values[] = null;
        }
        $sql = 'INSERT INTO users (' . implode(',', $fields) . ') VALUES (' . implode(',', array_fill(0, count($fields), '?')) . ')';
        $conn->prepare($sql)->execute($values);
    }

    if (!$hasRole || !$hasBranch) {
        return;
    }
    $sites = $conn->query('SELECT branch_name, company FROM bill_sites');
    $insertFields = ['username', 'password', 'role', 'branch_name'];
    if ($hasCompany) {
        $insertFields[] = 'company';
    }
    $insert = $conn->prepare(
        'INSERT INTO users (' . implode(',', $insertFields) . ') VALUES (' . implode(',', array_fill(0, count($insertFields), '?')) . ')'
    );
    foreach ($sites as $site) {
        $exists->execute([$site['branch_name']]);
        if ($exists->fetchColumn()) {
            continue;
        }
        $row = [$site['branch_name'], $hash, 'user', $site['branch_name']];
        if ($hasCompany) {
            $row[] = $site['company'];
        }
        $insert->execute($row);
    }
}

function ems_limits(PDO $conn): array
{
    $limits = ems_default_limits();
    foreach ($conn->query('SELECT * FROM electrical_limits') as $row) {
        $limits[$row['parameter_name']] = [
            'low' => (float) $row['low_limit'],
            'high' => (float) $row['high_limit'],
            'unit' => $row['unit_label'],
            'note' => $row['note'],
        ];
    }
    return $limits;
}

function ems_latest_value(PDO $conn, int $meterId, string $name): ?float
{
    $stmt = $conn->prepare("
        SELECT value FROM meter_readings
        WHERE meter_id = ? AND parameter_name = ?
        ORDER BY reading_time DESC, id DESC
        LIMIT 1
    ");
    $stmt->execute([$meterId, $name]);
    $value = $stmt->fetchColumn();
    return $value === false ? null : (float) $value;
}

function ems_counter_delta(PDO $conn, int $meterId, string $name): float
{
    $stmt = $conn->prepare("
        SELECT value, reading_time FROM meter_readings
        WHERE meter_id = ? AND parameter_name = ?
        ORDER BY reading_time DESC, id DESC
        LIMIT 1
    ");
    $stmt->execute([$meterId, $name]);
    $latest = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$latest) {
        return 0.0;
    }
    $stmt = $conn->prepare("
        SELECT value FROM meter_readings
        WHERE meter_id = ? AND parameter_name = ?
          AND reading_time <= DATE_SUB(?, INTERVAL 30 MINUTE)
        ORDER BY reading_time DESC, id DESC
        LIMIT 1
    ");
    $stmt->execute([$meterId, $name, $latest['reading_time']]);
    $earlier = $stmt->fetchColumn();
    if ($earlier === false) {
        return 0.0;
    }
    return max(0, (float) $latest['value'] - (float) $earlier);
}

function ems_meter_snapshot(PDO $conn, int $meterId): array
{
    $phases = [];
    foreach (['Vr', 'Vy', 'Vb'] as $name) {
        $value = ems_latest_value($conn, $meterId, $name);
        if ($value !== null) {
            $phases[$name] = $value;
        }
    }
    $currents = [];
    foreach (['Ir', 'Iy', 'Ib'] as $name) {
        $value = ems_latest_value($conn, $meterId, $name);
        if ($value !== null) {
            $currents[$name] = $value;
        }
    }
    $pf = ems_latest_value($conn, $meterId, 'PF');
    if ($pf === null) {
        $pf = ems_latest_value($conn, $meterId, 'Power Factor');
    }
    $avgV = $phases === [] ? 0.0 : array_sum($phases) / count($phases);
    $kw = ems_latest_value($conn, $meterId, 'KW-Tot.') ?? 0.0;
    $source = ems_classify_supply(
        $avgV,
        $kw,
        ems_counter_delta($conn, $meterId, 'EB-KWH'),
        ems_counter_delta($conn, $meterId, 'DG-KWH')
    );
    if ($phases === [] && $kw == 0.0) {
        $source = 'UNKNOWN';
    }
    return [
        'source' => $source,
        'avg_v' => $avgV,
        'kw' => $kw,
        'pf' => $pf,
        'phases' => $phases,
        'currents' => $currents,
    ];
}

function ems_raise_alarm(PDO $conn, int $meterId, string $branch, string $message, string $parameter, $limit, $actual, string $severity): bool
{
    $columns = ems_table_columns($conn, 'alarms');
    if ($columns === []) {
        return false;
    }
    $checkSql = "SELECT id FROM alarms WHERE alarm_message = ? AND status = 'ACTIVE'";
    $checkParams = [$message];
    if (isset($columns['meter_id'])) {
        $checkSql .= ' AND meter_id = ?';
        $checkParams[] = $meterId;
    }
    $check = $conn->prepare($checkSql);
    $check->execute($checkParams);
    if ($check->fetchColumn()) {
        return false;
    }

    $data = [];
    $put = function (string $name, $value) use (&$data, $columns) {
        if (isset($columns[strtolower($name)])) {
            $data[$columns[strtolower($name)]] = $value;
        }
    };
    $put('meter_id', $meterId);
    $put('site_name', $branch);
    $put('branch_name', $branch);
    $put('parameter_name', $parameter);
    $put('alarm_value', $limit);
    $put('actual_value', $actual);
    $put('alarm_message', $message);
    $put('severity', $severity);
    $put('status', 'ACTIVE');
    $put('alarm_time', date('Y-m-d H:i:s'));
    if ($data === []) {
        return false;
    }
    $fields = array_keys($data);
    $sql = 'INSERT INTO alarms (' . implode(',', array_map(function ($field) {
        return '`' . $field . '`';
    }, $fields)) . ') VALUES (' . implode(',', array_fill(0, count($fields), '?')) . ')';
    $conn->prepare($sql)->execute(array_values($data));
    ems_mail_alarm($conn, $branch, $message, $parameter, $actual, $limit, $severity);
    return true;
}

function ems_clear_alarm(PDO $conn, int $meterId, string $message): void
{
    $columns = ems_table_columns($conn, 'alarms');
    if (!isset($columns['status'], $columns['alarm_message'])) {
        return;
    }
    $sql = "UPDATE alarms SET status = 'CLEARED'";
    if (isset($columns['cleared_time'])) {
        $sql .= ', cleared_time = NOW()';
    }
    $sql .= " WHERE alarm_message = ? AND status = 'ACTIVE'";
    $params = [$message];
    if (isset($columns['meter_id'])) {
        $sql .= ' AND meter_id = ?';
        $params[] = $meterId;
    }
    $conn->prepare($sql)->execute($params);
}

function ems_sync_meter(PDO $conn, int $meterId, string $branch = ''): array
{
    $snapshot = ems_meter_snapshot($conn, $meterId);
    if ($branch === '') {
        $stmt = $conn->prepare('SELECT branch_name FROM meter_branches WHERE meter_id = ?');
        $stmt->execute([$meterId]);
        $branch = (string) ($stmt->fetchColumn() ?: ('Meter ' . $meterId));
    }
    $limits = ems_limits($conn);
    if ($snapshot['source'] === 'UNKNOWN') {
        return $snapshot;
    }

    if ($snapshot['source'] === 'EB') {
        ems_clear_alarm($conn, $meterId, 'DG RUNNING');
        ems_clear_alarm($conn, $meterId, 'NO SUPPLY');
        ems_clear_alarm($conn, $meterId, 'EB FAILURE');
    } elseif ($snapshot['source'] === 'DG') {
        ems_raise_alarm($conn, $meterId, $branch, 'DG RUNNING', 'Source', 0, $snapshot['kw'], 'WARNING');
        ems_raise_alarm($conn, $meterId, $branch, 'EB FAILURE', 'Source', 180, $snapshot['avg_v'], 'WARNING');
        ems_clear_alarm($conn, $meterId, 'NO SUPPLY');
    } else {
        ems_raise_alarm($conn, $meterId, $branch, 'NO SUPPLY', 'Source', 0, $snapshot['avg_v'], 'CRITICAL');
        ems_raise_alarm($conn, $meterId, $branch, 'EB FAILURE', 'Source', 180, $snapshot['avg_v'], 'CRITICAL');
        ems_clear_alarm($conn, $meterId, 'DG RUNNING');
    }

    foreach ($snapshot['phases'] as $name => $value) {
        $limit = $limits[$name] ?? $limits['Vr'];
        $state = $snapshot['source'] === 'NONE' ? null : ems_limit_alarm($value, $limit['low'], $limit['high']);
        $high = $name . ' HIGH';
        $low = $name . ' LOW';
        if ($state === 'HIGH') {
            ems_raise_alarm($conn, $meterId, $branch, $high, $name, $limit['high'], $value, 'CRITICAL');
        } else {
            ems_clear_alarm($conn, $meterId, $high);
        }
        if ($state === 'LOW') {
            ems_raise_alarm($conn, $meterId, $branch, $low, $name, $limit['low'], $value, 'WARNING');
        } else {
            ems_clear_alarm($conn, $meterId, $low);
        }
    }
    foreach ($snapshot['currents'] as $name => $value) {
        $limit = $limits[$name] ?? $limits['Ir'];
        $state = ems_limit_alarm($value, $limit['low'], $limit['high']);
        $high = $name . ' HIGH';
        $low = $name . ' LOW';
        if ($state === 'HIGH') {
            ems_raise_alarm($conn, $meterId, $branch, $high, $name, $limit['high'], $value, 'CRITICAL');
        } else {
            ems_clear_alarm($conn, $meterId, $high);
        }
        if ($state === 'LOW') {
            ems_raise_alarm($conn, $meterId, $branch, $low, $name, $limit['low'], $value, 'WARNING');
        } else {
            ems_clear_alarm($conn, $meterId, $low);
        }
    }

    $pfLimit = $limits['PF']['low'] ?? 0.85;
    $pfState = ems_pf_alarm($snapshot['pf'], $pfLimit);
    if ($pfState === 'LOW') {
        ems_raise_alarm($conn, $meterId, $branch, 'POWER FACTOR LOW', 'PF', $pfLimit, $snapshot['pf'], 'WARNING');
    } else {
        ems_clear_alarm($conn, $meterId, 'POWER FACTOR LOW');
    }

    $daily = $limits['DAILY-KWH'];
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $stmt = $conn->prepare("
        SELECT
            COALESCE(MAX(CASE WHEN parameter_name = 'EB-KWH' THEN value END) - MIN(CASE WHEN parameter_name = 'EB-KWH' THEN value END), 0)
            + COALESCE(MAX(CASE WHEN parameter_name = 'DG-KWH' THEN value END) - MIN(CASE WHEN parameter_name = 'DG-KWH' THEN value END), 0)
        FROM meter_readings
        WHERE meter_id = ? AND DATE(reading_time) = ?
    ");
    $stmt->execute([$meterId, $yesterday]);
    $yesterdayKwh = (float) $stmt->fetchColumn();
    $history = [];
    for ($i = 2; $i <= 8; $i++) {
        $day = date('Y-m-d', strtotime('-' . $i . ' day'));
        $stmt->execute([$meterId, $day]);
        $history[] = (float) $stmt->fetchColumn();
    }
    $average = array_sum($history) / max(1, count(array_filter($history, function ($value) {
        return $value > 0;
    })) ?: 1);
    $positive = array_values(array_filter($history, function ($value) {
        return $value > 0;
    }));
    $average = $positive === [] ? 0 : array_sum($positive) / count($positive);
    $consumption = ems_consumption_alarm($yesterdayKwh, $average, $daily['low'], $daily['high']);
    if ($consumption === 'HIGH') {
        ems_raise_alarm($conn, $meterId, $branch, 'DAILY CONSUMPTION HIGH', 'kWh', $average, $yesterdayKwh, 'WARNING');
        ems_clear_alarm($conn, $meterId, 'DAILY CONSUMPTION LOW');
    } elseif ($consumption === 'LOW') {
        ems_raise_alarm($conn, $meterId, $branch, 'DAILY CONSUMPTION LOW', 'kWh', $average, $yesterdayKwh, 'WARNING');
        ems_clear_alarm($conn, $meterId, 'DAILY CONSUMPTION HIGH');
    } else {
        ems_clear_alarm($conn, $meterId, 'DAILY CONSUMPTION HIGH');
        ems_clear_alarm($conn, $meterId, 'DAILY CONSUMPTION LOW');
    }

    return $snapshot;
}

function ems_mail_settings(PDO $conn): array
{
    $row = $conn->query('SELECT * FROM mail_settings WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
    return $row ?: [];
}

function ems_smtp_send(array $settings, string $to, string $subject, string $body): bool
{
    $host = $settings['host'] ?? '';
    $port = (int) ($settings['port'] ?? 587);
    if ($host === '' || $to === '') {
        return false;
    }
    $remote = ($settings['encryption'] ?? '') === 'ssl' ? 'ssl://' . $host : $host;
    $socket = @fsockopen($remote, $port, $errno, $errstr, 12);
    if (!$socket) {
        return false;
    }
    $read = function () use ($socket) {
        $data = '';
        while ($line = fgets($socket, 515)) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };
    $write = function (string $command) use ($socket, $read) {
        fwrite($socket, $command . "\r\n");
        return $read();
    };
    $read();
    $write('EHLO ems.local');
    if (($settings['encryption'] ?? '') === 'tls') {
        $write('STARTTLS');
        stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        $write('EHLO ems.local');
    }
    if (($settings['username'] ?? '') !== '') {
        $write('AUTH LOGIN');
        $write(base64_encode($settings['username']));
        $write(base64_encode($settings['password'] ?? ''));
    }
    $from = $settings['from_email'] ?: $settings['username'];
    $write('MAIL FROM:<' . $from . '>');
    $write('RCPT TO:<' . $to . '>');
    $write('DATA');
    $headers = 'From: ' . ($settings['from_name'] ?? 'EMS') . ' <' . $from . ">\r\n";
    $headers .= 'To: <' . $to . ">\r\n";
    $headers .= 'Subject: ' . $subject . "\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    fwrite($socket, $headers . "\r\n" . $body . "\r\n.\r\n");
    $read();
    $write('QUIT');
    fclose($socket);
    return true;
}

function ems_mail_alarm(PDO $conn, string $branch, string $message, string $parameter, $actual, $limit, string $severity): void
{
    $settings = ems_mail_settings($conn);
    $logDir = dirname(__DIR__) . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }
    $line = date('Y-m-d H:i:s') . " | $severity | $branch | $message | $parameter | actual=$actual | limit=$limit\n";
    @file_put_contents($logDir . '/alarms.log', $line, FILE_APPEND);
    if ((int) ($settings['enabled'] ?? 0) !== 1) {
        return;
    }
    $recipients = array_filter([
        $settings['admin_email'] ?? '',
        $settings['supervisor_email'] ?? '',
    ]);
    $stmt = $conn->prepare("SELECT email FROM users WHERE branch_name = ? AND email IS NOT NULL AND email <> ''");
    try {
        $stmt->execute([$branch]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $email) {
            $recipients[] = $email;
        }
    } catch (Throwable $e) {
        // Location mail is optional until the email column exists.
    }
    $subject = 'EMS ' . $severity . ' · ' . $branch . ' · ' . $message;
    $body = "Location: $branch\nAlarm: $message\nParameter: $parameter\nActual: $actual\nLimit: $limit\n";
    foreach (array_unique($recipients) as $to) {
        ems_smtp_send($settings, $to, $subject, $body);
    }
}

function ems_fault_status(PDO $conn, string $type, string $company, string $branch, string $broadcaster, string $transmitter): ?string
{
    $stmt = $conn->prepare("
        SELECT status FROM bill_fault_requests
        WHERE bill_type = ? AND company = ? AND branch_name = ? AND broadcaster = ? AND transmitter = ?
          AND status IN ('pending', 'approved')
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$type, $company, $branch, $broadcaster, $transmitter]);
    $status = $stmt->fetchColumn();
    return $status === false ? null : (string) $status;
}
