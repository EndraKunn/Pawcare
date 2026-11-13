<?php

namespace App\Modules\Elga\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Elga\Admin\Services\AdminManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(private readonly AdminManagementService $service) {}

    public function index(Request $request): View
    {
        $role = $request->query('role');
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        $users = User::query()
            ->when(in_array($role, [User::ROLE_CUSTOMER, User::ROLE_OWNER, User::ROLE_ADMIN], true), fn ($query) => $query->where('role', $role))
            ->when($q !== '', fn ($query) => $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%");
            }))
            ->orderBy('id')->paginate(15)->withQueryString();
        $counts = [
            'all' => User::count(),
            User::ROLE_CUSTOMER => User::where('role', User::ROLE_CUSTOMER)->count(),
            User::ROLE_OWNER => User::where('role', User::ROLE_OWNER)->count(),
            User::ROLE_ADMIN => User::where('role', User::ROLE_ADMIN)->count(),
        ];
        return view('elga-admin::users.index', compact('users', 'role', 'q', 'counts'));
    }

    public function show(int $user): View
    {
        $model = User::withCount(['pets','bookings','businesses'])->findOrFail($user);
        return view('elga-admin::users.show', [
            'user' => $model,
            'businesses' => $model->businesses()->withCount(['services','bookings'])->get(),
            'recentBookings' => $model->bookings()->with(['business','pet'])->latest('id')->take(5)->get(),
        ]);
    }

    public function toggle(Request $request, int $user): RedirectResponse
    {
        $target = $this->service->toggleUser($request->user(), User::findOrFail($user));
        return back()->with('success', 'Status akun '.$target->name.' berhasil diperbarui.');
    }
}
