<?php

namespace App\Http\Controllers\Export;

use App\Http\Controllers\Calculation\BaseCalculationController;
use App\Models\AssessmentPeriod;
use App\Models\SystemConfiguration;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Export XLSX comprehensive untuk semua modul CKPN dalam 1 sheet per assessment period.
 *
 * Structure Sheet:
 * 1. HEADER - Assessment period details, export metadata
 * 2. SNAPSHOT CORE - Snapshot data (financing + write_off + recovery)
 * 3. CKPN - Individual + Collective per segment + Consolidated
 * 4. PD - Matrix Detail (sesuai metode PD dari config)
 * 5. LGD - ER + CS + Weighted per segment
 * 6. SUMMARY - Summary tables transversal
 */
class ComprehensiveExportController extends BaseCalculationController
{
    /**
     * Export comprehensive untuk assessment period yang dipilih.
     */
    public function export(?int $periodId = null): Response
    {
        $period = $this->period($periodId);

        // Validation
        if (!$period) {
            abort(404, 'Assessment period tidak ditemukan.');
        }

        // Cek apakah semua data sudah dihitung
        $checkMissing = DB::table('stage_ckpn_results')
            ->where('assessment_period_id', $period->id)
            ->exists();

        abort_unless($checkMissing, 403, 'Data belum lengkap. Mohon hitung CKPN dahulu.');

        // Pakai OpenSpout untuk XLSX
        $fileName = sprintf(
            'Comprehensive_Export_%s.xlsx',
            $period->assessment_date
        );

        $writer = new Writer();
        $tempPath = tempnam(sys_get_temp_dir(), 'excel_export_');
        $writer->openToFile($tempPath);

        // Build sheet
        $style = $this->buildBaseStyle();
        $this->buildComprehensiveSheet($period, $writer, $style);

        $writer->close();

        return response()->download($tempPath, $fileName)
            ->deleteFileAfterSend(true);
    }

    /**
     * Build comprehensive sheet dengan 6 sections.
     */
    private function buildComprehensiveSheet(AssessmentPeriod $period, Writer $writer, Style $baseStyle): void
    {
        // Section 0: HEADER - Assessment period info
        $this->writeSectionHeader($writer, 'HEADER', "Assessment Period: {$period->assessment_date}");

        $headerStyle = (clone $baseStyle)
            ->setFontBold(true)
            ->setFontItalic(true)
            ->setFontColor('#FFFFFF')
            ->setBackgroundColor('#1E40AF')
            ->setBorder(new Border(Border::WIDTH_THIN, Border::STYLE_SOLID, Border::WIDTH_THIN, Border::STYLE_SOLID, Border::WIDTH_THIN, Border::STYLE_SOLID, Border::WIDTH_THIN, Border::STYLE_SOLID));

        $writer->addRows([
            [
                $period->assessment_date,
                $period->segment_dimensions ?? 'OFFICE|AKAD',
                Carbon::now()->format('Y-m-d H:i:s'),
                'System Generated',
                SystemConfiguration::getValue('pd_method', 'NETFLOW'),
                SystemConfiguration::getValue('lgd_method', 'WEIGHTED'),
            ]
        ]);

        // Center align header
        foreach (range(1, 5) as $col) {
            $headerStyle->setFontUnderline(true);
            $writer->addRow(new Row([$headerStyle], [$headerStyle], [$baseStyle]));
        }

        DB::transaction(function () use ($period, $writer, $baseStyle) {
            // Section 1: SNAPSHOT CORE
            $this->writeSectionHeader($writer, 'SECTION 1 - SNAPSHOT CORE', "Core snapshot data (financing, write-off, recovery)");
            $this->writeSnapshotCore($period, $writer, $baseStyle);

            // Section 2: CKPN
            $this->writeSectionHeader($writer, 'SECTION 2 - CKPN', 'Detail CKPN (Individual + Collective + Consolidated)');
            $this->writeCkpnDetail($period, $writer, $baseStyle);

            // Section 3: PD
            $pdMethod = SystemConfiguration::getValue('pd_method', 'NETFLOW');
            $sectionTitle = sprintf('SECTION 3 - PD (%s)', strtoupper($pdMethod));
            $this->writeSectionHeader($writer, $sectionTitle, "Matrix detail PD menggunakan metode {$pdMethod}");
            $this->writePdDetail($period, $pdMethod, $writer, $baseStyle);

            // Section 4: LGD
            $this->writeSectionHeader($writer, 'SECTION 4 - LGD', 'Detail LGD (ER + CS + Weighted per segment)');
            $this->writeLgdDetail($period, $writer, $baseStyle);

            // Section 5: SUMMARY
            $this->writeSectionHeader($writer, 'SECTION 5 - SUMMARY TABLES', 'Summary tables transversal');
            $this->writeSummaryTables($period, $writer, $baseStyle);
        });
    }

