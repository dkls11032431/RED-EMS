<?php

/*
 | Diagnostic only — NOT a real decoder. Splits a captured "data" hex
 | blob two different plausible ways (1 byte per index, and 2 bytes
 | per index) so you can eyeball which one produces numbers that look
 | like real meter readings, compared against parameter_array_index
 | values in parameter_master_princek2.
 |
 | Usage: edit $sampleData below to a real "data=" value from
 | raw_capture.log, then open this file in a browser.
*/

$sampleData = '0BB801036C00086BF402258F3E09B809A509AC008F00CE00D8000003E501F3000200000DC0000013C0000014C000A00000000006DE1000001C041A1B191E0B1B1A112E0000000006CA0D3B001B0518019E340601A00D30000000000000000000000000000000000DA0000013C0000014C019F6';

echo "<h3>Raw data string (length " . strlen($sampleData) . ")</h3>";
echo "<pre>{$sampleData}</pre>";

echo "<h3>Interpretation A: 1 byte per index (2 hex chars each)</h3>";
echo "<table border=1 cellpadding=4><tr><th>Index</th><th>Hex</th><th>Decimal</th></tr>";
for ($i = 0; $i < strlen($sampleData) - 1; $i += 2) {
    $byteHex = substr($sampleData, $i, 2);
    $index = ($i / 2) + 1;
    echo "<tr><td>{$index}</td><td>{$byteHex}</td><td>" . hexdec($byteHex) . "</td></tr>";
}
echo "</table>";

echo "<h3>Interpretation B: 1 register per index (4 hex chars = 2 bytes each)</h3>";
echo "<table border=1 cellpadding=4><tr><th>Index</th><th>Hex</th><th>Decimal</th></tr>";
for ($i = 0; $i < strlen($sampleData) - 3; $i += 4) {
    $regHex = substr($sampleData, $i, 4);
    $index = ($i / 4) + 1;
    echo "<tr><td>{$index}</td><td>{$regHex}</td><td>" . hexdec($regHex) . "</td></tr>";
}
echo "</table>";