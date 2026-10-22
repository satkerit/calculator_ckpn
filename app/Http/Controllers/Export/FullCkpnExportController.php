<?php

namespace App\Http\Controllers\Export;

use App\Http\Controllers\Calculation\BaseCalculationController;
use App\Models\AssessmentPeriod;
use App\Services\Bucket\BucketAssignmentService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Full CKPN Workbook Export Controller.
 * Menghasilkan 1 file Excel komprehensif berdesain profesional dengan multi-sheet:
 * 1. 00_Ringkasan_Eksekutif - KPI & parameter perhitungan
 * 2. 01_CKPN_Individual     - Detail per rekening individual & agunan
 * 3. 02_CKPN_Kolektif       - Detail per rekening kolektif beserta PD, LGD, & CKPN
 * 4. 03_PD_Matrix           - Detail matriks PD (NETFLOW atau MIGRATION sesuai konfigurasi)
 * 5. 04_LGD_ER              - Detail LGD Expected Recovery
 * 6. 05_LGD_CS              - Detail LGD Collateral Shortfall
 * 7. 06_LGD_Weighted        - Agregasi LGD Weighted
 * 8. 07_Snapshot_PD         - Snapshot data yang mendasari perhitungan PD
 * 9. 08_Snapshot_LGD        - Snapshot data yang mendasari perhitungan LGD
 */
class FullCkpnExportController extends BaseCalculationController
{
    private array $currentFile = [];

    // Palette & Styles (ui-styling)
    private Style $styleTitle;
    private Style $styleSection;
    private Style $styleSubSection;
    private Style $styleHeader;
    private Style $styleHeaderAccent;
    private Style $styleData;
    private Style $styleDataRight;
    private Style $styleDataCenter;
    private Style $styleTotal;
    private Style $styleTotalAccent;
    private Style $styleKpiCard;
    private Style $styleKpiVal;

    public function __construct()
    {
        $this->initStyles();
    }

    private function initStyles(): void
    {
        $thinBorder = new Border(
            new BorderPart(Border::TOP, 'D9D9D9', Border::WIDTH_THIN, Border::STYLE_SOLID),
            new BorderPart(Border::BOTTOM, 'D9D9D9', Border::WIDTH_THIN, Border::STYLE_SOLID),
            new BorderPart(Border::LEFT, 'D9D9D9', Border::WIDTH_THIN, Border::STYLE_SOLID),
            new BorderPart(Border::RIGHT, 'D9D9D9', Border::WIDTH_THIN, Border::STYLE_SOLID),
        );

        $doubleBottomBorder = new Border(
            new BorderPart(Border::TOP, '1F4E78', Border::WIDTH_THIN, Border::STYLE_SOLID),
            new BorderPart(Border::BOTTOM, '1F4E78', Border::WIDTH_MEDIUM, Border::STYLE_DOUBLE),
        );

        $this->styleTitle = (new Style())
            ->setFontBold()
            ->setFontSize(14)
            ->setFontColor('1F4E78')
            ->setBackgroundColor('EDF2F7');

        $this->styleSection = (new Style())
            ->setFontBold()
            ->setFontSize(11)
            ->setFontColor('1F4E78')
            ->setBackgroundColor('D9E1F2');

        $this->styleSubSection = (new Style())
            ->setFontBold()
            ->setFontSize(10)
            ->setFontColor('2D3748')
            ->setBackgroundColor('F0F4F8');

        $this->styleHeader = (new Style())
            ->setFontBold()
            ->setFontSize(10)
            ->setFontColor('FFFFFF')
            ->setBackgroundColor('1F4E78')
            ->setCellAlignment(CellAlignment::CENTER);

        $this->styleHeaderAccent = (new Style())
            ->setFontBold()
            ->setFontSize(10)
            ->setFontColor('FFFFFF')
            ->setBackgroundColor('2B579A')
            ->setCellAlignment(CellAlignment::CENTER);

        $this->styleData = (new Style())
            ->setFontSize(9)
            ->setBorder($thinBorder);

        $this->styleDataRight = (new Style())
            ->setFontSize(9)
            ->setCellAlignment(CellAlignment::RIGHT)
            ->setBorder($thinBorder);

        $this->styleDataCenter = (new Style())
            ->setFontSize(9)
            ->setCellAlignment(CellAlignment::CENTER)
            ->setBorder($thinBorder);

        $this->styleTotal = (new Style())
            ->setFontBold()
            ->setFontSize(10)
            ->setBackgroundColor('E9EEF4')
            ->setBorder($doubleBottomBorder);

        $this->styleTotalAccent = (new Style())
            ->setFontBold()
            ->setFontSize(10)
            ->setFontColor('1F4E78')
            ->setBackgroundColor('DCE6F1')
            ->setBorder($doubleBottomBorder);

        $this->styleKpiCard = (new Style())
            ->setFontBold()
            ->setFontSize(10)
            ->setFontColor('4A5568')
            ->setBackgroundColor('F7FAFC');

        $this->styleKpiVal = (new Style())
            ->setFontBold()
            ->setFontSize(11)
            ->setFontColor('1A365D')
            ->setBackgroundColor('EDF2F7');
    }

