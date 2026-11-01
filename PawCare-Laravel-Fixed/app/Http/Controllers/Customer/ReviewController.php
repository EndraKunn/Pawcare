<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
    public function store(Request $request, int $booking): RedirectResponse
    {
        $model = Booking::forCustomer($request->user())->findOrFail($booking);

        if ($model->status !== Booking::STATUS_DONE) {
            return back()->with('error', 'Ulasan hanya dapat diberikan setelah booking berstatus Selesai.');
        }

        if ($model->review()->exists()) {
            return back()->with('error', 'Booking ini sudah memiliki ulasan.');
        }

        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ], [
            'rating.required' => 'Pilih rating bintang 1 sampai 5.',
            'rating.integer' => 'Rating harus berupa angka bulat.',
            'rating.min' => 'Rating minimal 1 bintang.',
            'rating.max' => 'Rating maksimal 5 bintang.',
            'comment.max' => 'Ulasan maksimal 1.000 karakter.',
        ]);

        DB::transaction(function () use ($request, $model, $data) {
            if ($model->review()->exists()) {
                return;
            }

            Review::create([
                'booking_id' => $model->id,
                'customer_id' => $request->user()->id,
                'business_id' => $model->business_id,
                'rating' => (int) $data['rating'],
                'comment' => isset($data['comment']) ? trim($data['comment']) : null,
            ]);
        });

        return back()->with('success', 'Terima kasih! Rating dan ulasan Anda berhasil disimpan.');
    }

    public function update(Request $request, int $review): RedirectResponse
    {
        $model = Review::forCustomer($request->user())->findOrFail($review);

        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ], [
            'rating.min' => 'Rating minimal 1 bintang.',
            'rating.max' => 'Rating maksimal 5 bintang.',
            'comment.max' => 'Ulasan maksimal 1.000 karakter.',
        ]);

        $model->update([
            'rating' => (int) $data['rating'],
            'comment' => isset($data['comment']) ? trim($data['comment']) : null,
        ]);

        return back()->with('success', 'Ulasan Anda berhasil diperbarui.');
    }

    public function destroy(Request $request, int $review): RedirectResponse
    {
        $model = Review::forCustomer($request->user())->findOrFail($review);
        $model->delete();

        return back()->with('success', 'Ulasan berhasil dihapus.');
    }
}
