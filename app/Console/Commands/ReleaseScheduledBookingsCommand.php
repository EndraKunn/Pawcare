<?php

namespace App\Console\Commands;

use App\Events\BookingCreated;
use App\Models\Booking;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class ReleaseScheduledBookingsCommand extends Command
{
    protected $signature = 'ordering:release-scheduled {--days=1 : Batas hari ke depan untuk melepas pre-order ke papan operasional}';

    protected $description = 'Melepaskan booking pre-order/terjadwal yang memasuki jendela operasional ke Care Board.';

    public function handle(): int
    {
        if (! Schema::hasTable('bookings') || ! Schema::hasColumn('bookings', 'is_preorder')) {
            $this->info('Kolom pre-order belum tersedia; tidak ada booking yang dilepas.');

            return self::SUCCESS;
        }

        $days = max(0, (int) $this->option('days'));
        $cutoff = now()->addDays($days)->toDateString();

        $bookings = Booking::query()
            ->where('is_preorder', true)
            ->whereIn('status', [Booking::STATUS_WAITING, Booking::STATUS_CONFIRMED])
            ->whereDate('booking_date', '<=', $cutoff)
            ->orderBy('booking_date')
            ->orderBy('booking_time')
            ->get();

        foreach ($bookings as $booking) {
            BookingCreated::dispatch($booking);
        }

        $this->info("Selesai memproses {$bookings->count()} booking pre-order terjadwal (batas tanggal: {$cutoff}).");

        return self::SUCCESS;
    }
}
