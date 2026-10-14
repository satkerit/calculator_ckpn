<?php

namespace App\Http\Controllers\Export;

use App\Http\Controllers\Controller;
use App\Models\AssessmentPeriod;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Export XLSX untuk 8 modul CKPN Calculation + 1 backtest bundle.
 *
 * Setiap modul ekspor 1 sheet berisi: parameter input, data input, output.
 * Backtest bundle = 1 file XLSX multi-sheet berisi snapshot seluruh modul
 * untuk validasi kesesuaian sistem vs export (offline backtesting).
 */
class CalculationExportController extends Controller
{
    public const MODULES = [
        'config'           => ['sheet' => '01_Konfigurasi_CKPN',      'table' => 'stage_configs'],
        'classification'    => ['sheet' => '02_Klasifikasi_Segmentasi','table' => 'stage_classifications'],
        'pd-netflow'        => ['sheet' => '03_PD_NetFlow',             'table' => 'stage_pd_results'],
        'pd-migration'      => ['sheet' => '04_PD_Migration',           'table' => 'stage_pd_results'],
        'lgd-er'            => ['sheet' => '05_LGD_Expected_Recovery',  'table' => 'stage_lgd_results'],
        'lgd-cs'            => ['sheet' => '06_LGD_Collateral_Shortfall','table' => 'stage_lgd_results'],
        'detail-pd-ead'     => ['sheet' => '07_Detail_PD_EAD',          'table' => null],
        'detail-ckpn'       => ['sheet' => '08_Detail_CKPN',            'table' => null],
    ];

    public function module(string $module, ?AssessmentPeriod $assessmentPeriod): Response
    {
        abort_unless(array_key_exists($module, self::MODULES), 404, 'Modul tidak dikenal.');
        abort_unless($assessmentPeriod, 404, 'Assessment period tidak ditemukan.');

        // PD Migration: multi-sheet (00_Index + 1 sheet per grup). Layout sheet
        // per grup meniru drill-down view: ringkasan + matriks 5×7 + exposure.
        if ($module === 'pd-migration') {
            return $this->pdMigrationMultiSheet($assessmentPeriod);
        }

        return $this->moduleGeneric($module, $assessmentPeriod);
    }

