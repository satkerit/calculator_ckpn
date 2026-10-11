<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Progress Cache Store
    |--------------------------------------------------------------------------
    |
    | Store cache untuk fitur progress realtime (pushProgress + endpoint
    | polling calculation.progress). Harus NON-database karena progress bisa
    | ditulis dari dalam DB::transaction(); pada store `database` baris cache
    | belum ter-commit sehingga request polling tidak dapat membacanya hingga
    | transaksi selesai (animasi baru bergerak di akhir).
    |
    */

    'progress_store' => env('CALCULATION_PROGRESS_STORE', 'file'),

];
