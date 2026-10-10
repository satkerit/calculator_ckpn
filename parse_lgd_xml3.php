<?php
$zip = new ZipArchive();
$zip->open(__DIR__ . '/docs/Kertas kerja.xlsx');

$sst = [];
$sstXml = simplexml_load_string($zip->getFromName('xl/sharedStrings.xml'));
foreach ($sstXml->si as $si) {
    if (isset($si->t)) { $sst[] = (string)$si->t; }
    elseif (isset($si->r)) { $t=''; foreach ($si->r as $r) $t .= (string)$r->t; $sst[] = $t; }
    else { $sst[] = ''; }
}

$xml = simplexml_load_string($zip->getFromName('xl/worksheets/sheet6.xml'));
$lo = (int)($argv[1] ?? 41);
$hi = (int)($argv[2] ?? 96);
foreach ($xml->sheetData->row as $row) {
    $rNum = (int)$row['r'];
    if ($rNum < $lo || $rNum > $hi) continue;
    $cells = [];
    foreach ($row->c as $c) {
        $ref = (string)$c['r'];
        $type = (string)$c['t'];
        $formula = isset($c->f) ? '=' . (string)$c->f : null;
        $val = isset($c->v) ? (string)$c->v : '';
        if ($type === 's' && $val !== '' && isset($sst[(int)$val])) $val = $sst[(int)$val];
        if ($formula || ($val !== '' && $val !== null)) $cells[] = "$ref: " . ($formula ? "[$formula] " : '') . $val;
    }
    if ($cells) echo "R$rNum | " . implode(' | ', $cells) . "\n";
}
$zip->close();
