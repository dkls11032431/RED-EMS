<?php

function is_admin()
{
    return ($_SESSION['role'] ?? '') === 'admin';
}

function is_supervisor()
{
    return ($_SESSION['role'] ?? '') === 'supervisor';
}

function sees_all_sites()
{
    return is_admin() || is_supervisor();
}

function user_branch()
{
    return (string) ($_SESSION['branch'] ?? '');
}

function user_branches(): array
{
    $stored = $_SESSION['branches'] ?? null;
    if (is_array($stored)) {
        $names = [];
        foreach ($stored as $name) {
            $name = trim((string) $name);
            if ($name !== '') {
                $names[] = $name;
            }
        }
        if ($names !== []) {
            return array_values(array_unique($names));
        }
    }
    $one = user_branch();
    return $one !== '' ? [$one] : [];
}

function user_can_branch(string $branch): bool
{
    if (sees_all_sites()) {
        return true;
    }
    foreach (user_branches() as $allowed) {
        if (strcasecmp($allowed, $branch) === 0) {
            return true;
        }
    }
    return false;
}

function user_company()
{
    return $_SESSION['company'] ?? null;
}

function user_companies(): array
{
    if (sees_all_sites()) {
        return ['KAL', 'SAFL'];
    }
    $stored = $_SESSION['companies'] ?? null;
    if (is_array($stored) && $stored !== []) {
        return array_values($stored);
    }
    $company = user_company();
    return $company ? [(string) $company] : [];
}

function user_can_company(string $company): bool
{
    foreach (user_companies() as $allowed) {
        if (strcasecmp($allowed, $company) === 0) {
            return true;
        }
    }
    return false;
}

/**
 * @return array{0: string, 1: array<int, string>}
 */
function scope_branch_clause(string $column = 'branch_name'): array
{
    if (sees_all_sites()) {
        return ['', []];
    }
    $branches = user_branches();
    if ($branches === []) {
        return [' AND 1=0', []];
    }
    $placeholders = implode(',', array_fill(0, count($branches), '?'));
    return [' AND ' . $column . ' IN (' . $placeholders . ')', $branches];
}

function filter_location_groups(array $groups): array
{
    if (sees_all_sites()) {
        return $groups;
    }
    $allowed = user_branches();
    $locations = [];
    $used = [];
    foreach ($groups['locations'] ?? [] as $group) {
        $name = (string) ($group['location'] ?? '');
        $keep = false;
        foreach ($allowed as $branch) {
            if (strcasecmp($branch, $name) === 0 || stripos($name, $branch) !== false || stripos($branch, $name) !== false) {
                $keep = true;
                break;
            }
        }
        if (!$keep) {
            continue;
        }
        $locations[] = $group;
        foreach ($group['meters'] as $meter) {
            $used[(int) $meter['meter_id']] = true;
        }
    }
    return ['locations' => $locations, 'used' => $used];
}

/*
 | Critical check: verifies the given meter_id actually belongs to the
 | logged-in user's company before any page or API file uses it.
 | Without this, a client could edit ?meter_id=2 in the URL and view
 | another company's data even if the dropdown never shows it to them.
*/
function meter_belongs_to_user(PDO $conn, $meter_id)
{
    if (sees_all_sites()) {
        return true;
    }

    $branches = user_branches();
    if ($branches !== []) {
        $link = $conn->prepare('SELECT branch_name FROM meter_branches WHERE meter_id = ?');
        try {
            $link->execute([$meter_id]);
            $linked = $link->fetchColumn();
            if ($linked !== false) {
                foreach ($branches as $branch) {
                    if (strcasecmp((string) $linked, $branch) === 0) {
                        return true;
                    }
                }
            }
        } catch (Throwable $e) {
            // The link table is created the first time a plant page opens.
        }
        $name = $conn->prepare('SELECT meter_name FROM meter_master WHERE meter_id = ?');
        $name->execute([$meter_id]);
        $meterName = (string) $name->fetchColumn();
        if ($meterName !== '') {
            foreach ($branches as $branch) {
                if (stripos($meterName, $branch) !== false) {
                    return true;
                }
            }
        }
        return false;
    }

    $stmt = $conn->prepare("SELECT company FROM meter_master WHERE meter_id = ?");
    $stmt->execute([$meter_id]);
    $company = $stmt->fetchColumn();

    return $company !== false && $company !== null && $company !== '' && $company === user_company();
}
