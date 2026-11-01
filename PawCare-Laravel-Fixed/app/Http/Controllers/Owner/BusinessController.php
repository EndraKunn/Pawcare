<?php

namespace App\Http\Controllers\Owner;

use App\Models\Pet;
use App\Support\PhotoStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BusinessController extends OwnerController
{
    public function edit(Request $request): View
    {
        $business = $this->business($request);

        $business->load([
            'operatingHours',
            'closures' => fn ($query) => $query->orderBy('closed_date'),
        ]);

        return view('owner.business', compact('business'));
    }

    public function update(Request $request): RedirectResponse
    {
        $business = $this->business($request);

        $rules = [
            'business_name' => ['required', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:2000'],
            'accepted_species' => $request->boolean('accepted_species_submitted')
                ? ['required', 'array', 'min:1']
                : ['nullable', 'array'],
            'accepted_species.*' => ['required', 'string', Rule::in(Pet::SPECIES)],
            'bank_name' => ['nullable', 'string', 'max:50'],
            'bank_account_number' => ['nullable', 'string', 'max:50'],
            'bank_account_name' => ['nullable', 'string', 'max:100'],
            'cover' => PhotoStorage::rules(3072),
            'remove_cover' => ['nullable', 'boolean'],
        ];

        $data = $request->validate($rules, [
            'accepted_species.required' => 'Pilih minimal satu jenis hewan yang diterima bisnis.',
            'accepted_species.min' => 'Pilih minimal satu jenis hewan yang diterima bisnis.',
        ], [
            'business_name' => 'nama bisnis',
            'address' => 'alamat',
            'description' => 'deskripsi',
            'accepted_species' => 'jenis hewan yang diterima',
            'accepted_species.*' => 'jenis hewan',
            'bank_name' => 'nama bank',
            'bank_account_number' => 'nomor rekening',
            'bank_account_name' => 'nama pemilik rekening',
            'cover' => 'foto sampul',
        ]);

        $cover = $request->file('cover');
        unset($data['cover'], $data['remove_cover']);

        if ($request->has('accepted_species') || $request->boolean('accepted_species_submitted')) {
            $data['accepted_species'] = array_values(array_intersect(
                Pet::SPECIES,
                $data['accepted_species'] ?? []
            ));
        } else {
            unset($data['accepted_species']);
        }

        if ($cover) {
            $old = $business->cover_photo;
            $data['cover_photo'] = PhotoStorage::store($cover, 'businesses');
            PhotoStorage::delete($old);
        } elseif ($request->boolean('remove_cover')) {
            PhotoStorage::delete($business->cover_photo);
            $data['cover_photo'] = null;
        }

        $business->update($data);

        return back()->with('success', 'Profil bisnis berhasil disimpan.');
    }

    public function updateSchedule(Request $request): RedirectResponse
    {
        $business = $this->business($request);

        $data = $request->validate([
            'hours' => ['required', 'array'],
            'hours.*.is_closed' => ['nullable', 'boolean'],
            'hours.*.opens_at' => ['nullable', 'date_format:H:i'],
            'hours.*.closes_at' => ['nullable', 'date_format:H:i'],
            'hours.*.slot_duration_minutes' => ['nullable', 'integer', 'min:15', 'max:480'],
            'hours.*.slot_capacity' => ['nullable', 'integer', 'min:1', 'max:100'],
            'hours.*.break_starts_at' => ['nullable', 'date_format:H:i'],
            'hours.*.break_ends_at' => ['nullable', 'date_format:H:i'],
        ]);

        for ($day = 1; $day <= 7; $day++) {
            $row = $data['hours'][$day] ?? [];

            $closed = (bool) ($row['is_closed'] ?? false);
            $opens = $row['opens_at'] ?? null;
            $closes = $row['closes_at'] ?? null;
            $slotDuration = max(15, (int) ($row['slot_duration_minutes'] ?? 60));
            $slotCapacity = max(1, (int) ($row['slot_capacity'] ?? 5));
            $breakStart = filled($row['break_starts_at'] ?? null) ? $row['break_starts_at'] : null;
            $breakEnd = filled($row['break_ends_at'] ?? null) ? $row['break_ends_at'] : null;

            if (! $closed) {
                if (! $opens || ! $closes) {
                    throw ValidationException::withMessages([
                        "hours.$day.opens_at" => 'Isi jam buka dan jam tutup, atau tandai sebagai hari libur.',
                    ]);
                }

                if ($closes <= $opens) {
                    throw ValidationException::withMessages([
                        "hours.$day.closes_at" => 'Jam tutup harus setelah jam buka.',
                    ]);
                }

                if (($breakStart && ! $breakEnd) || (! $breakStart && $breakEnd)) {
                    throw ValidationException::withMessages([
                        "hours.$day.break_starts_at" => 'Isi jam mulai dan selesai istirahat secara lengkap, atau kosongkan keduanya.',
                    ]);
                }

                if ($breakStart && $breakEnd && $breakEnd <= $breakStart) {
                    throw ValidationException::withMessages([
                        "hours.$day.break_ends_at" => 'Jam selesai istirahat harus setelah jam mulai istirahat.',
                    ]);
                }
            }

            $business->operatingHours()->updateOrCreate(
                ['day_of_week' => $day],
                [
                    'is_closed' => $closed,
                    'opens_at' => $closed ? null : $opens,
                    'closes_at' => $closed ? null : $closes,
                    'slot_duration_minutes' => $slotDuration,
                    'slot_capacity' => $slotCapacity,
                    'break_starts_at' => $closed ? null : $breakStart,
                    'break_ends_at' => $closed ? null : $breakEnd,
                ]
            );
        }

        return back()->with('success', 'Jadwal operasional dan kapasitas slot berhasil disimpan.');
    }

    public function storeClosure(Request $request): RedirectResponse
    {
        $business = $this->business($request);

        $data = $request->validate([
            'closed_date' => ['required', 'date', 'after_or_equal:today'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $exists = $business->closures()
            ->whereDate('closed_date', $data['closed_date'])
            ->exists();

        if ($exists) {
            return back()
                ->withErrors([
                    'closed_date' => 'Tanggal libur tersebut sudah terdaftar.',
                ])
                ->withInput();
        }

        $business->closures()->create($data);

        return back()->with('success', 'Tanggal libur berhasil ditambahkan.');
    }

    public function destroyClosure(
        Request $request,
        int $closure
    ): RedirectResponse {
        $business = $this->business($request);

        $business->closures()->findOrFail($closure)->delete();

        return back()->with('success', 'Tanggal libur berhasil dihapus.');
    }
}
