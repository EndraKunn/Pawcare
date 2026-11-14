<?php

namespace App\Modules\Jeni\BookingCalendar\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OwnerPreorderController extends Controller
{
    public function edit(Request $request): View
    {
        $business = $request->user()->primaryBusiness();
        abort_unless($business, 403, 'Akun ini belum memiliki bisnis.');

        return view('jeni-booking::owner-preorder', compact('business'));
    }

    public function update(Request $request): RedirectResponse
    {
        $business = $request->user()->primaryBusiness();
        abort_unless($business, 403, 'Akun ini belum memiliki bisnis.');

        $data = $request->validate([
            'pre_order_enabled' => ['nullable', 'boolean'],
            'pre_order_max_days' => ['required', 'integer', 'min:31', 'max:365'],
            'pre_order_slot_capacity' => ['required', 'integer', 'min:1', 'max:100'],
        ]);
        // forceFill avoids modifying the shared Business model's fillable list.
        $business->forceFill([
            'pre_order_enabled' => $request->boolean('pre_order_enabled'),
            'pre_order_max_days' => (int) $data['pre_order_max_days'],
            'pre_order_slot_capacity' => (int) $data['pre_order_slot_capacity'],
        ])->save();

        return back()->with('success', 'Pengaturan pre-order berhasil disimpan.');
    }
}
