<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Manajemen klien = pemilik bisnis (users.role = business_owner) + bisnis miliknya. */
class ClientController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $status = $request->query('status');

        $businesses = Business::query()
            ->with('owner')
            ->withCount(['services', 'bookings'])
            ->when($q !== '', fn ($b) => $b->where(function ($w) use ($q) {
                $w->where('business_name', 'like', "%{$q}%")
                    ->orWhere('address', 'like', "%{$q}%")
                    ->orWhereHas('owner', fn ($o) => $o->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%"));
            }))
            ->when($status === 'aktif', fn ($b) => $b->where('status', true))
            ->when($status === 'suspend', fn ($b) => $b->where('status', false))
            ->orderBy('business_name')
            ->paginate(10)
            ->withQueryString();

        return view('admin.clients.index', [
            'businesses' => $businesses,
            'q' => $q,
            'status' => $status,
            'active' => Business::where('status', true)->count(),
            'suspended' => Business::where('status', false)->count(),
        ]);
    }

    public function show(int $business): View
    {
        $model = Business::with('owner')->withCount(['services', 'bookings'])->findOrFail($business);
        $paid = Payment::where('status', Payment::PAID)->whereHas('booking', fn ($b) => $b->where('business_id', $model->id));

        return view('admin.clients.show', [
            'business' => $model,
            'services' => $model->services()->orderBy('category')->orderBy('name')->get(),
            'paidCount' => (clone $paid)->count(),
            'revenue' => (float) (clone $paid)->sum('amount'),
        ]);
    }

    public function edit(int $business): View
    {
        return view('admin.clients.edit', ['business' => Business::with('owner')->findOrFail($business)]);
    }

    public function update(Request $request, int $business): RedirectResponse
    {
        $model = Business::findOrFail($business);

        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:2000'],
        ], [], ['business_name' => 'nama bisnis', 'address' => 'alamat', 'description' => 'deskripsi']);

        $model->update($data);

        return redirect()->route('admin.clients.show', $model->id)->with('success', 'Informasi bisnis diperbarui.');
    }

    public function toggle(int $business): RedirectResponse
    {
        $model = Business::findOrFail($business);
        $model->update(['status' => ! $model->status]);

        return back()->with('success', $model->status ? 'Bisnis berhasil diaktifkan.' : 'Bisnis berhasil disuspend. Bisnis tidak tampil di katalog dan tidak menerima booking baru.');
    }
}
