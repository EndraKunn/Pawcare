<?php

namespace App\Modules\Elga\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Payment;
use App\Modules\Elga\Admin\Services\AdminManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function __construct(private readonly AdminManagementService $service) {}

    public function index(Request $request): View
    {
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        $status = $request->query('status');
        $businesses = Business::query()->with('owner')->withCount(['services', 'bookings'])
            ->when($q !== '', fn ($builder) => $builder->where(function ($where) use ($q) {
                $where->where('business_name', 'like', "%{$q}%")
                    ->orWhere('address', 'like', "%{$q}%")
                    ->orWhereHas('owner', fn ($owner) => $owner->where('name','like',"%{$q}%")->orWhere('email','like',"%{$q}%"));
            }))
            ->when($status === 'aktif', fn ($query) => $query->where('status', true))
            ->when($status === 'suspend', fn ($query) => $query->where('status', false))
            ->orderBy('business_name')->paginate(10)->withQueryString();
        return view('elga-admin::clients.index', [
            'businesses' => $businesses, 'q' => $q, 'status' => $status,
            'active' => Business::where('status', true)->count(),
            'suspended' => Business::where('status', false)->count(),
        ]);
    }

    public function show(int $business): View
    {
        $model = Business::with('owner')->withCount(['services', 'bookings'])->findOrFail($business);
        $paid = Payment::where('status', Payment::PAID)->whereHas('booking', fn ($booking) => $booking->where('business_id', $model->id));
        return view('elga-admin::clients.show', [
            'business' => $model,
            'services' => $model->services()->orderBy('category')->orderBy('name')->get(),
            'paidCount' => (clone $paid)->count(),
            'revenue' => (float) (clone $paid)->sum('amount'),
        ]);
    }

    public function edit(int $business): View
    {
        return view('elga-admin::clients.edit', ['business' => Business::with('owner')->findOrFail($business)]);
    }

    public function update(Request $request, int $business): RedirectResponse
    {
        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
        $model = $this->service->updateBusiness($request->user(), Business::findOrFail($business), $data);
        return redirect()->route('admin.clients.show', $model->id)->with('success','Informasi bisnis diperbarui.');
    }

    public function toggle(Request $request, int $business): RedirectResponse
    {
        $model = $this->service->toggleBusiness($request->user(), Business::findOrFail($business));
        return back()->with('success', $model->status ? 'Bisnis diaktifkan.' : 'Bisnis dinonaktifkan tanpa menghapus riwayat.');
    }
}
