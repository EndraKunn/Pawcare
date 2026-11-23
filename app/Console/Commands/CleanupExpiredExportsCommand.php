<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class CleanupExpiredExportsCommand extends Command
{
    protected $signature = 'reports:cleanup-expired';

    protected $description = 'Menghapus berkas ekspor laporan yang sudah kedaluwarsa (> 24 jam).';

    public function handle(): int
    {
        $deletedFiles = 0;
        $disk = Storage::disk('local');

        if (Schema::hasTable('report_exports')) {
            $expired = DB::table('report_exports')
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now())
                ->get();

            foreach ($expired as $row) {
                if (! empty($row->path) && $disk->exists($row->path)) {
                    $disk->delete($row->path);
                    $deletedFiles++;
                }
                DB::table('report_exports')
                    ->where('id', $row->id)
                    ->update(['status' => 'expired', 'updated_at' => now()]);
            }
        }

        if ($disk->exists('report-exports')) {
            $cutoffTs = now()->subHours(24)->getTimestamp();
            foreach ($disk->files('report-exports') as $file) {
                if ($disk->lastModified($file) <= $cutoffTs) {
                    $disk->delete($file);
                    $deletedFiles++;
                }
            }
        }

        $this->info("Pembersihan berkas ekspor kedaluwarsa selesai ({$deletedFiles} file dihapus).");

        return self::SUCCESS;
    }
}
