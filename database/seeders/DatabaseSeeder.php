<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\BookingStatusLog;
use App\Models\Business;
use App\Models\Payment;
use App\Models\Pet;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Idempotent: jika data demo sudah ada, jangan gandakan.
        if (User::where('email', 'admin@pawcare.test')->exists()) {
            return;
        }

        User::create([
            'name' => 'Admin PawCare',
            'email' => 'admin@pawcare.test',
            'phone' => '0811 0000 0001',
            'password' => 'password',
            'role' => User::ROLE_ADMIN,
            'status' => true,
        ]);

        $owner1 = User::create([
            'name' => 'Endra Gustin',
            'email' => 'owner@pawcare.test',
            'phone' => '0351 555 0123',
            'password' => 'password',
            'role' => User::ROLE_OWNER,
            'status' => true,
        ]);

        $owner2 = User::create([
            'name' => 'Titah Wahyu Dian',
            'email' => 'owner2@pawcare.test',
            'phone' => '0822 3344 5566',
            'password' => 'password',
            'role' => User::ROLE_OWNER,
            'status' => true,
        ]);

        $owner3 = User::create([
            'name' => 'Jeni Kamayanti',
            'email' => 'owner3@pawcare.test',
            'phone' => '0857 7788 9900',
            'password' => 'password',
            'role' => User::ROLE_OWNER,
            'status' => true,
        ]);

        $owner4 = User::create([
            'name' => 'Rudi Hartono',
            'email' => 'owner4@pawcare.test',
            'phone' => '0878 1212 3434',
            'password' => 'password',
            'role' => User::ROLE_OWNER,
            'status' => true,
        ]);

        $cust1 = User::create([
            'name' => 'Endra',
            'email' => 'customer@pawcare.test',
            'phone' => '0812 3456 7890',
            'password' => 'password',
            'role' => User::ROLE_CUSTOMER,
            'status' => true,
        ]);

        $cust2 = User::create([
            'name' => 'Budi Santoso',
            'email' => 'budi@pawcare.test',
            'phone' => '0857 1122 3344',
            'password' => 'password',
            'role' => User::ROLE_CUSTOMER,
            'status' => true,
        ]);

        $cust3 = User::create([
            'name' => 'Sari Wulandari',
            'email' => 'sari@pawcare.test',
            'phone' => '0821 9988 7766',
            'password' => 'password',
            'role' => User::ROLE_CUSTOMER,
            'status' => true,
        ]);

        $biz1 = Business::create([
            'owner_id' => $owner1->id,
            'business_name' => 'Happy Paws Pet Hotel',
            'address' => 'Jl. Pahlawan No. 21, Madiun',
            'description' => 'Penitipan, grooming, dan perawatan dengan kandang ber-AC.',
            'accepted_species' => ['Anjing', 'Kucing', 'Kelinci'],
            'bank_name' => 'BCA',
            'bank_account_number' => '1234567890',
            'bank_account_name' => 'Happy Paws Pet Hotel',
            'status' => true,
        ]);

        $biz2 = Business::create([
            'owner_id' => $owner2->id,
            'business_name' => 'Meong & Guk Grooming',
            'address' => 'Jl. Diponegoro No. 8, Ngawi',
            'description' => 'Spesialis grooming kucing dan anjing.',
            'accepted_species' => ['Anjing', 'Kucing'],
            'bank_name' => 'BRI',
            'bank_account_number' => '9876543210',
            'bank_account_name' => 'Meong & Guk Grooming',
            'status' => true,
        ]);

        $biz3 = Business::create([
            'owner_id' => $owner3->id,
            'business_name' => 'Griya Satwa Sehat',
            'address' => 'Jl. Raya Magetan No. 45, Magetan',
            'description' => 'Penitipan dan perawatan untuk kucing, hamster, burung, dan reptil.',
            'accepted_species' => ['Kucing', 'Hamster', 'Burung', 'Reptil'],
            'bank_name' => 'Mandiri',
            'bank_account_number' => '1122334455',
            'bank_account_name' => 'Griya Satwa Sehat',
            'status' => true,
        ]);

        $biz4 = Business::create([
            'owner_id' => $owner4->id,
            'business_name' => 'Kandang Lama',
            'address' => 'Jl. Urip Sumoharjo No. 5, Ponorogo',
            'description' => 'Contoh bisnis yang disuspend Admin.',
            'accepted_species' => ['Anjing', 'Kucing'],
            'status' => false,
        ]);

        foreach ([$biz1, $biz2, $biz3, $biz4] as $biz) {
            for ($day = 1; $day <= 7; $day++) {
                $biz->operatingHours()->create([
                    'day_of_week' => $day,
                    'is_closed' => $day === 7,
                    'opens_at' => $day === 7 ? null : '08:00',
                    'closes_at' => $day === 7 ? null : '17:00',
                ]);
            }
        }

        $pet1 = Pet::create(['customer_id' => $cust1->id, 'name' => 'Milo', 'species' => 'Anjing', 'breed' => 'Golden Retriever', 'age' => 36, 'gender' => 'Jantan']);
        $pet2 = Pet::create(['customer_id' => $cust1->id, 'name' => 'Luna', 'species' => 'Kucing', 'breed' => 'Persia', 'age' => 24, 'gender' => 'Betina']);
        $pet3 = Pet::create(['customer_id' => $cust1->id, 'name' => 'Coco', 'species' => 'Kelinci', 'breed' => 'Holland Lop', 'age' => 12, 'gender' => 'Betina']);
        $pet4 = Pet::create(['customer_id' => $cust2->id, 'name' => 'Kiko', 'species' => 'Kucing', 'breed' => 'Domestik', 'age' => 18, 'gender' => 'Jantan']);
        $pet5 = Pet::create(['customer_id' => $cust3->id, 'name' => 'Kimi', 'species' => 'Kelinci', 'breed' => 'Lokal', 'age' => 10, 'gender' => 'Betina']);

        $s1 = Service::create(['business_id' => $biz1->id, 'name' => 'Penitipan Reguler', 'category' => 'Penitipan', 'unit' => 'hari', 'price' => 85000, 'status' => true]);
        $s2 = Service::create(['business_id' => $biz1->id, 'name' => 'Penitipan VIP', 'category' => 'Penitipan', 'unit' => 'hari', 'price' => 150000, 'status' => true]);
        $s3 = Service::create(['business_id' => $biz1->id, 'name' => 'Grooming Lengkap', 'category' => 'Grooming', 'unit' => 'sesi', 'price' => 120000, 'status' => true]);
        $s4 = Service::create(['business_id' => $biz1->id, 'name' => 'Cek Kesehatan Dasar', 'category' => 'Perawatan', 'unit' => 'sesi', 'price' => 150000, 'status' => false]);

        $s5 = Service::create(['business_id' => $biz2->id, 'name' => 'Grooming Kucing', 'category' => 'Grooming', 'unit' => 'sesi', 'price' => 90000, 'status' => true]);
        $s6 = Service::create(['business_id' => $biz2->id, 'name' => 'Grooming Anjing', 'category' => 'Grooming', 'unit' => 'sesi', 'price' => 120000, 'status' => true]);
        $s7 = Service::create(['business_id' => $biz2->id, 'name' => 'Perawatan Telinga & Kuku', 'category' => 'Perawatan', 'unit' => 'sesi', 'price' => 65000, 'status' => true]);

        $s8 = Service::create(['business_id' => $biz3->id, 'name' => 'Penitipan Kucing', 'category' => 'Penitipan', 'unit' => 'hari', 'price' => 60000, 'status' => true]);
        $s9 = Service::create(['business_id' => $biz3->id, 'name' => 'Penitipan Hamster & Burung', 'category' => 'Penitipan', 'unit' => 'hari', 'price' => 65000, 'status' => true]);
        $s10 = Service::create(['business_id' => $biz3->id, 'name' => 'Cek Kesehatan', 'category' => 'Perawatan', 'unit' => 'sesi', 'price' => 150000, 'status' => true]);

        Service::create(['business_id' => $biz4->id, 'name' => 'Penitipan Reguler', 'category' => 'Penitipan', 'unit' => 'hari', 'price' => 55000, 'status' => true]);

        $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        $demoBookings = [
            [$cust1, $biz1, $pet1, [[$s1, 3]], Booking::STATUS_CONFIRMED, Payment::UNPAID, null, $owner1, 1],
            [$cust2, $biz1, $pet4, [[$s3, 1]], Booking::STATUS_CONFIRMED, Payment::PAID, null, $owner1, 2],
            [$cust3, $biz1, $pet5, [[$s2, 2]], Booking::STATUS_PROGRESS, Payment::PAID, null, $owner1, 4],
            [$cust1, $biz1, $pet3, [[$s4, 1]], Booking::STATUS_DONE, Payment::PAID, null, $owner1, 12],
            [$cust2, $biz2, $pet4, [[$s5, 1], [$s7, 1]], Booking::STATUS_WAITING, Payment::UNPAID, null, $owner2, 1],
            [$cust1, $biz2, $pet2, [[$s5, 1]], Booking::STATUS_PROGRESS, Payment::PAID, null, $owner2, 3],
            [$cust2, $biz3, $pet4, [[$s8, 2]], Booking::STATUS_CONFIRMED, Payment::PENDING, null, $owner3, 2],
            [$cust3, $biz3, $pet5, [[$s10, 1]], Booking::STATUS_DONE, Payment::PAID, null, $owner3, 10],
            [$cust1, $biz1, $pet2, [[$s3, 1]], Booking::STATUS_CONFIRMED, Payment::REJECTED, 'Nominal transfer tidak sesuai dengan tagihan.', $owner1, 3],
        ];

        foreach ($demoBookings as $idx => [$cust, $biz, $pet, $items, $bStatus, $pStatus, $rejectReason, $owner, $daysAgo]) {
            $createdAt = now()->subDays($daysAgo);
            $total = 0;
            foreach ($items as [$svc, $qty]) {
                $total += $svc->price * $qty;
            }

            $booking = Booking::create([
                'invoice_number' => 'INV-'.$createdAt->format('Ymd').'-'.str_pad((string) ($idx + 1), 4, '0', STR_PAD_LEFT),
                'customer_id' => $cust->id,
                'business_id' => $biz->id,
                'pet_id' => $pet->id,
                'booking_date' => $createdAt->copy()->addDays(2)->toDateString(),
                'booking_time' => '10:00',
                'status' => $bStatus,
                'total_price' => $total,
                'notes' => null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            foreach ($items as [$svc, $qty]) {
                BookingItem::create([
                    'booking_id' => $booking->id,
                    'service_id' => $svc->id,
                    'price' => $svc->price,
                    'quantity' => $qty,
                ]);
            }

            $proofPath = null;
            if ($pStatus !== Payment::UNPAID) {
                $proofPath = 'payment-proofs/demo-'.$booking->id.'.png';
                Storage::disk(Payment::PROOF_DISK)->put($proofPath, $pngBytes);
            }

            Payment::create([
                'booking_id' => $booking->id,
                'amount' => $total,
                'proof_path' => $proofPath,
                'status' => $pStatus,
                'reject_reason' => $rejectReason,
                'verified_by' => in_array($pStatus, [Payment::PAID, Payment::REJECTED], true) ? $owner->id : null,
                'verified_at' => in_array($pStatus, [Payment::PAID, Payment::REJECTED], true) ? $createdAt->copy()->addHours(3) : null,
                'paid_at' => $pStatus === Payment::PAID ? $createdAt->copy()->addHours(3) : null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            BookingStatusLog::create([
                'booking_id' => $booking->id,
                'changed_by' => $cust->id,
                'status' => Booking::STATUS_WAITING,
                'note' => 'Booking dibuat oleh pelanggan',
                'changed_at' => $createdAt,
            ]);

            if ($bStatus !== Booking::STATUS_WAITING) {
                BookingStatusLog::create([
                    'booking_id' => $booking->id,
                    'changed_by' => $owner->id,
                    'status' => $bStatus,
                    'note' => null,
                    'changed_at' => $createdAt->copy()->addHours(2),
                ]);
            }
        }
    }
}
