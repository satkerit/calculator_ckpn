<?php
$zip = new ZipArchive();
if ($zip->open(__DIR__ . '/docs/Kertas kerja.xlsx') !== true) {
    die("failed open zip\n");
}

// 1. Workbook XML to map sheet names to r:id -> sheet file
$wbXml = simplexml_load_string($zip->getFromName('xl/workbook.xml'));
$relsXml = simplexml_load_string($zip->getFromName('xl/_rels/workbook.xml.rels'));

$rels = [];
foreach ($relsXml->Relationship as $r) {
    $rels[(string)$r['Id']] = (string)$r['Target'];
}

$sheetFiles = [];
foreach ($wbXml->sheets->sheet as $s) {
    $name = (string)$s['name'];
    $rId = (string)$s->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
    $target = $rels[$rId] ?? '';
    if (!str_starts_with($target, '/')) {
        $target = 'xl/' . $target;
    } else {
        $target = ltrim($target, '/');
    }
    $sheetFiles[$name] = $target;
    echo "Sheet '$name' -> $target\n";
}

// Shared strings
$sst = [];
if ($sstContent = $zip->getFromName('xl/sharedStrings.xml')) {
    $sstXml = simplexml_load_string($sstContent);
    foreach ($sstXml->si as $si) {
        if (isset($si->t)) {
            $sst[] = (string)$si->t;
        } elseif (isset($si->r)) {
            $t = '';
            foreach ($si->r as $r) $t .= (string)$r->t;
            $sst[] = $t;
        } else {
            $sst[] = '';
        }
    }
}
echo "Shared strings: " . count($sst) . "\n";

// Function to dump sheet with formulas and values
function dumpSheet($zip, $filePath, $sst, $maxRows = 80) {
    $xml = simplexml_load_string($zip->getFromName($filePath));
    $rowCount = 0;
    foreach ($xml->sheetData->row as $row) {
        $rNum = (int)$row['r'];
        $cells = [];
        foreach ($row->c as $c) {
            $ref = (string)$c['r'];
            $type = (string)$c['t'];
            $formula = isset($c->f) ? '=' . (string)$c->f : null;
            $val = isset($c->v) ? (string)$c->v : '';
            if ($type === 's' && $val !== '' && isset($sst[(int)$val])) {
                $val = $sst[(int)$val];
            }
            $repr = $ref . ': ' . ($formula ? "[$formula] " : '') . $val;
            $cells[] = $repr;
        }
        if ($cells) {
            echo "Row $rNum | " . implode(' | ', $cells) . "\n";
            $rowCount++;
            if ($rowCount >= $maxRows) {
                echo "... (truncated at $maxRows rows)\n";
                break;
            }
        }
    }
}

echo "\n======================================================\n";
echo "=== DUMP SHEET: B3.LGD-Expected Recoveries ===\n";
echo "======================================================\n";
dumpSheet($zip, $sheetFiles['B3.LGD-Expected Recoveries'], $sst, 100);

echo "\n======================================================\n";
echo "=== DUMP SHEET: B4.LGD-Collateral Shortfall ===\n";
echo "======================================================\n";
dumpSheet($zip, $sheetFiles['B4.LGD-Collateral Shortfall'], $sst, 100);

$zip->close();
