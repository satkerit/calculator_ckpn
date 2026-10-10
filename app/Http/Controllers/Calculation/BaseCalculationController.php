<?php

namespace App\Http\Controllers\Calculation;

use App\Http\Controllers\Controller;
use App\Models\AssessmentPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Helper bersama untuk 8 modul CKPN Calculation.
 *
 * Semua modul eksekusi sinkron (sync < 60 detik) dan menulis hasil ke tabel
 * stage_* yang sama seperti pipeline lama agar report existing tetap jalan.
 */
abstract class BaseCalculationController extends Controller
{
    /** Default config bila belum diset user. */
    protected const CONFIG_DEFAULTS = [
        'pd_method'                => 'NET_FLOW',
        'lgd_method'               => 'WEIGHTED',
        'historical_months'        => 36,
        'lgd_historical_months'    => 60,
        'sale_cost_percentage'     => 0.05,
        'segment_dimensions'       => 'OFFICE',
        // Kriteria CKPN Individual: debitur NPL (kualitas/kolektibilitas 3,4,5)
        // dengan TOP N outstanding terbesar.
        'individual_top_n'         => 30,
        'npl_ratings'              => [3, 4, 5],
    ];

    /**
     * Ambil config untuk periode, fallback ke default bila belum ada.
     */
    protected function configFor(int $assessmentPeriodId): array
    {
        $row = DB::table('stage_configs')
            ->where('assessment_period_id', $assessmentPeriodId)
            ->first();

        $config = self::CONFIG_DEFAULTS;
        if ($row) {
            foreach ($config as $key => $default) {
                if (property_exists($row, $key) && $row->{$key} !== null) {
                    $config[$key] = $row->{$key};
                }
            }
            // Map DB column segmentation_dims → config key segment_dimensions.
            if (property_exists($row, 'segmentation_dims') && $row->segmentation_dims !== null) {
                $config['segment_dimensions'] = $row->segmentation_dims;
            }
        }

        // Normalisasi tipe
        $config['historical_months']        = (int) $config['historical_months'];
        $config['lgd_historical_months']    = (int) $config['lgd_historical_months'];
        $config['sale_cost_percentage']     = (float) $config['sale_cost_percentage'];
        $config['individual_top_n']         = max(0, (int) $config['individual_top_n']);

        // npl_ratings: array kualitas yang dianggap NPL (default 3,4,5).
        $rawRatings = $config['npl_ratings'];
        if (is_string($rawRatings)) {
            $rawRatings = explode(',', $rawRatings);
        }
        $config['npl_ratings'] = collect((array) $rawRatings)
            ->map(fn ($v) => (int) trim((string) $v))
            ->filter(fn ($v) => $v >= 1 && $v <= 5)
            ->unique()
            ->sort()
            ->values()
            ->all();

        if (empty($config['npl_ratings'])) {
            $config['npl_ratings'] = [3, 4, 5];
        }

        return $config;
    }

    /**
     * Simpan / update config periode.
     */
    protected function saveConfig(int $assessmentPeriodId, array $values): void
    {
        // Map config key → DB column (kolom DB bernama segmentation_dims).
        if (array_key_exists('segment_dimensions', $values)) {
            $values['segmentation_dims'] = $values['segment_dimensions'];
            unset($values['segment_dimensions']);
        }

        // Whitelist kolom existing agar mismatch nama kolom tak sebabkan 42S22.
        if (Schema::hasTable('stage_configs')) {
            $cols = Schema::getColumnListing('stage_configs');
            $values = array_intersect_key($values, array_flip($cols));
        }

        $values['updated_at'] = now();

        $exists = DB::table('stage_configs')
            ->where('assessment_period_id', $assessmentPeriodId)
            ->exists();

        if ($exists) {
            DB::table('stage_configs')
                ->where('assessment_period_id', $assessmentPeriodId)
                ->update($values);

            return;
        }

        $values['assessment_period_id'] = $assessmentPeriodId;
        $values['created_at']           = now();
        DB::table('stage_configs')->insert($values);
    }

