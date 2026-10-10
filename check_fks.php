<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$fks = Illuminate\Support\Facades\DB::select("SELECT TABLE_NAME, CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE REFERENCED_TABLE_NAME = 'financing' AND TABLE_SCHEMA = DATABASE()");
foreach ($fks as $fk) {
    echo $fk->TABLE_NAME . '.' . $fk->CONSTRAINT_NAME . PHP_EOL;
}