    /**
     * Write section header row.
     */
    private function writeSectionHeader(Writer $writer, string $title, string $subtitle): void
    {
        $style = (clone $writer->getCurrentRowStyle())
            ->setFontBold(true)
            ->setFontSize(14)
            ->setFontColor('#FFFFFF')
            ->setBackgroundColor('#3B82F6')
            ->setBorder(new Border(Border::WIDTH_THICK, Border::STYLE_DOUBLE, Border::WIDTH_THICK, Border::STYLE_DOUBLE, Border::WIDTH_THICK, Border::STYLE_DOUBLE, Border::WIDTH_THICK, Border::STYLE_DOUBLE));

        $writer->addRows([
            Row::fromValues([$title, '', '', '', '', ''], [$style], [$style], [$style], [$style], [$style], [$style]),
            Row::fromValues([$subtitle], [$style], null, null, null, null, null, null, null, null, null, null),
        ]);
    }

    /**
     * Build base cell style.
     */
    private function buildBaseStyle(): Style
    {
        return (new Style())
            ->setFontFamily('Arial')
            ->setFontSize(10)
            ->setShouldWrapText(true)
            ->setBorder(new Border(Border::WIDTH_THIN, Border::STYLE_SOLID, Border::WIDTH_THIN, Border::STYLE_SOLID, Border::WIDTH_THIN, Border::STYLE_SOLID, Border::WIDTH_THIN, Border::STYLE_SOLID));
    }

    /**
     * Section 1: Snapshot Core Data.
     */
    private function writeSnapshotCore(AssessmentPeriod $period, Writer $writer, Style $baseStyle): void
    {
        $style = (clone $baseStyle)
            ->setFontBold(true)
            ->setBorder(new Border(Border::WIDTH_THIN, Border::STYLE_SOLID));

        $headers = [
            'Snapshot Period',
            'No Loan',
            'Office Code',
            'Product Code',
            'Akad Code',
            'Effective Date',
            'Expired Date',
            'Outstanding Balance',
            'Principal Arrears',
            'Active Flag',
            'Write Off Flag',
            'Util Date',
            'Util Balance',
            'Status Codes',
            'Likuidasi Value',
            'Sales Cost',
            'Recovery Date',
            'Recovery Amount',
        ];

        $writer->addRow(Row::fromValues($headers, [$style], null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null));

        // Fetch snapshot data per segment (2 dimensi: OFFICE|AKAD)
        $summary = DB::table('snapshot')
            ->where('assessment_period_id', $period->id)
            ->select(
                'periode_snapshot',
                'no_loan',
                'office_code',
                'product_code',
                'akad_code',
                'effective_date',
                'expired_date',
                DB::raw('COALESCE(outstanding_balance, 0) as outstanding_balance'),
                DB::raw('COALESCE(principal_arrears, 0) as principal_arrears'),
                DB::raw("CASE WHEN active_flag IN ('Y', 'y', '1') THEN 'Y' ELSE 'N' END as active_flag"),
                DB::raw("CASE WHEN write_off_flag IN ('Y', 'y', '1') THEN 'Y' ELSE 'N' END as write_off_flag"),
                'util_date',
                DB::raw('COALESCE(util_balance, 0) as util_balance'),
                'status_codes',
                DB::raw('COALESCE(likuidasi_value, 0) as likuidasi_value'),
                DB::raw('COALESCE(sales_cost, 0) as sales_cost'),
                'recovery_date',
                DB::raw('COALESCE(recovery_amount, 0) as recovery_amount')
            )
            ->get();

        $totalRows = $summary->count();
        $processed = 0;

        foreach ($summary as $row) {
            if ($totalRows > 5000 && $processed > 0 && $processed % 5000 === 0) {
                $this->writeSectionHeader($writer, 'SECTION 1 - CONTINUED', "Resume snapshot data...");
            }

            $writer->addRow(Row::fromValues([
                $row->periode_snapshot,
                (string) $row->no_loan,
                (string) $row->office_code,
                (string) $row->product_code,
                (string) $row->akad_code,
                $row->effective_date,
                $row->expired_date,
                (float) $row->outstanding_balance,
                (float) $row->principal_arrears,
                $row->active_flag,
                $row->write_off_flag,
                $row->util_date,
                (float) $row->util_balance,
                $row->status_codes,
                (float) $row->likuidasi_value,
                (float) $row->sales_cost,
                $row->recovery_date,
                (float) $row->recovery_amount,
            ], [$baseStyle], null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null));

            $processed++;
        }

        // Footer summary
        $summaryStyle = (clone $baseStyle)
            ->setFontBold(true)
            ->setBorder(new Border(Border::WIDTH_THICK, Border::STYLE_SOLID, Border::WIDTH_THICK, Border::STYLE_SOLID, Border::WIDTH_THICK, Border::STYLE_SOLID, Border::WIDTH_THICK, Border::STYLE_SOLID));

        $totalOutstanding = $summary->sum('outstanding_balance');
        $totalPrincipalArrears = $summary->sum('principal_arrears');
        $totalRecovery = $summary->sum('recovery_amount');

        $writer->addRow(Row::fromValues([
            "TOTAL RECORDS: {$totalRows}",
            "TOTAL OUTSTANDING: " . number_format($totalOutstanding, 2, ',', '.'),
            "TOTAL ARREARS: " . number_format($totalPrincipalArrears, 2, ',', '.'),
            "TOTAL RECOVERY: " . number_format($totalRecovery, 2, ',', '.'),
            '', '', '', '', '', '', '', '', '', '', '', '', '',
        ], [$summaryStyle], null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null));
    }