    public function export(?AssessmentPeriod $assessmentPeriod = null): Response
    {
        if (! $assessmentPeriod) {
            $assessmentPeriod = AssessmentPeriod::orderByDesc('assessment_date')->first();
            abort_if(! $assessmentPeriod, 404, 'Belum ada periode assessment.');
        }

        $writer = $this->makeWriter();

        // Deteksi metode PD yang aktif
        $cfg = $this->configFor($assessmentPeriod->id);
        $pdMethod = strtoupper((string) $cfg['pd_method']);
        $isMigration = $pdMethod === 'MIGRATION';

        // 1. Sheet Ringkasan Eksekutif & Parameter
        $writer->getCurrentSheet()->setName('00_Ringkasan_Eksekutif');
        $this->writeExecutiveSummary($writer, $assessmentPeriod, $cfg);

        // 2. Sheet CKPN Individual
        $writer->addNewSheetAndMakeItCurrent()->setName('01_CKPN_Individual');
        $this->writeCkpnIndividualSheet($writer, $assessmentPeriod);

        // 3. Sheet CKPN Kolektif
        $writer->addNewSheetAndMakeItCurrent()->setName('02_CKPN_Kolektif');
        $this->writeCkpnCollectiveSheet($writer, $assessmentPeriod, $isMigration);

        // 4. Sheet PD Matrix (sesuai konfigurasi aktif)
        $writer->addNewSheetAndMakeItCurrent()->setName('03_PD_Matrix');
        if ($isMigration) {
            $this->writePdMigrationSheet($writer, $assessmentPeriod);
        } else {
            $this->writePdNetflowSheet($writer, $assessmentPeriod);
        }

        // 5. Sheet LGD Expected Recovery
        $writer->addNewSheetAndMakeItCurrent()->setName('04_LGD_ER');
        $this->writeLgdErSheet($writer, $assessmentPeriod);

        // 6. Sheet LGD Collateral Shortfall
        $writer->addNewSheetAndMakeItCurrent()->setName('05_LGD_CS');
        $this->writeLgdCsSheet($writer, $assessmentPeriod);

        // 7. Sheet LGD Weighted
        $writer->addNewSheetAndMakeItCurrent()->setName('06_LGD_Weighted');
        $this->writeLgdWeightedSheet($writer, $assessmentPeriod);

        // 8. Sheet Snapshot PD
        $writer->addNewSheetAndMakeItCurrent()->setName('07_Snapshot_PD');
        $this->writeSnapshotPdSheet($writer, $assessmentPeriod, $isMigration);

        // 9. Sheet Snapshot LGD
        $writer->addNewSheetAndMakeItCurrent()->setName('08_Snapshot_LGD');
        $this->writeSnapshotLgdSheet($writer, $assessmentPeriod);

        $periodCode = $assessmentPeriod->assessment_date?->format('Ym') ?? 'unknown';
        $filename = sprintf('CKPN_Comprehensive_Audit_%s_%s.xlsx', $periodCode, now()->format('Ymd_His'));

        return $this->respond($writer, $filename);
    }

    /**
     * Sheet 00: Ringkasan Eksekutif & Parameter
     */
    private function writeExecutiveSummary(Writer $writer, AssessmentPeriod $period, array $config): void
    {
        $writer->addRow(Row::fromValues(['LAPORAN LENGKAP HASIL PERHITUNGAN CKPN BPRS (MULTI-SHEET WORKBOOK)'])->setStyle($this->styleTitle));
        $writer->addRow(Row::fromValues(['Format Kertas Kerja Audit & Validasi Manual Terintegrasi'])->setStyle($this->styleSubSection));
        $writer->addRow(Row::fromValues(['']));

        // Meta info
        $writer->addRow(Row::fromValues(['INFORMASI PERIODE & KONFIGURASI'])->setStyle($this->styleSection));
        $writer->addRow(Row::fromValues(['ID Assessment', $period->id, 'Tanggal Export', now()->format('Y-m-d H:i:s')]));
        $writer->addRow(Row::fromValues(['Tanggal Assessment', $period->assessment_date?->format('Y-m-d') ?? '-', 'Metode PD Aktif', $config['pd_method']]));
        $writer->addRow(Row::fromValues(['Status Periode', $period->status ?? 'ACTIVE', 'Metode LGD Aktif', $config['lgd_method']]));
        $writer->addRow(Row::fromValues(['Window PD (Bulan)', $config['historical_months'], 'Window LGD (Bulan)', $config['lgd_historical_months']]));
        $writer->addRow(Row::fromValues(['Biaya Penjualan Agunan (%)', round((float) $config['sale_cost_percentage'] * 100, 2).'%', 'Dimensi Segmentasi', $config['segment_dimensions']]));
        $writer->addRow(Row::fromValues(['']));

        // Aggregate Data
        $indTotalEad = (float) DB::table('stage_individual_ckpn')->where('assessment_period_id', $period->id)->sum('nilai_tercatat');
        $indTotalCkpn = (float) DB::table('stage_individual_ckpn')->where('assessment_period_id', $period->id)->sum('ckpn_individual');
        $indCount = (int) DB::table('stage_individual_ckpn')->where('assessment_period_id', $period->id)->count();

        $colTotalEad = (float) DB::table('stage_collective_ckpn')->where('assessment_period_id', $period->id)->sum('total_ead');
        $colTotalCkpn = (float) DB::table('stage_collective_ckpn')->where('assessment_period_id', $period->id)->sum('ckpn_kolektif');
        $colCount = (int) DB::table('stage_classifications')
            ->where('assessment_period_id', $period->id)
            ->where('classification', 'COLLECTIVE')
            ->count();

        $totalEad = $indTotalEad + $colTotalEad;
        $totalCkpn = $indTotalCkpn + $colTotalCkpn;
        $coverage = $totalEad > 0 ? round(($totalCkpn / $totalEad) * 100, 2) : 0.0;

        $writer->addRow(Row::fromValues(['RINGKASAN TOTAL HASIL CKPN (EKSEKUTIF)'])->setStyle($this->styleSection));
        $writer->addRow(Row::fromValues(['Komponen', 'Jumlah Rekening', 'Total EAD (Rp)', 'Total CKPN (Rp)', 'Rasio CKPN/EAD (%)'])->setStyle($this->styleHeader));
        $writer->addRow(Row::fromValues(['CKPN Individual', $indCount, $indTotalEad, $indTotalCkpn, $indTotalEad > 0 ? round(($indTotalCkpn / $indTotalEad) * 100, 2) : 0]));
        $writer->addRow(Row::fromValues(['CKPN Kolektif', $colCount, $colTotalEad, $colTotalCkpn, $colTotalEad > 0 ? round(($colTotalCkpn / $colTotalEad) * 100, 2) : 0]));
        $writer->addRow(Row::fromValues(['TOTAL SELURUHNYA', $indCount + $colCount, $totalEad, $totalCkpn, $coverage])->setStyle($this->styleTotalAccent));
        $writer->addRow(Row::fromValues(['']));

        // Navigasi Sheet
        $writer->addRow(Row::fromValues(['PETUNJUK SHEET WORKBOOK'])->setStyle($this->styleSection));
        $writer->addRow(Row::fromValues(['Sheet', 'Nama Sheet', 'Deskripsi & Tujuan Audit'])->setStyle($this->styleHeader));
        $sheets = [
            ['01', '01_CKPN_Individual', 'Detail seluruh debitur individual: Saldo EAD, Nilai Likuidasi Agunan, Biaya Penjualan, & CKPN Individual.'],
            ['02', '02_CKPN_Kolektif', 'Detail seluruh debitur kolektif: Nomor Akad, Segmen, Bucket/Rating, EAD, PD (%), LGD (%), & CKPN Debitur.'],
            ['03', '03_PD_Matrix', 'Matriks lengkap perhitungan PD sesuai metode aktif (Netflow transition atau Migration matrix triwulanan).'],
            ['04', '04_LGD_ER', 'Hasil perhitungan LGD metode Expected Recovery: Analisis historis recovery WO 5 tahun terakhir per segmen.'],
            ['05', '05_LGD_CS', 'Hasil perhitungan LGD metode Collateral Shortfall: Evaluasi shortfall agunan vs exposure WO per segmen.'],
            ['06', '06_LGD_Weighted', 'Agregasi pembobotan LGD Weighted: Perbandingan LGD ER vs LGD CS beserta bobot exposure.'],
            ['07', '07_Snapshot_PD', 'Data mentah historis / transisi bucket/rating yang digunakan sebagai sumber kalkulasi PD.'],
            ['08', '08_Snapshot_LGD', 'Data mentah debitur WO dan agunan yang digunakan sebagai sumber kalkulasi LGD ER dan CS.'],
        ];
        foreach ($sheets as $s) {
            $writer->addRow(Row::fromValues($s));
        }
    }

