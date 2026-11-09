<?php

/**
 * Branch, broadcaster and transmitter master for EB and DG worksheets.
 * South states are billed under KAL. Every other state on the sheet is SAFL.
 */

function bill_south_states(): array
{
    return ['ANDHRA PRADESH', 'KARNATAKA', 'KERALA', 'TAMILNADU'];
}

function bill_company_for_state(string $state): string
{
    return in_array(strtoupper(trim($state)), bill_south_states(), true) ? 'KAL' : 'SAFL';
}

function bill_legal_name(string $broadcaster, string $state = ''): string
{
    $key = strtoupper(preg_replace('/[^A-Z]/', '', $broadcaster));

    // South India RED FM stations
    if ($key === 'REDFM' && in_array(strtoupper(trim($state)), bill_south_states(), true)) {
        return 'M/s. Kal Radio Limited';
    }

    $map = [
        'ENILMIRCHI' => 'M/s. Entertainment Network (Radio Mirchi)',
        'ENILMIRCHILOVE' => 'M/s. Entertainment Network (Radio Mirchi Love)',
        'ENILMIRCHIKOOL' => 'M/s. Entertainment Network (Radio Mirchi Kool)',
        'REDFM' => 'M/s. South Asia (Red FM)',
        'BIGFM' => 'M/s. Reliance broadcast (Big FM)',
        'RADIOMANTRA' => 'M/s. Music Broadcast (Radio Mantra)',
        'RADIOCITY' => 'M/s. Music Broadcast (Radio City)',
        'MYFM' => 'M/s. DB Corp (My FM)',
        'RADIOONE' => 'M/s. Next Radio (Radio One)',
        'FEVERFM' => 'M/s. HT Media (Fever FM)',
        'RADIOORANGE' => 'M/s. Radio Orange',
        'ORANGEFM' => 'M/s. Orange FM',
        'RADIOTADKA' => 'M/s. Radio Tadka',
        'RADIOMISTI' => 'M/s. Radio Misti',
        'RADIOMISTY' => 'M/s. Radio Misty',
        'HELLOFM' => 'M/s. Hello FM',
        'SURYANFM' => 'M/s. Sun Network (Suryan FM)',
        'RADIOMANGO' => 'M/s. Radio Mango',
        'CLUBFM' => 'M/s. Club FM',
        'TOPFM' => 'M/s. Top FM',
        'RADIONATION' => 'M/s. Radio Nation',
        'MAGICFM' => 'M/s. Magic FM',
        'RADIOINDIGO' => 'M/s. Radio Indigo',
    ];
    return $map[$key] ?? $broadcaster;
}

/**
 * One row: state, branch, broadcaster, transmitter.
 * Locations from the branch sheet are included even when a transmitter row was blank.
 *
 * @return array<int, array{0:string,1:string,2:string,3:string}>
 */
