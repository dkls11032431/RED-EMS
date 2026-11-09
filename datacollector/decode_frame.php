<?php

/*
 | The USR module posts one Modbus read at a time.
 | Two bytes of start address, then a function-3 response:
 | slave, 0x03, byte count, register data, CRC.
 |
 | Block 0x000D is the SPMG300 display block (power, frequency,
 | line voltage). Block 0x0BB8 (3000) holds the cumulative kWh
 | counters. Registers 0-11 (phase voltage and current) are not
 | in either block, so Vr/Vy/Vb and Ir/Iy/Ib are derived from
 | the line voltages and the per-phase kW.
 */

function ems_signed32(int $hi, int $lo): int
{
    $hi &= 0xFFFF;
    $lo &= 0xFFFF;
    $value = ($hi << 16) | $lo;
    if ($hi >= 0x8000) {
        $value -= 0x100000000;
    }
    return (int) $value;
}

/**
 * @return array<int, int> register address => unsigned 16-bit value
 */
function ems_parse_push_frame(string $hex): array
{
    if ($hex === '' || strlen($hex) % 2 !== 0) {
        return [];
    }
    $raw = hex2bin($hex);
    if ($raw === false || strlen($raw) < 7) {
        return [];
    }

    $start = unpack('n', substr($raw, 0, 2))[1];
    $function = ord($raw[3]);
    $count = ord($raw[4]);
    if ($function !== 0x03 || strlen($raw) < 5 + $count) {
        return [];
    }

    $data = substr($raw, 5, $count);
    $registers = [];
    $words = intdiv(strlen($data), 2);
    for ($i = 0; $i < $words; $i++) {
        $registers[$start + $i] = unpack('n', substr($data, $i * 2, 2))[1];
    }
    return $registers;
}

/**
 * @param array<int, int> $registers
 * @return array<string, float>
 */
function ems_readings_from_registers(array $registers): array
{
    $map = require __DIR__ . '/../energy_monitor/includes/spmg300_register_map.php';
    $map[] = ['address' => 3000, 'parameter_name' => 'DG-KWH', 'resolution' => 0.01];
    $map[] = ['address' => 3002, 'parameter_name' => 'EB-KWH', 'resolution' => 0.01];

    $readings = [];
    foreach ($map as $entry) {
        $address = $entry['address'];
        if (!isset($registers[$address], $registers[$address + 1])) {
            continue;
        }
        $raw = ems_signed32($registers[$address], $registers[$address + 1]);
        $readings[$entry['parameter_name']] = $raw * $entry['resolution'];
    }

    if (isset($readings['Vry'], $readings['Vyb'], $readings['Vbr'])) {
        $readings['Vr'] = $readings['Vry'] / sqrt(3);
        $readings['Vy'] = $readings['Vyb'] / sqrt(3);
        $readings['Vb'] = $readings['Vbr'] / sqrt(3);
    }

    $phases = [
        'Ir' => ['KW-r', 'Vr', 'Total Power Factor'],
        'Iy' => ['KW-y', 'Vy', 'PF-Y'],
        'Ib' => ['KW-b', 'Vb', 'PF-B'],
    ];
    foreach ($phases as $name => [$kwName, $voltName, $pfName]) {
        if (!isset($readings[$kwName], $readings[$voltName]) || $readings[$voltName] == 0.0) {
            continue;
        }
        $pf = 1.0;
        if (isset($readings[$pfName]) && abs($readings[$pfName]) > 0.05) {
            $pf = abs($readings[$pfName]);
        }
        $readings[$name] = abs($readings[$kwName]) * 1000 / ($readings[$voltName] * $pf);
    }

    foreach ($readings as $name => $value) {
        $readings[$name] = round($value, 3);
    }
    return $readings;
}

/**
 * @return array<string, float>
 */
function ems_readings_from_hex(string $hex): array
{
    return ems_readings_from_registers(ems_parse_push_frame($hex));
}

