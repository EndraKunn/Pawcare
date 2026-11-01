<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Pet;
use App\Support\PhotoStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PetController extends Controller
{
    public function index(Request $request): View
    {
        $pets = Pet::ownedBy($request->user())->withCount('bookings')->orderBy('name')->paginate(12);

        return view('customer.pets.index', compact('pets'));
    }

    public function create(): View
    {
        return view('customer.pets.form', ['pet' => new Pet]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->user()->pets()->create($this->withPhoto($request, $this->validated($request)));

        return redirect()->route('customer.pets.index')->with('success', 'Data hewan berhasil ditambahkan.');
    }

    public function edit(Request $request, int $pet): View
    {
        return view('customer.pets.form', ['pet' => $this->findOwned($request, $pet)]);
    }

    public function update(Request $request, int $pet): RedirectResponse
    {
        $model = $this->findOwned($request, $pet);
        $model->update($this->withPhoto($request, $this->validated($request), $model));

        return redirect()->route('customer.pets.index')->with('success', 'Data hewan berhasil diperbarui.');
    }

    public function destroy(Request $request, int $pet): RedirectResponse
    {
        $model = $this->findOwned($request, $pet);

        if ($model->bookings()->exists()) {
            return back()->with('error', "{$model->name} tidak dapat dihapus karena sudah memiliki riwayat booking.");
        }

        $photo = $model->photo;
        $model->delete();
        PhotoStorage::delete($photo);

        return redirect()->route('customer.pets.index')->with('success', 'Data hewan dihapus.');
    }

    /** Tambahkan kolom `photo` (unggah baru / hapus) ke data yang sudah tervalidasi. */

    public function archive(Request $request, int $pet): RedirectResponse
{
    $model = $this->findOwned($request, $pet);

    if ($model->archived_at) {
        return back()->with('error', 'Hewan ini sudah diarsipkan.');
    }

    if (! $model->bookings()->exists()) {
        return back()->with(
            'error',
            'Hewan tanpa riwayat booking dapat dihapus langsung.'
        );
    }

    $model->setAttribute('archived_at', now()->toDateTimeString());
    $model->save();

    return redirect()
        ->route('customer.pets.index')
        ->with('success', "{$model->name} berhasil diarsipkan.");
}

public function restore(Request $request, int $pet): RedirectResponse
{
    $model = $this->findOwned($request, $pet);

    if (! $model->archived_at) {
        return back()->with('error', 'Hewan ini belum diarsipkan.');
    }

    $model->archived_at = null;
    $model->save();

    return redirect()
        ->route('customer.pets.index')
        ->with('success', "{$model->name} berhasil dipulihkan.");
}
    private function withPhoto(Request $request, array $data, ?Pet $pet = null): array
    {
        $file = $request->file('photo');

        if ($file) {
            $data['photo'] = PhotoStorage::store($file, 'pets');
            PhotoStorage::delete($pet?->photo);
        } elseif ($pet && $request->boolean('remove_photo')) {
            PhotoStorage::delete($pet->photo);
            $data['photo'] = null;
        }

        return $data;
    }

    /** Hewan milik customer lain -> 404 (tidak dapat dilihat/diubah). */
    private function findOwned(Request $request, int $id): Pet
    {
        return Pet::ownedBy($request->user())->findOrFail($id);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'species' => ['required', Rule::in(Pet::SPECIES)],
            'breed' => ['nullable', 'string', 'max:100'],
            'age_years' => ['nullable', 'integer', 'min:0', 'max:40'],
            'gender' => ['required', Rule::in(Pet::GENDERS)],
            'photo' => PhotoStorage::rules(2048),
            'remove_photo' => ['nullable', 'boolean'],
        ], [], [
            'name' => 'nama hewan', 'species' => 'jenis hewan', 'breed' => 'ras',
            'age_years' => 'umur', 'gender' => 'jenis kelamin', 'photo' => 'foto hewan',
        ]);

        return [
            'name' => $data['name'],
            'species' => $data['species'],
            'breed' => $data['breed'] ?? null,
            'age' => isset($data['age_years']) ? (int) $data['age_years'] * 12 : null, // disimpan dalam bulan
            'gender' => $data['gender'],
        ];
    }
}
