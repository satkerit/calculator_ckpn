<?php

namespace App\Http\Controllers;

use App\Models\Snapshot;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SnapshotHistoryController extends Controller
{
    public function index(Request $request): View
    {
        $query = Snapshot::query();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('loan_number', 'like', "%{$search}%")
                  ->orWhere('office_code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('office_code')) {
            $query->where('office_code', $request->input('office_code'));
        }

        if ($request->filled('period_from') || $request->filled('period_to')) {
            $from = preg_replace('/[^\d]/', '', (string) $request->input('period_from'));
            $to = preg_replace('/[^\d]/', '', (string) $request->input('period_to'));

            if ($from !== '' && $to !== '') {
                $query->whereBetween('periode_snapshot', [$from, $to]);
            } elseif ($from !== '') {
                $query->where('periode_snapshot', '>=', $from);
            } elseif ($to !== '') {
                $query->where('periode_snapshot', '<=', $to);
            }
        }

        $snapshots = $query->orderByDesc('periode_snapshot')->paginate(50);
        $offices = \App\Models\Office::pluck('office_name', 'office_code')->toArray();

        return view('snapshot-history.index', compact('snapshots', 'offices'));
    }

    public function show(string $id): View
    {
        $snapshot = Snapshot::findOrFail($id);
        return view('snapshot-history.show', compact('snapshot'));
    }

    public function export(Request $request): StreamedResponse
    {
        $query = Snapshot::query();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('loan_number', 'like', "%{$search}%")
                  ->orWhere('office_code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('office_code')) {
            $query->where('office_code', $request->input('office_code'));
        }

        if ($request->filled('period_from') || $request->filled('period_to')) {
            $from = preg_replace('/[^\d]/', '', (string) $request->input('period_from'));
            $to = preg_replace('/[^\d]/', '', (string) $request->input('period_to'));

            if ($from !== '' && $to !== '') {
                $query->whereBetween('periode_snapshot', [$from, $to]);
            } elseif ($from !== '') {
                $query->where('periode_snapshot', '>=', $from);
            } elseif ($to !== '') {
                $query->where('periode_snapshot', '<=', $to);
            }
        }

        $snapshots = $query->orderByDesc('periode_snapshot')->get();

        $response = new StreamedResponse(function () use ($snapshots) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Loan Number', 'Office Code', 'Outstanding Balance', 'Principal Arrears', 'Period Snapshot', 'Created At']);

            foreach ($snapshots as $snapshot) {
                fputcsv($file, [
                    $snapshot->loan_number,
                    $snapshot->office_code,
                    $snapshot->outstanding_balance,
                    $snapshot->principal_arrears,
                    $snapshot->periode_snapshot,
                    $snapshot->created_at,
                ]);
            }

            fclose($file);
        });

        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="snapshot_history.csv"');

        return $response;
    }
}
