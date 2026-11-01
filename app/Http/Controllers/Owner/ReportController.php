<?php

namespace App\Http\Controllers\Owner;

use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends OwnerController
{
    public function index(Request $request, ReportService $reports): View|StreamedResponse
    {
        [$from, $to] = $this->resolveDateRange($request);

        if ($request->query('export') === 'csv') {
            return $this->export($request, $reports);
        }

        // Hanya bisnis milik owner yang login yang dihitung.
        $summary = $reports->businessSummary($this->businessIds($request), $from, $to);

        return view('owner.report', [
            'business' => $this->business($request),
            's' => $summary,
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function export(Request $request, ReportService $reports): StreamedResponse
    {
        [$from, $to] = $this->resolveDateRange($request);

        $business = $this->business($request);
        $business->loadMissing('owner:id,name');

        return $reports->streamOwnerCsv(
            $business,
            $this->businessIds($request),
            $from,
            $to
        );
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
