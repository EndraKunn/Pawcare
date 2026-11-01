<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Business;
use App\Models\Payment;
use App\Models\Review;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Laporan dan Analitik dihitung langsung dari tabel transaksi dan ulasan aktual.
 * Definisi Periode:
 * - Metrik Booking dihitung berdasarkan tanggal pembuatan booking (bookings.created_at).
 * - Metrik Pendapatan Terverifikasi dihitung berdasarkan tanggal pelunasan/verifikasi (payments.paid_at)
 *   khusus untuk transaksi berstatus Dibayar (Payment::PAID).
 * $businessIds = null berarti seluruh platform (Admin).
 */
class ReportService
{
    public const PERIODS = [
        'today' => 'Hari Ini',
        '7d' => '7 Hari Terakhir',
        '30d' => '30 Hari Terakhir',
        'month' => 'Bulan Ini',
        'custom' => 'Rentang Tanggal Khusus',
    ];

    /**
     * Resolusi filter periode untuk Dashboard Analitik Owner & Admin.
     *
     * @return array{period:string,from:Carbon,to:Carbon,label:string}
     */
    public function resolvePeriod(Request $request, string $defaultPeriod = 'month'): array
    {
        $request->validate([
            'period' => ['nullable', 'string'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ], [
            'from.date' => 'Tanggal mulai tidak valid.',
            'to.date' => 'Tanggal akhir tidak valid.',
            'to.after_or_equal' => 'Tanggal akhir harus sama dengan atau setelah tanggal mulai.',
        ]);

        $period = (string) $request->query('period', '');
        if ($period === '' && ($request->filled('from') || $request->filled('to'))) {
            $period = 'custom';
        }
        if (! array_key_exists($period, self::PERIODS)) {
            $period = $defaultPeriod;
        }

        $now = now();
        [$from, $to] = match ($period) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            '7d' => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
            '30d' => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
            'custom' => [
                $request->filled('from') ? Carbon::parse((string) $request->query('from'))->startOfDay() : $now->copy()->startOfMonth(),
                $request->filled('to') ? Carbon::parse((string) $request->query('to'))->endOfDay() : $now->copy()->endOfDay(),
            ],
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfDay()],
        };

        if ($from->gt($to)) {
            $from = $to->copy()->startOfDay();
        }

