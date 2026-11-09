<?php

require __DIR__ . '/includes/plant_standards.php';

assert(ems_classify_supply(230, 14, 1.2, 0) === 'EB');
assert(ems_classify_supply(40, 12, 0, 1.5) === 'DG');
assert(ems_classify_supply(0, 0, 0, 0) === 'NONE');
assert(ems_limit_alarm(260, 207, 253) === 'HIGH');
assert(ems_limit_alarm(190, 207, 253) === 'LOW');
assert(ems_limit_alarm(230, 207, 253) === null);
assert(ems_limit_alarm(0.2, 0, 100) === null);
assert(ems_pf_alarm(0.80, 0.85) === 'LOW');
assert(ems_pf_alarm(0.91, 0.85) === null);
assert(ems_pf_alarm(null, 0.85) === null);
assert(ems_consumption_alarm(150, 100, 75, 125) === 'HIGH');
assert(ems_consumption_alarm(50, 100, 75, 125) === 'LOW');
assert(ems_consumption_alarm(100, 100, 75, 125) === null);
assert(isset(ems_branch_coordinates()['TRICHY']));
assert(isset(ems_branch_coordinates()['VARANASI']));

echo "plant_standards_test: ok\n";
