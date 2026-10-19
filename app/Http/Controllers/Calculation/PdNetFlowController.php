<?php

namespace App\Http\Controllers\Calculation;

use App\Services\PdNetflow\PdNetflowCalculator;
use App\Services\PdNetflow\PdNetflowService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Modul 3: PD Net Flow.
 *
 * Hitung PD per grup segmentasi dengan metode Net Flow: EAD-weighted share
 * di bucket loss (≥ 7 = 210+ hari) ditambah compound dari migration matrix
 * historis bila periode historis cukup.
 */
class PdNetFlowController extends BaseCalculationController
{
    public function index(Request $request): View
    {
        $period = $this->resolvePeriod($request);
        $periods = $this->periods();
        $config = $this->configFor($period->id);
        $results = $this->loadResults($period->id);
        // Hasil basi bila konfigurasi (segmentasi/window/metode PD) berubah setelah run terakhir.
        $pdStale = in_array('PD', $this->staleResultTypes($period->id), true);

        return view('calculation.modules.pd_netflow', compact('period', 'periods', 'config', 'results', 'pdStale'));
    }

    public function run(Request $request): RedirectResponse|JsonResponse
    {
        $period = $this->resolvePeriod($request);
        $config = $this->configFor($period->id);
        $histM = (int) $config['historical_months'];
        $dims = $this->segmentDimensions($request, $period->id);
        $token = $this->progressToken($request);

        // Seluruh proses utama dibungkus run-lock per periode (abort 423 bila sedang berjalan).
        return $this->withRunLock($period->id, function () use ($request, $period, $histM, $dims, $token) {
            $this->pushProgress($token, 'Menyiapkan data (window '.$histM.' bulan)…', 4, 'Persiapan');

            // 1) Muat grup segmentasi COLLECTIVE untuk perhitungan PD.
            $groups = $this->loadGroups($period->id, $dims);
            if ($groups->isEmpty()) {
                return $this->backTo($request, 'calculation.module.pd_netflow', [], 'Belum ada klasifikasi COLLECTIVE. Jalankan Modul 2 dulu.');
            }

            // Validasi awal sudah lulus — baru bersihkan stage hasil lama.
            $this->cleanStage($period->id, ['stage_pd_results', 'stage_segmented_snapshots', 'stage_historical_buckets', 'stage_migration_rates', 'stage_compound_flow']);

            // 1) Panggil PdNetflowCalculator untuk menghitung 3 tabel (Bucket Snapshots, Transitions, Compound Flow)
            $calculator = app(PdNetflowCalculator::class);
            $service = app(PdNetflowService::class);

            // Fase tulis atomik: kalkulasi service (menulis ke DB) + penulisan stage_pd_results
            // dalam satu transaksi — bila gagal di tengah tidak ada stage parsial.
            DB::transaction(function () use ($period, $groups, $calculator, $service, $histM, $token) {
                // Matriks dihitung per SEGMEN saja (bucket adalah atribut debitur,
                // bukan dimensi matriks) — dedupe group_key.
                $uniqueKeys = $groups->pluck('group_key')->unique()->values();
                $totalGroups = max(1, $uniqueKeys->count());
                $i = 0;
                foreach ($uniqueKeys as $groupKey) {
                    $i++;
                    $this->pushProgress($token, "Menghitung matriks 3 tabel grup {$i}/{$totalGroups} ({$groupKey})…", 4 + ($i / $totalGroups) * 88, [
                        'current' => $i, 'total' => $totalGroups, 'label' => 'Hitung Matriks Bucket',
                    ]);
                    $calculator->calculate($period->id, $this->paddedGroupKey($groupKey));
                }

                // 2) Simpan PD final per SEGMEN × BUCKET (PD Rate Tabel 3 Compound Flow
                // pada source_bucket sesuai bucket debitur) ke stage_pd_results
                $this->pushProgress($token, 'Menyimpan hasil PD per grup…', 95, 'Menyimpan Hasil');

                DB::table('stage_pd_results')
                    ->where('assessment_period_id', $period->id)
                    ->where('method', 'NET_FLOW')
                    ->delete();

                $batch = [];
                $now = now();
                // Bucket aktif berurutan; bucket terakhir = bucket loss (tidak punya compound flow).
                $activeBucketIds = $service->getActiveBucketIds();
                if (empty($activeBucketIds)) {
                    throw new \RuntimeException('Konfigurasi PD Netflow Bucket aktif tidak ditemukan. Atur bucket terlebih dahulu.');
                }
                $lossBucket = (int) end($activeBucketIds);

                // PD mengikuti SEGMENTASI + BUCKET: debitur dengan segmentasi sama dan
                // bucket sama memakai rate PD compound flow pada source_bucket tersebut.
                foreach ($groups as $g) {
                    $bucketNo = (int) $g->bucket_no;
                    $compoundData = $service->loadCompoundFlows($period->id, $this->paddedGroupKey($g->group_key));

                    // Debitur di bucket loss sudah default: rate dianggap 100%.
                    if ($bucketNo === $lossBucket) {
                        $sourceBucketId = $bucketNo;
                        $pdRate = 1.0;
                    } else {
                        // Tanpa fallback: bila data historical tidak tersedia, perhitungan diblokir.
                        if (! isset($compoundData[$bucketNo])) {
                            throw new \RuntimeException("PD Netflow grup {$g->group_key} bucket {$bucketNo} tidak memiliki data compound flow. Jalankan ulang perhitungan PD.");
                        }
                        $sourceBucketId = (int) $compoundData[$bucketNo]['source_bucket'];
                        $pdRate = min(1.0, max(0.0, (float) $compoundData[$bucketNo]['pd_rate']));
                    }

                    $batch[] = [
                        'assessment_period_id' => $period->id,
                        'group_key' => $g->group_key,
                        'bucket_no' => $bucketNo,
                        'pd_calculated' => round($pdRate, 6),
                        'method' => 'NET_FLOW',
                        'periods_analyzed' => $histM,
                        'detail' => json_encode([
                            'method' => 'NET_FLOW_ENGINE',
                            'source_bucket' => $sourceBucketId,
                            'bucket_no' => $bucketNo,
                            'bucket_label' => $service->bucketLabel($bucketNo),
                            'pd_rate' => round($pdRate, 6),
                            'total_ead' => round((float) $g->total_ead, 2),
                            'member_count' => (int) $g->member_count,
                        ]),
                        'created_at' => $now, 'updated_at' => $now,
                    ];
                }

                foreach (array_chunk($batch, 200) as $chunk) {
                    $this->safeInsert('stage_pd_results', $chunk);
                }
            });

            // Tandai hasil PD sah dengan konfigurasi yang benar-benar dipakai saat run
            // (fingerprint) — konfigurasi berikutnya yang berubah akan menandai hasil basi.
            $this->markResults($period->id, 'PD', ['segment_dimensions' => $dims]);

            $this->audit('PD_NETFLOW', 'RUN', $period->id, [
                'groups' => $groups->count(),
                'window' => $histM,
                'dims' => implode('|', $dims),
            ]);

            $this->pushProgress($token, 'Selesai', 100, 'Selesai');

            // Redirect dgn show=1 agar setelah kalkulasi langsung masuk state "data tampil".
            $redirect = redirect()->route('calculation.module.pd_netflow', [
                'assessment_period_id' => $period->id,
                'show' => 1,
            ]);

            $summary = [
                'groups' => $groups->count(),
                'method' => 'NET_FLOW',
                'window' => $histM.' bln',
            ];

            // Mode fetch+polling: balas JSON target redirect agar flash session
            // tetap utuh untuk navigasi browser berikutnya.
            if ($request->filled('progress_token')) {
                return response()->json(['redirect' => $redirect->with('success', $this->formatSummary($summary))->getTargetUrl()]);
            }

            return $redirect->with('success', $this->formatSummary($summary))->with('result', $summary);
        });
    }