    /**
     * Resolve AssessmentPeriod dari request (id / model binding) atau auto-create dummy.
     */
    protected function resolvePeriod(Request $request): AssessmentPeriod
    {
        $id = $request->input('assessment_period_id');

        if ($id) {
            return AssessmentPeriod::findOrFail($id);
        }

        $period = AssessmentPeriod::orderByDesc('assessment_date')->first();

        return $period ?: $this->createDefaultPeriod();
    }

    /**
     * Daftar periode untuk dropdown, ensure minimal 1 periode exist.
     */
    protected function periods(): \Illuminate\Support\Collection
    {
        $periods = AssessmentPeriod::orderByDesc('assessment_date')->get();

        return $periods->isNotEmpty() ? $periods : collect([$this->createDefaultPeriod()]);
    }

    /** ponytail: dedupe — kedua resolver di atas butuh perilaku "pastikan ada 1 periode". */
    private function createDefaultPeriod(): AssessmentPeriod
    {
        return AssessmentPeriod::create([
            'period_code' => 'DEFAULT',
            'period_name' => 'Default Assessment Period',
            'fiscal_month' => now()->month,
            'fiscal_year' => now()->year,
            'assessment_date' => now()->toDateString(),
            'status' => 'DRAFT',
            'created_by' => auth()->id() ?? 1,
        ]);
    }

    /**
     * Dimensi segmentasi yang dipilih user, dari request atau config.
     *
     * @return array<int, string> contoh ['OFFICE','AKAD','USAGE']
     */
    protected function segmentDimensions(Request $request, int $assessmentPeriodId): array
    {
        $raw = $request->input('segment_dimensions');

        if ($raw === null) {
            $raw = $this->configFor($assessmentPeriodId)['segment_dimensions'];
        }

        if (is_array($raw)) {
            $dims = $raw;
        } else {
            $dims = preg_split('/[|,]/', (string) $raw) ?: [];
        }

        $dims = array_values(array_unique(array_filter(array_map(
            fn ($d) => strtoupper(trim($d)),
            $dims
        ))));

        $allowed = ['OFFICE', 'AKAD', 'USAGE'];
        $dims    = array_values(array_intersect($dims, $allowed));

        return empty($dims) ? ['OFFICE'] : $dims;
    }

    /**
     * Satu segmen = grup 3 dimensi. Dimensi yang tidak dipilih diabaikan
     * sehingga group_key menjadi combo (mis. OFFICE=01 + AKAD=03 + USAGE=1).
     *
     * @param  array<int, string>  $dims
     * @return array{group_key: string, segments: array<string, ?string>}
     */
    protected function buildSegment(array $dims, ?string $office, ?string $akad, ?string $usage): array
    {
        $segments = [];
        $parts    = [];

        foreach (['OFFICE', 'AKAD', 'USAGE'] as $dim) {
            if (! in_array($dim, $dims, true)) {
                continue;
            }

            $value = match ($dim) {
                'OFFICE' => $office ?: 'NA',
                'AKAD'   => $akad ?: 'NA',
                'USAGE'  => $usage ?: 'NA',
            };

            $segments[$dim] = $value;
            $parts[]        = $value;
        }

        return [
            'group_key' => implode('|', $parts),
            'segments'  => $segments,
        ];
    }

