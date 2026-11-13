<?php

namespace App\Modules\Elga\Business\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Pet;
use App\Modules\Elga\Business\Services\BusinessManagementService;
use App\Support\PhotoStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BusinessController extends Controller
{
    public function __construct(private readonly BusinessManagementService $businesses) {}

    public function edit(Request $request): View
    {
        $business = $this->businesses->owned($request->user());
        $business->load(['operatingHours', 'closures' => fn ($q) => $q->orderBy('closed_date')]);
        return view('elga-business::business.edit', compact('business'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:2000'],
            'accepted_species' => $request->boolean('accepted_species_submitted') ? ['required', 'array', 'min:1'] : ['sometimes', 'array'],
            'accepted_species.*' => ['required', Rule::in(Pet::SPECIES)],
            'bank_name' => ['nullable', 'string', 'max:50'],
            'bank_account_number' => ['nullable', 'string', 'max:50'],
            'bank_account_name' => ['nullable', 'string', 'max:100'],
            'cover' => PhotoStorage::rules(3072),
            'remove_cover' => ['nullable', 'boolean'],
        ]);
        $this->businesses->updateProfile($request->user(), $data, $request->file('cover'), $request->boolean('remove_cover'));
        return back()->with('success', 'Profil bisnis berhasil disimpan.');
    }

    public function updateSchedule(Request $request): RedirectResponse
    {
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
        $this->businesses->updateSchedule($request->user(), $data['hours']);
        return back()->with('success', 'Jadwal operasional berhasil diperbarui.');
    }

    public function storeClosure(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'closed_date' => ['required', 'date', 'after_or_equal:today'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);
        $this->businesses->storeClosure($request->user(), $data);
        return back()->with('success', 'Tanggal libur dicatat/diaktifkan.');
    }

    public function deactivateClosure(Request $request, int $closure): RedirectResponse
    {
        $this->businesses->deactivateClosure($request->user(), $closure);
        return back()->with('success', 'Tanggal libur dinonaktifkan tanpa menghapus riwayat.');
    }
}
