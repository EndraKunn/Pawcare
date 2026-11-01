<?php

namespace App\Console\Commands;

use App\Support\PawcareImage;
use Illuminate\Console\Command;

class PawcareImagesStatus extends Command
{
    protected $signature = 'pawcare:images';

    protected $description = 'Tampilkan slot foto PawCare: sudah memakai foto lokal atau masih ilustrasi fallback';

    public function handle(): int
    {
        $rows = [];
        $missing = 0;

        foreach (PawcareImage::slots() as $s) {
            $found = null;
            foreach (PawcareImage::EXTENSIONS as $ext) {
                if (is_file(PawcareImage::root().'/'.$s['slot'].'.'.$ext)) {
                    $found = $s['slot'].'.'.$ext;
                    break;
                }
            }
            $fallbackOk = is_file(PawcareImage::root().'/fallback/'.$s['fallback']);
            if (! $found) {
                $missing++;
            }
            $rows[] = [
                $s['label'],
                'public/images/pawcare/'.$s['slot'].'.jpg',
                $found ? 'FOTO LOKAL' : 'fallback ilustrasi',
                $fallbackOk ? 'ada' : 'HILANG',
            ];
        }

        $this->table(['Slot', 'Taruh foto di', 'Status', 'File fallback'], $rows);
        $this->line($missing === 0
            ? 'Semua slot sudah memakai foto lokal.'
            : "{$missing} slot masih memakai ilustrasi fallback. Lihat public/images/pawcare/README.md.");

        return self::SUCCESS;
    }
}
