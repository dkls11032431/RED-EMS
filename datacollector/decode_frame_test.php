<?php

require __DIR__ . '/decode_frame.php';

$blockB = '000D01036CFD6FFFFFFC1B0000033900000550000009D800000708FFFFF9E8FFFFFF60000004D800000820000009D80000087000001630FFFFFE2000001630FFFFFC1C0000C31F007B000000061E0C000622F80006123F0000029900000000000003BE0B00000D03190000038F0B1E0018BA73';
$blockA = '0BB801036C0009CECA00AFAE1508F109230900005A006B005DFFFFFC1C01F3000200000550000009B0000006E000C80000000003BE0B00000D031910F31C2D10F31D3200000000038F0B1E00180419055D2A2F055D27050000000000000000000000000000000007F800000988000008486ACE';

$power = ems_readings_from_hex($blockB);
assert(abs($power['KW-Tot.'] - 5.680) < 0.001);
assert(abs($power['KW-r'] - 1.360) < 0.001);
assert(abs($power['Fr.'] - 49.951) < 0.001);
assert(abs($power['Vry'] - 400.908) < 0.001);
assert(abs($power['Vr'] - (400.908 / sqrt(3))) < 0.01);
assert(isset($power['Ir']) && $power['Ir'] > 0);

$energy = ems_readings_from_hex($blockA);
assert(abs($energy['EB-KWH'] - 115133.65) < 0.02);
assert(abs($energy['DG-KWH'] - 6427.62) < 0.02);
assert(!isset($energy['KW-Tot.']));

assert(ems_frame_is_complete($blockB));
assert(!ems_frame_is_complete(substr($blockB, 0, 93)));
$piece = substr($blockB, 0, 93);
assert(ems_assemble_frame('TESTSITE', $piece) === '');
assert(ems_assemble_frame('TESTSITE', substr($blockB, 93)) === $blockB);

$trichy = ems_site_profile('0723DL0011');
assert($trichy['location'] === 'Trichy');
assert($trichy['dg'] === false);
assert($trichy['main_meter_id'] === 0);
$pune = ems_site_profile('0923DL0035');
assert($pune['location'] === 'Pune');
assert($pune['dg'] === true);
assert($pune['main_meter_id'] === 1);

echo "decode_frame_test: ok\n";
