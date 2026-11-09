<?php

require __DIR__ . '/gateway_request.php';

$sample = 'db=spzems&siteno=0923DL0035&data=0BB801036C';

[$db, $siteno, $data] = ems_gateway_fields($sample, [], []);
assert($db === 'spzems');
assert($siteno === '0923DL0035');
assert($data === '0BB801036C');

[$db, $siteno, $data] = ems_gateway_fields('', ['db' => 'spzems', 'siteno' => '0723DL0011', 'data' => '000D'], []);
assert($db === 'spzems' && $siteno === '0723DL0011' && $data === '000D');

[$db, $siteno, $data] = ems_gateway_fields('', [], ['db' => 'spzems', 'siteno' => 'SITE', 'data' => 'ABCD']);
assert($db === 'spzems' && $siteno === 'SITE' && $data === 'ABCD');

[$db, $siteno, $data] = ems_gateway_fields('', [], []);
assert($db === '' && $siteno === '' && $data === '');

$full = str_repeat('AB', 115);
[$db, $siteno, $data] = ems_gateway_fields(
    'db=spzems&siteno=0923DL0035&data=' . $full,
    [],
    ['data' => '000D0103']
);
assert($data === $full);
assert($siteno === '0923DL0035');

echo "gateway_request_test: ok\n";
