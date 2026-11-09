<?php

/*
 | Minimal Modbus TCP client (Function Code 3 - Read Holding Registers).
 | No external libraries needed - builds the MBAP header + PDU manually
 | and reads the raw response over a plain TCP socket.
 |
 | This talks to the USR-TCP232 gateway once it's reconfigured into
 | "TCP Server" work mode with Modbus Type = Modbus TCP/RTU (per the
 | gateway's own manual, section 5.1.1 - "Ethernet master with serial
 | slave"). The gateway then transparently forwards each request to
 | the SPMG300 meter over RS485 and relays the real Modbus RTU reply
 | back as a Modbus TCP response.
*/

class ModbusTcpException extends Exception {}

/**
 * Reads $quantity holding registers starting at $startAddress from a
 * Modbus TCP server (the USR gateway), for slave/unit ID $unitId.
 *
 * Returns an array of 16-bit unsigned integers (one per register).
 */
function modbus_read_holding_registers(string $host, int $port, int $startAddress, int $quantity, int $unitId = 1, float $timeoutSeconds = 3.0): array
{
    static $transactionId = 0;
    $transactionId = ($transactionId + 1) % 0xFFFF;

    $socket = @fsockopen($host, $port, $errno, $errstr, $timeoutSeconds);
    if ($socket === false) {
        throw new ModbusTcpException("Could not connect to {$host}:{$port} - {$errstr} ({$errno})");
    }
    stream_set_timeout($socket, (int) $timeoutSeconds);

    // PDU: function code 3, start address, quantity of registers
    $pdu = pack('Cnn', 0x03, $startAddress, $quantity);

    // MBAP header: transaction id, protocol id (0), length, unit id
    $length = strlen($pdu) + 1; // +1 for unit id byte that follows in the header count
    $mbap = pack('nnnC', $transactionId, 0x0000, $length, $unitId);

    $request = $mbap . $pdu;
    fwrite($socket, $request);

    $response = fread($socket, 512);
    fclose($socket);

    if ($response === false || strlen($response) < 9) {
        throw new ModbusTcpException("Empty or too-short Modbus response");
    }

    $header = unpack('ntxn/nproto/nlen/Cunit', substr($response, 0, 7));
    $functionCode = ord($response[7]);

    if ($functionCode & 0x80) {
        $exceptionCode = ord($response[8]);
        throw new ModbusTcpException("Modbus exception response, code {$exceptionCode}");
    }

    if ($functionCode !== 0x03) {
        throw new ModbusTcpException("Unexpected function code {$functionCode}");
    }

    $byteCount = ord($response[8]);
    $registerData = substr($response, 9, $byteCount);

    $registers = [];
    for ($i = 0; $i < $byteCount; $i += 2) {
        $registers[] = unpack('n', substr($registerData, $i, 2))[1];
    }

    return $registers;
}

/**
 * Reads a 32-bit value spanning 2 registers and applies a decimal scale.
 * $wordOrder: 'hi-lo' (first register is high word - most common) or
 * 'lo-hi' (some devices reverse this - try this if values look 65536x too big/small).
 */
function modbus_read_32bit_scaled(string $host, int $port, int $address, float $scale, int $unitId = 1, string $wordOrder = 'hi-lo'): float
{
    $regs = modbus_read_holding_registers($host, $port, $address, 2, $unitId);

    if ($wordOrder === 'hi-lo') {
        $raw = ($regs[0] << 16) | $regs[1];
    } else {
        $raw = ($regs[1] << 16) | $regs[0];
    }

    return $raw * $scale;
}