    /**
     * Section 2: CKPN Detail.
     */
    private function writeCkpnDetail(AssessmentPeriod $period, Writer $writer, Style $baseStyle): void
    {
        $style = (clone $baseStyle)
            ->setFontBold(true)
            ->setBorder(new Border(Border::WIDTH_THIN, Border::STYLE_SOLID));

        // Individual + Collective + Consolidated rows
        $headers = [
            'Row Type',
            'Group Key',
            'Office Code',
            'Product Code',
            'Akad Code',
            'EAD',
            'PD',
            'LGD',
            'CKPN',
            'Status',
            'Detail',
        ];

        $writer->addRow(Row::fromValues($headers, [$style], null, null, null, null, null, null, null, null, null));

        // CKPN Individual
        $individuals = $this->getCkpnIndividual($period->id);
        foreach ($individuals as $row) {
            $writer->addRow(Row::fromValues([
                'INDIVIDUAL',
                (string) $row->group_key,
                (string) $row->office_code,
                (string) $row->product_code,
                (string) $row->akad_code,
                number_format((float) $row->ead, 2, ',', '.'),
                number_format((float) $row->pd, 6, '.', '') . '%',
                number_format((float) $row->lgd * 100, 2, ',', '.') . '%',
                number_format((float) $row->ckpn, 2, ',', '.'),
                'INDIVIDUAL',
                $row->reason ?? '',
            ], [$baseStyle], null, null, null, null, null, null, null, null, null));
        }

        // CKPN Collective per segment
        $collectives = $this->getCkpnCollective($period->id);
        foreach ($collectives as $row) {
            $writer->addRow(Row::fromValues([
                'COLLECTIVE',
                (string) $row->group_key,
                (string) $row->office_code,
                (string) $row->product_code,
                (string) $row->akad_code,
                number_format((float) $row->ead, 2, ',', '.'),
                number_format((float) $row->pd, 6, '.', '') . '%',
                number_format((float) $row->lgd * 100, 2, ',', '.') . '%',
                number_format((float) $row->ckpn, 2, ',', '.'),
                'COLLECTIVE',
                'Total: ' . $row->member_count . ' loans',
            ], [$baseStyle], null, null, null, null, null, null, null, null, null));
        }

        // Consolidated Summary
        $consolidated = DB::table('stage_ckpn_results')
            ->where('assessment_period_id', $period->id)
            ->select(
                'total_individual_ckpn',
                'total_collective_ckpn',
                DB::raw('COALESCE(total_individual_ckpn, 0) + COALESCE(total_collective_ckpn, 0) as total_ckpn')
            )
            ->first();

        $consolidatedStyle = (clone $baseStyle)
            ->setFontBold(true)
            ->setBorder(new Border(Border::WIDTH_THICK, Border::STYLE_SOLID, Border::WIDTH_THICK, Border::STYLE_SOLID, Border::WIDTH_THICK, Border::STYLE_SOLID, Border::WIDTH_THICK, Border::STYLE_SOLID));

        $writer->addRow(Row::fromValues([
            'CONSOLIDATED',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            number_format((float) $consolidated->total_ckpn, 2, ',', '.'),
            'TOTAL CKPN',
            sprintf(
                'Individual: %s, Collective: %s',
                number_format((float) $consolidated->total_individual_ckpn, 2, ',', '.'),
                number_format((float) $consolidated->total_collective_ckpn, 2, ',', '.')
            ),
        ], [$consolidatedStyle], null, null, null, null, null, null, null, null, null, null));
    }

