<?php

namespace App\Http\Controllers\Calculation;

use App\Services\PdNetflow\PdNetflowCalculator;
use App\Services\PdNetflow\PdNetflowService;
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
                $totalGroups = max(1, $groups->count());
            $i = 0;
            foreach ($groups as $g) {
                $i++;
                $this->pushProgress($token, "Menghitung matriks 3 tabel grup {$i}/{$totalGroups} ({$g->group_key})…", 4 + ($i / $totalGroups) * 88, [
                    'current' => $i, 'total' => $totalGroups, 'label' => 'Hitung Matriks Bucket',
                ]);
                $calculator->calculate($period->id, $this->paddedGroupKey($g->group_key));
            }

                // 2) Simpan PD final (PD Rate dari Bucket 1 Tabel 3 Compound Flow) ke stage_pd_results
                $this->pushProgress($token, 'Menyimpan hasil PD per grup…', 95, 'Menyimpan Hasil');

                DB::table('stage_pd_results')
                    ->where('assessment_period_id', $period->id)
                    ->where('method', 'NET_FLOW')
                    ->delete();

                $batch = [];
                $now = now();
                foreach ($groups as $g) {
                    $compoundData = $service->loadCompoundFlows($period->id, $this->paddedGroupKey($g->group_key));
                    // Ambil PD Rate dari Bucket 1 (Lancar / 0 hari) — titik awal peminjam.
                    $pdRate = (float) ($compoundData[1]['pd_rate'] ?? 0.0);
                    $pdRate = min(1.0, max(0.0, $pdRate));

                    $batch[] = [
                        'assessment_period_id' => $period->id,
                        'group_key' => $g->group_key,
                        'pd_calculated' => round($pdRate, 6),
                        'method' => 'NET_FLOW',
                        'periods_analyzed' => $histM,
                        'detail' => json_encode([
                            'method' => 'NET_FLOW_ENGINE',
                            'source_bucket' => 1,
                            'pd_rate' => round($pdRate, 6),
                            'total_ead' => round((float) $g->total_ead, 2),
                            'member_count' => (int) $g->member_count,
                        ]),
                        'created_at' => $now,
                        'updated_at' => $now,
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
        $query = DB::table('stage_classifications')
            ->where('assessment_period_id', $periodId)
            ->where('classification', 'COLLECTIVE');

        $rows = $query
            ->selectRaw('office_code, akad_code, usage_code, COUNT(*) as member_count, SUM(ead_amount) as total_ead')
            ->groupBy('office_code', 'akad_code', 'usage_code')
            ->get();

        $groups = $rows->map(function ($g) use ($dims) {
            $seg = $this->buildSegment($dims, $g->office_code, $g->akad_code, $g->usage_code);
            $g->group_key = $seg['group_key'];

            return $g;
        });

        // Group by group_key agar count & iterasi sesuai dimensi yang dipilih
        return $groups->groupBy('group_key')->map(function ($subGroups) {
            $first = $subGroups->first();
            $first->member_count = $subGroups->sum('member_count');
            $first->total_ead = $subGroups->sum('total_ead');

            return $first;
        })->values();
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
            ->orderBy('pd_calculated', 'desc')
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
