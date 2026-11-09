<?php

/*
 | Gateway site number => location.
 | main_meter_id / dg_meter_id 0 means create "{Location} Main" or "{Location} DG".
 | A location with dg false does not get a diesel meter or DG-KWH.
 | UPS is not in these gateway packets, so no UPS row is created.
 */
return [
    '0923DL0035' => [
        'location' => 'Pune',
        'main_meter_id' => 1,
        'dg' => true,
        'dg_meter_id' => 0,
    ],
    '0723DL0011' => [
        'location' => 'Trichy',
        'main_meter_id' => 0,
        'dg' => false,
        'dg_meter_id' => 0,
    ],
];