    /**
     * Export seluruh detail matriks PD Migration dalam 1 file XLSX, dengan
     * layout tabel yang mengikuti kertas kerja B2.PD-Migration: untuk setiap
     * grup ditampilkan ringkasan, lalu per triwulan 2 tabel (Pergerakan
     * Outstanding dalam rupiah & Rate/PD dalam %).
     */
    public function pdMigrationMatrixDetail(AssessmentPeriod $assessmentPeriod): Response
    {
        $rows = DB::table('stage_pd_results')
            ->where('assessment_period_id', $assessmentPeriod->id)
            ->where('method', 'MIGRATION')
            ->orderBy('pd_calculated', 'desc')
            ->get();

        abort_if($rows->isEmpty(), 422, 'Belum ada hasil PD Migration pada periode ini. Jalankan perhitungan PD Migration terlebih dahulu.');

        $headerStyle = (new Style())->setFontBold()->setFontColor('FFFFFF')->setBackgroundColor('4F81BD');
        $titleStyle  = (new Style())->setFontBold()->setBackgroundColor('DCE6F1');
        $subStyle    = (new Style())->setFontBold()->setBackgroundColor('D9E1F2');
        $bold        = (new Style())->setFontBold();

        $writer = $this->makeWriter();
        $writer->getCurrentSheet()->setName('PD_Migration_Matrix');

        // --- Header meta ---
        $writer->addRow(Row::fromValues(['Detail Matriks PD Migration — Layout Kertas Kerja B2.PD-Migration'])->setStyle($titleStyle));
        $writer->addRow(Row::fromValues(['Assessment Period', $assessmentPeriod->id]));
        $writer->addRow(Row::fromValues(['Assessment Date', $assessmentPeriod->assessment_date?->format('Y-m-d') ?? '-']));
        $writer->addRow(Row::fromValues(['Jumlah Grup', $rows->count()]));
        $writer->addRow(Row::fromValues(['Tanggal Export', now()->format('Y-m-d H:i:s')]));
        $writer->addRow(Row::fromValues(['']));

        $destLabels = [1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5', 6 => 'WO(Hapus Buku)', 7 => 'Pembayaran'];

        foreach ($rows as $r) {
            $d = json_decode($r->detail ?? '{}', true) ?: [];
            $matrices = $d['matrices'] ?? [];

            $writer->addRow(Row::fromValues(['GROUP: '.$r->group_key])->setStyle($titleStyle));
            $writer->addRow(Row::fromValues(['PD Grup (%)', round((float) $r->pd_calculated * 100, 4)]));
            $writer->addRow(Row::fromValues(['Jumlah Matriks Triwulan', count($matrices)]));
            $writer->addRow(Row::fromValues(['']));

            foreach ($matrices as $mi => $s) {
                $startLabel = $s['t_start_label'] ?? ($s['t_start_ym'] ?? '');
                $endLabel   = $s['t_end_label'] ?? ($s['t_end_ym'] ?? '');
                $writer->addRow(Row::fromValues(['Matriks '.($mi + 1).' · '.($s['quarter'] ?? 'Q').' ('.$startLabel.' → '.$endLabel.')'])->setStyle($subStyle));

                // Tabel 1: Pergerakan outstanding (rupiah), layout kertas kerja.
                $writer->addRow(Row::fromValues(['Pergerakan Outstanding (Rp) — Saldo awal '.$startLabel.' → posisi '.$endLabel])->setStyle($subStyle));
                $writer->addRow(Row::fromValues(array_merge(
                    ['Kualitas', 'Saldo awal'."\n".$startLabel],
                    array_values($destLabels),
                    ['TOTAL']
                ))->setStyle($headerStyle));
                for ($src = 1; $src <= 5; $src++) {
                    $row = [$src, (float) ($s['saldo_awal'][$src] ?? 0)];
                    for ($t = 1; $t <= 7; $t++) {
                        $row[] = (float) ($s['matrix'][$src][$t] ?? 0);
                    }
                    $row[] = (float) ($s['row_total'][$src] ?? 0);
                    $writer->addRow(Row::fromValues($row));
                }
                $totRow = ['', (float) array_sum($s['saldo_awal'] ?? [])];
                for ($t = 1; $t <= 7; $t++) {
                    $totRow[] = null;
                }
                $totRow[] = (float) ($s['grand_total'] ?? 0);
                $writer->addRow(Row::fromValues($totRow)->setStyle($bold));

                // Tabel 2: Rate / PD (%) — proporsi terhadap saldo awal baris.
                $writer->addRow(Row::fromValues(['Rate / PD (%) — proporsi terhadap saldo awal baris '.$startLabel.' → '.$endLabel])->setStyle($subStyle));
                $writer->addRow(Row::fromValues(array_merge(
                    ['Kualitas', 'Saldo awal'."\n".$startLabel],
                    array_values($destLabels),
                    ['TOTAL (PD)']
                ))->setStyle($headerStyle));
                $colTotals = $s['total_to'] ?? [];
                $grandRate = array_sum($colTotals) ?: 0.0;
                $grandBase = (float) array_sum($s['saldo_awal'] ?? []);
                $weightedPd = $grandBase > 0
                    ? array_sum(array_map(fn ($x) => (float) ($s['pd'][$x] ?? 0) * (float) ($s['saldo_awal'][$x] ?? 0), [1, 2, 3, 4, 5])) / $grandBase
                    : 0.0;
                for ($src = 1; $src <= 5; $src++) {
                    // Kolom "Saldo awal" pada tabel rate = rupiah (sama spt kertas kerja).
                    $row = [$src, (float) ($s['saldo_awal'][$src] ?? 0)];
                    for ($t = 1; $t <= 7; $t++) {
                        $row[] = round((float) ($s['rate'][$src][$t] ?? 0) * 100, 2);
                    }
                    $row[] = round((float) ($s['pd'][$src] ?? 0) * 100, 2);
                    $writer->addRow(Row::fromValues($row));
                }
                $rateTotRow = ['', $grandBase];
                for ($t = 1; $t <= 7; $t++) {
                    $rateTotRow[] = $grandRate > 0 ? round(((float) ($colTotals[$t] ?? 0) / $grandRate) * 100, 2) : 0.0;
                }
                $rateTotRow[] = round($weightedPd * 100, 2);
                $writer->addRow(Row::fromValues($rateTotRow)->setStyle($bold));
                $writer->addRow(Row::fromValues(['']));
            }

            $writer->addRow(Row::fromValues(['']));
        }

        return $this->respond($writer, sprintf(
            'pd_migration_matrix_detail_%s_%s.xlsx',
            $assessmentPeriod->assessment_date?->format('Ym') ?? 'unknown',
            now()->format('Ymd_His'),
        ));
    }

    /**
     * Jalur export generik (single sheet) untuk modul selain PD Migration.
     */
    private function moduleGeneric(string $module, AssessmentPeriod $assessmentPeriod): Response
    {

        $writer = $this->makeWriter();
        $writer->getCurrentSheet()->setName(self::MODULES[$module]['sheet']);

        $this->writeMetaHeader($writer, $assessmentPeriod, $module);
        $this->writeModuleContent($writer, $module, $assessmentPeriod);

        return $this->respond($writer, sprintf(
            'ckpn_%s_%s_%s.xlsx',
            $module,
            $assessmentPeriod->assessment_date?->format('Ym') ?? 'unknown',
            now()->format('Ymd_His'),
        ));
    }

    /**
     * Module export dengan periode default (terbaru).
     */
    public function moduleByName(string $module): Response
    {
        $period = AssessmentPeriod::orderByDesc('assessment_date')->first();
        abort_if(! $period, 404, 'Belum ada periode assessment.');

        return $this->module($module, $period);
    }

    /**
     * Bundle semua modul jadi 1 file XLSX multi-sheet.
     */
    public function backtestBundle(AssessmentPeriod $assessmentPeriod): Response
    {
        $writer = $this->makeWriter();
        $writer->getCurrentSheet()->setName('00_Index');

        $this->writeBundleIndex($writer, $assessmentPeriod);

        foreach (self::MODULES as $key => $meta) {
            $writer->addNewSheetAndMakeItCurrent()->setName($meta['sheet']);
            $this->writeMetaHeader($writer, $assessmentPeriod, $key);
            $this->writeModuleContent($writer, $key, $assessmentPeriod);
        }

        return $this->respond($writer, sprintf(
            'backtest_bundle_%s_%s.xlsx',
            $assessmentPeriod->assessment_date?->format('Ym') ?? 'unknown',
            substr(hash('sha256', $assessmentPeriod->id . '|' . now()->format('U')), 0, 8),
        ));
    }

    /**
     * Backtest bundle untuk periode terbaru.
     */
    public function backtestBundleByName(): Response
    {
        $period = AssessmentPeriod::orderByDesc('assessment_date')->first();
        abort_if(! $period, 404, 'Belum ada periode assessment.');

        return $this->backtestBundle($period);
    }

    /**
     * Export PD Net Flow Detail: 1 sheet per grup, 3 tabel identik dengan view
     * (Tabel 1 Min. Bucket, Tabel 2 Perpindahan Bucket, Tabel 3 Compound Flow to Loss).
     * Filter: seluruh grup (?), atau satu kombinasi segmentasi (?group_key=OFFICE|AKAD|USAGE).
     */
    public function pdNetflowBacktest(AssessmentPeriod $assessmentPeriod, Request $request): Response
    {
        $groupKeyFilter = trim((string) $request->query('group_key', ''));

        // Ambil semua grup yang sudah dihitung pada periode ini.
        $pdRows = DB::table('stage_pd_results')
            ->where('assessment_period_id', $assessmentPeriod->id)
            ->where('method', 'NET_FLOW')
            ->orderBy('group_key')
            ->get()
            ->keyBy('group_key');

        abort_if($pdRows->isEmpty(), 422, 'Belum ada hasil PD Net Flow pada periode ini. Jalankan perhitungan PD terlebih dahulu.');

        if ($groupKeyFilter !== '') {
            abort_unless($pdRows->has($groupKeyFilter), 422, "Grup {$groupKeyFilter} tidak ditemukan pada hasil PD Net Flow periode ini.");
            $pdRows = $pdRows->only([$groupKeyFilter]);
        }

        /** @var \App\Services\PdNetflow\PdNetflowService $service */
        $service = app(\App\Services\PdNetflow\PdNetflowService::class);
        /** @var \App\Services\PdNetflow\PdNetflowCalculator $calculator */
        $calculator = app(\App\Services\PdNetflow\PdNetflowCalculator::class);

        $writer = $this->makeWriter();

        // Sheet 00_Info
        $writer->getCurrentSheet()->setName('00_Info');
        foreach ([
            ['Modul',              'PD Net Flow Detail'],
            ['Assessment Period',  $assessmentPeriod->id],
            ['Assessment Date',    $assessmentPeriod->assessment_date?->format('Y-m-d') ?? '-'],
            ['Scope',              $groupKeyFilter !== '' ? "Grup: {$groupKeyFilter}" : 'Seluruh grup'],
            ['Jumlah Grup',        $pdRows->count()],
            ['Tanggal Export',     now()->format('Y-m-d H:i:s')],
        ] as $r) {
            $writer->addRow(Row::fromValues($r));
        }

        // Header style: bold white on blue
        $headerStyle = (new Style())
            ->setFontBold()
            ->setFontColor('FFFFFF')
            ->setBackgroundColor('1F4E78');
        
        // Table title style: bold light gray bg
        $tableTitleStyle = (new Style())
            ->setFontBold()
            ->setBackgroundColor('D9E1F2');
        
        // Satu sheet per grup, identik dengan tampilan view.
        foreach ($pdRows as $gk => $pdResult) {
            // Pastikan data snapshot kalkulasi ada (seperti di view controller)
            $hasData = DB::table('pd_netflow_bucket_snapshots')
                ->where('assessment_period_id', $assessmentPeriod->id)
                ->where('group_key', $gk)
                ->exists();

            if (!$hasData) {
                $calculator->calculate($assessmentPeriod->id, $gk);
            }

            $data = $service->loadGroupDetail($assessmentPeriod->id, $gk);

            $sheetLabel = str_replace(['|', '/'], '-', $gk);
            $sheetName  = strlen($sheetLabel) > 31 ? substr($sheetLabel, 0, 31) : $sheetLabel;
            $writer->addNewSheetAndMakeItCurrent()->setName($sheetName);

            // --- Header grup ---
            $writer->addRow(Row::fromValues(["Group Segmentasi: {$gk}"])->setStyle($tableTitleStyle));
            $writer->addRow(Row::fromValues([
                'PD Final (%)',
                number_format((float) $pdResult->pd_calculated * 100, 2, '.', ''),
            ]));
            $writer->addRow(Row::fromValues([
                'Periode Analisis (bulan)',
                (int) $pdResult->periods_analyzed,
            ]));
            $writer->addRow(Row::fromValues(['']));

            // --- Tabel 1: Min. Bucket – Outstanding Balance per Bucket per Periode ---
            $writer->addRow(Row::fromValues(['Tabel 1: Min. Bucket'])->setStyle($tableTitleStyle));
            $writer->addRow(Row::fromValues(['Outstanding Balance per Bucket per Periode (Rupiah)']));

            $periods = $data['periods'];
            $periodLabels = array_map(
                fn ($p) => \Carbon\Carbon::parse($p)->format('M-y'),
                $periods
            );
            $writer->addRow(Row::fromValues(array_merge(['No. Bucket', 'Hari Tunggakan'], $periodLabels))->setStyle($headerStyle));

            foreach ($data['buckets'] as $bucket) {
                $row = [$bucket['bucket_no'], $bucket['hari_tunggakan']];
                foreach ($periods as $p) {
                    $row[] = (float) ($bucket['periods'][$p] ?? 0);
                }
                $writer->addRow(Row::fromValues($row));
            }

            $writer->addRow(Row::fromValues(['']));

            // --- Tabel 2: Perpindahan Bucket – Transition Rate ---
            $writer->addRow(Row::fromValues(['Tabel 2: Perpindahan Bucket'])->setStyle($tableTitleStyle));
            $writer->addRow(Row::fromValues(['Transition Rate antar Bucket (Persentase 0-100%)']));

            $transPeriods = $data['transition_periods'];
            $transLabels  = array_map(fn ($p) => \Carbon\Carbon::parse($p)->format('M-y'), $transPeriods);
            $writer->addRow(Row::fromValues(array_merge(['Perpindahan', 'Flow Count (avg)'], $transLabels))->setStyle($headerStyle));

            foreach ($data['transitions'] as $transition) {
                $counts   = array_values($transition['flow_counts'] ?? []);
                $avgCount = !empty($counts) ? (int) round(array_sum($counts) / count($counts)) : 0;
                $row = [$transition['label'], $avgCount > 0 ? $avgCount : '-'];
                foreach ($transPeriods as $p) {
                    $rate = ($transition['periods'][$p] ?? 0) * 100;
                    $row[] = round((float) $rate, 2);
                }
                $writer->addRow(Row::fromValues($row));
            }

            $writer->addRow(Row::fromValues(['']));

            // --- Tabel 3: Compound Flow to Loss – PD Compound ---
            $writer->addRow(Row::fromValues(['Tabel 3: Compound Flow to Loss'])->setStyle($tableTitleStyle));
            $writer->addRow(Row::fromValues(['PD Compound per Source Bucket (Persentase 0-100%)']));

            $compoundPeriods = $data['compound_periods'] ?? $data['transition_periods'];
            $compoundLabels  = array_map(fn ($p) => \Carbon\Carbon::parse($p)->format('M-y'), $compoundPeriods);
            $writer->addRow(Row::fromValues(array_merge(['Bucket', 'Label'], $compoundLabels, ['PD Rate (%)']))->setStyle($headerStyle));

            foreach ($data['compounds'] as $compound) {
                $row = [$compound['source_bucket'], $compound['label']];
                foreach ($compoundPeriods as $p) {
                    $row[] = round((float) ($compound['periods'][$p] ?? 0) * 100, 2);
                }
                $row[] = round((float) $compound['pd_rate'] * 100, 2);
                $writer->addRow(Row::fromValues($row));
            }

            $writer->addRow(Row::fromValues(['']));
        }

        return $this->respond($writer, sprintf(
            'pd_netflow_detail_%s%s_%s.xlsx',
            $assessmentPeriod->assessment_date?->format('Ym') ?? 'unknown',
            $groupKeyFilter !== '' ? '_' . str_replace(['|', '/'], '-', $groupKeyFilter) : '',
            now()->format('Ymd_His'),
        ));
    }

    private array $currentFile = [];

    private function makeWriter(): Writer
    {
        $tmp = tempnam(sys_get_temp_dir(), 'ckpn_xlsx_');
        $writer = new Writer();
        $writer->openToFile($tmp);
        $this->currentFile = ['path' => $tmp, 'filename' => ''];

        return $writer;
    }

    private function writeMetaHeader(Writer $writer, AssessmentPeriod $period, string $module): void
    {
        $rows = [
            ['Modul', self::MODULES[$module]['sheet']],
            ['Assessment Period ID', $period->id],
            ['Assessment Date', $period->assessment_date?->format('Y-m-d') ?? '-'],
            ['Periode Snapshot', $period->assessment_date?->format('Ym') ?? '-'],
            ['Tanggal Export', now()->format('Y-m-d H:i:s')],
            ['SHA256 (period+date)', substr(hash('sha256', $period->id . '|' . $module . '|' . now()->format('Y-m-d')), 0, 16)],
        ];
        foreach ($rows as $r) {
            $writer->addRow(Row::fromValues($r));
        }
        $writer->addRow(Row::fromValues(['']));
    }

    private function writeModuleContent(Writer $writer, string $module, AssessmentPeriod $period): void
    {
        match ($module) {
            'config'         => $this->writeConfig($writer, $period),
            'classification' => $this->writeClassifications($writer, $period),
            'pd-netflow'     => $this->writePdResults($writer, $period, 'NET_FLOW'),
            'pd-migration'   => $this->writePdMigration($writer, $period),
            'lgd-er'         => $this->writeLgd($writer, $period, 'ER'),
            'lgd-cs'         => $this->writeLgd($writer, $period, 'CS'),
            'detail-pd-ead'  => $this->writeDetailPdEad($writer, $period),
            'detail-ckpn'    => $this->writeDetailCkpn($writer, $period),
            default          => null,
        };
    }

    private function writeConfig(Writer $writer, AssessmentPeriod $period): void
    {
        $writer->addRow(Row::fromValues(['Section', 'Key', 'Value']));
        $row = DB::table('stage_configs')->where('assessment_period_id', $period->id)->first();
        $config = (array) ($row ?? []);
        foreach ($config as $key => $value) {
            $writer->addRow(Row::fromValues(['config', $key, is_scalar($value) ? (string) $value : json_encode($value)]));
        }
    }

    private function writeClassifications(Writer $writer, AssessmentPeriod $period): void
    {
        $writer->addRow(Row::fromValues([
            'Nomor Akad', 'Klasifikasi', 'Kode Kantor', 'Kode Akad', 'Jenis Penggunaan',
            'EAD (Rp)', 'Alasan', 'Aturan Teraplikasi', 'Tanggal Dibuat',
        ]));
        
        // Direct query tanpa chunked untuk debug
        $records = DB::table('stage_classifications')
            ->where('assessment_period_id', $period->id)
            ->get();
            
        if ($records->isEmpty()) {
            $writer->addRow(Row::fromValues([
                'Tidak ada data klasifikasi untuk periode ini (ID: ' . $period->id . ')',
                '', '', '', '', '', '', '', ''
            ]));
            return;
        }
        
        foreach ($records as $r) {
            $writer->addRow(Row::fromValues([
                $r->loan_number,
                $r->classification,
                $r->office_code,
                $r->akad_code,
                $r->usage_code,
                (float) $r->ead_amount,
                $r->reason ?? '',
                $r->applied_rule ?? '',
                $r->created_at ?? '',
            ]));
        }
    }

    private function writePdResults(Writer $writer, AssessmentPeriod $period, string $method): void
    {
        $writer->addRow(Row::fromValues([
            'group_key', 'office_code', 'akad_code', 'usage_code',
            'pd_calculated', 'method', 'periods_analyzed', 'detail',
        ]));
        DB::table('stage_pd_results')
            ->where('assessment_period_id', $period->id)
            ->where('method', $method)
            ->orderBy('group_key')
            ->chunk(200, function ($chunk) use ($writer) {
                foreach ($chunk as $r) {
                    $writer->addRow(Row::fromValues([
                        $r->group_key, $r->office_code ?? '', $r->akad_code ?? '', $r->usage_code ?? '',
                        (float) $r->pd_calculated, $r->method, $r->periods_analyzed, $r->detail,
                    ]));
                }
            });
    }

    /**
     * Export PD Migration multi-sheet: sheet pertama = 00_Index (info + ringkasan
     * seluruh grup), sheet berikutnya = 1 per grup berisi ringkasan grup,
     * matriks migrasi 5×7, exposure posisi assessment per rating, dan
     * formula cascade backward (salin-tempel ke Excel).
     */
    private function pdMigrationMultiSheet(AssessmentPeriod $assessmentPeriod): Response
    {
        $headerStyle = (new Style())->setFontBold()->setFontColor('FFFFFF')->setBackgroundColor('1F4E78');
        $titleStyle  = (new Style())->setFontBold()->setBackgroundColor('D9E1F2');
        $destLabels  = [1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5', 6 => 'Hapus Buku', 7 => 'Pembayaran'];

        $rows = DB::table('stage_pd_results')
            ->where('assessment_period_id', $assessmentPeriod->id)
            ->where('method', 'MIGRATION')
            ->orderBy('pd_calculated', 'desc')
            ->get();

        abort_if($rows->isEmpty(), 422, 'Belum ada hasil PD Migration pada periode ini. Jalankan perhitungan PD Migration terlebih dahulu.');

        $writer = $this->makeWriter();

        // --- Sheet 00_Index: meta + ringkasan seluruh grup ---
        $writer->getCurrentSheet()->setName('00_Index');
        foreach ([
            ['Modul', 'PD Migration Detail (multi-sheet)'],
            ['Assessment Period', $assessmentPeriod->id],
            ['Assessment Date', $assessmentPeriod->assessment_date?->format('Y-m-d') ?? '-'],
            ['Jumlah Grup', $rows->count()],
            ['Tanggal Export', now()->format('Y-m-d H:i:s')],
        ] as $r) {
            $writer->addRow(Row::fromValues($r));
        }
        $writer->addRow(Row::fromValues(['']));
        $writer->addRow(Row::fromValues(['Ringkasan PD per Group'])->setStyle($titleStyle));
        $writer->addRow(Row::fromValues(array_merge(
            ['#', 'Group Key', 'Sheet', 'PD Grup (%)', 'Matriks'],
            ['PD R1 (%)', 'PD R2 (%)', 'PD R3 (%)', 'PD R4 (%)', 'PD R5 (%)'],
        ))->setStyle($headerStyle));

        $decoded = [];
        // Nama sheet unik per grup (Excel maks 31 char, case-insensitive).
        $sheetNames = $this->uniqueSheetLabels($rows->pluck('group_key')->all(), ['00_Index']);
        $i = 0;
        foreach ($rows as $r) {
            $i++;
            $d = json_decode($r->detail ?? '{}', true) ?: [];
            $decoded[$r->group_key] = $d;
            $rp = $d['rating_pd'] ?? [];
            $row = [$i, $r->group_key, $sheetNames[$r->group_key], round((float) $r->pd_calculated * 100, 4), (int) $r->periods_analyzed];
            for ($k = 1; $k <= 5; $k++) {
                $row[] = isset($rp[$k]) ? round((float) $rp[$k] * 100, 4) : '';
            }
            $writer->addRow(Row::fromValues($row));
        }

        // --- 1 sheet per grup: 8 tabel matriks (4 outstanding + 4 rate) + PD + cascade ---
        foreach ($rows as $r) {
            $writer->addNewSheetAndMakeItCurrent()->setName($sheetNames[$r->group_key]);
            $this->writePdMigrationGroupDetail($writer, $r->group_key, $decoded[$r->group_key], (float) $r->pd_calculated);
        }

        return $this->respond($writer, sprintf(
            'pd_migration_detail_%s_%s.xlsx',
            $assessmentPeriod->assessment_date?->format('Ym') ?? 'unknown',
            now()->format('Ymd_His'),
        ));
    }

    /**
     * Tulis detail satu grup PD Migration: 4 tabel outstanding (Rp) +
     * 4 tabel rate/PD per triwulan, matriks gabungan, exposure, dan PD
     * per rating hasil weighted average. Dipakai oleh export multi-sheet
     * (satu sheet per grup) dan export bundle (satu sheet berisi semua grup).
     */
    private function writePdMigrationGroupDetail(Writer $writer, string $groupKey, array $d, float $pdCalculated): void
    {
        $headerStyle = (new Style())->setFontBold()->setFontColor('FFFFFF')->setBackgroundColor('1F4E78');
        $titleStyle  = (new Style())->setFontBold()->setBackgroundColor('D9E1F2');
        $destLabels  = [1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5', 6 => 'Hapus Buku', 7 => 'Pembayaran'];

        $matrices = $d['matrices'] ?? [];
        $exp = $d['rating_exposure'] ?? [];
        $rp = $d['rating_pd'] ?? [];

        $writer->addRow(Row::fromValues(["Group: {$groupKey}"])->setStyle($titleStyle));
        $writer->addRow(Row::fromValues(['PD Grup (%)', round($pdCalculated * 100, 4)]));
        $writer->addRow(Row::fromValues(['Jumlah Matriks Triwulan', count($matrices)]));
        $writer->addRow(Row::fromValues(['']));

        foreach ($matrices as $mi => $s) {
            $startLabel = $s['t_start_label'] ?? ($s['t_start_ym'] ?? '');
            $endLabel   = $s['t_end_label'] ?? ($s['t_end_ym'] ?? '');
            $mLabel = ($s['quarter'] ?? 'Q').' ('.$startLabel.'→'.$endLabel.')';
            $writer->addRow(Row::fromValues(['=== Matriks '.($mi + 1).' · '.$mLabel.' ==='])->setStyle($titleStyle));

            // Tabel 1: pergerakan outstanding (rupiah) — baris sumber 1..5.
            // Struktur kolom: Kualitas | Saldo Awal | tujuan 1..5 | Hapus Buku | Pembayaran | Total.
            $writer->addRow(Row::fromValues(['Kualitas', 'Saldo awal '.$startLabel, '', 'POSISI '.$endLabel, '', '', '', '', '', '', 'TOTAL'])->setStyle($headerStyle));
            $writer->addRow(Row::fromValues(array_merge(
                ['', ''],
                array_values($destLabels),
                ['']
            ))->setStyle($headerStyle));
            for ($src = 1; $src <= 5; $src++) {
                $row = [$src, (float) ($s['saldo_awal'][$src] ?? 0)];
                for ($t = 1; $t <= 7; $t++) {
                    $row[] = (float) ($s['matrix'][$src][$t] ?? 0);
                }
                $row[] = (float) ($s['row_total'][$src] ?? 0);
                $writer->addRow(Row::fromValues($row));
            }
            $totRow = ['', (float) array_sum($s['saldo_awal'] ?? [])];
            for ($t = 1; $t <= 7; $t++) {
                $totRow[] = null;
            }
            $totRow[] = (float) ($s['grand_total'] ?? 0);
            $writer->addRow(Row::fromValues($totRow)->setStyle($titleStyle));

            // Tabel 2: rate / PD (proporsi terhadap saldo awal baris).
            // Struktur kolom: Kualitas | Saldo Awal | rate 1..5 | Hapus Buku | Pembayaran | TOTAL (PD).
            $writer->addRow(Row::fromValues(['Kualitas', 'Saldo awal '.$startLabel, '', 'POSISI '.$endLabel, '', '', '', '', '', '', 'TOTAL'])->setStyle($headerStyle));
            $writer->addRow(Row::fromValues(array_merge(
                ['', ''],
                array_values($destLabels),
                ['']
            ))->setStyle($headerStyle));
            $colTotals = $s['total_to'] ?? [];
            $grandRate = array_sum($colTotals) ?: 0.0;
            $grandBase = (float) array_sum($s['saldo_awal'] ?? []);
            $weightedPd = $grandBase > 0
                ? array_sum(array_map(fn ($x) => (float) ($s['pd'][$x] ?? 0) * (float) ($s['saldo_awal'][$x] ?? 0), [1, 2, 3, 4, 5])) / $grandBase
                : 0.0;
            for ($src = 1; $src <= 5; $src++) {
                $row = [$src, (float) ($s['saldo_awal'][$src] ?? 0)];
                for ($t = 1; $t <= 7; $t++) {
                    $row[] = round((float) ($s['rate'][$src][$t] ?? 0), 6);
                }
                $row[] = round((float) ($s['pd'][$src] ?? 0), 6);
                $writer->addRow(Row::fromValues($row));
            }
            $rateTotRow = ['', $grandBase];
            for ($t = 1; $t <= 7; $t++) {
                $rateTotRow[] = $grandRate > 0 ? round((float) ($colTotals[$t] ?? 0) / $grandRate, 6) : 0.0;
            }
            $rateTotRow[] = round($weightedPd, 6);
            $writer->addRow(Row::fromValues($rateTotRow)->setStyle($titleStyle));
            $writer->addRow(Row::fromValues(['']));
        }

        // Matriks gabungan (rate rata-rata) + exposure.
        $writer->addRow(Row::fromValues(['Matriks Migrasi Gabungan (rate rata-rata berbobot, %)']));
        $writer->addRow(Row::fromValues(array_merge(['Rating Awal'], array_values($destLabels)))->setStyle($headerStyle));
        for ($src = 1; $src <= 5; $src++) {
            $row = ["Rating {$src}"];
            for ($t = 1; $t <= 7; $t++) {
                $row[] = round((float) ($d['matrix'][$src][$t] ?? 0) * 100, 4);
            }
            $writer->addRow(Row::fromValues($row));
        }
        $writer->addRow(Row::fromValues(['']));

        $writer->addRow(Row::fromValues(['Exposure posisi assessment (rupiah)']));
        $writer->addRow(Row::fromValues(['Rating', 'Exposure (Rp)'])->setStyle($headerStyle));
        $total = 0.0;
        for ($rr = 1; $rr <= 5; $rr++) {
            $e = (float) ($exp[$rr] ?? 0);
            $total += $e;
            $writer->addRow(Row::fromValues(["Rating {$rr}", $e]));
        }
        $writer->addRow(Row::fromValues(['Total', $total])->setStyle($titleStyle));
        $writer->addRow(Row::fromValues(['']));

        // PD per rating + weighted average lintas matriks (verifikasi saldo awal).
        $writer->addRow(Row::fromValues(['PD per rating (weighted average saldo awal lintas matriks)']));
        $writer->addRow(Row::fromValues(['Rating', 'PD (%)'])->setStyle($headerStyle));
        for ($rr = 5; $rr >= 1; $rr--) {
            $writer->addRow(Row::fromValues(["Rating {$rr}", $this->pct4($rp[$rr] ?? null)]));
        }
        $writer->addRow(Row::fromValues(['']));
        $writer->addRow(Row::fromValues(['Catatan: tiap triwulan menyediakan 1 tabel outstanding (Rp) + 1 tabel rate. PD rating matriks mengikuti cascade mundur R5→R1; PD final = Σ(saldo awal × PD rating) / Σ saldo awal.']));
        $writer->addRow(Row::fromValues(['']));
    }

    /**
     * Buat nama sheet unik per group_key. Excel membatasi 31 karakter dan
     * menganggap nama tidak case-sensitive, sehingga grup yang terpotong sama
     * atau beda hanya di kapitalisasi harus di-disambiguasi (suffix _2, _3, ...).
     *
     * @param  array<int,string>  $groupKeys
     * @param  array<int,string>  $reserved  nama sheet yang sudah dipakai (mis. '00_Index')
     * @return array<string,string>  map group_key => nama sheet unik
     */
    private function uniqueSheetLabels(array $groupKeys, array $reserved = []): array
    {
        $used = [];
        foreach ($reserved as $name) {
            $used[strtolower($name)] = true;
        }

        $labels = [];
        foreach ($groupKeys as $key) {
            $base = $this->sheetLabel($key);
            $candidate = $base;
            $n = 1;
            while (isset($used[strtolower($candidate)])) {
                $n++;
                $suffix = '_'.$n;
                // Sisakan ruang untuk suffix agar total <= 31 karakter.
                $candidate = substr($base, 0, 31 - strlen($suffix)).$suffix;
            }
            $used[strtolower($candidate)] = true;
            $labels[$key] = $candidate;
        }

        return $labels;
    }

    private function sheetLabel(string $groupKey): string
    {
        $label = str_replace(['|', '/'], '-', $groupKey);
        $label = preg_replace('/[\\\\?*:\[\]]/', '-', $label) ?? $label;
        $label = trim($label);

        return strlen($label) > 31 ? substr($label, 0, 31) : ($label !== '' ? $label : 'grup');
    }

    private function pct4($v): string|float
    {
        return $v === null || $v === '' ? '' : round((float) $v * 100, 4);
    }

    /**
     * Export PD Migration sesuai tampilan view: ringkasan PD per grup
     * (PD Grup + PD R1..R5), lalu blok matriks migrasi 5×7 (kolom 1..5 +
     * Hapus Buku + Pembayaran) dan exposure posisi assessment per rating.
     */
    private function writePdMigration(Writer $writer, AssessmentPeriod $period): void
    {
        $titleStyle  = (new Style())->setFontBold()->setBackgroundColor('D9E1F2');
        $headerStyle = (new Style())->setFontBold()->setFontColor('FFFFFF')->setBackgroundColor('1F4E78');

        $rows = DB::table('stage_pd_results')
            ->where('assessment_period_id', $period->id)
            ->where('method', 'MIGRATION')
            ->orderBy('pd_calculated', 'desc')
            ->get();

        if ($rows->isEmpty()) {
            $writer->addRow(Row::fromValues(['Belum ada hasil PD Migration untuk periode ini.']));

            return;
        }

        // --- Ringkasan per grup (sesuai tabel utama view) ---
        $writer->addRow(Row::fromValues(['Ringkasan PD per Group'])->setStyle($titleStyle));
        $writer->addRow(Row::fromValues(array_merge(
            ['Group Key', 'PD Grup (%)', 'Matriks'],
            ['PD R1 (%)', 'PD R2 (%)', 'PD R3 (%)', 'PD R4 (%)', 'PD R5 (%)'],
        ))->setStyle($headerStyle));

        $decoded = [];
        foreach ($rows as $r) {
            $d = json_decode($r->detail ?? '{}', true) ?: [];
            $decoded[$r->group_key] = $d;
            $rp = $d['rating_pd'] ?? [];
            $row = [$r->group_key, round((float) $r->pd_calculated * 100, 4), (int) $r->periods_analyzed];
            for ($i = 1; $i <= 5; $i++) {
                $row[] = isset($rp[$i]) ? round((float) $rp[$i] * 100, 4) : '';
            }
            $writer->addRow(Row::fromValues($row));
        }
        $writer->addRow(Row::fromValues(['']));

        // --- Blok detail per grup (4 matriks outstanding + 4 rate) ---
        foreach ($rows as $r) {
            $this->writePdMigrationGroupDetail($writer, $r->group_key, $decoded[$r->group_key], (float) $r->pd_calculated);
        }
    }

    private function writeLgd(Writer $writer, AssessmentPeriod $period, string $method): void
    {
        $writer->addRow(Row::fromValues([
            'group_key', 'office_code', 'akad_code', 'usage_code', 'method',
            'recovery_rate', 'total_write_off', 'total_recovery',
            'total_exposure_wo', 'total_shortfall', 'collateral_coverage',
            'lgd_calculated', 'detail',
        ]));
        DB::table('stage_lgd_results')
            ->where('assessment_period_id', $period->id)
            ->where('method', $method)
            ->orderBy('group_key')
            ->chunk(200, function ($chunk) use ($writer) {
                foreach ($chunk as $r) {
                    $writer->addRow(Row::fromValues([
                        $r->group_key, $r->office_code ?? '', $r->akad_code ?? '', $r->usage_code ?? '', $r->method,
                        (float) ($r->recovery_rate ?? 0),
                        (float) ($r->total_write_off ?? 0),
                        (float) ($r->total_recovery ?? 0),
                        (float) ($r->total_exposure_wo ?? 0),
                        (float) ($r->total_shortfall ?? 0),
                        (float) ($r->collateral_coverage ?? 0),
                        (float) $r->lgd_calculated,
                        $r->detail,
                    ]));
                }
            });
    }

    private function writeDetailPdEad(Writer $writer, AssessmentPeriod $period): void
    {
        // EAD per bucket.
        $writer->addRow(Row::fromValues(['== EAD per Bucket ==']));
        $writer->addRow(Row::fromValues(['bucket_no', 'total_ead', 'total_members']));
        DB::table('stage_ead_buckets')
            ->where('assessment_period_id', $period->id)
            ->selectRaw('bucket_no, SUM(ead) AS total_ead, SUM(member_count) AS total_members')
            ->groupBy('bucket_no')
            ->orderBy('bucket_no')
            ->chunk(200, function ($chunk) use ($writer) {
                foreach ($chunk as $r) {
                    $writer->addRow(Row::fromValues([
                        $r->bucket_no, (float) $r->total_ead, (int) $r->total_members,
                    ]));
                }
            });

        $writer->addRow(Row::fromValues(['']));

        // Per loan classification + pd.
        $writer->addRow(Row::fromValues(['== Per-Loan Detail ==']));
        $writer->addRow(Row::fromValues([
            'loan_number', 'classification', 'office_code', 'akad_code', 'usage_code',
            'ead_amount', 'pd_calculated', 'pd_method',
        ]));
        DB::table('stage_classifications as sc')
            ->leftJoin('stage_pd_results as pd', function ($j) use ($period) {
                $j->on(DB::raw("CONCAT_WS('|', sc.office_code, sc.akad_code, sc.usage_code)"), '=', 'pd.group_key')
                  ->where('pd.assessment_period_id', '=', $period->id);
            })
            ->where('sc.assessment_period_id', $period->id)
            ->orderBy('sc.classification')
            ->orderBy('sc.ead_amount', 'desc')
            ->select('sc.loan_number', 'sc.classification', 'sc.office_code', 'sc.akad_code', 'sc.usage_code',
                'sc.ead_amount', 'pd.pd_calculated', 'pd.method as pd_method')
            ->chunk(200, function ($chunk) use ($writer) {
                foreach ($chunk as $r) {
                    $writer->addRow(Row::fromValues([
                        $r->loan_number, $r->classification,
                        $r->office_code, $r->akad_code, $r->usage_code,
                        (float) $r->ead_amount,
                        $r->pd_calculated !== null ? (float) $r->pd_calculated : '',
                        $r->pd_method ?? '',
                    ]));
                }
            });
    }

    private function writeDetailCkpn(Writer $writer, AssessmentPeriod $period): void
    {
        // Individual
        $writer->addRow(Row::fromValues(['== CKPN Individual ==']));
        $writer->addRow(Row::fromValues([
            'loan_number', 'nilai_tercatat', 'estimated_recovery', 'ckpn_individual', 'recovery_method',
        ]));
        DB::table('stage_individual_ckpn')
            ->where('assessment_period_id', $period->id)
            ->orderBy('ckpn_individual', 'desc')
            ->chunk(200, function ($chunk) use ($writer) {
                foreach ($chunk as $r) {
                    $writer->addRow(Row::fromValues([
                        $r->loan_number, (float) $r->nilai_tercatat,
                        (float) $r->estimated_recovery, (float) $r->ckpn_individual,
                        $r->recovery_method,
                    ]));
                }
            });

        $writer->addRow(Row::fromValues(['']));

        // Collective
        $writer->addRow(Row::fromValues(['== CKPN Kolektif ==']));
        $writer->addRow(Row::fromValues([
            'group_key', 'office_code', 'akad_code', 'usage_code',
            'total_ead', 'pd', 'lgd', 'lgd_method', 'ckpn_kolektif', 'member_count',
        ]));
        DB::table('stage_collective_ckpn')
            ->where('assessment_period_id', $period->id)
            ->orderBy('ckpn_kolektif', 'desc')
            ->chunk(200, function ($chunk) use ($writer) {
                foreach ($chunk as $r) {
                    $writer->addRow(Row::fromValues([
                        $r->group_key, $r->office_code, $r->akad_code, $r->usage_code,
                        (float) $r->total_ead, (float) $r->pd, (float) $r->lgd,
                        $r->lgd_method, (float) $r->ckpn_kolektif, (int) $r->member_count,
                    ]));
                }
            });

        $writer->addRow(Row::fromValues(['']));
        $writer->addRow(Row::fromValues(['== Summary ==']));

        $ind = (float) DB::table('stage_individual_ckpn')->where('assessment_period_id', $period->id)->sum('ckpn_individual');
        $col = (float) DB::table('stage_collective_ckpn')->where('assessment_period_id', $period->id)->sum('ckpn_kolektif');
        $writer->addRow(Row::fromValues(['Total CKPN Individual', $ind]));
        $writer->addRow(Row::fromValues(['Total CKPN Kolektif', $col]));
        $writer->addRow(Row::fromValues(['Total CKPN', round($ind + $col, 2)]));
    }

    private function writeBundleIndex(Writer $writer, AssessmentPeriod $period): void
    {
        $writer->addRow(Row::fromValues(['Backtest Bundle Index']));
        $writer->addRow(Row::fromValues(['Period', $period->assessment_date?->format('Y-m-d') ?? '-']));
        $writer->addRow(Row::fromValues(['Tanggal Export', now()->format('Y-m-d H:i:s')]));
        $writer->addRow(Row::fromValues(['']));
        $writer->addRow(Row::fromValues(['#', 'Modul', 'Sheet']));
        $i = 0;
        foreach (self::MODULES as $key => $meta) {
            $writer->addRow(Row::fromValues([++$i, $key, $meta['sheet']]));
        }
    }

    private function respond(Writer $writer, string $filename): Response
    {
        $writer->close();
        $path = $this->currentFile['path'] ?? null;

        if (! $path || ! is_file($path)) {
            abort(500, 'Export gagal menulis file.');
        }

        $contents = file_get_contents($path);
        @unlink($path);
        $this->currentFile = [];

        return response($contents, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Content-Length'      => (string) strlen($contents),
        ]);
    }
}