function bill_master_rows(): array
{
    $rows = [
        // GUJRAT — SAFL
        ['GUJRAT', 'AHMEDABAD', 'RED FM', 'FM10S'],
        ['GUJRAT', 'AHMEDABAD', 'RADIO CITY', 'Harris 10 KW'],
        ['GUJRAT', 'AHMEDABAD', 'MY FM', 'Harris 10 KW'],
        ['GUJRAT', 'AHMEDABAD', 'RADIO ONE', 'Harris 10 KW'],
        ['GUJRAT', 'AHMEDABAD', 'ENIL - MIRCHI', 'STX HP 10 KW'],
        ['GUJRAT', 'AHMEDABAD', 'BIG FM', 'PMSC'],
        ['GUJRAT', 'AHMEDABAD', 'RED FM', 'R&S 5 KW'],
        ['GUJRAT', 'Rajkot', 'ENIL - MIRCHI', 'R&S 5 KW'],
        ['GUJRAT', 'Rajkot', 'MY FM', 'DIGITAL BROADCAST 5 KW'],
        ['GUJRAT', 'Vadodara', 'RED FM', 'R&S 5 KW'],
        ['GUJRAT', 'Vadodara', 'BIG FM', 'Harris 5 KW'],
        ['GUJRAT', 'Vadodara', 'RADIO CITY', 'Harris 5 KW'],
        ['GUJRAT', 'Vadodara', 'ENIL - MIRCHI', 'GatesAir Flexiva 5 KW'],
        ['GUJRAT', 'Vadodara', 'RADIO CITY', 'STX LP 5 KW'],
        ['GUJRAT', 'SURAT', 'RED FM', 'STX HP 10 KW'],
        ['GUJRAT', 'SURAT', 'BIG FM', 'Harris 10 KW'],
        ['GUJRAT', 'SURAT', 'ENIL - MIRCHI', 'STX HP 10 KW'],
        ['GUJRAT', 'SURAT', 'RADIO CITY', 'STX HP 20 KW'],
        ['GUJRAT', 'SURAT', 'MY FM', 'R&S 10 KW'],

        // MAHARASTRA
        ['MAHARASTRA', 'PUNE', 'RED FM', 'Harris 10 KW'],
        ['MAHARASTRA', 'PUNE', 'RADIO CITY', 'Harris 10 KW'],
        ['MAHARASTRA', 'PUNE', 'RADIO ONE', 'Harris 10 KW'],
        ['MAHARASTRA', 'PUNE', 'BIG FM', 'GatesAir Flexiva 10 KW'],
        ['MAHARASTRA', 'PUNE', 'ENIL - MIRCHI', 'STX HP 10 KW'],
        ['MAHARASTRA', 'PUNE', 'ENIL - MIRCHI LOVE', 'STX HP 10 KW'],
        ['MAHARASTRA', 'NAGPUR', 'RED FM', 'GatesAir Flexiva 10 KW'],
        ['MAHARASTRA', 'NAGPUR', 'ENIL - MIRCHI', 'GatesAir Flexiva 10 KW'],
        ['MAHARASTRA', 'NAGPUR', 'RADIO CITY', 'GatesAir Flexiva 10 KW'],
        ['MAHARASTRA', 'NAGPUR', 'MY FM', 'R&S 5 KW'],
        ['MAHARASTRA', 'NAGPUR', 'ENIL - MIRCHI LOVE', 'STX HP 10 KW'],
        ['MAHARASTRA', 'NAGPUR', 'BIG FM', 'GatesAir Flexiva 10 KW'],
        ['MAHARASTRA', 'DHULE', 'RED FM', 'R&S 5 KW'],
        ['MAHARASTRA', 'DHULE', 'RADIO ORANGE', 'R&S 5 KW'],
        ['MAHARASTRA', 'DHULE', 'MY FM', 'DIGITAL BROADCAST 5 KW'],
        ['MAHARASTRA', 'NANDED', 'RADIO CITY', 'Harris 5 KW'],
        ['MAHARASTRA', 'NANDED', 'RED FM', 'Harris 5 KW'],
        ['MAHARASTRA', 'NANDED', 'MY FM', 'DIGITAL BROADCAST 5 KW'],

        // MADHYA PRADESH
        ['MADHYA PRADESH', 'BHOPAL', 'RED FM', 'Harris 5 KW'],
        ['MADHYA PRADESH', 'BHOPAL', 'BIG FM', 'Harris 5 KW'],
        ['MADHYA PRADESH', 'BHOPAL', 'ENIL - MIRCHI', 'Harris 5 KW'],
        ['MADHYA PRADESH', 'BHOPAL', 'MY FM', 'Harris 5 KW'],
        ['MADHYA PRADESH', 'JABALPUR', 'RED FM', 'R&S 5 KW'],
        ['MADHYA PRADESH', 'JABALPUR', 'ENIL - MIRCHI', 'R&S 5 KW'],
        ['MADHYA PRADESH', 'JABALPUR', 'MY FM', 'R&S 5 KW'],
        ['MADHYA PRADESH', 'JABALPUR', 'ORANGE FM', 'R&S 5 KW'],
        ['MADHYA PRADESH', 'INDORE', 'RED FM', 'Harris 5 KW'],
        ['MADHYA PRADESH', 'INDORE', 'BIG FM', 'Harris 5 KW'],
        ['MADHYA PRADESH', 'INDORE', 'MY FM', 'Harris 5 KW'],
        ['MADHYA PRADESH', 'INDORE', 'ENIL - MIRCHI', 'R&S 5 KW'],

        // UP & RAJASTHAN
        ['UP & RAJASTHAN', 'JAIPUR', 'RED FM', 'R&S 5 KW'],
        ['UP & RAJASTHAN', 'JAIPUR', 'ENIL - MIRCHI LOVE', 'STX HP 10 KW'],
        ['UP & RAJASTHAN', 'JAIPUR', 'ENIL - MIRCHI', 'GatesAir Flexiva 10 KW'],
        ['UP & RAJASTHAN', 'JAIPUR', 'RADIO CITY', 'Harris 10 KW'],
        ['UP & RAJASTHAN', 'JAIPUR', 'MY FM', 'R&S 5 KW'],
        ['UP & RAJASTHAN', 'JAIPUR', 'RADIO TADKA', 'R&S 10 KW'],
        ['UP & RAJASTHAN', 'KANPUR', 'RED FM', 'Harris 5 KW'],
        ['UP & RAJASTHAN', 'KANPUR', 'ENIL - MIRCHI', 'STX HP 10 KW'],
        ['UP & RAJASTHAN', 'KANPUR', 'BIG FM', 'Harris 5 KW'],
        ['UP & RAJASTHAN', 'KANPUR', 'FEVER FM', 'GatesAir Flexiva 10 KW'],
        ['UP & RAJASTHAN', 'KANPUR', 'ENIL - MIRCHI LOVE', 'STX HP 10 KW'],
        ['UP & RAJASTHAN', 'KANPUR', 'RADIO CITY', 'GatesAir Flexiva 10 KW'],
        ['UP & RAJASTHAN', 'ALLAHABAD', 'RED FM', 'Harris 5 KW'],
        ['UP & RAJASTHAN', 'ALLAHABAD', 'BIG FM', 'Harris 5 KW'],
        ['UP & RAJASTHAN', 'ALLAHABAD', 'FEVER FM', 'Harris 5 KW'],
        ['UP & RAJASTHAN', 'ALLAHABAD', 'RADIO TADKA', 'R&S 5 KW'],
        ['UP & RAJASTHAN', 'VARANASI', 'ENIL - MIRCHI', 'GatesAir Flexiva 5 KW'],
        ['UP & RAJASTHAN', 'VARANASI', 'RED FM', 'GatesAir Flexiva 5 KW'],
        ['UP & RAJASTHAN', 'VARANASI', 'RADIO MANTRA', 'PMSC'],
        ['UP & RAJASTHAN', 'VARANASI', 'BIG FM', 'GatesAir Flexiva 5 KW'],
        ['UP & RAJASTHAN', 'LUCKNOW', 'RED FM', 'Harris 5 KW'],
        ['UP & RAJASTHAN', 'LUCKNOW', 'ENIL - MIRCHI', 'STX HP 10 KW'],
        ['UP & RAJASTHAN', 'LUCKNOW', 'RADIO CITY', 'GatesAir Flexiva 10 KW'],
        ['UP & RAJASTHAN', 'LUCKNOW', 'FEVER FM', 'Harris 5 KW'],
        ['UP & RAJASTHAN', 'LUCKNOW', 'BIG FM', 'GatesAir Flexiva 10 KW'],
        ['UP & RAJASTHAN', 'LUCKNOW', 'ENIL - MIRCHI LOVE', 'STX HP 10 KW'],
        ['UP & RAJASTHAN', 'JHANSI', 'BIG FM', 'Harris 5 KW'],
        ['UP & RAJASTHAN', 'JHANSI', 'RADIO TADKA', 'R&S 5 KW'],
        ['UP & RAJASTHAN', 'JHANSI', 'ENIL - MIRCHI', 'GatesAir Flexiva 5 KW'],

        // BIHAR
        ['BIHAR', 'PATNA', 'RED FM', 'STX LP 5 KW'],
        ['BIHAR', 'PATNA', 'BIG FM', 'GatesAir Flexiva 5 KW'],
        ['BIHAR', 'PATNA', 'ENIL - MIRCHI', 'GatesAir Flexiva 5 KW'],
        ['BIHAR', 'PATNA', 'RADIO CITY', 'STX LP 5 KW'],
        ['BIHAR', 'MUZAFFARPUR', 'RED FM', 'STX LP 5 KW'],
        ['BIHAR', 'MUZAFFARPUR', 'RADIO ORANGE', 'R&S 5 KW'],
        ['BIHAR', 'MUZAFFARPUR', 'BIG FM', 'GatesAir Flexiva 5 KW'],
        ['BIHAR', 'MUZAFFARPUR', 'RADIO TADKA', 'R&S 5 KW'],

        // NORTH
        ['NORTH', 'CHANDIGARH', 'RED FM', 'STX LP 5 KW'],
        ['NORTH', 'CHANDIGARH', 'BIG FM', 'Harris 5 KW'],
        ['NORTH', 'CHANDIGARH', 'MY FM', 'Harris 5 KW'],
        ['NORTH', 'CHANDIGARH', 'ENIL - MIRCHI', 'DIGITAL BROADCAST 5 KW'],
        ['NORTH', 'AMRITSAR', 'RED FM', 'STX LP 5 KW'],
        ['NORTH', 'AMRITSAR', 'BIG FM', 'Harris 5 KW'],
        ['NORTH', 'AMRITSAR', 'MY FM', 'Harris 5 KW'],
        ['NORTH', 'JAMMU', 'RED FM', 'STX LP 5 KW'],
        ['NORTH', 'JAMMU', 'ENIL - MIRCHI', 'GatesAir Flexiva 5 KW'],
        ['NORTH', 'JAMMU', 'MY FM', 'Harris 5 KW'],
        ['NORTH', 'JAMMU', 'RADIO TADKA', 'EDDYSTONE 5 KW'],
        ['NORTH', 'LEH', 'RED FM', 'STX LP 5 KW'],
        ['NORTH', 'LEH', 'ENIL - MIRCHI', 'DIGITAL BROADCAST 5 KW'],
        ['NORTH', 'LEH', 'RADIO TADKA', 'EDDYSTONE 5 KW'],
        ['NORTH', 'LEH', 'TOP FM', 'GatesAir Flexiva 5 KW'],
        ['NORTH', 'LEH', 'RADIO NATION', 'GatesAir Flexiva 5 KW'],

        // WEST BENGAL and NORTH EAST stay with SAFL
        ['WEST BENGAL', 'SILIGURI', 'RED FM', 'Harris 5 KW'],
        ['WEST BENGAL', 'SILIGURI', 'RADIO MISTI', 'EDDYSTONE 5 KW'],
        ['WEST BENGAL', 'SILIGURI', 'ENIL - MIRCHI', 'GatesAir Flexiva 3 KW'],
        ['WEST BENGAL', 'GANGTOK', 'RED FM', 'Harris 3 KW'],
        ['WEST BENGAL', 'GANGTOK', 'RADIO MISTI', 'EDDYSTONE 3 KW'],
        ['NORTH EAST', 'ASANSOL', 'RED FM', 'Harris 5 KW'],
        ['NORTH EAST', 'ASANSOL', 'ENIL - MIRCHI', 'GatesAir Flexiva 5 KW'],
        ['NORTH EAST', 'ASANSOL', 'BIG FM', 'Harris 5 KW'],
        ['NORTH EAST', 'AGARTALA', 'RED FM', 'Harris 5 KW'],
        ['NORTH EAST', 'AGARTALA', 'BIG FM', 'Harris 5 KW'],
        ['NORTH EAST', 'AGARTALA', 'ENIL - MIRCHI', 'GatesAir Flexiva 3 KW'],

        // South — KAL
        ['ANDHRA PRADESH', 'VIJAYAWADA', 'RED FM', 'PMSC'],
        ['ANDHRA PRADESH', 'VIJAYAWADA', 'BIG FM', 'STX LP 5 KW'],
        ['ANDHRA PRADESH', 'VIJAYAWADA', 'ENIL - MIRCHI', 'GatesAir Flexiva 5 KW'],
        ['ANDHRA PRADESH', 'RAJAMUNDRY', 'RED FM', 'Harris 5 KW'],
        ['ANDHRA PRADESH', 'RAJAMUNDRY', 'ENIL - MIRCHI', 'GatesAir Flexiva 5 KW'],
        ['ANDHRA PRADESH', 'RAJAMUNDRY', 'BIG FM', 'STX LP 5 KW'],
        ['ANDHRA PRADESH', 'WARANGAL', 'RED FM', 'PMSC'],
        ['ANDHRA PRADESH', 'WARANGAL', 'BIG FM', 'Harris 5 KW'],
        ['ANDHRA PRADESH', 'WARANGAL', 'ENIL - MIRCHI', 'GatesAir Flexiva 5 KW'],
        ['ANDHRA PRADESH', 'HYDERABAD', 'RED FM', 'Harris 10 KW'],
        ['ANDHRA PRADESH', 'HYDERABAD', 'BIG FM', 'Harris 10 KW'],
        ['ANDHRA PRADESH', 'HYDERABAD', 'ENIL - MIRCHI', 'Harris 10 KW'],
        ['ANDHRA PRADESH', 'HYDERABAD', 'ENIL - MIRCHI LOVE', 'STX HP 10 KW'],
        ['ANDHRA PRADESH', 'HYDERABAD', 'RADIO CITY', 'STX HP 10 KW'],
        ['ANDHRA PRADESH', 'HYDERABAD', 'ENIL - MIRCHI KOOL', 'GatesAir Flexiva 10 KW'],
        ['ANDHRA PRADESH', 'HYDERABAD', 'MAGIC FM', 'STX HP 10 KW'],
        ['ANDHRA PRADESH', 'Vizag', 'ENIL - MIRCHI', 'GatesAir Flexiva 5 KW'],
        ['ANDHRA PRADESH', 'Vizag', 'RADIO CITY', 'STX LP 5 KW'],
        ['ANDHRA PRADESH', 'Vizag', 'RED FM', 'Harris 5 KW'],

        ['KARNATAKA', 'BANGALORE', 'RED FM', 'Harris 10 KW'],
        ['KARNATAKA', 'BANGALORE', 'BIG FM', 'Harris 10 KW'],
        ['KARNATAKA', 'BANGALORE', 'ENIL - MIRCHI', 'STX LP 5 KW'],
        ['KARNATAKA', 'BANGALORE', 'RADIO CITY', 'Harris 10 KW'],
        ['KARNATAKA', 'BANGALORE', 'RADIO ONE', 'Harris 10 KW'],
        ['KARNATAKA', 'BANGALORE', 'FEVER FM', 'Harris 10 KW'],
        ['KARNATAKA', 'BANGALORE', 'RADIO INDIGO', 'R&S 10 KW'],
        ['KARNATAKA', 'BANGALORE', 'ENIL - MIRCHI LOVE', 'STX LP 5 KW'],
        ['KARNATAKA', 'MANGALORE', 'RED FM', 'Harris 5 KW'],
        ['KARNATAKA', 'MANGALORE', 'ENIL - MIRCHI', 'GatesAir Flexiva 5 KW'],
        ['KARNATAKA', 'MANGALORE', 'BIG FM', 'Harris 5 KW'],
        ['KARNATAKA', 'Mysore', 'RED FM', 'Harris 5 KW'],
        ['KARNATAKA', 'Mysore', 'ENIL - MIRCHI', 'GatesAir Flexiva 5 KW'],
        ['KARNATAKA', 'Mysore', 'BIG FM', 'Harris 5 KW'],

        ['KERALA', 'Trivandrum', 'RED FM', 'Harris 5 KW'],
        ['KERALA', 'Trivandrum', 'BIG FM', 'Harris 5 KW'],
        ['KERALA', 'Trivandrum', 'ENIL - MIRCHI', 'GatesAir Flexiva 5 KW'],
        ['KERALA', 'Trivandrum', 'CLUB FM', 'Harris 5 KW'],
        ['KERALA', 'Thrissur', 'RED FM', 'Harris 5 KW'],
        ['KERALA', 'Thrissur', 'BIG FM', 'Harris 5 KW'],
        ['KERALA', 'Thrissur', 'CLUB FM', 'Harris 5 KW'],
        ['KERALA', 'Thrissur', 'RADIO MANGO', 'PMSC'],
        ['KERALA', 'KANNUR', 'RED FM', 'Harris 5 KW'],
        ['KERALA', 'KANNUR', 'RADIO MANGO', 'PMSC'],
        ['KERALA', 'KANNUR', 'CLUB FM', 'Harris 5 KW'],
        ['KERALA', 'KOZHIKODE', 'RED FM', 'Harris 5 KW'],
        ['KERALA', 'KOZHIKODE', 'RADIO MANGO', 'PMSC'],
        ['KERALA', 'KOZHIKODE', 'ENIL - MIRCHI', 'Harris 5 KW'],
        ['KERALA', 'KOZHIKODE', 'CLUB FM', 'GatesAir Flexiva 5 KW'],

        ['TAMILNADU', 'MADURAI', 'RED FM', 'Harris 5 KW'],
        ['TAMILNADU', 'MADURAI', 'ENIL - MIRCHI', 'GatesAir Flexiva 5 KW'],
        ['TAMILNADU', 'MADURAI', 'HELLO FM', 'PMSC'],
        ['TAMILNADU', 'MADURAI', 'RADIO CITY', 'STX LP 5 KW'],
        ['TAMILNADU', 'MADURAI', 'ENIL - MIRCHI KOOL', 'GatesAir Flexiva 5 KW'],
        ['TAMILNADU', 'Pondychery', 'ENIL - MIRCHI KOOL', 'GatesAir Flexiva 5 KW'],
        ['TAMILNADU', 'Pondychery', 'RED FM', 'Harris 5 KW'],
        ['TAMILNADU', 'Pondychery', 'HELLO FM', 'Harris 5 KW'],
        ['TAMILNADU', 'Pondychery', 'BIG FM', 'Harris 5 KW'],
        ['TAMILNADU', 'TRICHY', 'ENIL - MIRCHI', 'GatesAir Flexiva 5 KW'],
        ['TAMILNADU', 'TRICHY', 'HELLO FM', 'PMSC'],
        ['TAMILNADU', 'TRICHY', 'SURYAN FM', 'Harris 5 KW'],
        ['TAMILNADU', 'Tuticorin', 'HELLO FM', 'Harris 5 KW'],
        ['TAMILNADU', 'Tuticorin', 'SURYAN FM', 'Harris 5 KW'],
        ['TAMILNADU', 'Tuticorin', 'ENIL - MIRCHI', 'GatesAir Flexiva 5 KW'],
        ['TAMILNADU', 'Tuticorin', 'RADIO CITY', 'STX LP 5 KW'],
        ['TAMILNADU', 'Coimbatore', 'ENIL - MIRCHI', 'GatesAir Flexiva 5 KW'],
        ['TAMILNADU', 'Coimbatore', 'RADIO CITY', 'STX LP 5 KW'],
        ['TAMILNADU', 'Coimbatore', 'HELLO FM', 'Harris 5 KW'],
        ['TAMILNADU', 'Coimbatore', 'SURYAN FM', 'Harris 5 KW'],
        ['TAMILNADU', 'Nellai', 'ENIL - MIRCHI', 'GatesAir Flexiva 5 KW'],
        ['TAMILNADU', 'Nellai', 'HELLO FM', 'Harris 5 KW'],
        ['TAMILNADU', 'Nellai', 'SURYAN FM', 'STX LP 5 KW'],
    ];

    return $rows;
}

