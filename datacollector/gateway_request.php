<?php

/**
 * Read a gateway packet from form fields, the query string, or a raw
 * application/x-www-form-urlencoded body.
 *
 * Devices in the field do not always send a Content-Type. PHP then
 * leaves $_POST empty while the body is still on php://input. Other
 * firmware posts multipart fields or puts the same keys on the URL.
 * For db and siteno the first non-empty source wins. For data, the
 * longest value wins: a short query string must not hide a full body.
 *
 * @param array<string, mixed> $post
 * @param array<string, mixed> $get
 * @return array{0: string, 1: string, 2: string}
 */
function ems_gateway_fields(string $raw, array $post, array $get): array
{
    $fromBody = [];
    if ($raw !== '') {
        parse_str($raw, $fromBody);
        if (!is_array($fromBody)) {
            $fromBody = [];
        }
    }

    $db = '';
    $siteno = '';
    $dataHex = '';

    foreach ([$post, $get, $fromBody] as $src) {
        if ($db === '' && isset($src['db']) && $src['db'] !== '') {
            $db = (string) $src['db'];
        }
        if ($siteno === '' && isset($src['siteno']) && $src['siteno'] !== '') {
            $siteno = (string) $src['siteno'];
        }
        if (isset($src['data']) && is_string($src['data']) && strlen($src['data']) > strlen($dataHex)) {
            $dataHex = $src['data'];
        }
    }

    $bare = trim($raw);
    if ($bare !== '' && preg_match('/^[0-9A-Fa-f]+$/', $bare) && strlen($bare) > strlen($dataHex)) {
        $dataHex = $bare;
    }

    return [$db, $siteno, $dataHex];
}