    private function loadGroups(int $periodId, array $dims): Collection
    {
        // Bucket per debitur diambil dari snapshot periode assessment (bucket_no
        // di-assign Modul 2 / di-refresh PdNetflowCalculator), bukan dari stage.
        $periodeYm = $this->assessmentYm($periodId);

        $rows = DB::table('stage_classifications as sc')
            ->leftJoin('snapshot as s', function ($j) use ($periodeYm) {
                $j->on('sc.loan_number', '=', 's.loan_number')
                    ->where('s.periode_snapshot', '=', $periodeYm)
                    ->where('s.active_flag', '=', true);
            })
            ->where('sc.assessment_period_id', $periodId)
            ->where('sc.classification', 'COLLECTIVE')
            ->selectRaw('sc.office_code, sc.akad_code, sc.usage_code, COALESCE(s.bucket_no, 1) as bucket_no, COUNT(*) as member_count, SUM(sc.ead_amount) as total_ead')
            ->groupBy('sc.office_code', 'sc.akad_code', 'sc.usage_code', DB::raw('COALESCE(s.bucket_no, 1)'))
            ->get();

        $groups = $rows->map(function ($g) use ($dims) {
            $seg = $this->buildSegment($dims, $g->office_code, $g->akad_code, $g->usage_code);
            $g->group_key = $seg['group_key'];
            $g->bucket_no = (int) $g->bucket_no;

            return $g;
        });

        // Group by group_key + bucket_no: satu baris PD per kombinasi segmentasi × bucket.
        return $groups->groupBy(fn ($g) => $g->group_key.'|'.$g->bucket_no)->map(function ($subGroups) {
            $first = $subGroups->first();
            $first->member_count = $subGroups->sum('member_count');
            $first->total_ead = $subGroups->sum('total_ead');

            return $first;
        })->sortBy([['group_key', 'asc'], ['bucket_no', 'asc']])->values();
    }

    /**
     * Periode snapshot (Ym) dari assessment_date periode — konsisten dengan
     * parsing yang dipakai ClassificationController & BucketAssignmentService.
     */
    private function assessmentYm(int $periodId): string
    {
        $assessmentDate = DB::table('assessment_periods')->where('id', $periodId)->value('assessment_date');

        return $assessmentDate ? Carbon::parse($assessmentDate)->format('Ym') : '999912';
    }

    /**
     * Normalisasi group_key ke 3 bagian (pad 'NA') agar konsisten dengan tabel
     * matriks PD yang selalu memakai format OFFICE|AKAD|USAGE.
     */
    private function paddedGroupKey(string $groupKey): string
    {
        $parts = explode('|', $groupKey);

        return implode('|', [
            $parts[0] ?? 'NA',
            $parts[1] ?? 'NA',
            $parts[2] ?? 'NA',
        ]);
    }

    private function loadResults(int $periodId): array
    {
        $rows = DB::table('stage_pd_results')
            ->where('assessment_period_id', $periodId)
            ->where('method', 'NET_FLOW')
            ->orderBy('group_key')
            ->orderBy('bucket_no')
            ->get();

        $avg = $rows->isEmpty() ? 0.0 : (float) $rows->avg('pd_calculated');

        return [
            'rows' => $rows,
            'count' => $rows->count(),
            'avg_pd' => round($avg, 6),
            'method' => 'NET_FLOW',
        ];
    }
}
