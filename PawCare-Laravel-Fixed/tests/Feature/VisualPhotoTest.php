<?php

namespace Tests\Feature;

use App\Models\Pet;
use App\Models\Service;
use App\Support\PawcareImage;
use App\Support\PhotoStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesPawCareData;
use Tests\TestCase;

class VisualPhotoTest extends TestCase
{
    use RefreshDatabase, CreatesPawCareData;

    /** PNG 1x1 valid (tidak butuh ekstensi GD). */
    private function png(string $name = 'foto.png'): UploadedFile
    {
        $bin = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        return UploadedFile::fake()->createWithContent($name, $bin);
    }

    public function test_semua_file_fallback_tersedia(): void
    {
        foreach (PawcareImage::slots() as $s) {
            $this->assertFileExists(PawcareImage::root().'/fallback/'.$s['fallback'], $s['label']);
        }
        $this->assertFileExists(PawcareImage::root().'/fallback/generic.svg');
    }

    public function test_halaman_publik_menampilkan_gambar_dan_tetap_berfungsi(): void
    {
        [, $biz] = $this->makeOwnerWithBusiness();
        $this->makeService($biz, 90000, ['category' => 'Grooming']);

        $this->get(route('home'))->assertOk()->assertSee('images/pawcare', false)->assertSee('onerror=', false);
        $this->get(route('catalog'))->assertOk()->assertSee($biz->business_name)->assertSee('images/pawcare', false);
        $this->get(route('catalog.show', $biz->id))->assertOk()->assertSee($biz->business_name)->assertSee('service-grooming', false);
    }

    public function test_url_gambar_model_memakai_fallback_tanpa_foto(): void
    {
        [, $biz] = $this->makeOwnerWithBusiness();
        $pet = $this->makePet($this->makeUser());
        $svc = $this->makeService($biz, 1000, ['category' => 'Penitipan']);

        $this->assertStringContainsString('images/pawcare/', $biz->cover_url);
        $this->assertStringContainsString('pet-anjing', $pet->photo_url);
        $this->assertStringContainsString('service-penitipan', $svc->image_url);
        $this->assertFalse($biz->has_cover_photo);
        $this->assertFalse($pet->has_photo);
    }

    public function test_owner_dapat_unggah_dan_hapus_foto_sampul(): void
    {
        Storage::fake(PhotoStorage::DISK);
        [$owner, $biz] = $this->makeOwnerWithBusiness();
        $this->actingAs($owner);

        $base = ['business_name' => 'Bisnis Baru', 'address' => 'Jl. Baru 2'];
        $this->put(route('owner.business.update'), $base + ['cover' => $this->png()])->assertSessionHasNoErrors();
        $path = $biz->fresh()->cover_photo;
        $this->assertNotNull($path);
        Storage::disk(PhotoStorage::DISK)->assertExists($path);

        $this->put(route('owner.business.update'), $base + ['remove_cover' => '1'])->assertSessionHasNoErrors();
        $this->assertNull($biz->fresh()->cover_photo);
        Storage::disk(PhotoStorage::DISK)->assertMissing($path);
    }

    public function test_unggahan_svg_atau_file_bukan_gambar_ditolak(): void
    {
        Storage::fake(PhotoStorage::DISK);
        [$owner] = $this->makeOwnerWithBusiness();
        $this->actingAs($owner);

        $svg = UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $this->put(route('owner.business.update'), ['business_name' => 'A', 'address' => 'B', 'cover' => $svg])->assertSessionHasErrors('cover');

        $txt = UploadedFile::fake()->createWithContent('x.jpg', 'bukan gambar');
        $this->put(route('owner.business.update'), ['business_name' => 'A', 'address' => 'B', 'cover' => $txt])->assertSessionHasErrors('cover');
    }

    public function test_customer_dapat_unggah_ganti_dan_hapus_foto_hewan(): void
    {
        Storage::fake(PhotoStorage::DISK);
        $cust = $this->makeUser();
        $this->actingAs($cust);

        $this->post(route('customer.pets.store'), ['name' => 'Milo', 'species' => 'Anjing', 'gender' => 'Jantan', 'photo' => $this->png()])
            ->assertRedirect(route('customer.pets.index'));
        $pet = Pet::firstOrFail();
        Storage::disk(PhotoStorage::DISK)->assertExists($pet->photo);
        $first = $pet->photo;

        $this->put(route('customer.pets.update', $pet->id), ['name' => 'Milo', 'species' => 'Anjing', 'gender' => 'Jantan', 'photo' => $this->png('b.png')]);
        $second = $pet->fresh()->photo;
        $this->assertNotSame($first, $second);
        Storage::disk(PhotoStorage::DISK)->assertMissing($first);

        $this->get(route('customer.pets.index'))->assertOk()->assertSee('Milo');
        $this->get(route('customer.pets.edit', $pet->id))->assertOk();

        $this->delete(route('customer.pets.destroy', $pet->id));
        Storage::disk(PhotoStorage::DISK)->assertMissing($second);
    }

    public function test_foto_hewan_milik_customer_lain_tidak_bisa_diubah(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();
        $pet = $this->makePet($a);

        $this->actingAs($b)->put(route('customer.pets.update', $pet->id), ['name' => 'X', 'species' => 'Anjing', 'gender' => 'Jantan'])->assertNotFound();
    }
}