function bill_inr($amount): string
{
    $negative = ((float) $amount) < 0;
    $amount = abs(round((float) $amount, 2));
    $parts = explode('.', number_format($amount, 2, '.', ''));
    $int = $parts[0];
    $dec = $parts[1];
    if (strlen($int) > 3) {
        $last3 = substr($int, -3);
        $rest = substr($int, 0, -3);
        $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
        $int = $rest . ',' . $last3;
    }
    return ($negative ? '-' : '') . $int . '.' . $dec;
}

/**
 * Split a quantity across n parties so the pieces add back to the original.
 * Earlier parties receive the extra fraction of a unit.
 *
 * @return array<int, float>
 */
function bill_split_units(float $total, int $count): array
{
    if ($count <= 0) {
        return [];
    }
    $cents = (int) round($total * 100);
    $each = intdiv($cents, $count);
    $remainder = $cents - ($each * $count);
    $shares = [];
    for ($i = 0; $i < $count; $i++) {
        $shares[] = ($each + ($i < $remainder ? 1 : 0)) / 100;
    }
    return $shares;
}

/**
 * Apportion a bill the way the BECIL worksheet does.
 * Common-area units are shared across broadcasters.
 * Proportionate energy amounts add up to the bill.
 * GST is 18% of the management charge. The invoice column is management plus GST.
 *
 * @param array<int, float> $ownUnits
 * @return array<int, array<string, float>>
 */
