<?php

namespace App\Modules\Elga\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\PawCareWelcomeMail;
use App\Modules\Elga\Auth\Services\AuthenticationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function __construct(private readonly AuthenticationService $auth) {}

    public function showLogin(): View { return view('elga-auth::login'); }
    public function showRegisterCustomer(): View { return view('elga-auth::register-customer'); }
    public function showRegisterOwner(): View { return view('elga-auth::register-owner'); }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        if (! $this->auth->login($data, $request->boolean('remember'))) {
            return back()->withInput($request->only('email'))
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

    public function registerCustomer(Request $request): RedirectResponse
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $user = $this->auth->registerCustomer($request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:100', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:20'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]));
        $this->welcome($user->email, $user->name, $user->role);
        Auth::login($user);
        $request->session()->regenerate();
        return redirect()->route('customer.dashboard')->with('success', 'Akun berhasil dibuat.');
    }

    public function registerOwner(Request $request): RedirectResponse
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $user = $this->auth->registerOwner($request->validate([
            'name' => ['required', 'string', 'max:100'],
            'business_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:100', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:20'],
            'address' => ['required', 'string', 'max:500'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]));
        $this->welcome($user->email, $user->name, $user->role);
        Auth::login($user);
        $request->session()->regenerate();
        return redirect()->route('owner.dashboard')->with('success', 'Pendaftaran mitra berhasil.');
    }

    private function welcome(string $email, string $name, string $role): void
    {
        try {
            Mail::to($email)->send(new PawCareWelcomeMail($name, $role));
        } catch (\Throwable $exception) {
            // Email gagal tidak menggagalkan registrasi; detail kredensial SMTP tidak dicatat.
            Log::warning('PawCare welcome email gagal dikirim', ['exception' => get_class($exception)]);
        }
    }
}