    /**
     * Sheet 01: Detail CKPN Individual
     */
    private function writeCkpnIndividualSheet(Writer $writer, AssessmentPeriod $period): void
    {
        $writer->addRow(Row::fromValues(['DETAIL PERHITUNGAN CKPN INDIVIDUAL PER REKENING'])->setStyle($this->styleTitle));
        $writer->addRow(Row::fromValues(['Formula: CKPN = MAX(0, Nilai Tercatat - Estimasi Recovery)'])->setStyle($this->styleSubSection));
        $writer->addRow(Row::fromValues(['']));

        $headers = [
            'No. Rekening / Akad', 'Kantor', 'Akad', 'Penggunaan', 'Nilai Tercatat / EAD (Rp)',
            'Nilai Likuidasi Agunan (Rp)', 'Biaya Penjualan Agunan (Rp)',
            'Estimasi Arus Kas (Rp)', 'Estimasi Nilai Recovery (Rp)', 'CKPN Individual (Rp)',
            'Metode Recovery', 'Alasan Individual',
        ];
        $writer->addRow(Row::fromValues($headers)->setStyle($this->styleHeader));

        $rows = DB::table('stage_individual_ckpn as ind')
            ->leftJoin('stage_classifications as sc', function ($j) use ($period) {
                $j->on('ind.loan_number', '=', 'sc.loan_number')
                    ->where('sc.assessment_period_id', '=', $period->id);
            })
            ->where('ind.assessment_period_id', $period->id)
            ->select(
                'ind.loan_number', 'sc.office_code', 'sc.akad_code', 'sc.usage_code',
                'ind.nilai_tercatat', 'ind.liquidity_value', 'ind.sale_cost',
                'ind.estimated_recovery', 'ind.ckpn_individual',
                'ind.recovery_method', 'sc.reason'
            )
            ->orderByDesc('ind.ckpn_individual')
            ->get();

        if ($rows->isEmpty()) {
            $writer->addRow(Row::fromValues(['Tidak ada debitur dengan klasifikasi Individual pada periode ini.']));
            return;
        }

        $sumEad = 0.0;
        $sumLiq = 0.0;
        $sumSale = 0.0;
        $sumRec = 0.0;
        $sumCkpn = 0.0;

        foreach ($rows as $r) {
            $ead = (float) $r->nilai_tercatat;
            $liq = (float) ($r->liquidity_value ?? 0);
            $sale = (float) ($r->sale_cost ?? 0);
            $rec = (float) $r->estimated_recovery;
            $ckpn = (float) $r->ckpn_individual;

            $sumEad += $ead;
            $sumLiq += $liq;
            $sumSale += $sale;
            $sumRec += $rec;
            $sumCkpn += $ckpn;

            $writer->addRow(Row::fromValues([
                $r->loan_number,
                $r->office_code ?? '',
                $r->akad_code ?? '',
                $r->usage_code ?? '',
                $ead,
                $liq,
                $sale,
                0.0, // Estimasi Arus Kas (jika ada model DCF)
                $rec,
                $ckpn,
                $r->recovery_method ?? 'COLLATERAL',
                $r->reason ?? 'Exposure signifikan / WO / Restrukturisasi',
            ]));
        }

        $writer->addRow(Row::fromValues([
            'TOTAL KESELURUHAN', '', '', '',
            $sumEad, $sumLiq, $sumSale, 0.0, $sumRec, $sumCkpn, '', '',
        ])->setStyle($this->styleTotalAccent));
    }