    /**
     * Helper method untuk fetch CKPN Individual.
     */
    private function getCkpnIndividual(int $periodId): array
    {
        return DB::table('stage_ckpn_individual_results')
            ->where('assessment_period_id', $periodId)
            ->get()
            ->map(fn ($r) => (object) [
                'group_key' => $r->getRawOriginal('group_key'),
                'office_code' => explode('|', (string) $r->getRawOriginal('group_key'))[0] ?? '',
                'product_code' => explode('|', (string) $r->getRawOriginal('group_key'))[1] ?? '',
                'akad_code' => explode('|', (string) $r->getRawOriginal('group_key'))[2] ?? '',
                'ead' => $r->ead,
                'pd' => $r->pd,
                'lgd' => $r->lgd,
                'ckpn' => $r->ckpn,
                'reason' => $r->reason,
            ])
            ->toArray();
    }

    /**
     * Helper method untuk fetch CKPN Collective per segment.
     */
    private function getCkpnCollective(int $periodId): array
    {
        return DB::table('stage_ckpn_collective_results')
            ->where('assessment_period_id', $periodId)
            ->get()
            ->map(fn ($r) => (object) [
                'group_key' => $r->group_key,
                'office_code' => explode('|', $r->group_key)[0] ?? '',
                'product_code' => explode('|', $r->group_key)[1] ?? '',
                'akad_code' => explode('|', $r->group_key)[2] ?? '',
                'ead' => $r->ead,
                'pd' => $r->pd,
                'lgd' => $r->lgd,
                'ckpn' => $r->ckpn,
                'member_count' => $r->member_count,
            ])
            ->toArray();
    }

