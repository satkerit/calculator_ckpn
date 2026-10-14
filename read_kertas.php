<?php
require 'vendor/autoload.php';
$wb = PhpOffice\PhpSpreadsheet\IOFactory::load('d:\laragon\www\calculator_ckpn\docs\Kertas kerja.xlsx');
$ws = $wb->getActiveSheet();
echo $ws->getTitle() . ' | ' . $ws->getHighestRow() . 'x' . $ws->getHighestColumn() . PHP_EOL;
for ($row = 1; $row <= min($ws->getHighestRow(), 60); $row++) {
    $cells = [];
    for ($col = 1; $col <= min($ws->getHighestColumn(), 26); $col++) {
        $colName = PHPOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
        $val = $ws->getCell($colName . $row)->getValue();
        if ($val !== null) $cells[] = $col . ':' . $val;
    }
    if (!empty($cells)) echo implode(' | ', $cells) . PHP_EOL;
}