        return [
            'period' => $period,
            'from' => $from,
            'to' => $to,
            'label' => self::PERIODS[$period] ?? 'Bulan Ini',
        ];
    }

    public function businessSummary(?array $businessIds, Carbon $from, Carbon $to): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();

        $bookings = fn () => Booking::query()
            ->when($businessIds !== null, fn ($q) => $q->whereIn('business_id', $businessIds))
            ->whereBetween('bookings.created_at', [$from, $to]);

        $payments = fn () => Payment::query()
            ->when($businessIds !== null, fn ($q) => $q->whereHas('booking', fn ($b) => $b->whereIn('business_id', $businessIds)))
            ->whereBetween('payments.created_at', [$from, $to]);

        $byStatus = [];
        foreach (Booking::STATUSES as $s) {
            $byStatus[$s] = $bookings()->where('status', $s)->count();
        }
        $payByStatus = [];
        foreach (Payment::STATUSES as $s) {
            $payByStatus[$s] = $payments()->where('status', $s)->count();
        }

        $paidQuery = Payment::query()
            ->where('status', Payment::PAID)
            ->when($businessIds !== null, fn ($q) => $q->whereHas('booking', fn ($b) => $b->whereIn('business_id', $businessIds)))
            ->whereBetween('paid_at', [$from, $to]);

        $revenue = (float) (clone $paidQuery)->sum('amount');
        $paidCount = (clone $paidQuery)->count();

        $total = array_sum($byStatus);

        $ratingSummary = $this->ratingSummary($businessIds);
        $customerRetention = $this->customerRetention($businessIds, $from, $to);
        $dailyTrend = $this->dailyTrend($businessIds, $from, $to);
        $periodComparison = $this->periodComparison($businessIds, $from, $to, $total, $revenue);

        return [
            'from' => $from,
            'to' => $to,
            'booking_total' => $total,
            'booking_by_status' => $byStatus,
            'payment_by_status' => $payByStatus,
            'payment_total' => array_sum($payByStatus),
            'revenue' => $revenue,
            'paid_count' => $paidCount,
            'avg_transaction' => $paidCount > 0 ? $revenue / $paidCount : 0,
            'service_count' => Service::query()
                ->when($businessIds !== null, fn ($q) => $q->whereIn('business_id', $businessIds))->count(),
            'monthly' => $this->monthlyBookings($businessIds, $to),
            'top_services' => $this->topServices($businessIds, $from, $to),
            'daily_revenue' => $dailyTrend['revenue'],
            'daily_bookings' => $dailyTrend['bookings'],
            'customers_new' => $customerRetention['new'],
            'customers_repeat' => $customerRetention['repeat'],
            'rating_avg' => $ratingSummary['avg'],
            'rating_count' => $ratingSummary['count'],
            'rating_distribution' => $ratingSummary['distribution'],
            'period_comparison' => $periodComparison,
        ];
    }

    /**
     * Statistik rating dan distribusi bintang 1-5 dari ulasan sah (booking Selesai).
     */
    public function ratingSummary(?array $businessIds): array
    {
        $dist = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];

        if (! Schema::hasTable('reviews')) {
            return ['avg' => 0.0, 'count' => 0, 'distribution' => $dist];
        }

        $query = Review::query()
            ->validCompleted()
            ->when($businessIds !== null, fn ($q) => $q->whereIn('business_id', $businessIds));

        $count = (clone $query)->count();
        $avg = $count > 0 ? round((float) (clone $query)->avg('rating'), 1) : 0.0;

        $rows = (clone $query)
            ->selectRaw('rating, COUNT(*) as total')
            ->groupBy('rating')
            ->pluck('total', 'rating');

        foreach ([5, 4, 3, 2, 1] as $star) {
            $dist[$star] = (int) ($rows[$star] ?? 0);
        }

        return [
            'avg' => $avg,
            'count' => $count,
            'distribution' => $dist,
        ];
    }

    /**
     * Hitung pelanggan baru vs pelanggan berulang pada periode terpilih.
     *
     * @return array{new:int,repeat:int}
     */
    public function customerRetention(?array $businessIds, Carbon $from, Carbon $to): array
    {
        $customerIds = Booking::query()
            ->when($businessIds !== null, fn ($q) => $q->whereIn('business_id', $businessIds))
            ->where('status', '!=', Booking::STATUS_CANCELLED)
            ->whereBetween('created_at', [$from, $to])
            ->distinct()
            ->pluck('customer_id')
            ->all();

        if (empty($customerIds)) {
            return ['new' => 0, 'repeat' => 0];
        }

        $newCount = 0;
        $repeatCount = 0;

        foreach ($customerIds as $cid) {
            $totalValidBookingsUpToEnd = Booking::query()
                ->when($businessIds !== null, fn ($q) => $q->whereIn('business_id', $businessIds))
                ->where('customer_id', $cid)
                ->where('status', '!=', Booking::STATUS_CANCELLED)
                ->where('created_at', '<=', $to)
                ->count();

            $hadPriorBooking = Booking::query()
                ->when($businessIds !== null, fn ($q) => $q->whereIn('business_id', $businessIds))
                ->where('customer_id', $cid)
                ->where('status', '!=', Booking::STATUS_CANCELLED)
                ->where('created_at', '<', $from)
                ->exists();

            if ($hadPriorBooking || $totalValidBookingsUpToEnd >= 2) {
                $repeatCount++;
            } else {
                $newCount++;
            }
        }

        return ['new' => $newCount, 'repeat' => $repeatCount];
    }

    /**
     * Tren harian (atau titik waktu teragregasi) untuk grafik pendapatan & jumlah booking.
     */
    public function dailyTrend(?array $businessIds, Carbon $from, Carbon $to): array
    {
        $daysDiff = max(1, (int) $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1);

        // Ambil maksimal 7 titik agar komponen SVG <x-bars> mudah dipindai
        $points = min(7, $daysDiff);
        $stepDays = max(1, (int) ceil($daysDiff / $points));

        $labels = [];
        $revValues = [];
        $bkValues = [];

        $cursor = $from->copy()->startOfDay();
        while ($cursor->lte($to)) {
            $bucketEnd = $cursor->copy()->addDays($stepDays - 1)->endOfDay();
            if ($bucketEnd->gt($to)) {
                $bucketEnd = $to->copy()->endOfDay();
            }

            $labels[] = $stepDays === 1
                ? $cursor->translatedFormat('d M')
                : $cursor->translatedFormat('d M');

            $revSum = (float) Payment::query()
                ->where('status', Payment::PAID)
                ->when($businessIds !== null, fn ($q) => $q->whereHas('booking', fn ($b) => $b->whereIn('business_id', $businessIds)))
                ->whereBetween('paid_at', [$cursor, $bucketEnd])
                ->sum('amount');

            $bkCount = Booking::query()
                ->when($businessIds !== null, fn ($q) => $q->whereIn('business_id', $businessIds))
                ->whereBetween('created_at', [$cursor, $bucketEnd])
                ->count();

            $revValues[] = round($revSum / 1000, 1); // dalam ribu rupiah
            $bkValues[] = $bkCount;

            $cursor->addDays($stepDays);
        }

        return [
            'revenue' => ['labels' => $labels, 'values' => $revValues],
            'bookings' => ['labels' => $labels, 'values' => $bkValues],
        ];
    }

    /**
     * Ringkasan perubahan aktivitas dibandingkan periode sebelumnya dengan durasi sama.
     */
    public function periodComparison(
        ?array $businessIds,
        Carbon $from,
        Carbon $to,
        int $currentBookings,
        float $currentRevenue
    ): array {
        $days = max(1, (int) $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1);
        $prevTo = $from->copy()->subSecond();
        $prevFrom = $prevTo->copy()->subDays($days - 1)->startOfDay();

        $prevBookings = Booking::query()
            ->when($businessIds !== null, fn ($q) => $q->whereIn('business_id', $businessIds))
            ->whereBetween('created_at', [$prevFrom, $prevTo])
            ->count();

        $prevRevenue = (float) Payment::query()
            ->where('status', Payment::PAID)
            ->when($businessIds !== null, fn ($q) => $q->whereHas('booking', fn ($b) => $b->whereIn('business_id', $businessIds)))
            ->whereBetween('paid_at', [$prevFrom, $prevTo])
            ->sum('amount');

        return [
            'prev_from' => $prevFrom,
            'prev_to' => $prevTo,
            'prev_bookings' => $prevBookings,
            'prev_revenue' => $prevRevenue,
            'booking_diff' => $currentBookings - $prevBookings,
            'revenue_diff' => $currentRevenue - $prevRevenue,
        ];
    }

    /** Jumlah booking per bulan untuk 6 bulan yang berakhir pada bulan $to. */
    public function monthlyBookings(?array $businessIds, Carbon $to): array
    {
        $start = $to->copy()->startOfMonth()->subMonths(5);
        $end = $to->copy()->endOfMonth();

        $rows = Booking::query()
            ->when($businessIds !== null, fn ($q) => $q->whereIn('business_id', $businessIds))
            ->whereBetween('created_at', [$start, $end])
            ->get(['created_at']);

        $counts = [];
        for ($i = 0; $i < 6; $i++) {
            $counts[$start->copy()->addMonths($i)->format('Y-m')] = 0;
        }
        foreach ($rows as $row) {
            $key = $row->created_at->format('Y-m');
            if (isset($counts[$key])) {
                $counts[$key]++;
            }
        }

        $labels = [];
        $values = [];
        foreach ($counts as $key => $n) {
            $labels[] = Carbon::createFromFormat('Y-m-d', $key.'-01')->translatedFormat('M');
            $values[] = $n;
        }

        return ['labels' => $labels, 'values' => $values];
    }

    public function topServices(?array $businessIds, Carbon $from, Carbon $to, int $limit = 5): array
    {
        $rows = DB::table('booking_items')
            ->join('bookings', 'bookings.id', '=', 'booking_items.booking_id')
            ->join('services', 'services.id', '=', 'booking_items.service_id')
            ->when($businessIds !== null, fn ($q) => $q->whereIn('bookings.business_id', $businessIds))
            ->where('bookings.status', '!=', Booking::STATUS_CANCELLED)
            ->whereBetween('bookings.created_at', [$from, $to])
            ->groupBy('services.id', 'services.name')
            ->selectRaw('services.name as name, COUNT(*) as total')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();

        $sum = max(1, $rows->sum('total'));

        return $rows->map(fn ($r) => [
            'name' => $r->name,
            'total' => (int) $r->total,
            'percent' => (int) round($r->total / $sum * 100),
        ])->all();
    }

    /** Rekap per bisnis untuk Admin. */
    public function perBusiness(Carbon $from, Carbon $to)
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();

        $hasReviews = Schema::hasTable('reviews');

        return Business::query()
            ->with('owner:id,name')
            ->withCount([
                'services',
                'bookings as bookings_count' => fn ($q) => $q->whereBetween('created_at', [$from, $to]),
            ])
            ->orderBy('business_name')
            ->get()
            ->each(function (Business $b) use ($from, $to, $hasReviews) {
                $paid = Payment::query()
                    ->where('status', Payment::PAID)
                    ->whereBetween('paid_at', [$from, $to])
                    ->whereHas('booking', fn ($q) => $q->where('business_id', $b->id));
                $b->paid_count = (clone $paid)->count();
                $b->revenue = (float) (clone $paid)->sum('amount');

                if ($hasReviews) {
                    $revQuery = Review::query()->validCompleted()->where('business_id', $b->id);
                    $b->reviews_count = (clone $revQuery)->count();
                    $b->rating_avg = $b->reviews_count > 0 ? round((float) (clone $revQuery)->avg('rating'), 1) : 0.0;
                } else {
                    $b->reviews_count = 0;
                    $b->rating_avg = 0.0;
                }
            });
    }

    public function platformTotals(): array
    {
        return [
            'users' => User::count(),
            'customers' => User::where('role', User::ROLE_CUSTOMER)->count(),
            'owners' => User::where('role', User::ROLE_OWNER)->count(),
            'admins' => User::where('role', User::ROLE_ADMIN)->count(),
            'businesses' => Business::count(),
            'businesses_active' => Business::where('status', true)->count(),
        ];
    }

    /**
     * Proteksi CSV Injection (Formula Injection):
     * Nilai teks yang diawali karakter =, +, -, @, tab, atau CR diberi prefix tanda kutip tunggal (')
     * agar tidak dieksekusi sebagai rumus saat dibuka di Microsoft Excel / LibreOffice Calc.
     */
    public function sanitizeCsvCell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        $text = str_replace(["\r\n", "\r", "\n"], ' ', (string) $value);
        if ($text === '' || $text === '-') {
            return $text;
        }

        $trimmedLeft = ltrim($text);

        if (
            in_array($text[0], ['=', '+', '-', '@', "\t", "\r"], true)
            || ($trimmedLeft !== '' && $trimmedLeft !== '-' && in_array($trimmedLeft[0], ['=', '+', '-', '@'], true))
        ) {
            return "'".$text;
        }

        return $text;
    }

    /**
     * Tulis satu baris ke stream CSV setelah seluruh sel disanitasi dari CSV formula injection.
     */
    private function writeCsvRow($handle, array $row): void
    {
        $sanitized = array_map(fn ($cell) => $this->sanitizeCsvCell($cell), $row);
        fputcsv($handle, $sanitized);
    }

    /**
     * Ekspor CSV Laporan Bisnis khusus untuk Owner (hanya bisnis milik Owner yang sedang login).
     */
    public function streamOwnerCsv(
        Business $business,
        array $businessIds,
        Carbon $from,
        Carbon $to
    ): StreamedResponse {
        $start = $from->copy()->startOfDay();
        $end = $to->copy()->endOfDay();

        $summary = $this->businessSummary($businessIds, $start, $end);
        $periodLabel = $start->format('Y-m-d').' s/d '.$end->format('Y-m-d');
        $slug = Str::slug($business->business_name ?: 'bisnis');
        $filename = "laporan-bisnis-{$slug}-{$start->format('Y-m-d')}-sd-{$end->format('Y-m-d')}.csv";

        $paymentAmountsByStatus = [];
        foreach (Payment::STATUSES as $pStatus) {
            $paymentAmountsByStatus[$pStatus] = (float) Payment::query()
                ->where('status', $pStatus)
                ->whereHas('booking', fn ($b) => $b->whereIn('business_id', $businessIds))
                ->whereBetween('payments.created_at', [$start, $end])
                ->sum('amount');
        }

        $serviceUsage = DB::table('booking_items')
            ->join('bookings', 'bookings.id', '=', 'booking_items.booking_id')
            ->join('services', 'services.id', '=', 'booking_items.service_id')
            ->whereIn('bookings.business_id', $businessIds)
            ->where('bookings.status', '!=', Booking::STATUS_CANCELLED)
            ->whereBetween('bookings.created_at', [$start, $end])
            ->groupBy('services.id', 'services.name', 'services.category', 'services.unit')
            ->selectRaw('services.name, services.category, services.unit, COUNT(*) as booking_count, COALESCE(SUM(booking_items.quantity), 0) as total_qty, COALESCE(SUM(booking_items.price * booking_items.quantity), 0) as total_nominal')
            ->orderByDesc('booking_count')
            ->get();

        $customers = User::query()
            ->where('role', User::ROLE_CUSTOMER)
            ->whereHas('bookings', fn ($b) => $b->whereIn('business_id', $businessIds)->whereBetween('created_at', [$start, $end]))
            ->select(['id', 'name', 'phone'])
            ->withCount([
                'bookings as period_bookings_count' => fn ($b) => $b
                    ->whereIn('business_id', $businessIds)
                    ->whereBetween('created_at', [$start, $end]),
            ])
            ->orderBy('name')
            ->get()
            ->map(function (User $c) use ($businessIds, $start, $end) {
                $paidAmount = (float) Payment::query()
                    ->where('status', Payment::PAID)
                    ->whereBetween('paid_at', [$start, $end])
                    ->whereHas('booking', fn ($b) => $b->whereIn('business_id', $businessIds)->where('customer_id', $c->id))
                    ->sum('amount');

                return [
                    'name' => $c->name,
                    'phone' => $c->phone ?? '-',
                    'bookings_count' => (int) $c->period_bookings_count,
                    'paid_amount' => $paidAmount,
                ];
            });

        $bookings = Booking::query()
            ->whereIn('business_id', $businessIds)
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('bookings.created_at', [$start, $end])
                    ->orWhereHas('payment', fn ($p) => $p->where('status', Payment::PAID)->whereBetween('paid_at', [$start, $end]));
            })
            ->with(['customer:id,name,phone', 'pet:id,name,species', 'items.service:id,name,unit', 'payment'])
            ->orderByDesc('bookings.created_at')
            ->get();

        return response()->streamDownload(function () use (
            $business,
            $periodLabel,
            $summary,
            $paymentAmountsByStatus,
            $serviceUsage,
            $customers,
            $bookings,
            $start,
            $end
        ) {
            $handle = fopen('php://output', 'w');

            fwrite($handle, "\xEF\xBB\xBF");

            $this->writeCsvRow($handle, ['LAPORAN AKTIVITAS BISNIS PAWCARE']);
            $this->writeCsvRow($handle, ['Nama Bisnis', $business->business_name]);
            $this->writeCsvRow($handle, ['Pemilik Bisnis', $business->owner?->name ?? '-']);
            $this->writeCsvRow($handle, ['Periode Laporan', $periodLabel]);
            $this->writeCsvRow($handle, ['Total Layanan Terdaftar', (int) $summary['service_count']]);
            $this->writeCsvRow($handle, ['Total Booking (Periode)', (int) $summary['booking_total']]);
            $this->writeCsvRow($handle, ['Jumlah Transaksi Pembayaran Terverifikasi (Dibayar)', (int) $summary['paid_count']]);
            $this->writeCsvRow($handle, ['Total Pendapatan Terverifikasi (Rp)', number_format((float) $summary['revenue'], 0, '', '')]);
            $this->writeCsvRow($handle, ['Rata-rata Nilai Transaksi (Rp)', number_format((float) round($summary['avg_transaction']), 0, '', '')]);
            $this->writeCsvRow($handle, []);

            $this->writeCsvRow($handle, ['REKAPITULASI BOOKING BERDASARKAN STATUS']);
            $this->writeCsvRow($handle, ['Status Booking', 'Jumlah Booking']);
            foreach ($summary['booking_by_status'] as $status => $count) {
                $this->writeCsvRow($handle, [$status, (int) $count]);
            }
            $this->writeCsvRow($handle, []);

            $this->writeCsvRow($handle, ['REKAPITULASI PEMBAYARAN BERDASARKAN STATUS']);
            $this->writeCsvRow($handle, ['Status Pembayaran', 'Jumlah Pembayaran', 'Nominal Tagihan (Rp)', 'Pendapatan Diakui (Rp)', 'Keterangan']);
            foreach ($summary['payment_by_status'] as $pStatus => $count) {
                $isPaid = $pStatus === Payment::PAID;
                $recognized = $isPaid ? (float) $summary['revenue'] : 0;
                $note = $isPaid
                    ? 'Dihitung sebagai pendapatan terverifikasi'
                    : 'Tidak dihitung sebagai pendapatan (belum terverifikasi/ditolak)';

                $this->writeCsvRow($handle, [
                    $pStatus,
                    (int) $count,
                    number_format((float) ($paymentAmountsByStatus[$pStatus] ?? 0), 0, '', ''),
                    number_format($recognized, 0, '', ''),
                    $note,
                ]);
            }
            $this->writeCsvRow($handle, []);

            $this->writeCsvRow($handle, ['LAYANAN YANG DIGUNAKAN (TIDAK TERMASUK BOOKING DIBATALKAN)']);
            $this->writeCsvRow($handle, ['Nama Layanan', 'Kategori Layanan', 'Satuan', 'Frekuensi Booking', 'Total Kuantitas', 'Total Nilai Layanan (Rp)']);
            foreach ($serviceUsage as $svc) {
                $this->writeCsvRow($handle, [
                    $svc->name,
                    $svc->category,
                    $svc->unit,
                    (int) $svc->booking_count,
                    (int) $svc->total_qty,
                    number_format((float) $svc->total_nominal, 0, '', ''),
                ]);
            }
            $this->writeCsvRow($handle, []);

            $this->writeCsvRow($handle, ['RINGKASAN PELANGGAN BISNIS']);
            $this->writeCsvRow($handle, ['Nama Pelanggan', 'Nomor Telepon', 'Jumlah Booking (Periode)', 'Pembayaran Terverifikasi (Rp)']);
            foreach ($customers as $cust) {
                $this->writeCsvRow($handle, [
                    $cust['name'],
                    $cust['phone'],
                    $cust['bookings_count'],
                    number_format((float) $cust['paid_amount'], 0, '', ''),
                ]);
            }
            $this->writeCsvRow($handle, []);

            $this->writeCsvRow($handle, ['DAFTAR BOOKING DAN TRANSAKSI PEMBAYARAN']);
            $this->writeCsvRow($handle, [
                'No. Invoice',
                'Kode Transaksi',
                'Tanggal Booking Dibuat',
                'Jadwal Layanan',
                'Nama Pelanggan',
                'Nama Hewan',
                'Jenis Hewan',
                'Rincian Layanan',
                'Status Booking',
                'Status Pembayaran',
                'Nominal Tagihan (Rp)',
                'Pendapatan Terverifikasi (Rp)',
                'Tanggal Pembayaran Diverifikasi',
                'Catatan',
            ]);

            foreach ($bookings as $b) {
                $pay = $b->payment;
                $isVerifiedInPeriod = $pay
                    && $pay->status === Payment::PAID
                    && $pay->paid_at
                    && $pay->paid_at->betweenIncluded($start, $end);

                $verifiedRevenue = $isVerifiedInPeriod ? (float) $pay->amount : 0.0;
                $servicesDesc = $b->items->map(fn ($i) => ($i->service?->name ?? 'Layanan').' x'.$i->quantity)->implode('; ');

                $this->writeCsvRow($handle, [
                    $b->invoice_number,
                    $pay?->transaction_code ?? '-',
                    $b->created_at?->format('Y-m-d H:i') ?? '-',
                    ($b->booking_date?->format('Y-m-d') ?? '-').' '.$b->time_label,
                    $b->customer?->name ?? '-',
                    $b->pet?->name ?? '-',
                    $b->pet?->species ?? '-',
                    $servicesDesc ?: '-',
                    $b->status,
                    $pay?->status ?? Payment::UNPAID,
                    number_format((float) $b->total_price, 0, '', ''),
                    number_format($verifiedRevenue, 0, '', ''),
                    $pay?->paid_at?->format('Y-m-d H:i') ?? '-',
                    $b->notes ?? '',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Ekspor CSV Laporan Sistem keseluruhan platform khusus untuk Admin.
     */
    public function streamAdminCsv(Carbon $from, Carbon $to): StreamedResponse
    {
        $start = $from->copy()->startOfDay();
        $end = $to->copy()->endOfDay();

        $totals = $this->platformTotals();
        $summary = $this->businessSummary(null, $start, $end);
        $businessRows = $this->perBusiness($start, $end);
        $topServices = $this->topServices(null, $start, $end, 10);

        $periodLabel = $start->format('Y-m-d').' s/d '.$end->format('Y-m-d');
        $filename = "laporan-platform-pawcare-{$start->format('Y-m-d')}-sd-{$end->format('Y-m-d')}.csv";

        $paymentAmountsByStatus = [];
        foreach (Payment::STATUSES as $pStatus) {
            $paymentAmountsByStatus[$pStatus] = (float) Payment::query()
                ->where('status', $pStatus)
                ->whereBetween('payments.created_at', [$start, $end])
                ->sum('amount');
        }

        $paidPayments = Payment::query()
            ->where('status', Payment::PAID)
            ->whereBetween('paid_at', [$start, $end])
            ->with(['booking.business:id,business_name', 'booking.customer:id,name', 'verifier:id,name'])
            ->orderByDesc('paid_at')
            ->get();

        return response()->streamDownload(function () use (
            $periodLabel,
            $totals,
            $summary,
            $paymentAmountsByStatus,
            $businessRows,
            $topServices,
            $paidPayments
        ) {
            $handle = fopen('php://output', 'w');

            fwrite($handle, "\xEF\xBB\xBF");

            $this->writeCsvRow($handle, ['LAPORAN SISTEM PLATFORM PAWCARE']);
            $this->writeCsvRow($handle, ['Periode Laporan', $periodLabel]);
            $this->writeCsvRow($handle, ['Jumlah Bisnis Terdaftar', (int) $totals['businesses']]);
            $this->writeCsvRow($handle, ['Jumlah Bisnis Aktif', (int) $totals['businesses_active']]);
            $this->writeCsvRow($handle, ['Jumlah Bisnis Nonaktif/Suspend', (int) ($totals['businesses'] - $totals['businesses_active'])]);
            $this->writeCsvRow($handle, ['Jumlah Pengguna Total', (int) $totals['users']]);
            $this->writeCsvRow($handle, ['Jumlah Pelanggan (Customer)', (int) $totals['customers']]);
            $this->writeCsvRow($handle, ['Jumlah Pemilik Bisnis (Owner)', (int) $totals['owners']]);
            $this->writeCsvRow($handle, ['Jumlah Administrator', (int) $totals['admins']]);
            $this->writeCsvRow($handle, ['Total Booking Platform (Periode)', (int) $summary['booking_total']]);
            $this->writeCsvRow($handle, ['Total Transaksi Dibayar (Periode)', (int) $summary['paid_count']]);
            $this->writeCsvRow($handle, ['Total Pendapatan Platform Terverifikasi (Rp)', number_format((float) $summary['revenue'], 0, '', '')]);
            $this->writeCsvRow($handle, ['Rata-rata Nilai Transaksi (Rp)', number_format((float) round($summary['avg_transaction']), 0, '', '')]);
            $this->writeCsvRow($handle, []);

            $this->writeCsvRow($handle, ['JUMLAH BOOKING PLATFORM BERDASARKAN STATUS']);
            $this->writeCsvRow($handle, ['Status Booking', 'Jumlah Booking']);
            foreach ($summary['booking_by_status'] as $status => $count) {
                $this->writeCsvRow($handle, [$status, (int) $count]);
            }
            $this->writeCsvRow($handle, []);

            $this->writeCsvRow($handle, ['RINGKASAN PEMBAYARAN PLATFORM BERDASARKAN STATUS']);
            $this->writeCsvRow($handle, ['Status Pembayaran', 'Jumlah Pembayaran', 'Nominal Tagihan (Rp)', 'Pendapatan Diakui (Rp)', 'Keterangan']);
            foreach ($summary['payment_by_status'] as $pStatus => $count) {
                $isPaid = $pStatus === Payment::PAID;
                $recognized = $isPaid ? (float) $summary['revenue'] : 0;
                $note = $isPaid
                    ? 'Dihitung sebagai pendapatan terverifikasi'
                    : 'Tidak dihitung sebagai pendapatan (belum terverifikasi/ditolak)';

                $this->writeCsvRow($handle, [
                    $pStatus,
                    (int) $count,
                    number_format((float) ($paymentAmountsByStatus[$pStatus] ?? 0), 0, '', ''),
                    number_format($recognized, 0, '', ''),
                    $note,
                ]);
            }
            $this->writeCsvRow($handle, []);

            $this->writeCsvRow($handle, ['REKAP PENDAPATAN DAN AKTIVITAS PER BISNIS MITRA']);
            $this->writeCsvRow($handle, [
                'Nama Bisnis',
                'Nama Pemilik',
                'Status Bisnis',
                'Jumlah Layanan',
                'Jumlah Booking (Periode)',
                'Pembayaran Terverifikasi (Periode)',
                'Pendapatan Terverifikasi (Rp)',
            ]);
            foreach ($businessRows as $r) {
                $this->writeCsvRow($handle, [
                    $r->business_name,
                    $r->owner?->name ?? '-',
                    $r->status ? 'Aktif' : 'Suspend',
                    (int) $r->services_count,
                    (int) $r->bookings_count,
                    (int) $r->paid_count,
                    number_format((float) $r->revenue, 0, '', ''),
                ]);
            }
            $this->writeCsvRow($handle, []);

            $this->writeCsvRow($handle, ['LAYANAN TERATAS PLATFORM (PERIODE)']);
            $this->writeCsvRow($handle, ['Nama Layanan', 'Jumlah Penggunaan Booking', 'Persentase (%)']);
            foreach ($topServices as $svc) {
                $this->writeCsvRow($handle, [
                    $svc['name'],
                    (int) $svc['total'],
                    (int) $svc['percent'],
                ]);
            }
            $this->writeCsvRow($handle, []);

            $this->writeCsvRow($handle, ['DAFTAR TRANSAKSI PEMBAYARAN TERVERIFIKASI (DIBAYAR)']);
            $this->writeCsvRow($handle, [
                'Kode Transaksi',
                'No. Invoice',
                'Tanggal Pembayaran',
                'Nama Bisnis',
                'Nama Pelanggan',
                'Status Booking',
                'Status Pembayaran',
                'Nominal Pendapatan Terverifikasi (Rp)',
                'Diverifikasi Oleh',
            ]);
            foreach ($paidPayments as $pay) {
                $this->writeCsvRow($handle, [
                    $pay->transaction_code ?? '-',
                    $pay->booking?->invoice_number ?? '-',
                    $pay->paid_at?->format('Y-m-d H:i') ?? '-',
                    $pay->booking?->business?->business_name ?? '-',
                    $pay->booking?->customer?->name ?? '-',
                    $pay->booking?->status ?? '-',
                    $pay->status,
                    number_format((float) $pay->amount, 0, '', ''),
                    $pay->verifier?->name ?? '-',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
