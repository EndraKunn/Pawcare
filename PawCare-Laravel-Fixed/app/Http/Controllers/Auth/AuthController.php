<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Hanya akun aktif yang dapat login. Role ditentukan oleh akun, bukan dari formulir.
        $attempt = Auth::attempt(
            ['email' => $credentials['email'], 'password' => $credentials['password'], 'status' => true],
            $request->boolean('remember')
        );

        if (! $attempt) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Email atau password salah, atau akun dinonaktifkan.']);
        }

        $request->session()->regenerate();

        return redirect()->route($request->user()->dashboardRoute());
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('success', 'Anda telah keluar.');
    }

    public function showRegisterCustomer(): View
    {
        return view('auth.register-customer');
    }

    public function registerCustomer(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:100', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:20'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => $data['password'], // di-hash otomatis oleh cast 'hashed'
            'role' => User::ROLE_CUSTOMER,
            'status' => true,
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('customer.dashboard')->with('success', 'Akun berhasil dibuat. Tambahkan data hewan Anda.');
    }

    public function showRegisterOwner(): View
    {
        return view('auth.register-owner');
    }

    public function registerOwner(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'business_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:100', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:20'],
            'address' => ['required', 'string', 'max:500'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => $data['password'],
                'role' => User::ROLE_OWNER,
                'status' => true,
            ]);

            Business::create([
                'owner_id' => $user->id,
                'business_name' => $data['business_name'],
                'address' => $data['address'],
                'status' => true,
            ]);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('owner.dashboard')->with('success', 'Pendaftaran mitra berhasil. Lengkapi profil bisnis dan tambahkan layanan.');
    }
}
