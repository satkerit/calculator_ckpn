<?php

namespace App\Http\Controllers\Calculation;

use App\Http\Controllers\Controller;
use App\Models\AssessmentPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Helper bersama untuk 9 modul CKPN Calculation.
 *
 * Semua modul eksekusi sinkron (sync < 60 detik) dan menulis hasil ke tabel
 * stage_* yang sama seperti pipeline lama agar report existing tetap jalan.
 */
abstract class BaseCalculationController extends Controller
{
    /** Default config bila belum diset user. */
    protected const CONFIG_DEFAULTS = [
        'pd_method' => 'NET_FLOW',
        'lgd_method' => 'WEIGHTED',
        'historical_months' => 36,
        'lgd_historical_months' => 60,
        'sale_cost_percentage' => 0.05,
        'segment_dimensions' => 'OFFICE',
        // Kriteria CKPN Individual: debitur NPL (kualitas/kolektibilitas 3,4,5)
        // dengan TOP N outstanding terbesar.
        'individual_top_n' => 30,
        'npl_ratings' => [3, 4, 5],
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
        $config['historical_months'] = (int) $config['historical_months'];
        $config['lgd_historical_months'] = (int) $config['lgd_historical_months'];
        $config['sale_cost_percentage'] = (float) $config['sale_cost_percentage'];
        $config['individual_top_n'] = max(0, (int) $config['individual_top_n']);

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
        $values['created_at'] = now();
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

        // Hanya tampilkan periode terakhir; JANGAN auto-create dummy pada GET
        // (sebelumnya membuat baris 'DEFAULT' diam-diam hanya dari membuka halaman).
        $period = AssessmentPeriod::orderByDesc('assessment_date')->first();

        abort_if($period === null, 422, 'Belum ada periode assessment. Buat periode terlebih dahulu.');

        return $period;
    }

    /**
     * Daftar periode untuk dropdown. Bila belum ada periode sama sekali,
     * kembalikan koleksi kosong (view menampilkan state kosong) — tidak lagi
     * membuat baris 'DEFAULT' diam-diam.
     */
    protected function periods(): Collection
    {
        return AssessmentPeriod::orderByDesc('assessment_date')->get();
    }

    /**
     * Jalankan proses kalkulasi di bawah lock per-periode agar dua user tidak
     * memproses periode yang sama secara bersamaan (mencegah hasil campuran).
     *
     * @template T
     *
     * @param  callable():T  $callback
     * @return T
     */
    protected function withRunLock(int $assessmentPeriodId, callable $callback)
    {
        $lock = Cache::lock("ckpn_run_{$assessmentPeriodId}", 600);

        if (! $lock->block(5)) {
            abort(423, 'Perhitungan periode ini sedang berjalan oleh proses lain. Coba lagi setelah selesai.');
        }

        try {
            return $callback();
        } finally {
            optional($lock)->release();
        }
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
        $dims = array_values(array_intersect($dims, $allowed));

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
        $parts = [];

        foreach (['OFFICE', 'AKAD', 'USAGE'] as $dim) {
            if (! in_array($dim, $dims, true)) {
                continue;
            }

            $value = match ($dim) {
                'OFFICE' => $office ?: 'NA',
                'AKAD' => $akad ?: 'NA',
                'USAGE' => $usage ?: 'NA',
            };

            $segments[$dim] = $value;
            $parts[] = $value;
        }

        return [
            'group_key' => implode('|', $parts),
            'segments' => $segments,
        ];
    }

    /**
     * Base keys yang membatalkan hasil kalkulasi saat nilainya berubah.
     *
     * - PD: dimensi segmentasi (group_key) + metode PD + metode LGD (untuk
     *   konsolidasi) — semuanya langsung memengaruhi nilai per grup.
     *   `historical_months` ikut dibandingkan karena dipakai PD Netflow maupun
     *   PD Migration (lihat {@see fingerprintKeys()}).
     * - CKPN Kolektif: dimensi segmentasi + metode PD + metode LGD + window PD.
     * - LGD: dimensi segmentasi + metode LGD yang dipakai saat konsolidasi.
     */
    protected const FINGERPRINT_KEYS = [
        'PD' => ['segment_dimensions', 'pd_method'],
        'CKPN' => ['segment_dimensions', 'pd_method', 'lgd_method'],
        'LGD' => ['segment_dimensions', 'lgd_method'],
    ];

    /**
     * Fingerprint keys untuk result type.
     *
     * `historical_months` selalu disertakan untuk PD & CKPN Kolektif: window
     * historis dipakai oleh kedua metode PD — Net Flow memakainya sebagai
     * jumlah titik bucket bulanan, Migration sebagai jumlah matriks triwulan
     * (floor(window / 3)). Mengubahnya karena itu membatalkan hasil PD metode
     * aktif beserta CKPN Kolektif yang diturunkan darinya.
     *
     * @param  array<string, mixed>  $config  konfigurasi efektif saat run
     * @return array<int, string>
     */
    protected function fingerprintKeys(string $resultType, array $config): array
    {
        $keys = self::FINGERPRINT_KEYS[$resultType] ?? self::FINGERPRINT_KEYS['PD'];

        if (in_array($resultType, ['PD', 'CKPN'], true) && ! in_array('historical_months', $keys, true)) {
            $keys[] = 'historical_months';
        }

        return $keys;
    }

