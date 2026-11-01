<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request, ReportService $reports): View|StreamedResponse
    {
        [$from, $to] = $this->resolveDateRange($request);

        if ($request->query('export') === 'csv') {
            return $this->export($request, $reports);
        }

        return view('admin.report', [
            'totals' => $reports->platformTotals(),
            's' => $reports->businessSummary(null, $from, $to),
            'rows' => $reports->perBusiness($from, $to),
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function export(Request $request, ReportService $reports): StreamedResponse
    {
        [$from, $to] = $this->resolveDateRange($request);

        return $reports->streamAdminCsv($from, $to);
    }

    /**
     * @return array{0:Carbon,1:Carbon}
     */
    private function resolveDateRange(Request $request): array
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ], [
            'from.date' => 'Tanggal mulai tidak valid.',
            'to.date' => 'Tanggal akhir tidak valid.',
            'to.after_or_equal' => 'Tanggal akhir harus sama dengan atau setelah tanggal mulai.',
        ]);

        $from = $request->filled('from')
            ? Carbon::parse((string) $request->query('from'))
            : now()->startOfMonth();

        $to = $request->filled('to')
            ? Carbon::parse((string) $request->query('to'))
            : now();

        if ($from->gt($to)) {
            $from = $to->copy()->startOfMonth();
        }

        return [$from, $to];
    }
}
