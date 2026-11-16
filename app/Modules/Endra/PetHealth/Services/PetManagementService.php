<?php

namespace App\Modules\Endra\PetHealth\Services;

use App\Models\Pet;
use App\Models\User;
use App\Modules\Elga\Shared\AuditTrail;
use App\Support\PhotoStorage;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PetManagementService
{
    public function __construct(private AuditTrail $audit)
    {
    }

    public function store(User $customer, Request $request): Pet
    {
        $payload = $this->withPhoto($request, $this->validated($request));
        $pet = $customer->pets()->create($payload);

        $this->recordAudit('pet.created', $pet, [], [
            'name' => $pet->name,
            'species' => $pet->species,
            'gender' => $pet->gender,
        ], $customer->id);

        return $pet;
    }

    public function update(User $customer, Pet $pet, Request $request): Pet
    {
        $before = [
            'name' => $pet->name,
            'species' => $pet->species,
            'gender' => $pet->gender,
            'weight' => $pet->weight,
        ];

        $payload = $this->withPhoto($request, $this->validated($request), $pet);
        $pet->update($payload);

        $this->recordAudit('pet.updated', $pet, $before, [
            'name' => $pet->name,
            'species' => $pet->species,
            'gender' => $pet->gender,
            'weight' => $pet->weight,
        ], $customer->id);

        return $pet;
    }

    /**
     * @return array{deleted:bool,message:string}
     */
    public function destroy(User $customer, Pet $pet): array
    {
        if ($pet->bookings()->exists()) {
            return [
                'deleted' => false,
                'message' => "{$pet->name} tidak dapat dihapus karena sudah memiliki riwayat booking.",
            ];
        }

        $photo = $pet->photo;
        $pet->delete();
        PhotoStorage::delete($photo);

        return [
            'deleted' => true,
            'message' => 'Data hewan dihapus.',
        ];
    }

    /**
     * @return array{ok:bool,message:string}
     */
    public function archive(User $customer, Pet $pet): array
    {
        if ($pet->archived_at) {
            return ['ok' => false, 'message' => 'Hewan ini sudah diarsipkan.'];
        }

        if (! $pet->bookings()->exists()) {
            return ['ok' => false, 'message' => 'Hewan tanpa riwayat booking dapat dihapus langsung.'];
        }

        $pet->setAttribute('archived_at', now()->toDateTimeString());
        $pet->save();

        $this->recordAudit('pet.archived', $pet, ['archived_at' => null], [
            'archived_at' => (string) $pet->archived_at,
        ], $customer->id);

        return ['ok' => true, 'message' => "{$pet->name} berhasil diarsipkan."];
    }

    /**
     * @return array{ok:bool,message:string}
     */
    public function restore(User $customer, Pet $pet): array
    {
        if (! $pet->archived_at) {
            return ['ok' => false, 'message' => 'Hewan ini belum diarsipkan.'];
        }

        $before = (string) $pet->archived_at;
        $pet->archived_at = null;
        $pet->save();

        $this->recordAudit('pet.restored', $pet, ['archived_at' => $before], [
            'archived_at' => null,
        ], $customer->id);

        return ['ok' => true, 'message' => "{$pet->name} berhasil dipulihkan."];
    }

    public function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'species' => ['required', Rule::in(Pet::SPECIES)],
            'breed' => ['nullable', 'string', 'max:100'],
            'age_years' => ['nullable', 'integer', 'min:0', 'max:40'],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'weight' => ['nullable', 'numeric', 'min:0.1', 'max:250'],
            'gender' => ['required', Rule::in(Pet::GENDERS)],
            'photo' => PhotoStorage::rules(2048),
            'remove_photo' => ['nullable', 'boolean'],
        ], [], [
            'name' => 'nama hewan',
            'species' => 'jenis hewan',
            'breed' => 'ras',
            'age_years' => 'umur',
            'birth_date' => 'tanggal lahir',
            'weight' => 'berat badan',
            'gender' => 'jenis kelamin',
            'photo' => 'foto hewan',
        ]);

        $ageMonths = null;
        if (isset($data['age_years']) && $data['age_years'] !== '') {
            $ageMonths = (int) $data['age_years'] * 12;
        } elseif (! empty($data['birth_date'])) {
            $ageMonths = max(0, (int) Carbon::parse($data['birth_date'])->diffInMonths(now()));
        }

        $payload = [
            'name' => trim($data['name']),
            'species' => $data['species'],
            'breed' => $data['breed'] ?? null,
            'age' => $ageMonths,
            'gender' => $data['gender'],
        ];

        if (Schema::hasColumn('pets', 'birth_date')) {
            $payload['birth_date'] = $data['birth_date'] ?? null;
        }
        if (Schema::hasColumn('pets', 'weight')) {
            $payload['weight'] = isset($data['weight']) && $data['weight'] !== '' ? (float) $data['weight'] : null;
        }

        return $payload;
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

    private function recordAudit(string $action, Pet $pet, array $before, array $after, int $actorId): void
    {
        if (Schema::hasTable('audit_logs')) {
            $this->audit->record($action, $pet, $before, $after, $actorId);
        }
    }
}