    /**
     * Fingerprint konfigurasi untuk deteksi hasil basi (stale).
     *
     * Dimensi disortir + dinormalkan agar urutan pilihan user tidak memengaruhi
     * hash, dan overrides dapat membawa nilai efektif yang benar-benar dipakai
     * saat run (mis. dimensi dari request, bukan dari config tersimpan).
     *
     * @param  array<string, mixed>  $config
     * @param  array<int, string>  $keys
     */
    protected function fingerprintFrom(array $config, array $keys): string
    {
        $parts = [];

        foreach ($keys as $key) {
            $value = $config[$key] ?? '';

            if ($key === 'segment_dimensions') {
                $dims = is_array($value) ? $value : preg_split('/[|,]/', (string) $value);
                $dims = array_values(array_unique(array_filter(array_map(
                    fn ($d) => strtoupper(trim((string) $d)),
                    (array) $dims
                ))));
                $dims = array_values(array_intersect($dims, ['OFFICE', 'AKAD', 'USAGE']));
                sort($dims);
                $value = implode('|', $dims);
            } elseif ($key === 'historical_months') {
                $value = (int) $value;
            } elseif ($key === 'pd_method') {
                $value = strtoupper((string) $value);
            }

            $parts[$key] = $value;
        }

        return sha1((string) json_encode($parts));
    }

    /**
     * Tandai bahwa hasil kalkulasi $resultType untuk periode dihitung dengan
     * konfigurasi saat ini (dipanggil setelah run sukses).
     *
     * @param  array<string, mixed>  $overrides  nilai efektif yang dipakai saat run
     */
    protected function markResults(int $assessmentPeriodId, string $resultType, array $overrides = []): void
    {
        if (! Schema::hasTable('stage_calculation_marks')) {
            return;
        }

        $config = array_merge($this->configFor($assessmentPeriodId), $overrides);
        $keys = $this->fingerprintKeys($resultType, $config);

        DB::table('stage_calculation_marks')->updateOrInsert(
            ['assessment_period_id' => $assessmentPeriodId, 'result_type' => $resultType],
            ['config_fingerprint' => $this->fingerprintFrom($config, $keys), 'updated_at' => now()]
        );
    }

    /**
     * Daftar hasil kalkulasi yang TIDAK VALID karena konfigurasi berubah setelah
     * dihitung (mis. segmentasi ulang → group_key & hasil PD tidak akurat).
     *
     * Hasil yang ada tapi tanpa penanda (data lama / ditulis manual) ikut dianggap
     * basi — tidak diketahui konfigurasi apa yang dipakai saat dihitung.
     *
     * @return array<int, string> subset dari ['PD', 'LGD', 'CKPN']
     */
    protected function staleResultTypes(int $assessmentPeriodId): array
    {
        $tables = [
            'PD' => 'stage_pd_results',
            'LGD' => 'stage_lgd_results',
            'CKPN' => 'stage_collective_ckpn',
        ];

        $config = $this->configFor($assessmentPeriodId);
        $stale = [];

        foreach ($tables as $type => $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $hasResults = DB::table($table)
                ->where('assessment_period_id', $assessmentPeriodId)
                ->exists();
            if (! $hasResults) {
                continue;
            }

            $mark = null;
            if (Schema::hasTable('stage_calculation_marks')) {
                $mark = DB::table('stage_calculation_marks')
                    ->where('assessment_period_id', $assessmentPeriodId)
                    ->where('result_type', $type)
                    ->value('config_fingerprint');
            }

            if ($mark !== $this->fingerprintFrom($config, $this->fingerprintKeys($type, $config))) {
                $stale[] = $type;
            }
        }

        return $stale;
    }

    /** Label hasil yang ramah dibaca user (untuk pesan error/banner). */
    protected function staleResultLabel(string $resultType): string
    {
        return match ($resultType) {
            'PD' => 'PD Net Flow / Migration',
            'LGD' => 'LGD (ER & CS)',
            'CKPN' => 'CKPN Kolektif',
            default => $resultType,
        };
    }

