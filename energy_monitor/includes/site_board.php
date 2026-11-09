<?php

/**
 * One tile per billing location. Colour comes from the latest stored sample.
 * Green is EB, orange is DG, red is no communication. Nothing here is simulated.
 *
 * @return array<int, array<string, mixed>>
 */
function ems_site_board(PDO $conn, array $locationGroups): array
{
    $sql = 'SELECT state_name, branch_name, company FROM bill_sites';
    $params = [];
    if (!sees_all_sites()) {
        [$clause, $params] = scope_branch_clause('branch_name');
        $sql .= ' WHERE 1=1' . $clause;
    }
    $sql .= ' ORDER BY branch_name';
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $sites = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $byBranch = [];
    foreach ($conn->query('SELECT meter_id, branch_name FROM meter_branches') as $row) {
        $byBranch[strtoupper((string) $row['branch_name'])][] = (int) $row['meter_id'];
    }
    $names = [];
    foreach ($conn->query('SELECT meter_id, meter_name FROM meter_master') as $row) {
        $names[(int) $row['meter_id']] = (string) $row['meter_name'];
    }
    $last = [];
    foreach ($conn->query('SELECT meter_id, MAX(reading_time) AS reading_time FROM meter_readings GROUP BY meter_id') as $row) {
        $last[(int) $row['meter_id']] = $row['reading_time'];
    }

    $fromGroups = [];
    foreach ($locationGroups['locations'] ?? [] as $group) {
        $key = strtoupper(ems_match_branch((string) $group['location']));
        foreach ($group['meters'] as $meter) {
            $fromGroups[$key][] = (int) $meter['meter_id'];
            $names[(int) $meter['meter_id']] = (string) $meter['meter_name'];
        }
    }

    $tiles = [];
    foreach ($sites as $site) {
        $key = strtoupper(ems_match_branch((string) $site['branch_name']));
        $ids = array_values(array_unique(array_merge($byBranch[$key] ?? [], $byBranch[strtoupper($site['branch_name'])] ?? [], $fromGroups[$key] ?? [])));
        $meters = [];
        $newest = null;
        $mainId = 0;
        foreach ($ids as $id) {
            if ($id <= 0) {
                continue;
            }
            $meterName = $names[$id] ?? ('Meter ' . $id);
            $meters[] = ['meter_id' => $id, 'meter_name' => $meterName];
            $when = $last[$id] ?? null;
            if ($when && ($newest === null || strtotime((string) $when) > strtotime((string) $newest))) {
                $newest = $when;
            }
            $isDg = (bool) preg_match('/\bDG\b|DIESEL/i', $meterName);
            if ($mainId === 0 || ($isDg === false && preg_match('/\bDG\b|DIESEL/i', $names[$mainId] ?? ''))) {
                $mainId = $id;
            }
        }
        $age = $newest ? (time() - strtotime((string) $newest)) / 60 : null;
        $tone = 'down';
        $label = 'No communication';
        if ($newest !== null && $age !== null && $age <= 5 && $mainId > 0) {
            $dgId = 0;
            foreach ($meters as $meter) {
                if (preg_match('/\bDG\b|DIESEL/i', $meter['meter_name'])) {
                    $dgId = (int) $meter['meter_id'];
                }
            }
            $snap = ems_meter_snapshot($conn, $mainId);
            $source = ems_classify_supply(
                (float) $snap['avg_v'],
                (float) $snap['kw'],
                ems_counter_delta($conn, $mainId, 'EB-KWH'),
                $dgId > 0 ? ems_counter_delta($conn, $dgId, 'DG-KWH') : ems_counter_delta($conn, $mainId, 'DG-KWH')
            );
            if ($source === 'EB') {
                $tone = 'eb';
                $label = 'Running on EB';
            } elseif ($source === 'DG') {
                $tone = 'dg';
                $label = 'Running on DG';
            }
        }
        $tiles[] = [
            'branch' => $site['branch_name'],
            'state' => $site['state_name'],
            'company' => $site['company'],
            'tone' => $tone,
            'label' => $label,
            'updated' => $newest ? (string) $newest : 'No sample stored',
            'main_meter_id' => $mainId,
            'meters' => $meters,
        ];
    }
    return $tiles;
}
