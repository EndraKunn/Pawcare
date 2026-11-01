<?php

namespace App\Http\Controllers\Owner;

use App\Models\Review;
use App\Services\ReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReviewController extends OwnerController
{
    public function index(Request $request, ReportService $reports): View
    {
        $business = $this->business($request);
        $businessIds = $this->businessIds($request);
        $ratingFilter = (int) $request->query('rating', 0);

        $reviews = Review::query()
            ->validCompleted()
            ->whereIn('business_id', $businessIds)
            ->when($ratingFilter >= 1 && $ratingFilter <= 5, fn ($q) => $q->where('rating', $ratingFilter))
            ->with(['customer:id,name', 'booking.pet:id,name,species', 'booking.items.service:id,name', 'reply.owner:id,name'])
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        $summary = $reports->ratingSummary($businessIds);

        return view('owner.reviews.index', [
            'business' => $business,
            'reviews' => $reviews,
            'summary' => $summary,
            'ratingFilter' => $ratingFilter,
        ]);
    }

    /**
     * Menyimpan tanggapan Owner pada entitas terpisah (review_replies)
     * tanpa mengubah rating maupun isi ulasan pelanggan.
     */
    public function reply(Request $request, int $review): RedirectResponse
    {
        $model = Review::forOwner($request->user())->findOrFail($review);

        $data = $request->validate([
            'reply' => ['required', 'string', 'max:1000'],
        ], [
            'reply.required' => 'Tanggapan tidak boleh kosong.',
            'reply.max' => 'Tanggapan maksimal 1.000 karakter.',
        ]);

        $model->reply()->updateOrCreate(
            ['review_id' => $model->id],
            [
                'owner_id' => $request->user()->id,
                'reply' => trim($data['reply']),
            ]
        );

        return back()->with('success', 'Tanggapan ulasan berhasil disimpan.');
    }
}