    /**
     * Hapus hasil lama untuk periode (scoped per tabel stage).
     */
    protected function cleanStage(int $assessmentPeriodId, array $tables): void
    {
        foreach ($tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'assessment_period_id')) {
                DB::table($table)->where('assessment_period_id', $assessmentPeriodId)->delete();
            }
        }
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
            'user_id' => auth()->id(),
            'agent_name' => $module,
            'action' => $action,
            'module' => 'CKPN_CALCULATION',
            'record_type' => $module,
            'record_id' => $assessmentPeriodId,
            'new_value' => json_encode($detail),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Redirect kembali ke halaman modul dengan flash result.
     * Mode progress (fetch + polling): balas JSON target redirect agar flash
     * session tidak ikut terkonsumsi oleh permintaan fetch.
     */
    protected function backTo(Request $request, string $route, array $result, ?string $error = null): RedirectResponse|JsonResponse
    {
        $redirect = redirect()->route($route, $request->input('assessment_period_id'));

        if ($request->filled('progress_token')) {
            if ($error) {
                // Mode progress: tanggapi JSON penanda error agar frontend dapat
                // menghentikan animasi & menampilkan pesan kesalahan.
                return response()->json(['error' => $error]);
            }

            return response()->json(['redirect' => $redirect->with('success', $this->formatSummary($result))->with('result', $result)->getTargetUrl()]);
        }

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
    protected function loadCollectiveGroups(int $periodId, ?array $dims = null): Collection
    {
        $dims = empty($dims) ? ['OFFICE', 'AKAD', 'USAGE'] : $dims;

        $columns = ['OFFICE' => 'office_code', 'AKAD' => 'akad_code', 'USAGE' => 'usage_code'];
        $select = [];
        $group = [];

        foreach ($columns as $dim => $column) {
            if (in_array($dim, $dims, true)) {
                $select[] = "COALESCE({$column}, 'NA') as {$column}";
                $group[] = "COALESCE({$column}, 'NA')";
            } else {
                $select[] = "'NA' as {$column}";
            }
        }

        return DB::table('stage_classifications')
            ->where('assessment_period_id', $periodId)
            ->where('classification', 'COLLECTIVE')
            ->selectRaw(implode(', ', $select).', COUNT(*) as member_count')
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
            $parts[] = str_replace('_', ' ', $key).': '.$value;
        }

        return $parts ? implode(' · ', $parts) : 'Selesai.';
    }

    /**
     * Ambil token progress valid dari request (opsional; no-op bila tidak ada).
     */
    protected function progressToken(Request $request): ?string
    {
        $token = (string) $request->input('progress_token', '');

        return preg_match('/^[A-Za-z0-9_.\-]{8,80}$/', $token) ? $token : null;
    }

    /**
     * Store cache khusus progress realtime.
     *
     * WAJIB non-database: progress sering ditulis dari DALAM DB::transaction()
     * (mis. loop matriks PD). Bila memakai store `database`, baris cache belum
     * ter-commit sehingga request polling dari koneksi lain tidak melihatnya —
     * animasi baru bergerak setelah transaksi selesai. Store `file` bebas dari
     * batasan transaksi sehingga persentase tampil bertahap.
     */
    protected function progressStore(): string
    {
        return (string) config('calculation.progress_store', 'file');
    }

    /**
     * Progress realtime untuk form berat: dipush ke Cache (store file),
     * dibaca frontend via endpoint polling calculation.progress.
     *
     * @param  string|array<string, mixed>  $step  Label langkah, atau array {current,total,label}
     */
    protected function pushProgress(?string $token, string $message, float $percentage, $step = null): void
    {
        if ($token === null) {
            return;
        }

        $store = Cache::store($this->progressStore());

        // Throttle: minimal 0.8 detik ATAU kenaikan >= 2 poin, agar tidak membanjiri
        // write cache. Status 100/0 dan pesan berubah tetap lolos.
        if (! app()->runningUnitTests()) {
            $prev = $store->get("ckpn_progress_{$token}");
            if (is_array($prev)) {
                $elapsed = now()->diffInMilliseconds(Carbon::parse($prev['updated_at']));
                $delta = abs($percentage - (float) ($prev['percentage'] ?? 0));
                $sameMsg = ($prev['message'] ?? null) === $message;
                if ($elapsed < 800 && $delta < 2 && $percentage < 100 && $sameMsg) {
                    return;
                }
            }
        }

        $store->put("ckpn_progress_{$token}", [
            'message' => $message,
            'percentage' => max(0.0, min(100.0, $percentage)),
            'step' => is_array($step) ? ($step['label'] ?? null) : $step,
            'current' => is_array($step) ? ($step['current'] ?? null) : null,
            'total' => is_array($step) ? ($step['total'] ?? null) : null,
            'updated_at' => now()->toDateTimeString(),
        ], now()->addMinutes(15));

        // Selesai → singkat TTL agar cache tidak menumpuk baris mati.
        if ($percentage >= 100) {
            $store->put("ckpn_progress_{$token}", [
                'message' => $message,
                'percentage' => 100.0,
                'step' => is_array($step) ? ($step['label'] ?? null) : $step,
                'current' => is_array($step) ? ($step['current'] ?? null) : null,
                'total' => is_array($step) ? ($step['total'] ?? null) : null,
                'updated_at' => now()->toDateTimeString(),
            ], now()->addMinutes(1));
        }
    }
}
