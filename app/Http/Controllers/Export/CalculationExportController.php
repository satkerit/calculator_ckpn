<?php

namespace App\Http\Controllers\Export;

use App\Http\Controllers\Controller;
use App\Models\AssessmentPeriod;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use OpenSpout\Common\Entity\Row;
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
    public function backtestBundle(AssessmentPeriod $period): Response
    {
        $writer = $this->makeWriter();
        $writer->getCurrentSheet()->setName('00_Index');

        $this->writeBundleIndex($writer, $period);

        foreach (self::MODULES as $key => $meta) {
            $writer->addNewSheetAndMakeItCurrent()->setName($meta['sheet']);
            $this->writeMetaHeader($writer, $period, $key);
            $this->writeModuleContent($writer, $key, $period);
        }

        return $this->respond($writer, sprintf(
            'backtest_bundle_%s_%s.xlsx',
            $period->assessment_date?->format('Ym') ?? 'unknown',
            substr(hash('sha256', $period->id . '|' . now()->format('U')), 0, 8),
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
            'pd-migration'   => $this->writePdResults($writer, $period, 'MIGRATION'),
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