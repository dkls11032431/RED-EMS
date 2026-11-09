<?php

require __DIR__ . '/includes/bill_catalog.php';

assert(bill_company_for_state('TAMILNADU') === 'KAL');
assert(bill_company_for_state('KERALA') === 'KAL');
assert(bill_company_for_state('ANDHRA PRADESH') === 'KAL');
assert(bill_company_for_state('KARNATAKA') === 'KAL');
assert(bill_company_for_state('GUJRAT') === 'SAFL');
assert(bill_company_for_state('UP & RAJASTHAN') === 'SAFL');
assert(bill_company_for_state('WEST BENGAL') === 'SAFL');
assert(bill_company_for_state('NORTH EAST') === 'SAFL');

$shares = bill_split_units(1074, 4);
assert(count($shares) === 4);
assert(abs(array_sum($shares) - 1074) < 0.001);

$lines = bill_apportion(246846, 1074, [6487, 6264, 4431, 2910], 2250, 18);
assert(count($lines) === 4);
$proportion = 0.0;
$common = 0.0;
$units = 0.0;
foreach ($lines as $line) {
    $proportion += $line['proportionate'];
    $common += $line['common'];
    $units += $line['units'];
    assert(abs($line['invoice'] - (2250 + 405)) < 0.001);
    assert(abs($line['total'] - ($line['units'] + $line['common'])) < 0.001);
}
assert(abs($proportion - 246846) < 0.001);
assert(abs($common - 1074) < 0.001);
assert(abs($units - 20092) < 0.001);
assert(abs($lines[0]['units'] - 6487) < 0.001);

$sites = [];
foreach (bill_master_rows() as $row) {
    $sites[$row[0] . '|' . $row[1]] = bill_company_for_state($row[0]);
}
foreach (['GUJRAT|SURAT', 'MAHARASTRA|PUNE', 'UP & RAJASTHAN|VARANASI', 'BIHAR|PATNA', 'NORTH|LEH', 'TAMILNADU|TRICHY', 'KERALA|KOZHIKODE', 'ANDHRA PRADESH|Vizag'] as $key) {
    assert(isset($sites[$key]), $key);
}
assert($sites['TAMILNADU|TRICHY'] === 'KAL');
assert($sites['UP & RAJASTHAN|VARANASI'] === 'SAFL');
assert(bill_legal_name('ENIL - MIRCHI') === 'M/s. Entertainment Network (Radio Mirchi)');
assert(bill_legal_name('RED FM') === 'M/s. South Asia (Red FM)');

echo "bill_apportion_test: ok\n";