    /**
     * Sheet 02: Detail CKPN Kolektif
     */
    private function writeCkpnCollectiveSheet(Writer $writer, AssessmentPeriod $period, bool $isMigration): void
    {
        $writer->addRow(Row::fromValues(['DETAIL PERHITUNGAN CKPN KOLEKTIF PER REKENING'])->setStyle($this->styleTitle));
        $writer->addRow(Row::fromValues(['Formula: CKPN Debitur = EAD × PD × LGD (PD & LGD dalam bentuk desimal)'])->setStyle($this->styleSubSection));
        $writer->addRow(Row::fromValues(['']));

        $dims = $this->segmentDimensions(request(), $period->id);
        $periodeYm = Carbon::parse($period->assessment_date)->format('Ym');

        $ckpnMap = DB::table('stage_collective_ckpn')
            ->where('assessment_period_id', $period->id)
            ->get()
            ->keyBy(fn ($r) => $r->group_key.'|'.(int) $r->bucket_no);

        $headers = [
            'No. Rekening / Akad', 'Kode Kantor', 'Kode Akad', 'Jenis Penggunaan', 'Segmen Group Key',
            $isMigration ? 'Rating Kualitas' : 'Nomor Bucket',
            $isMigration ? 'Keterangan Kualitas' : 'Hari Tunggakan',
            'EAD / Baki Debet (Rp)', 'PD (Desimal)', 'LGD (Desimal)', 'Metode LGD',
            'CKPN Kolektif (Rp)',
        ];
        $writer->addRow(Row::fromValues($headers)->setStyle($this->styleHeader));

        $rows = DB::table('stage_classifications as sc')
            ->leftJoin('snapshot as s', function ($j) use ($periodeYm) {
                $j->on('sc.loan_number', '=', 's.loan_number')
                    ->where('s.periode_snapshot', '=', $periodeYm)
                    ->where('s.active_flag', '=', true);
            })
            ->where('sc.assessment_period_id', $period->id)
            ->where('sc.classification', 'COLLECTIVE')
            ->select(
                'sc.loan_number', 'sc.ead_amount', 'sc.office_code', 'sc.akad_code', 'sc.usage_code',
                DB::raw('COALESCE(s.bucket_no, 1) as bucket_no'),
                DB::raw('CAST(COALESCE(s.quality_rating, 1) AS UNSIGNED) as quality_rating')
            )
            ->get();

        if ($rows->isEmpty()) {
            $writer->addRow(Row::fromValues(['Tidak ada data debitur kolektif pada periode ini.']));
            return;
        }

        $sumEad = 0.0;
        $sumCkpn = 0.0;

        foreach ($rows as $r) {
            $groupKey = $this->buildSegment($dims, $r->office_code, $r->akad_code, $r->usage_code)['group_key'];
            $dimKey = $isMigration ? (int) $r->quality_rating : (int) $r->bucket_no;
            $g = $ckpnMap[$groupKey.'|'.$dimKey] ?? null;

            $pd = $g ? (float) $g->pd : 0.0;
            $lgd = $g ? (float) $g->lgd : 0.0;
            $ead = (float) $r->ead_amount;
            $ckpn = round($ead * $pd * $lgd, 2);

            $dimLabel = $isMigration
                ? 'Rating '.$r->quality_rating
                : \App\Services\Bucket\BucketAssignmentService::getBucketLabel((int) $r->bucket_no);

            $sumEad += $ead;
            $sumCkpn += $ckpn;

            $writer->addRow(Row::fromValues([
                $r->loan_number,
                $r->office_code ?? '',
                $r->akad_code ?? '',
                $r->usage_code ?? '',
                $groupKey,
                $dimKey,
                $dimLabel,
                $ead,
                round($pd, 2),
                round($lgd, 2),
                $g->lgd_method ?? 'WEIGHTED',
                $ckpn,
            ]));
        }

        $writer->addRow(Row::fromValues([
            'TOTAL KESELURUHAN', '', '', '', '', '', '',
            $sumEad, '', '', '', $sumCkpn,
        ])->setStyle($this->styleTotalAccent));

        $writer->addRow(Row::fromValues(['']));
        $writer->addRow(Row::fromValues(['RINGKASAN AGREGASI PER GRUP & BUCKET/RATING'])->setStyle($this->styleSection));
        $writer->addRow(Row::fromValues([
            'Group Key', 'Bucket/Rating', 'Kantor', 'Akad', 'Penggunaan',
            'Total EAD (Rp)', 'PD (Desimal)', 'LGD (Desimal)', 'Metode LGD', 'Total CKPN (Rp)', 'Jumlah Rekening',
        ])->setStyle($this->styleHeaderAccent));

        $aggs = DB::table('stage_collective_ckpn')
            ->where('assessment_period_id', $period->id)
            ->orderBy('group_key')
            ->orderBy('bucket_no')
            ->get();

        foreach ($aggs as $a) {
            $writer->addRow(Row::fromValues([
                $a->group_key,
                (int) $a->bucket_no,
                $a->office_code ?? '',
                $a->akad_code ?? '',
                $a->usage_code ?? '',
                (float) $a->total_ead,
                round((float) $a->pd, 2),
                round((float) $a->lgd, 2),
                $a->lgd_method,
                (float) $a->ckpn_kolektif,
                (int) $a->member_count,
            ]));
        }
    }

    /**
     * Sheet 03: PD Matrix - Mode Netflow
     */
    private function writePdNetflowSheet(Writer $writer, AssessmentPeriod $period): void
    {
        $writer->addRow(Row::fromValues(['DETAIL MATRIKS PERHITUNGAN PD METODE NET FLOW'])->setStyle($this->styleTitle));
        $writer->addRow(Row::fromValues(['Model: Roll Rate / Transition Rate Perpindahan Bucket ke Default'])->setStyle($this->styleSubSection));
        $writer->addRow(Row::fromValues(['']));

        $pdRows = DB::table('stage_pd_results')
            ->where('assessment_period_id', $period->id)
            ->where('method', 'NET_FLOW')
            ->orderBy('group_key')
            ->get()
            ->unique('group_key')
            ->keyBy('group_key');

        if ($pdRows->isEmpty()) {
            $writer->addRow(Row::fromValues(['Belum ada hasil perhitungan PD Net Flow untuk periode ini.']));
            return;
        }

        /** @var \App\Services\PdNetflow\PdNetflowService $service */
        $service = app(\App\Services\PdNetflow\PdNetflowService::class);
        /** @var \App\Services\PdNetflow\PdNetflowCalculator $calculator */
        $calculator = app(\App\Services\PdNetflow\PdNetflowCalculator::class);

        foreach ($pdRows as $gk => $res) {
            $hasData = DB::table('pd_netflow_bucket_snapshots')
                ->where('assessment_period_id', $period->id)
                ->where('group_key', $gk)
                ->exists();

            if (! $hasData) {
                $calculator->calculate($period->id, $gk);
            }

            $data = $service->loadGroupDetail($period->id, $gk);

            $writer->addRow(Row::fromValues(["SEGMEN GROUP: {$gk}"])->setStyle($this->styleSection));
            $writer->addRow(Row::fromValues([
                'PD Final (%)', round(min(1.0, max(0.0, (float) $res->pd_calculated)) * 100, 2),
                'Periode Analisis', (int) $res->periods_analyzed.' Bulan',
            ]));
            $writer->addRow(Row::fromValues(['']));

            // Tabel 1: Saldo Bucket per Periode Historis
            $writer->addRow(Row::fromValues(['Tabel 1: Saldo Outstanding per Bucket per Periode (Rp)'])->setStyle($this->styleSubSection));
            $periods = $data['periods'] ?? [];
            $pLabels = array_map(fn ($p) => Carbon::parse($p)->format('M-Y'), $periods);
            $writer->addRow(Row::fromValues(array_merge(['Bucket No', 'Hari Tunggakan'], $pLabels))->setStyle($this->styleHeader));

            foreach ($data['buckets'] ?? [] as $b) {
                $row = [$b['bucket_no'], $b['hari_tunggakan']];
                foreach ($periods as $p) {
                    $row[] = (float) ($b['periods'][$p] ?? 0);
                }
                $writer->addRow(Row::fromValues($row));
            }
            $writer->addRow(Row::fromValues(['']));

            // Tabel 2: Transition Rate antar Bucket
            $writer->addRow(Row::fromValues(['Tabel 2: Perpindahan / Transition Rate Antar Bucket (Desimal, Maks 2 Desimal)'])->setStyle($this->styleSubSection));
            $transPeriods = $data['transition_periods'] ?? [];
            $transLabels = array_map(fn ($p) => Carbon::parse($p)->format('M-Y'), $transPeriods);
            $writer->addRow(Row::fromValues(array_merge(['Perpindahan Bucket', 'Rata-rata Flow'], $transLabels))->setStyle($this->styleHeader));

            foreach ($data['transitions'] ?? [] as $t) {
                $counts = array_values($t['flow_counts'] ?? []);
                $avg = ! empty($counts) ? (int) round(array_sum($counts) / count($counts)) : 0;
                $row = [$t['label'], $avg > 0 ? $avg : '-'];
                foreach ($transPeriods as $p) {
                    // Capping desimal [0, 1] & maks 2 desimal
                    $rate = min(1.0, max(0.0, (float) ($t['periods'][$p] ?? 0)));
                    $row[] = round($rate, 2);
                }
                $writer->addRow(Row::fromValues($row));
            }
            $writer->addRow(Row::fromValues(['']));

            // Tabel 3: Compound Flow to Loss
            $writer->addRow(Row::fromValues(['Tabel 3: Compound Flow to Loss (PD Kumulatif Desimal, Maks 2 Desimal)'])->setStyle($this->styleSubSection));
            $compPeriods = $data['compound_periods'] ?? $transPeriods;
            $compLabels = array_map(fn ($p) => Carbon::parse($p)->format('M-Y'), $compPeriods);
            $writer->addRow(Row::fromValues(array_merge(['Source Bucket', 'Label Tunggakan'], $compLabels, ['Rata-rata PD (%)']))->setStyle($this->styleHeaderAccent));

            foreach ($data['compounds'] ?? [] as $c) {
                $row = [$c['source_bucket'], $c['label']];
                foreach ($compPeriods as $p) {
                    // Capping desimal [0, 1] & maks 2 desimal
                    $pd = min(1.0, max(0.0, (float) ($c['periods'][$p] ?? 0)));
                    $row[] = round($pd, 2);
                }
                // Rata-rata PD per source bucket dalam persentase, maks 2 desimal
                $pdFinal = min(1.0, max(0.0, (float) $c['pd_rate']));
                $row[] = round($pdFinal * 100, 2);
                $writer->addRow(Row::fromValues($row));
            }
            $writer->addRow(Row::fromValues(['']));
        }
    }