function bill_apportion(float $billAmount, float $commonUnits, array $ownUnits, float $managementEach, float $gstRate = 18.0): array
{
    $count = count($ownUnits);
    $shares = bill_split_units($commonUnits, $count);
    $totals = [];
    $sumTotal = 0.0;
    foreach ($ownUnits as $i => $own) {
        $totals[$i] = round(((float) $own) + $shares[$i], 2);
        $sumTotal += $totals[$i];
    }

    $lines = [];
    $allocated = 0.0;
    $sumCents = (int) round($sumTotal * 100);
    $billCents = (int) round($billAmount * 100);
    $given = 0;
    foreach ($ownUnits as $i => $own) {
        if ($i === $count - 1) {
            $proportionCents = $billCents - $given;
        } elseif ($sumCents === 0) {
            $proportionCents = 0;
        } else {
            $proportionCents = (int) round($billCents * (int) round($totals[$i] * 100) / $sumCents);
            $given += $proportionCents;
        }
        $management = round($managementEach, 2);
        $gst = round($management * $gstRate / 100, 2);
        $lines[] = [
            'units' => round((float) $own, 2),
            'common' => $shares[$i],
            'total' => $totals[$i],
            'proportionate' => $proportionCents / 100,
            'management' => $management,
            'gst' => $gst,
            'invoice' => round($management + $gst, 2),
        ];
        $allocated += $proportionCents / 100;
    }
    return $lines;
}

