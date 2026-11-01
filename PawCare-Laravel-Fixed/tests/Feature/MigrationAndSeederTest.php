<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MigrationAndSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_migrasi_menyediakan_seluruh_tabel_dan_kolom_penting(): void
    {
        // RefreshDatabase menyiapkan skema bersih sebelum tes dan menjaga database in-memory tetap konsisten.
        // Jangan menjalankan migrate:fresh atau mengelola transaksi manual di dalam tes ini.
        foreach ([
            'users',
            'businesses',
            'pets',
            'services',
            'bookings',
            'booking_items',
            'payments',
            'booking_status_logs',
            'business_operating_hours',
            'business_closures',
            'notifications',
            'reviews',
            'review_replies',
            'conversations',
            'conversation_messages',
            'payment_gateway_logs',
            'pet_health_records',
            'pet_care_notes',
        ] as $t) {
            $this->assertTrue(Schema::hasTable($t), "Tabel $t tidak ada");
        }

        $this->assertTrue(Schema::hasColumn('businesses', 'accepted_species'), 'Kolom businesses.accepted_species tidak ada');
    }

    public function test_seeder_menghasilkan_data_demo_konsisten_dan_akun_demo_bisa_login(): void
    {
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, User::where('role', 'admin')->count());
        $this->assertGreaterThan(1, User::where('role', 'business_owner')->count());
        $this->assertGreaterThan(0, Booking::count());
        $this->assertSame(Booking::count(), Payment::count());

        foreach (['admin@pawcare.test', 'owner@pawcare.test', 'customer@pawcare.test'] as $email) {
            $this->post(route('login.attempt'), ['email' => $email, 'password' => 'password'])->assertRedirect();
            $this->assertAuthenticated();
            auth()->logout();
        }

        // Total tiap booking = jumlah item (harga x jumlah).
        foreach (Booking::with('items')->get() as $b) {
            $this->assertEquals($b->items->sum(fn ($i) => $i->price * $i->quantity), $b->total_price);
        }

        // Seeder dijalankan ulang tidak menggandakan data.
        $count = User::count();
        $this->seed(DatabaseSeeder::class);
        $this->assertSame($count, User::count());
    }
}