    /**
     * Sheet 03: PD Matrix - Mode Migration
     */
    private function writePdMigrationSheet(Writer $writer, AssessmentPeriod $period): void
    {
        $writer->addRow(Row::fromValues(['DETAIL MATRIKS PERHITUNGAN PD METODE MIGRATION'])->setStyle($this->styleTitle));
        $writer->addRow(Row::fromValues(['Layout Kertas Kerja B2.PD-Migration: Triwulanan Outstanding, Matriks Rate, & Cascade Backward'])->setStyle($this->styleSubSection));
        $writer->addRow(Row::fromValues(['']));

        $rows = DB::table('stage_pd_results')
            ->where('assessment_period_id', $period->id)
            ->where('method', 'MIGRATION')
            ->orderBy('bucket_no')
            ->get()
            ->groupBy('group_key')
            ->map(function ($grp) {
                $r = $grp->first();
                $d = json_decode($r->detail ?? '{}', true) ?: [];
                $ratingPd = array_map('floatval', $d['rating_pd'] ?? []);
                $exposure = array_map('floatval', $d['rating_exposure'] ?? []);

                $expSum = array_sum($exposure);
                if ($expSum > 0) {
                    $pd = 0.0;
                    foreach ([1, 2, 3, 4, 5] as $k) {
                        $pd += (($exposure[$k] ?? 0.0) / $expSum) * ($ratingPd[$k] ?? 0.0);
                    }
                    $r->pd_calculated = round($pd, 2);
                } elseif (isset($d['pd_group'])) {
                    $r->pd_calculated = (float) $d['pd_group'];
                }
                return $r;
            })
            ->sortByDesc(fn ($r) => (float) $r->pd_calculated)
            ->values();

        if ($rows->isEmpty()) {
            $writer->addRow(Row::fromValues(['Belum ada hasil perhitungan PD Migration untuk periode ini.']));
            return;
        }

        $destLabels = [1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5', 6 => 'WO (Hapus Buku)', 7 => 'Pembayaran'];

        foreach ($rows as $r) {
            $d = json_decode($r->detail ?? '{}', true) ?: [];
            $matrices = $d['matrices'] ?? [];

            $writer->addRow(Row::fromValues(["SEGMEN GROUP: {$r->group_key}"])->setStyle($this->styleSection));
            $writer->addRow(Row::fromValues(['PD Grup Final (%)', round((float) $r->pd_calculated * 100, 2), 'Jumlah Matriks Triwulanan', count($matrices)]));
            $writer->addRow(Row::fromValues(['']));

            foreach ($matrices as $mi => $s) {
                $startLabel = $s['t_start_label'] ?? ($s['t_start_ym'] ?? '');
                $endLabel = $s['t_end_label'] ?? ($s['t_end_ym'] ?? '');
                $writer->addRow(Row::fromValues(['Matriks '.($mi + 1).' · '.($s['quarter'] ?? 'Q')." ({$startLabel} → {$endLabel})"])->setStyle($this->styleSubSection));

                // Tabel Outstanding (Rp)
                $writer->addRow(Row::fromValues(['1. Pergerakan Outstanding (Rp) — Saldo Awal '.$startLabel.' ke Posisi '.$endLabel]));
                $writer->addRow(Row::fromValues(array_merge(['Kualitas', 'Saldo Awal '.$startLabel], array_values($destLabels), ['TOTAL']))->setStyle($this->styleHeader));

                for ($src = 1; $src <= 5; $src++) {
                    $row = [$src, (float) ($s['saldo_awal'][$src] ?? 0)];
                    for ($t = 1; $t <= 7; $t++) {
                        $row[] = (float) ($s['matrix'][$src][$t] ?? 0);
                    }
                    $row[] = (float) ($s['row_total'][$src] ?? 0);
                    $writer->addRow(Row::fromValues($row));
                }
                $totRow = ['TOTAL', (float) array_sum($s['saldo_awal'] ?? [])];
                for ($t = 1; $t <= 7; $t++) {
                    $totRow[] = null;
                }
                $totRow[] = (float) ($s['grand_total'] ?? 0);
                $writer->addRow(Row::fromValues($totRow)->setStyle($this->styleTotal));

                // Tabel Rate (Desimal, maks 2 desimal)
                $writer->addRow(Row::fromValues(['2. Proporsi Migrasi / Rate / PD (Desimal, Maks 2 Desimal) — Terhadap Saldo Awal']));
                $writer->addRow(Row::fromValues(array_merge(['Kualitas', 'Saldo Awal (Rp)'], array_values($destLabels), ['TOTAL (PD)']))->setStyle($this->styleHeaderAccent));

                $colTotals = $s['total_to'] ?? [];
                $grandRate = array_sum($colTotals) ?: 0.0;
                $grandBase = (float) array_sum($s['saldo_awal'] ?? []);
                $weightedPd = $grandBase > 0
                    ? array_sum(array_map(fn ($x) => (float) ($s['pd'][$x] ?? 0) * (float) ($s['saldo_awal'][$x] ?? 0), [1, 2, 3, 4, 5])) / $grandBase
                    : 0.0;

                for ($src = 1; $src <= 5; $src++) {
                    $row = [$src, (float) ($s['saldo_awal'][$src] ?? 0)];
                    for ($t = 1; $t <= 7; $t++) {
                        $row[] = round((float) ($s['rate'][$src][$t] ?? 0), 2);
                    }
                    $row[] = round((float) ($s['pd'][$src] ?? 0), 2);
                    $writer->addRow(Row::fromValues($row));
                }

                $rateTotRow = ['TOTAL', $grandBase];
                for ($t = 1; $t <= 7; $t++) {
                    $rateTotRow[] = $grandRate > 0 ? round(((float) ($colTotals[$t] ?? 0) / $grandRate), 2) : 0.0;
                }
                $rateTotRow[] = round($weightedPd, 2);
                $writer->addRow(Row::fromValues($rateTotRow)->setStyle($this->styleTotalAccent));
                $writer->addRow(Row::fromValues(['']));
            }

            // Ringkasan PD per Rating
            $rp = $d['rating_pd'] ?? [];
            $exp = $d['rating_exposure'] ?? [];
            $writer->addRow(Row::fromValues(['PD Akhir per Rating & Exposure Posisi Assessment'])->setStyle($this->styleSubSection));
            $writer->addRow(Row::fromValues(['Rating Kualitas', 'Exposure Baki Debet (Rp)', 'PD Rating (Desimal)'])->setStyle($this->styleHeader));
            for ($rr = 1; $rr <= 5; $rr++) {
                $writer->addRow(Row::fromValues([
                    'Rating '.$rr,
                    (float) ($exp[$rr] ?? 0),
                    isset($rp[$rr]) ? round((float) $rp[$rr], 2) : '-',
                ]));
            }
            $writer->addRow(Row::fromValues(['TOTAL EXPOSURE', array_sum($exp), round((float) $r->pd_calculated * 100, 2)])->setStyle($this->styleTotalAccent));
            $writer->addRow(Row::fromValues(['']));
        }
    }

    /**
     * Sheet 04: LGD Expected Recovery
     */
    private function writeLgdErSheet(Writer $writer, AssessmentPeriod $period): void
    {
        $writer->addRow(Row::fromValues(['DETAIL PERHITUNGAN LGD METODE EXPECTED RECOVERY (ER)'])->setStyle($this->styleTitle));
        $writer->addRow(Row::fromValues(['Formula: LGD ER = 1 - Recovery Rate (Desimal) | Populasi: WO Historis Tanpa Agunan Likuid'])->setStyle($this->styleSubSection));
        $writer->addRow(Row::fromValues(['']));

        $headers = [
            'Group Key', 'Kode Kantor', 'Kode Akad', 'Penggunaan',
            'Total Write Off (Rp)', 'Total Recovery Terkumpul (Rp)', 'Recovery Rate (Desimal)',
            'LGD ER (Desimal)', 'Periode Analisis (Tahun)', 'Jumlah Rekening WO',
        ];
        $writer->addRow(Row::fromValues($headers)->setStyle($this->styleHeader));

        $rows = DB::table('stage_lgd_results')
            ->where('assessment_period_id', $period->id)
            ->where('method', 'ER')
            ->orderBy('group_key')
            ->get();

        if ($rows->isEmpty()) {
            $writer->addRow(Row::fromValues(['Belum ada hasil LGD ER pada periode ini.']));
            return;
        }

        $sumWo = 0.0;
        $sumRec = 0.0;

        foreach ($rows as $r) {
            $detail = json_decode($r->detail ?? '{}', true) ?: [];
            $wo = (float) $r->total_write_off;
            $rec = (float) $r->total_recovery;
            $recRate = $r->recovery_rate !== null ? round((float) $r->recovery_rate, 2) : 0.0;
            $lgd = round((float) $r->lgd_calculated, 2);

            $sumWo += $wo;
            $sumRec += $rec;

            $writer->addRow(Row::fromValues([
                $r->group_key,
                $r->office_code ?? '',
                $r->akad_code ?? '',
                $r->usage_code ?? '',
                $wo,
                $rec,
                $recRate,
                $lgd,
                (int) ($r->years_analyzed ?? 5),
                $detail['wo_accounts'] ?? $r->account_count ?? '-',
            ]));
        }

        $writer->addRow(Row::fromValues([
            'TOTAL KESELURUHAN', '', '', '',
            $sumWo, $sumRec,
            $sumWo > 0 ? round(($sumRec / $sumWo), 2) : 0.0,
            $sumWo > 0 ? round((1 - ($sumRec / $sumWo)), 2) : 0.0,
            '', '',
        ])->setStyle($this->styleTotalAccent));
    }

    /**
     * Sheet 05: LGD Collateral Shortfall
     */
    private function writeLgdCsSheet(Writer $writer, AssessmentPeriod $period): void
    {
        $writer->addRow(Row::fromValues(['DETAIL PERHITUNGAN LGD METODE COLLATERAL SHORTFALL (CS)'])->setStyle($this->styleTitle));
        $writer->addRow(Row::fromValues(['Formula: LGD CS = Total Shortfall / Total Exposure | Populasi: Kol 5 Aktif & WO Memiliki Agunan'])->setStyle($this->styleSubSection));
        $writer->addRow(Row::fromValues(['']));

        $headers = [
            'Group Key', 'Kode Kantor', 'Kode Akad', 'Penggunaan',
            'Total Exposure WO (Rp)', 'Net Recovery Agunan (Rp)', 'Total Shortfall (Rp)',
            'Collateral Coverage (Desimal)', 'LGD CS (Desimal)', 'Jumlah Rekening',
        ];
        $writer->addRow(Row::fromValues($headers)->setStyle($this->styleHeader));

        $rows = DB::table('stage_lgd_results')
            ->where('assessment_period_id', $period->id)
            ->where('method', 'CS')
            ->orderBy('group_key')
            ->get();

        if ($rows->isEmpty()) {
            $writer->addRow(Row::fromValues(['Belum ada hasil LGD CS pada periode ini.']));
            return;
        }

        $sumExp = 0.0;
        $sumNet = 0.0;
        $sumShortfall = 0.0;

        foreach ($rows as $r) {
            $exp = (float) $r->total_exposure_wo;
            $shortfall = (float) $r->total_shortfall;
            $netRec = round($exp - $shortfall, 2);
            $cov = $r->collateral_coverage !== null ? round((float) $r->collateral_coverage, 2) : 0.0;
            $lgd = round((float) $r->lgd_calculated, 2);

            $sumExp += $exp;
            $sumNet += $netRec;
            $sumShortfall += $shortfall;

            $writer->addRow(Row::fromValues([
                $r->group_key,
                $r->office_code ?? '',
                $r->akad_code ?? '',
                $r->usage_code ?? '',
                $exp,
                $netRec,
                $shortfall,
                $cov,
                $lgd,
                (int) ($r->account_count ?? 0),
            ]));
        }

        $writer->addRow(Row::fromValues([
            'TOTAL KESELURUHAN', '', '', '',
            $sumExp, $sumNet, $sumShortfall,
            $sumExp > 0 ? round(($sumNet / $sumExp), 2) : 0.0,
            $sumExp > 0 ? round(($sumShortfall / $sumExp), 2) : 0.0,
            '',
        ])->setStyle($this->styleTotalAccent));
    }

    /**
     * Sheet 06: LGD Weighted
     */
    private function writeLgdWeightedSheet(Writer $writer, AssessmentPeriod $period): void
    {
        $writer->addRow(Row::fromValues(['DETAIL AGREGASI BOBOT LGD WEIGHTED (ER & CS)'])->setStyle($this->styleTitle));
        $writer->addRow(Row::fromValues(['Formula: LGD Weighted = (Loss ER + Loss CS) / (Exposure ER + Exposure CS)'])->setStyle($this->styleSubSection));
        $writer->addRow(Row::fromValues(['']));

        $headers = [
            'Group Key', 'Kode Kantor', 'Kode Akad', 'Penggunaan',
            'LGD ER (Desimal)', 'Exposure ER (Rp)', 'LGD CS (Desimal)', 'Exposure CS (Rp)',
            'LGD Weighted Final (Desimal)', 'Metode Terpilih',
        ];
        $writer->addRow(Row::fromValues($headers)->setStyle($this->styleHeader));

        $erMap = DB::table('stage_lgd_results')
            ->where('assessment_period_id', $period->id)
            ->where('method', 'ER')
            ->get()
            ->keyBy('group_key');

        $csMap = DB::table('stage_lgd_results')
            ->where('assessment_period_id', $period->id)
            ->where('method', 'CS')
            ->get()
            ->keyBy('group_key');

        $weightedRows = DB::table('stage_lgd_results')
            ->where('assessment_period_id', $period->id)
            ->where('method', 'WEIGHTED')
            ->orderBy('group_key')
            ->get();

        if ($weightedRows->isEmpty()) {
            $writer->addRow(Row::fromValues(['Belum ada hasil LGD Weighted pada periode ini.']));
            return;
        }

        foreach ($weightedRows as $w) {
            $er = $erMap[$w->group_key] ?? null;
            $cs = $csMap[$w->group_key] ?? null;

            $lgdEr = $er ? round((float) $er->lgd_calculated, 2) : '-';
            $expEr = $er ? (float) $er->total_write_off : 0.0;

            $cs = $csMap->get($w->group_key);
            $lgdCs = $cs ? round((float) $cs->lgd_calculated, 2) : '-';
            $expCs = $cs ? (float) $cs->total_exposure_wo : 0.0;

            $writer->addRow(Row::fromValues([
                $w->group_key,
                $w->office_code ?? '',
                $w->akad_code ?? '',
                $w->usage_code ?? '',
                $lgdEr,
                $expEr,
                $lgdCs,
                $expCs,
                round((float) $w->lgd_calculated, 2),
                $w->method,
            ]));
        }
    }

    /**
     * Sheet 07: Snapshot Data untuk Kalkulasi PD
     */
    private function writeSnapshotPdSheet(Writer $writer, AssessmentPeriod $period, bool $isMigration): void
    {
        $writer->addRow(Row::fromValues(['SNAPSHOT DATA YANG DIGUNAKAN UNTUK PERHITUNGAN PD'])->setStyle($this->styleTitle));
        $writer->addRow(Row::fromValues([$isMigration ? 'Data Riwayat Migrasi Rating Triwulanan' : 'Data Riwayat Outstanding Saldo per Bucket'])->setStyle($this->styleSubSection));
        $writer->addRow(Row::fromValues(['']));

        if ($isMigration) {
            $headers = ['Group Key', 'Triwulan', 'T_Start', 'T_End', 'Rating Awal', 'Rating Tujuan', 'Outstanding Pindah (Rp)', 'Rate Proporsi'];
            $writer->addRow(Row::fromValues($headers)->setStyle($this->styleHeader));

            $records = DB::table('stage_migration_rates')
                ->where('assessment_period_id', $period->id)
                ->orderBy('group_key')
                ->orderBy('quarter')
                ->get();

            if ($records->isEmpty()) {
                // Alternatif: ambil dari JSON detail stage_pd_results jika stage_migration_rates tidak diisi terpisah
                $pdRows = DB::table('stage_pd_results')
                    ->where('assessment_period_id', $period->id)
                    ->where('method', 'MIGRATION')
                    ->get();

                foreach ($pdRows as $p) {
                    $d = json_decode($p->detail ?? '{}', true) ?: [];
                    foreach ($d['matrices'] ?? [] as $m) {
                        for ($src = 1; $src <= 5; $src++) {
                            for ($dst = 1; $dst <= 7; $dst++) {
                                $amt = (float) ($m['matrix'][$src][$dst] ?? 0);
                                if ($amt > 0) {
                                    $writer->addRow(Row::fromValues([
                                        $p->group_key,
                                        $m['quarter'] ?? '',
                                        $m['t_start_label'] ?? '',
                                        $m['t_end_label'] ?? '',
                                        $src,
                                        $dst,
                                        $amt,
                                        round((float) ($m['rate'][$src][$dst] ?? 0), 2),
                                    ]));
                                }
                            }
                        }
                    }
                }
            } else {
                foreach ($records as $r) {
                    $writer->addRow(Row::fromValues([
                        $r->group_key,
                        $r->quarter ?? '',
                        $r->t_start ?? '',
                        $r->t_end ?? '',
                        $r->source_rating,
                        $r->target_rating,
                        (float) $r->amount,
                        round((float) $r->rate, 2),
                    ]));
                }
            }
        } else {
            // Net Flow snapshot: tabel pd_netflow_bucket_snapshots atau stage_historical_buckets
            $headers = ['Group Key', 'Bucket No', 'Periode Snapshot (YYYY-MM-DD)', 'Total Outstanding (Rp)', 'Jumlah Rekening'];
            $writer->addRow(Row::fromValues($headers)->setStyle($this->styleHeader));

            $rows = DB::table('pd_netflow_bucket_snapshots')
                ->where('assessment_period_id', $period->id)
                ->orderBy('group_key')
                ->orderBy('periode_snapshot')
                ->orderBy('bucket_no')
                ->get();

            if ($rows->isEmpty()) {
                // Fallback ke stage_historical_buckets bila snapshot Netflow belum dihitung ulang
                $rows = DB::table('stage_historical_buckets')
                    ->where('assessment_period_id', $period->id)
                    ->orderBy('group_key')
                    ->orderBy('periode_snapshot')
                    ->orderBy('bucket_no')
                    ->get();

                foreach ($rows as $r) {
                    $writer->addRow(Row::fromValues([
                        $r->group_key,
                        (int) $r->bucket_no,
                        $r->periode_snapshot,
                        (float) $r->ead,
                        (int) ($r->member_count ?? 1),
                    ]));
                }
            } else {
                foreach ($rows as $r) {
                    $writer->addRow(Row::fromValues([
                        $r->group_key,
                        (int) $r->bucket_no,
                        $r->periode_snapshot instanceof \Carbon\Carbon ? $r->periode_snapshot->format('Y-m-d') : (string) $r->periode_snapshot,
                        (float) $r->outstanding_balance,
                        (int) ($r->account_count ?? 1),
                    ]));
                }
            }
        }
    }

    /**
     * Sheet 08: Snapshot Data untuk Kalkulasi LGD
     */
    private function writeSnapshotLgdSheet(Writer $writer, AssessmentPeriod $period): void
    {
        $writer->addRow(Row::fromValues(['SNAPSHOT DATA DEBITUR WO & AGUNAN UNTUK PERHITUNGAN LGD'])->setStyle($this->styleTitle));
        $writer->addRow(Row::fromValues(['Populasi: Rekening Write-Off 5 Tahun Terakhir & Rekening Kol 5 Aktif Periode Assessment'])->setStyle($this->styleSubSection));
        $writer->addRow(Row::fromValues(['']));

        $headers = [
            'No. Akad', 'Kantor', 'Produk / Akad', 'Tgl WO', 'Tahun WO',
            'Outstanding Awal WO (Rp)', 'Outstanding Posisi Assessment (Rp)', 'Total Recovery Terkumpul (Rp)',
            'Nilai Likuidasi Agunan (Rp)', 'Kategori LGD (Populasi)',
        ];
        $writer->addRow(Row::fromValues($headers)->setStyle($this->styleHeader));

        $config = $this->configFor($period->id);
        $histM = (int) ($config['lgd_historical_months'] ?? 60);
        $assessmentDate = $period->assessment_date;
        $assessYm = $assessmentDate->format('Ym');
        $startYm = $assessmentDate->copy()->subMonths($histM)->format('Ym');

        // Populasi WO
        $woLoans = DB::table('snapshot')
            ->where('write_off_flag', 1)
            ->whereNotNull('tglwo')->where('tglwo', '<>', '')
            ->whereRaw('SUBSTRING(tglwo, 1, 6) >= ?', [$startYm])
            ->whereRaw('SUBSTRING(tglwo, 1, 6) <= ?', [$assessYm])
            ->distinct()
            ->pluck('loan_number');

        // Populasi K5
        $k5Loans = DB::table('snapshot')
            ->where('periode_snapshot', $assessYm)
            ->where('active_flag', 1)
            ->whereRaw('CAST(quality_rating AS UNSIGNED) = 5')
            ->where('write_off_flag', '<>', 1)
            ->distinct()
            ->pluck('loan_number');

        $allLoans = $woLoans->merge($k5Loans)->unique()->values();

        if ($allLoans->isEmpty()) {
            $writer->addRow(Row::fromValues(['Tidak ada data snapshot historis untuk populasi LGD.']));
            return;
        }

        // Nilai agunan likuid
        $collateralByLoan = DB::table('collateral')
            ->whereIn('loan_number', $allLoans)
            ->where('active_flag', 1)
            ->where('liquidity_value', '>', 0)
            ->selectRaw('loan_number, COALESCE(SUM(liquidity_value), 0) as liq_value')
            ->groupBy('loan_number')
            ->pluck('liq_value', 'loan_number');

        // Snapshot awal WO
        $woRows = DB::table('snapshot')
            ->whereIn('loan_number', $allLoans)
            ->where('write_off_flag', 1)
            ->orderBy('periode_snapshot')
            ->get(['loan_number', 'periode_snapshot', 'outstanding_balance', 'office_code', 'product_code', 'tglwo'])
            ->groupBy('loan_number')
            ->map(fn ($r) => $r->last());

        // Snapshot posisi assessment
        $lastBalances = DB::table('snapshot')
            ->whereIn('loan_number', $allLoans)
            ->where('periode_snapshot', $assessYm)
            ->pluck('outstanding_balance', 'loan_number');

        foreach ($allLoans as $ln) {
            $woData = $woRows->get($ln);
            $isWo = $woData !== null;
            $tglwo = $isWo ? ($woData->tglwo ?? '-') : 'KOL-5 AKTIF';
            $thnWo = $isWo && ! empty($woData->tglwo) ? substr((string) $woData->tglwo, 0, 4) : '-';

            $osAwal = $isWo ? (float) ($woData->outstanding_balance ?? 0) : 0.0;
            $osAkhir = (float) ($lastBalances[$ln] ?? 0.0);
            $recReal = max(0.0, $osAwal - $osAkhir);

            $liq = (float) ($collateralByLoan[$ln] ?? 0.0);

            // Tentukan kategori populasi
            if ($isWo && $liq <= 0) {
                $kat = 'ER (WO Tanpa Agunan)';
            } elseif ($isWo && $liq > 0) {
                $kat = 'CS (WO Beragunan)';
            } else {
                $kat = 'CS (Kol 5 Aktif)';
            }

            $writer->addRow(Row::fromValues([
                $ln,
                $woData->office_code ?? '',
                $woData->product_code ?? '',
                $tglwo,
                $thnWo,
                $osAwal,
                $osAkhir,
                $recReal,
                $liq,
                $kat,
            ]));
        }
    }

    private function makeWriter(): Writer
    {
        $tmp = tempnam(sys_get_temp_dir(), 'ckpn_full_xlsx_');
        $writer = new Writer();
        $writer->openToFile($tmp);
        $this->currentFile = ['path' => $tmp, 'filename' => ''];

        return $writer;
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
