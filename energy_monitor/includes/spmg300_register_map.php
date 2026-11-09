<?php

/*
 | Confirmed register map for the SPMG300 meter, from SPZ's own
 | "SPMG300 Display Parameters" document. Function Code 3 (Read
 | Holding Registers), values received as raw integers, scaled by
 | the given resolution.
 |
 | 'parameter_name' matches the naming used elsewhere in this project
 | (parameter_master_princek2, meter_readings) where a sensible match
 | exists. Where the project uses a different name for the same
 | physical quantity, both are noted - confirm before relying on this
 | for anything beyond the immediate voltage/current/power values.
 |
 | NOTE: EB-KWH / DG-KWH (the cumulative energy totals) are NOT in
 | this table - the source document only lists them by name on a
 | different page, without register addresses. Add them here once
 | found; nothing below can populate meter_readings' EB-KWH/DG-KWH
 | rows yet.
*/

return [
    ['address' => 0,  'parameter_name' => 'Vr',  'resolution' => 0.001],
    ['address' => 2,  'parameter_name' => 'Vy',  'resolution' => 0.001],
    ['address' => 4,  'parameter_name' => 'Vb',  'resolution' => 0.001],
    ['address' => 6,  'parameter_name' => 'Ir',  'resolution' => 0.001],
    ['address' => 8,  'parameter_name' => 'Iy',  'resolution' => 0.001],
    ['address' => 10, 'parameter_name' => 'Ib',  'resolution' => 0.001],
    ['address' => 12, 'parameter_name' => 'PF-R', 'resolution' => 0.001],
    ['address' => 14, 'parameter_name' => 'PF-Y', 'resolution' => 0.001],
    ['address' => 16, 'parameter_name' => 'PF-B', 'resolution' => 0.001],
    ['address' => 18, 'parameter_name' => 'KW-r', 'resolution' => 0.001],
    ['address' => 20, 'parameter_name' => 'KW-y', 'resolution' => 0.001],
    ['address' => 22, 'parameter_name' => 'KW-b', 'resolution' => 0.001],
    ['address' => 24, 'parameter_name' => 'KVAR-R', 'resolution' => 0.001],
    ['address' => 26, 'parameter_name' => 'KVAR-Y', 'resolution' => 0.001],
    ['address' => 28, 'parameter_name' => 'KVAR-B', 'resolution' => 0.001],
    ['address' => 30, 'parameter_name' => 'KVA-R', 'resolution' => 0.001],
    ['address' => 32, 'parameter_name' => 'KVA-Y', 'resolution' => 0.001],
    ['address' => 34, 'parameter_name' => 'KVA-B', 'resolution' => 0.001],
    ['address' => 36, 'parameter_name' => 'KW-Tot.', 'resolution' => 0.001],
    ['address' => 38, 'parameter_name' => 'Total Reactive Power', 'resolution' => 0.001],
    ['address' => 40, 'parameter_name' => 'KVA-Tot.', 'resolution' => 0.001],
    ['address' => 42, 'parameter_name' => 'Total Power Factor', 'resolution' => 0.001],
    ['address' => 44, 'parameter_name' => 'Fr.', 'resolution' => 0.001],
    // address 46 (Phase sequence) intentionally omitted - not a numeric reading
    ['address' => 48, 'parameter_name' => 'Vry', 'resolution' => 0.001],
    ['address' => 50, 'parameter_name' => 'Vyb', 'resolution' => 0.001],
    ['address' => 52, 'parameter_name' => 'Vbr', 'resolution' => 0.001],
    ['address' => 54, 'parameter_name' => 'Rising Demand-EB', 'resolution' => 0.01],
    ['address' => 58, 'parameter_name' => 'Pr. Demand', 'resolution' => 0.01],
    // address 60 (MD Time & Date) intentionally omitted - not a numeric reading
    ['address' => 78, 'parameter_name' => 'Power on hrs - EB', 'resolution' => 1],
    ['address' => 80, 'parameter_name' => 'Power on hrs - DG', 'resolution' => 1],
    ['address' => 84, 'parameter_name' => 'Load on hrs - EB', 'resolution' => 1],
    ['address' => 86, 'parameter_name' => 'Load on hrs - DG', 'resolution' => 1],
];
