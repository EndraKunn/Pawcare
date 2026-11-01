<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $role = $request->query('role');
        $q = trim((string) $request->query('q', ''));

        $users = User::query()
            ->when(in_array($role, [User::ROLE_CUSTOMER, User::ROLE_OWNER, User::ROLE_ADMIN], true), fn ($x) => $x->where('role', $role))
            ->when($q !== '', fn ($x) => $x->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")))
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        $counts = [
            'all' => User::count(),
            User::ROLE_CUSTOMER => User::where('role', User::ROLE_CUSTOMER)->count(),
            User::ROLE_OWNER => User::where('role', User::ROLE_OWNER)->count(),
            User::ROLE_ADMIN => User::where('role', User::ROLE_ADMIN)->count(),
        ];

        return view('admin.users.index', compact('users', 'role', 'q', 'counts'));
    }

    public function show(int $user): View
    {
        $model = User::withCount(['pets', 'bookings', 'businesses'])->findOrFail($user);
        $businesses = $model->businesses()->withCount(['services', 'bookings'])->get();
        $recentBookings = $model->bookings()->with(['business', 'pet'])->latest('id')->take(5)->get();

        return view('admin.users.show', ['user' => $model, 'businesses' => $businesses, 'recentBookings' => $recentBookings]);
    }

    public function toggle(Request $request, int $user): RedirectResponse
    {
        $model = User::findOrFail($user);

        if ($model->id === $request->user()->id) {
            return back()->with('error', 'Anda tidak dapat menonaktifkan akun Anda sendiri.');
        }

        $model->update(['status' => ! $model->status]);

        return back()->with('success', 'Akun '.$model->name.($model->status ? ' diaktifkan.' : ' dinonaktifkan.'));
    }
}