    /**
     * Section 3: PD Detail (Netflow atau Migration sesuai config PD method).
     */
    private function writePdDetail(AssessmentPeriod $period, string $pdMethod, Writer $writer, Style $baseStyle): void
    {
        $method = strtoupper($pdMethod);
        if (!in_array($method, ['NETFLOW', 'MIGRATION'])) {
            $method = 'NETFLOW';
        }

        $style = (clone $baseStyle)
            ->setFontBold(true)
            ->setBorder(new Border(Border::WIDTH_THIN, Border::STYLE_SOLID));

        $headers = [
            'Group Key',
            'PD Calculated',
            'Average PD',
            'Number Periods',
            'Rating PD (15)',
            'Rating PD (15)',
            'Rating PD (15)',
            'Rating PD (15)',
            'Rating PD (15)',
            'Rating PD (15)',
            'Rating Exposure (15)',
            'Rating Exposure (15)',
            'Rating Exposure (15)',
            'Rating Exposure (15)',
            'Rating Exposure (15)',
        ];

        $writer->addRow(Row::fromValues($headers, [$style], null, null, null, null, null, null, null, null, null, null, null, null, null, null));

        // PD Results
        $rows = DB::table('stage_pd_results')
            ->where('assessment_period_id', $period->id)
            ->where('method', $method)
            ->orderBy('group_key')
            ->orderBy('bucket_no')
            ->get();

        $grouped = $rows->groupBy('group_key');
        foreach ($grouped as $groupKey => $records) {
            $firstRecord = $records->first();
            $detail = json_decode($firstRecord->detail ?? '{}', true) ?: [];

            $rowType = 'GROUP_SUMMARY';
            $rowCount = 1;

            foreach ($records as $record) {
                $ratingNo = $record->bucket_no;
                $ratingPd = $detail['rating_pd'][$ratingNo] ?? 0;
                $ratingExposure = $detail['rating_exposure'][$ratingNo] ?? 0;

                $writer->addRow(Row::fromValues([
                    $groupKey,
                    number_format((float) $record->pd_calculated * 100, 2, ',', '.') . '%',
                    '',
                    $record->periods_analyzed,
                    $ratingNo === 1 ? number_format((float) $ratingPd * 100, 2, ',', '.') . '%' : '',
                    $ratingNo === 2 ? number_format((float) $ratingPd * 100, 2, ',', '.') . '%' : '',
                    $ratingNo === 3 ? number_format((float) $ratingPd * 100, 2, ',', '.') . '%' : '',
                    $ratingNo === 4 ? number_format((float) $ratingPd * 100, 2, ',', '.') . '%' : '',
                    $ratingNo === 5 ? number_format((float) $ratingPd * 100, 2, ',', '.') . '%' : '',
                    $ratingNo === 1 ? number_format((float) $ratingExposure, 0, ',', '.') : '',
                    $ratingNo === 2 ? number_format((float) $ratingExposure, 0, ',', '.') : '',
                    $ratingNo === 3 ? number_format((float) $ratingExposure, 0, ',', '.') : '',
                    $ratingNo === 4 ? number_format((float) $ratingExposure, 0, ',', '.') : '',
                    $ratingNo === 5 ? number_format((float) $ratingExposure, 0, ',', '.') : '',
                ], [$baseStyle], null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null));
            }
        }

        // Total PD Average (summary row di akhir)
        $totalPd = DB::table('stage_pd_results')
            ->where('assessment_period_id', $period->id)
            ->where('method', $method)
            ->avg('pd_calculated');

        $writer->addRow(Row::fromValues([
            'TOTAL_AVERAGE_PD',
            number_format((float) $totalPd * 100, 4, '.', '') . '%',
            number_format((float) $totalPd * 100, 2, ',', '.') . '%',
            DB::table('stage_pd_results')->where('assessment_period_id', $period->id)->where('method', $method)->max('periods_analyzed'),
            '', '', '', '', '', '', '', '', '', '',
        ], [$baseStyle], null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null));
    }

    /**
     * Section 4: LGD Detail (ER + CS + Weighted per segment).
     */
    private function writeLgdDetail(AssessmentPeriod $period, Writer $writer, Style $baseStyle): void
    {
        // Section 4A: LGD ER
        $this->writeSectionHeader($writer, 'LGD ER', 'Detail LGD Expected Recovery per segment');
        $this->writeLgdErDetail($period, $writer, $baseStyle);

        // Section 4B: LGD CS
        $this->writeSectionHeader($writer, 'LGD CS', 'Detail LGD Collateral Shortfall per segment');
        $this->writeLgdCsDetail($period, $writer, $baseStyle);

        // Section 4C: LGD Weighted
        $this->writeSectionHeader($writer, 'LGD Weighted', 'Detail LGD Weighted per segment');
        $this->writeLgdWeightedDetail($period, $writer, $baseStyle);
    }