function ems_frame_is_complete(string $hex): bool
{
    if ($hex === '' || strlen($hex) % 2 !== 0) {
        return false;
    }
    $raw = hex2bin($hex);
    if ($raw === false || strlen($raw) < 7 || ord($raw[3]) !== 0x03) {
        return false;
    }
    $count = ord($raw[4]);
    return strlen($raw) >= 5 + $count + 2;
}

/**
 * The gateway sometimes posts a short first chunk. Hold it until the
 * rest arrives. A chunk that already starts a new frame replaces the
 * fragment instead of being glued onto it.
 */
function ems_assemble_frame(string $siteno, string $hex): string
{
    $hex = strtoupper((string) preg_replace('/[^0-9A-Fa-f]/', '', $hex));
    $safeSite = preg_replace('/[^A-Za-z0-9_-]/', '', $siteno);
    $path = __DIR__ . '/partial_' . ($safeSite !== '' ? $safeSite : 'unknown') . '.txt';

    if (ems_frame_is_complete($hex)) {
        if (is_file($path)) {
            unlink($path);
        }
        return $hex;
    }

    $previous = is_file($path) ? (string) file_get_contents($path) : '';
    $newFrame = strncmp($hex, '000D', 4) === 0 || strncmp($hex, '0BB8', 4) === 0;
    if ($previous !== '' && $newFrame) {
        $previous = '';
    }

    $combined = $previous . $hex;
    if (strlen($combined) > 600) {
        $combined = $hex;
    }

    if (ems_frame_is_complete($combined)) {
        if (is_file($path)) {
            unlink($path);
        }
        return $combined;
    }

    file_put_contents($path, $combined);
    return '';
}

/**
 * @return array{location: string, main_meter_id: int, dg: bool, dg_meter_id: int}
 */
function ems_site_profile(string $siteno): array
{
    $assigned = require __DIR__ . '/site_meters.php';
    $row = $assigned[$siteno] ?? null;
    $profile = [
        'location' => '',
        'main_meter_id' => 0,
        'dg' => false,
        'dg_meter_id' => 0,
    ];
    if (is_array($row)) {
        $profile['location'] = (string) ($row['location'] ?? $siteno);
        $profile['main_meter_id'] = (int) ($row['main_meter_id'] ?? $row['meter_id'] ?? 0);
        $profile['dg'] = !empty($row['dg']);
        $profile['dg_meter_id'] = (int) ($row['dg_meter_id'] ?? 0);
        return $profile;
    }
    if (is_int($row) || (is_string($row) && ctype_digit((string) $row))) {
        $profile['main_meter_id'] = (int) $row;
        $profile['dg'] = true;
    }
    return $profile;
}

/**
 * @return array<string, array<string, mixed>>
 */
function ems_meter_columns(PDO $conn): array
{
    static $columns = null;
    if ($columns === null) {
        $columns = [];
        foreach ($conn->query('SHOW COLUMNS FROM meter_master') as $column) {
            $columns[strtolower($column['Field'])] = $column;
        }
    }
    return $columns;
}

function ems_meter_name(PDO $conn, int $meterId): string
{
    $stmt = $conn->prepare('SELECT meter_name FROM meter_master WHERE meter_id = ?');
    $stmt->execute([$meterId]);
    $name = $stmt->fetchColumn();
    return $name !== false ? (string) $name : ('Meter ' . $meterId);
}

