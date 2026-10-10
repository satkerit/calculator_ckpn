<?php
require __DIR__ . '/vendor/autoload.php';

use OpenSpout\Reader\XLSX\Reader;

$file = __DIR__ . '/docs/Kertas kerja.xlsx';
$reader = new Reader();
$reader->open($file);

$sheetNames = [];
foreach ($reader->getSheetIterator() as $sheet) {
    $sheetNames[] = $sheet->getName();
}
echo "== Sheets ==\n";
foreach ($sheetNames as $i => $n) echo "$i: $n\n";
$reader->close();
