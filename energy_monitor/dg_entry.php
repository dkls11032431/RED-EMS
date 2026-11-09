<?php

$company = strtoupper($_GET['company'] ?? 'SAFL');
if (!in_array($company, ['KAL', 'SAFL'], true)) {
    $company = 'SAFL';
}
header('Location: bill_entry.php?type=DG&company=' . urlencode($company));
exit;