function bill_ensure(PDO $conn): void
{
    $conn->exec("
        CREATE TABLE IF NOT EXISTS bill_sites (
            id INT AUTO_INCREMENT PRIMARY KEY,
            state_name VARCHAR(80) NOT NULL,
            branch_name VARCHAR(80) NOT NULL,
            company VARCHAR(10) NOT NULL,
            UNIQUE KEY uniq_site (state_name, branch_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $conn->exec("
        CREATE TABLE IF NOT EXISTS bill_site_broadcasters (
            id INT AUTO_INCREMENT PRIMARY KEY,
            state_name VARCHAR(80) NOT NULL,
            branch_name VARCHAR(80) NOT NULL,
            broadcaster VARCHAR(120) NOT NULL,
            transmitter VARCHAR(120) NOT NULL,
            legal_name VARCHAR(180) NOT NULL,
            UNIQUE KEY uniq_tx (state_name, branch_name, broadcaster, transmitter)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $conn->exec("
        CREATE TABLE IF NOT EXISTS eb_worksheets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            bill_type VARCHAR(20) NOT NULL,
            company VARCHAR(10) NOT NULL,
            state_name VARCHAR(80) NOT NULL,
            branch_name VARCHAR(80) NOT NULL,
            bill_month CHAR(7) NOT NULL,
            bill_date DATE NULL,
            category CHAR(1) NOT NULL DEFAULT 'B',
            utility_name VARCHAR(255) NOT NULL DEFAULT '',
            bill_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
            management_charge DECIMAL(12,2) NOT NULL DEFAULT 2250,
            gst_rate DECIMAL(5,2) NOT NULL DEFAULT 18,
            common_old DECIMAL(14,3) NOT NULL DEFAULT 0,
            common_new DECIMAL(14,3) NOT NULL DEFAULT 0,
            common_units DECIMAL(14,3) NOT NULL DEFAULT 0,
            broadcaster_count INT NOT NULL DEFAULT 0,
            dg_capacity VARCHAR(40) NULL,
            fuel_litres DECIMAL(12,2) NULL,
            runtime_hours DECIMAL(8,2) NULL,
            remarks VARCHAR(500) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uniq_sheet (bill_type, company, branch_name, bill_month)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $conn->exec("
        CREATE TABLE IF NOT EXISTS eb_worksheet_lines (
            id INT AUTO_INCREMENT PRIMARY KEY,
            worksheet_id INT NOT NULL,
            line_no INT NOT NULL,
            broadcaster VARCHAR(120) NOT NULL,
            legal_name VARCHAR(180) NOT NULL,
            transmitter VARCHAR(120) NOT NULL DEFAULT '',
            old_reading DECIMAL(14,3) NOT NULL DEFAULT 0,
            new_reading DECIMAL(14,3) NOT NULL DEFAULT 0,
            units DECIMAL(14,3) NOT NULL DEFAULT 0,
            common_area_units DECIMAL(14,3) NOT NULL DEFAULT 0,
            total_units DECIMAL(14,3) NOT NULL DEFAULT 0,
            proportionate_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
            management_charges DECIMAL(14,2) NOT NULL DEFAULT 0,
            gst_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
            invoice_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
            KEY idx_sheet (worksheet_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $conn->exec("
        CREATE TABLE IF NOT EXISTS bill_catalog_state (
            state_key VARCHAR(40) PRIMARY KEY,
            state_value VARCHAR(40) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $flag = $conn->query("SELECT state_value FROM bill_catalog_state WHERE state_key = 'reconciled'")->fetchColumn();
    if ($flag === '1') {
        return;
    }
    $existingSites = (int) $conn->query('SELECT COUNT(*) FROM bill_sites')->fetchColumn();
    if ($existingSites === 0) {
        bill_seed_master($conn);
    }
    bill_reconcile_catalog($conn, $existingSites === 0);
    $conn->prepare('
        INSERT INTO bill_catalog_state (state_key, state_value) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE state_value = VALUES(state_value)
    ')->execute(['reconciled', '1']);
}

function bill_seed_master(PDO $conn): void
{
    $site = $conn->prepare('INSERT IGNORE INTO bill_sites (state_name, branch_name, company) VALUES (?, ?, ?)');
    $tx = $conn->prepare('
        INSERT IGNORE INTO bill_site_broadcasters
            (state_name, branch_name, broadcaster, transmitter, legal_name)
        VALUES (?, ?, ?, ?, ?)
    ');
    $seen = [];
    foreach (bill_master_rows() as $row) {
        [$state, $branch, $broadcaster, $transmitter] = $row;
        $key = strtoupper($branch);
        if (!isset($seen[$key])) {
            $site->execute([$state, $branch, bill_company_for_state($state)]);
            $seen[$key] = true;
        }
        $tx->execute([$state, $branch, $broadcaster, $transmitter, bill_legal_name($broadcaster, $state)]);
    }
}

function bill_canonical_state(string $state): string
{
    $clean = trim($state);
    $key = strtoupper($clean);
    $map = [
        'ANDHRA PRADEH' => 'ANDHRA PRADESH',
        'ANDHRA PRADESH' => 'ANDHRA PRADESH',
    ];
    return $map[$key] ?? $clean;
}

function bill_name_key(string $value): string
{
    return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $value));
}

function bill_alias_key(string $value): string
{
    $key = bill_name_key($value);
    $map = [
        'EFM' => 'BIGFM',
        'RADIOMISTY' => 'RADIOMISTI',
    ];
    return $map[$key] ?? $key;
}

function bill_workbook_index(): array
{
    $path = dirname(__DIR__) . '/data/broadcasters.json';
    if (!is_file($path)) {
        return [];
    }
    $rows = json_decode((string) file_get_contents($path), true);
    if (!is_array($rows)) {
        return [];
    }
    $index = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $state = bill_canonical_state((string) ($row['state'] ?? ''));
        $branch = trim((string) ($row['branch'] ?? ''));
        $broadcaster = trim((string) ($row['broadcaster'] ?? ''));
        if ($state === '' || $branch === '' || $broadcaster === '') {
            continue;
        }
        $branchKey = strtoupper($branch);
        if (!isset($index[$branchKey])) {
            $index[$branchKey] = [
                'state' => $state,
                'branch' => $branch,
                'company' => bill_company_for_state($state),
                'names' => [],
            ];
        }
        $index[$branchKey]['names'][bill_alias_key($broadcaster)] = $broadcaster;
    }
    return $index;
}

/**
 * Collapses duplicate locations and broadcaster spellings once.
 * After this, administrator edits and deletions are left as saved.
 */
function bill_reconcile_catalog(PDO $conn, bool $fillMissing): void
{
    $desired = bill_workbook_index();
    $masterTx = [];
    foreach (bill_master_rows() as $row) {
        [$state, $branch, $broadcaster, $transmitter] = $row;
        $branchKey = strtoupper($branch);
        $alias = bill_alias_key($broadcaster);
        $masterTx[$branchKey][$alias][strtoupper(trim($transmitter))] = $transmitter;
        if (!isset($desired[$branchKey])) {
            $desired[$branchKey] = [
                'state' => bill_canonical_state($state),
                'branch' => $branch,
                'company' => bill_company_for_state($state),
                'names' => [],
            ];
        }
    }

    $sites = $conn->query('SELECT id, state_name, branch_name, company FROM bill_sites')->fetchAll(PDO::FETCH_ASSOC);
    $grouped = [];
    foreach ($sites as $site) {
        $grouped[strtoupper($site['branch_name'])][] = $site;
    }

    $updateSite = $conn->prepare('UPDATE bill_sites SET state_name = ?, branch_name = ?, company = ? WHERE id = ?');
    $moveTx = $conn->prepare('UPDATE bill_site_broadcasters SET state_name = ?, branch_name = ? WHERE id = ?');
    $deleteTx = $conn->prepare('DELETE FROM bill_site_broadcasters WHERE id = ?');
    $deleteSite = $conn->prepare('DELETE FROM bill_sites WHERE id = ?');
    $findTx = $conn->prepare('
        SELECT id FROM bill_site_broadcasters
        WHERE state_name = ? AND branch_name = ? AND broadcaster = ? AND transmitter = ?
        LIMIT 1
    ');

    foreach ($grouped as $branchKey => $group) {
        $info = $desired[$branchKey] ?? null;
        $keeper = $group[0];
        foreach ($group as $site) {
            if ($info && strcasecmp($site['branch_name'], $info['branch']) === 0 && strcasecmp($site['state_name'], $info['state']) === 0) {
                $keeper = $site;
                break;
            }
        }
        $state = $info['state'] ?? bill_canonical_state($keeper['state_name']);
        $branch = $info['branch'] ?? $keeper['branch_name'];
        $company = $info['company'] ?? $keeper['company'];
        $selectRows = $conn->prepare('SELECT id, broadcaster, transmitter FROM bill_site_broadcasters WHERE state_name = ? AND branch_name = ?');
        foreach ($group as $site) {
            $selectRows->execute([$site['state_name'], $site['branch_name']]);
            foreach ($selectRows->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if ($site['state_name'] === $state && $site['branch_name'] === $branch) {
                    continue;
                }
                $findTx->execute([$state, $branch, $row['broadcaster'], $row['transmitter']]);
                $clash = $findTx->fetchColumn();
                if ($clash && (int) $clash !== (int) $row['id']) {
                    $deleteTx->execute([(int) $row['id']]);
                } else {
                    $moveTx->execute([$state, $branch, (int) $row['id']]);
                }
            }
            if ((int) $site['id'] !== (int) $keeper['id']) {
                $deleteSite->execute([(int) $site['id']]);
            }
        }
        $updateSite->execute([$state, $branch, $company, (int) $keeper['id']]);
    }

    $rename = $conn->prepare('UPDATE bill_site_broadcasters SET broadcaster = ?, legal_name = ? WHERE id = ?');
    $insertTx = $conn->prepare('
        INSERT INTO bill_site_broadcasters (state_name, branch_name, broadcaster, transmitter, legal_name)
        VALUES (?, ?, ?, ?, ?)
    ');
    foreach ($desired as $branchKey => $info) {
        $stmt = $conn->prepare('SELECT id, broadcaster, transmitter FROM bill_site_broadcasters WHERE UPPER(branch_name) = ?');
        $stmt->execute([$branchKey]);
        $byAlias = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $byAlias[bill_alias_key($row['broadcaster'])][] = $row;
        }
        foreach ($byAlias as $alias => $rows) {
            if (!isset($info['names'][$alias])) {
                foreach ($rows as $row) {
                    $deleteTx->execute([(int) $row['id']]);
                }
                continue;
            }
            $display = $info['names'][$alias];
            $hasTransmitter = false;
            foreach ($rows as $row) {
                if (trim((string) $row['transmitter']) !== '') {
                    $hasTransmitter = true;
                    break;
                }
            }
            $seen = [];
            foreach ($rows as $row) {
                $txKey = strtoupper(trim((string) $row['transmitter']));
                if (($txKey === '' && $hasTransmitter) || isset($seen[$txKey])) {
                    $deleteTx->execute([(int) $row['id']]);
                    continue;
                }
                $seen[$txKey] = true;
                if ($row['broadcaster'] !== $display) {
                    try {
                        $rename->execute([$display, bill_legal_name($display, $info['state']), (int) $row['id']]);
                    } catch (Throwable $e) {
                        $deleteTx->execute([(int) $row['id']]);
                    }
                }
            }
        }
        if (!$fillMissing) {
            continue;
        }
        foreach ($info['names'] as $alias => $display) {
            if (!empty($byAlias[$alias])) {
                continue;
            }
            $transmitters = array_values($masterTx[$branchKey][$alias] ?? ['']);
            if ($transmitters === []) {
                $transmitters = [''];
            }
            foreach ($transmitters as $transmitter) {
                try {
                    $insertTx->execute([
                        $info['state'],
                        $info['branch'],
                        $display,
                        $transmitter,
                        bill_legal_name($display, $info['state']),
                    ]);
                } catch (Throwable $e) {
                    continue;
                }
            }
        }
    }
}

function bill_sites(PDO $conn, string $company): array
{
    $stmt = $conn->prepare("
        SELECT state_name, branch_name, company
        FROM bill_sites
        WHERE company = ?
        ORDER BY state_name, branch_name
    ");
    $stmt->execute([$company]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function bill_broadcasters(PDO $conn, string $state, string $branch): array
{
    $stmt = $conn->prepare("
        SELECT broadcaster, transmitter, legal_name
        FROM bill_site_broadcasters
        WHERE state_name = ? AND branch_name = ?
        ORDER BY broadcaster, transmitter
    ");
    $stmt->execute([$state, $branch]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function bill_previous_reading(PDO $conn, string $billType, string $company, string $branch, string $month, string $broadcaster, string $transmitter): ?float
{
    $stmt = $conn->prepare("
        SELECT l.new_reading
        FROM eb_worksheet_lines l
        INNER JOIN eb_worksheets w ON w.id = l.worksheet_id
        WHERE w.bill_type = ?
          AND w.company = ?
          AND w.branch_name = ?
          AND w.bill_month < ?
          AND l.broadcaster = ?
          AND l.transmitter = ?
        ORDER BY w.bill_month DESC, l.id DESC
        LIMIT 1
    ");
    $stmt->execute([$billType, $company, $branch, $month, $broadcaster, $transmitter]);
    $value = $stmt->fetchColumn();
    return $value === false ? null : (float) $value;
}

function bill_previous_common(PDO $conn, string $billType, string $company, string $branch, string $month): ?float
{
    $stmt = $conn->prepare("
        SELECT common_new
        FROM eb_worksheets
        WHERE bill_type = ?
          AND company = ?
          AND branch_name = ?
          AND bill_month < ?
        ORDER BY bill_month DESC
        LIMIT 1
    ");
    $stmt->execute([$billType, $company, $branch, $month]);
    $value = $stmt->fetchColumn();
    return $value === false ? null : (float) $value;
}

function bill_find_sheet(PDO $conn, string $billType, string $company, string $branch, string $month): ?array
{
    $stmt = $conn->prepare("
        SELECT * FROM eb_worksheets
        WHERE bill_type = ? AND company = ? AND branch_name = ? AND bill_month = ?
        LIMIT 1
    ");
    $stmt->execute([$billType, $company, $branch, $month]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function bill_sheet_lines(PDO $conn, int $worksheetId): array
{
    $stmt = $conn->prepare("
        SELECT * FROM eb_worksheet_lines
        WHERE worksheet_id = ?
        ORDER BY line_no, id
    ");
    $stmt->execute([$worksheetId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