function ems_find_or_create_meter(PDO $conn, string $name): int
{
    $columns = ems_meter_columns($conn);
    if (!isset($columns['meter_name'])) {
        return 1;
    }

    $stmt = $conn->prepare('SELECT meter_id FROM meter_master WHERE meter_name = ? LIMIT 1');
    $stmt->execute([$name]);
    $existing = $stmt->fetchColumn();
    if ($existing) {
        return (int) $existing;
    }

    $nextId = (int) $conn->query('SELECT COALESCE(MAX(meter_id), 0) + 1 FROM meter_master')->fetchColumn();
    $fields = [$columns['meter_id']['Field'] ?? 'meter_id', $columns['meter_name']['Field']];
    $values = [$nextId, $name];
    if (isset($columns['company']) && $columns['company']['Null'] === 'NO' && $columns['company']['Default'] === null) {
        $fields[] = $columns['company']['Field'];
        $values[] = '';
    }
    $placeholders = implode(', ', array_fill(0, count($fields), '?'));
    $sql = 'INSERT INTO meter_master (' . implode(', ', array_map(function ($field) {
        return '`' . $field . '`';
    }, $fields)) . ') VALUES (' . $placeholders . ')';
    $conn->prepare($sql)->execute($values);
    return $nextId;
}

/**
 * Copy dashboard parameter cards from one meter to another.
 * $onlyNames null copies every card except DG-KWH.
 *
 * @param array<int, string>|null $onlyNames
 */
function ems_clone_parameters(PDO $conn, int $fromMeter, int $toMeter, ?array $onlyNames): void
{
    if ($fromMeter === $toMeter) {
        return;
    }

    $columnMeta = [];
    foreach ($conn->query('SHOW COLUMNS FROM parameter_master_princek2') as $column) {
        $columnMeta[$column['Field']] = $column;
    }

    $existing = [];
    $have = $conn->prepare('SELECT parameter_name FROM parameter_master_princek2 WHERE meter_group_id = ?');
    $have->execute([$toMeter]);
    foreach ($have->fetchAll(PDO::FETCH_COLUMN) as $name) {
        $existing[(string) $name] = true;
    }

    $source = $conn->prepare('SELECT * FROM parameter_master_princek2 WHERE meter_group_id = ?');
    $source->execute([$fromMeter]);
    $rows = $source->fetchAll(PDO::FETCH_ASSOC);
    if ($rows === []) {
        return;
    }

    foreach ($rows as $row) {
        $name = (string) ($row['parameter_name'] ?? '');
        if ($name === '' || isset($existing[$name])) {
            continue;
        }
        if ($onlyNames === null && $name === 'DG-KWH') {
            continue;
        }
        if ($onlyNames !== null && !in_array($name, $onlyNames, true)) {
            continue;
        }

        $data = [];
        foreach ($row as $field => $value) {
            if (!isset($columnMeta[$field])) {
                continue;
            }
            if (stripos((string) $columnMeta[$field]['Extra'], 'auto_increment') !== false) {
                continue;
            }
            $data[$field] = $value;
        }
        if (!isset($data['meter_group_id'], $data['parameter_name'])) {
            continue;
        }
        $data['meter_group_id'] = $toMeter;
        if (array_key_exists('displayindashboard', $data) && ($data['displayindashboard'] === null || $data['displayindashboard'] === '')) {
            $data['displayindashboard'] = 1;
        }

        $fields = array_keys($data);
        $sql = 'INSERT INTO parameter_master_princek2 (' . implode(', ', array_map(function ($field) {
            return '`' . $field . '`';
        }, $fields)) . ') VALUES (' . implode(', ', array_fill(0, count($fields), '?')) . ')';
        try {
            $conn->prepare($sql)->execute(array_values($data));
            $existing[$name] = true;
        } catch (Throwable $e) {
            // A single card that cannot be copied must not stop the rest.
        }
    }
}

/**
 * @return array{location: string, main: int, dg: int|null}
 */