    /**
     * Section 4A: LGD ER Detail.
     */
    private function writeLgdErDetail(AssessmentPeriod $period, Writer $writer, Style $baseStyle): void
    {
        $style = (clone $baseStyle)
            ->setFontBold(true)
            ->setBorder(new Border(Border::WIDTH_THIN, Border::STYLE_SOLID));

        $headers = [
            'Group Key',
            'Total Write Off',
            'Total Recovery',
            'Recovery Rate',
            'LGD ER Rate',
        ];

        $writer->addRow(Row::fromValues($headers, [$style], null, null, null, null));

        $rows = DB::table('stage_lgd_er_results')
            ->where('assessment_period_id', $period->id)
            ->orderBy('group_key')
            ->get();

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues([
                $row->group_key,
                number_format((float) $row->total_write_off, 2, ',', '.'),
                number_format((float) $row->total_recovery, 2, ',', '.'),
                number_format((float) $row->recovery_rate * 100, 2, ',', '.') . '%',
                number_format((float) $row->lgd_rate * 100, 2, ',', '.') . '%',
            ], [$baseStyle], null, null, null, null, null, null, null, null));
        }
    }

    /**
     * Section 4B: LGD CS Detail.
     */
    private function writeLgdCsDetail(AssessmentPeriod $period, Writer $writer, Style $baseStyle): void
    {
        $style = (clone $baseStyle)
            ->setFontBold(true)
            ->setBorder(new Border(Border::WIDTH_THIN, Border::STYLE_SOLID));

        $headers = [
            'Group Key',
            'Baki Debet H-1',
            'Outstanding Write Off',
            'Total Balance (Baki + WO)',
            'Net Likuidasi',
            'Total Recovery WO',
            'Shortfall',
            'LGD CS Rate',
        ];

        $writer->addRow(Row::fromValues($headers, [$style], null, null, null, null, null, null, null, null));

        $rows = DB::table('stage_lgd_cs_results')
            ->where('assessment_period_id', $period->id)
            ->orderBy('group_key')
            ->get();

        foreach ($rows as $row) {
            $totalBalance = (float) ($row->baki_debet_h1 ?? 0) + (float) ($row->outstanding_wo ?? 0);
            $netLikuidasi = (float) ($row->likuidasi_value ?? 0) - (float) ($row->sales_cost_pct * $row->likuidasi_value ?? 0);
            $totalRecovery = (float) $row->total_recovery_wo;
            $shortfall = $totalBalance - $netLikuidasi + $totalRecovery;

            $writer->addRow(Row::fromValues([
                $row->group_key,
                number_format((float) $row->baki_debet_h1, 2, ',', '.'),
                number_format((float) $row->outstanding_wo, 2, ',', '.'),
                number_format((float) $totalBalance, 2, ',', '.'),
                number_format((float) $netLikuidasi, 2, ',', '.'),
                number_format((float) $totalRecovery, 2, ',', '.'),
                number_format((float) $shortfall, 2, ',', '.'),
                number_format((float) $row->lgd_rate * 100, 2, ',', '.') . '%',
            ], [$baseStyle], null, null, null, null, null, null, null, null, null));
        }
    }

    /**
     * Section 4C: LGD Weighted Detail.
     */
    private function writeLgdWeightedDetail(AssessmentPeriod $period, Writer $writer, Style $baseStyle): void
    {
        $style = (clone $baseStyle)
            ->setFontBold(true)
            ->setBorder(new Border(Border::WIDTH_THIN, Border::STYLE_SOLID));

        $headers = [
            'Group Key',
            'WO ER',
            'Baki CS',
            'Total WO',
            'Loss ER',
            'Shortfall CS',
            'Total LGD',
            'LGD Weighted Rate',
        ];

        $writer->addRow(Row::fromValues($headers, [$style], null, null, null, null, null, null, null, null));

        $rows = DB::table('stage_lgd_weighted_results')
            ->where('assessment_period_id', $period->id)
            ->orderBy('group_key')
            ->get();

        foreach ($rows as $row) {
            $woEr = (float) $row->wo_er ?? 0;
            $bakiCs = (float) $row->baki_cs ?? 0;
            $totalWo = $woEr + $bakiCs;

            $lossEr = $woEr - (float) ($row->total_recovery_er ?? 0);
            $shortfallCs = (float) $row->shortfall_cs ?? 0;
            $totalLgd = $lossEr + $shortfallCs;

            $writer->addRow(Row::fromValues([
                $row->group_key,
                number_format((float) $woEr, 2, ',', '.'),
                number_format((float) $bakiCs, 2, ',', '.'),
                number_format((float) $totalWo, 2, ',', '.'),
                number_format((float) $lossEr, 2, ',', '.'),
                number_format((float) $shortfallCs, 2, ',', '.'),
                number_format((float) $totalLgd, 2, ',', '.'),
                number_format((float) $row->lgd_rate * 100, 2, ',', '.') . '%',
            ], [$baseStyle], null, null, null, null, null, null, null, null, null));
        }

        // Total LGD Weighted (summary row)
        $totalWo = DB::table('stage_lgd_weighted_results')
            ->where('assessment_period_id', $period->id)
            ->sum(DB::raw("COALESCE(wo_er, 0) + COALESCE(baki_cs, 0)"));
        $totalLgd = DB::table('stage_lgd_weighted_results')
            ->where('assessment_period_id', $period->id)
            ->sum(DB::raw("(COALESCE(wo_er, 0) - COALESCE(total_recovery_er, 0)) + COALESCE(shortfall_cs, 0)"));

        $writer->addRow(Row::fromValues([
            'TOTAL',
            number_format((float) $totalWo, 2, ',', '.'),
            '',
            number_format((float) $totalWo, 2, ',', '.'),
            number_format((float) ($totalWo - DB::table('stage_lgd_weighted_results')->where('assessment_period_id', $period->id)->sum('total_recovery_er')), 2, ',', '.'),
            '',
            number_format((float) $totalLgd, 2, ',', '.'),
            number_format((float) $totalLgd > 0 ? ($totalLgd / $totalWo * 100) : 0, 2, ',', '.') . '%',
        ], [$baseStyle], null, null, null, null, null, null, null, null, null, null));
    }

    /**
     * Section 5: Summary Tables.
     */
    private function writeSummaryTables(AssessmentPeriod $period, Writer $writer, Style $baseStyle): void
    {
        $this->writeSectionHeader($writer, 'SUMMARY TABLES', 'Summary tables transversal');

        // 5.1: CKPN by Office
        $style = (clone $baseStyle)
            ->setFontBold(true)
            ->setBorder(new Border(Border::WIDTH_THIN, Border::STYLE_SOLID));

        $headers = [
            'Office Code',
            'Total Individual CKPN',
            'Total Collective CKPN',
            'Total CKPN',
        ];

        $writer->addRow(Row::fromValues($headers, [$style], null, null, null));

        $rows = DB::table('stage_ckpn_collective_results')
            ->select('office_code', DB::raw('SUM(ckpn) as ckpn'))
            ->groupBy('office_code')
            ->get();

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues([
                $row->office_code,
                '', // Individual tidak grouped by office in current implementation
                number_format((float) $row->ckpn, 2, ',', '.'),
                number_format((float) $row->ckpn, 2, ',', '.'),
            ], [$baseStyle], null, null, null, null, null, null, null, null));
        }

        // 5.2: LGD Weighted by Office
        $headers = [
            'Office Code',
            'Total WO',
            'Total LGD',
            'LGD Weighted Rate',
        ];

        $writer->addRow(Row::fromValues($headers, [$style], null, null, null));

        $rows = DB::table('stage_lgd_weighted_results')
            ->select('office_code', DB::raw('SUM(wo_er + baki_cs) as total_wo'), DB::raw('SUM(loss_er + shortfall_cs) as total_lgd'))
            ->groupBy('office_code')
            ->get();

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues([
                $row->office_code,
                number_format((float) $row->total_wo, 2, ',', '.'),
                number_format((float) $row->total_lgd, 2, ',', '.'),
                number_format((float) $row->total_lgd > 0 ? ($row->total_lgd / $row->total_wo * 100) : 0, 2, ',', '.') . '%',
            ], [$baseStyle], null, null, null, null, null, null, null, null));
        }

        // 5.3: PD by Office
        $pdMethod = SystemConfiguration::getValue('pd_method', 'NETFLOW');
        $headers = [
            'Office Code',
            'Total PD (%)',
            'Number Groups',
            'Number Periods Analyzed',
        ];

        $writer->addRow(Row::fromValues($headers, [$style], null, null, null));

        $rows = DB::table('stage_pd_results')
            ->where('assessment_period_id', $period->id)
            ->where('method', $pdMethod)
            ->groupBy('office_code')
            ->select('office_code', DB::raw('AVG(pd_calculated * 100) as avg_pd'), DB::raw('COUNT(DISTINCT group_key) as groups_count'))
            ->get();

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues([
                $row->office_code,
                number_format((float) $row->avg_pd, 2, ',', '.') . '%',
                (int) $row->groups_count,
                DB::table('stage_pd_results')
                    ->where('assessment_period_id', $period->id)
                    ->where('method', $pdMethod)
                    ->max('periods_analyzed'),
            ], [$baseStyle], null, null, null, null, null, null, null, null));
        }
    }
}