    /**
     * Bersihkan isi satu atau lebih tabel stage untuk periode tertentu.
     * Aman terhadap tabel yang belum exist (fresh install) dan selalu
     * ter-scope ke assessment_period_id sehingga tidak menyentuh master data.
     *
     * @param  array<int, string>  $tables
     */
    protected function cleanStage(int $assessmentPeriodId, array $tables): void
    {
        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            DB::table($table)->where('assessment_period_id', $assessmentPeriodId)->delete();
        }
    }

    /**
     * Upsert aman ke tabel stage. Cek tabel ada + scope ke period.
     * Return jumlah baris ter-insert.
     *
     * @param  array<int, string>  $uniqueBy
     * @param  array<int, string>  $updateOnly
     */
    protected function safeUpsert(string $table, array $rows, array $uniqueBy, array $updateOnly = []): int
    {
        if (! Schema::hasTable($table) || empty($rows)) {
            return 0;
        }
        DB::table($table)->upsert($rows, $uniqueBy, $updateOnly);

        return count($rows);
    }

    /**
     * Insert aman ke tabel stage. Cek tabel ada; skip bila kosong.
     */
    protected function safeInsert(string $table, array $rows): int
    {
        if (! Schema::hasTable($table) || empty($rows)) {
            return 0;
        }
        DB::table($table)->insert($rows);

        return count($rows);
    }

    /**
     * Audit trail untuk compliance (AGENTS.md #14).
     * No-op bila tabel audit_logs belum ada (test/fresh install).
     */
    protected function audit(string $module, string $action, int $assessmentPeriodId, array $detail = []): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        DB::table('audit_logs')->insert([
            'user_id'      => auth()->id(),
            'agent_name'   => $module,
            'action'       => $action,
            'module'       => 'CKPN_CALCULATION',
            'record_type'  => $module,
            'record_id'    => $assessmentPeriodId,
            'new_value'    => json_encode($detail),
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    /**
     * Redirect kembali ke halaman modul dengan flash result.
     */
    protected function backTo(Request $request, string $route, array $result, ?string $error = null): RedirectResponse
    {
        $redirect = redirect()->route($route, $request->input('assessment_period_id'));

        if ($error) {
            return $redirect->with('error', $error);
        }

        return $redirect->with('success', $this->formatSummary($result))->with('result', $result);
    }

    /**
     * Load grup kolektif (office_code, akad_code, usage_code) dari stage_classifications.
     *
     * Dimensi yang tidak dipilih diisi 'NA' sehingga group_key identik dengan
     * group_key PD Netflow (mis. dims OFFICE|AKAD → "01|03|NA").
     *
     * @param  array<int, string>|null  $dims
     */
    protected function loadCollectiveGroups(int $periodId, ?array $dims = null): \Illuminate\Support\Collection
    {
        $dims = empty($dims) ? ['OFFICE', 'AKAD', 'USAGE'] : $dims;

        $columns = ['OFFICE' => 'office_code', 'AKAD' => 'akad_code', 'USAGE' => 'usage_code'];
        $select  = [];
        $group   = [];

        foreach ($columns as $dim => $column) {
            if (in_array($dim, $dims, true)) {
                $select[] = "COALESCE({$column}, 'NA') as {$column}";
                $group[]  = "COALESCE({$column}, 'NA')";
            } else {
                $select[] = "'NA' as {$column}";
            }
        }

        return DB::table('stage_classifications')
            ->where('assessment_period_id', $periodId)
            ->where('classification', 'COLLECTIVE')
            ->selectRaw(implode(', ', $select) . ', COUNT(*) as member_count')
            ->groupByRaw(implode(', ', $group))
            ->get();
    }

    /**
     * Bangun group_key dari baris hasil loadCollectiveGroups, mengikuti dimensi terpilih.
     * Format konsisten dengan buildSegment / pipeline stage_* (mis. dims OFFICE|AKAD → "01|03").
     */
    protected function collectiveGroupKey(array $dims, ?string $office, ?string $akad, ?string $usage): string
    {
        return $this->buildSegment($dims, $office, $akad, $usage)['group_key'];
    }

    protected function formatSummary(array $result): string
    {
        $parts = [];
        foreach ($result as $key => $value) {
            if (is_array($value) || is_bool($value)) {
                continue;
            }
            $parts[] = str_replace('_', ' ', $key) . ': ' . $value;
        }

        return $parts ? implode(' · ', $parts) : 'Selesai.';
    }
}