function ems_bind_location_meters(PDO $conn, string $siteno): array
{
    $profile = ems_site_profile($siteno);
    $location = $profile['location'] !== '' ? $profile['location'] : $siteno;
    $mainId = $profile['main_meter_id'] > 0
        ? $profile['main_meter_id']
        : ems_find_or_create_meter($conn, $location . ' Main');
    ems_clone_parameters($conn, 1, $mainId, null);

    $dgId = null;
    if ($profile['dg']) {
        $dgId = $profile['dg_meter_id'] > 0
            ? $profile['dg_meter_id']
            : ems_find_or_create_meter($conn, $location . ' DG');
        ems_clone_parameters($conn, $mainId, $dgId, ['DG-KWH']);
    }

    return [
        'location' => $location,
        'main' => $mainId,
        'dg' => $dgId,
    ];
}

/**
 * @return array{locations: array<int, array<string, mixed>>, used: array<int, bool>}
 */
function ems_location_groups(PDO $conn): array
{
    $assigned = require __DIR__ . '/site_meters.php';
    $locations = [];
    $used = [];
    foreach ($assigned as $siteno => $row) {
        if (!is_array($row)) {
            continue;
        }
        $bound = ems_bind_location_meters($conn, (string) $siteno);
        $meters = [[
            'meter_id' => $bound['main'],
            'meter_name' => ems_meter_name($conn, $bound['main']),
        ]];
        $used[$bound['main']] = true;
        if ($bound['dg'] !== null) {
            $meters[] = [
                'meter_id' => $bound['dg'],
                'meter_name' => ems_meter_name($conn, $bound['dg']),
            ];
            $used[$bound['dg']] = true;
        }
        $locations[] = [
            'location' => $bound['location'],
            'siteno' => (string) $siteno,
            'meters' => $meters,
        ];
    }
    return ['locations' => $locations, 'used' => $used];
}

function ems_meter_id_for_site(PDO $conn, string $siteno): int
{
    return ems_bind_location_meters($conn, $siteno)['main'];
}

/**
 * @param array<string, float> $readings
 */
function ems_store_readings(PDO $conn, int $meterId, array $readings): int
{
    if ($readings === []) {
        return 0;
    }

    $lookup = $conn->prepare("
        SELECT id FROM parameter_master_princek2
        WHERE parameter_name = ? AND meter_group_id = ?
        LIMIT 1
    ");
    $insert = $conn->prepare("
        INSERT INTO meter_readings (id, meter_id, parameter_id, parameter_name, value, reading_time)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    date_default_timezone_set('Asia/Kolkata');
    $readingTime = date('Y-m-d H:i:s');

    $conn->beginTransaction();
    try {
        $nextId = (int) $conn->query('SELECT COALESCE(MAX(id), 0) FROM meter_readings FOR UPDATE')->fetchColumn();
        $stored = 0;
        foreach ($readings as $name => $value) {
            $lookup->execute([$name, $meterId]);
            $parameterId = $lookup->fetchColumn();
            $nextId++;
            $insert->execute([
                $nextId,
                $meterId,
                $parameterId !== false ? $parameterId : null,
                $name,
                $value,
                $readingTime,
            ]);
            $stored++;
        }
        $conn->commit();
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        return ems_store_readings_unlocked($conn, $meterId, $readings, $readingTime);
    }
    return $stored;
}

/**
 * Used when the table cannot lock or will not accept an explicit id.
 *
 * @param array<string, float> $readings
 */
function ems_store_readings_unlocked(PDO $conn, int $meterId, array $readings, string $readingTime): int
{
    $lookup = $conn->prepare("
        SELECT id FROM parameter_master_princek2
        WHERE parameter_name = ? AND meter_group_id = ?
        LIMIT 1
    ");
    $insert = $conn->prepare("
        INSERT INTO meter_readings (meter_id, parameter_id, parameter_name, value, reading_time)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stored = 0;
    foreach ($readings as $name => $value) {
        $lookup->execute([$name, $meterId]);
        $parameterId = $lookup->fetchColumn();
        $insert->execute([
            $meterId,
            $parameterId !== false ? $parameterId : null,
            $name,
            $value,
            $readingTime,
        ]);
        $stored++;
    }
    return $stored;
}
