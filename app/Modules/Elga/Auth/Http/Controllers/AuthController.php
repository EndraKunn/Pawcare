<?php

namespace App\Modules\Elga\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\PawCareWelcomeMail;
use App\Models\User;
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
    public function __construct(
        private readonly AuthenticationService $auth
    ) {}

    public function showLogin(): View
    {
        return view('elga-auth::login');
    }

    public function showRegisterCustomer(): View
    {
        return view('elga-auth::register-customer');
    }

    public function showRegisterOwner(): View
    {
        return view('elga-auth::register-owner');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! $this->auth->login($data, $request->boolean('remember'))) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => 'Email atau password salah, atau akun dinonaktifkan.',
                ]);
        }

        $request->session()->regenerate();

        $user = $request->user();

        if (! $user->isAdmin() && ! $user->hasVerifiedEmail()) {
            return redirect()
                ->route('verification.notice')
                ->with('success', 'Silakan verifikasi email Anda sebelum mengakses dashboard.');
        }

        return redirect()->route($user->dashboardRoute());
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('home')
            ->with('success', 'Anda telah keluar.');
    }

    public function registerCustomer(Request $request): RedirectResponse
    {
        $request->merge([
            'email' => mb_strtolower(trim((string) $request->input('email'))),
        ]);

        $user = $this->auth->registerCustomer($request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:100', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:20'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]));

        Auth::login($user);
        $request->session()->regenerate();

        $this->sendRegistrationEmails($user);

        return redirect()
            ->route('verification.notice')
            ->with('success', 'Akun berhasil dibuat. Silakan verifikasi email Anda.');
    }

    public function registerOwner(Request $request): RedirectResponse
    {
        $request->merge([
            'email' => mb_strtolower(trim((string) $request->input('email'))),
        ]);

        $user = $this->auth->registerOwner($request->validate([
            'name' => ['required', 'string', 'max:100'],
            'business_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:100', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:20'],
            'address' => ['required', 'string', 'max:500'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]));

        Auth::login($user);
        $request->session()->regenerate();

        $this->sendRegistrationEmails($user);

        return redirect()
            ->route('verification.notice')
            ->with('success', 'Pendaftaran mitra berhasil. Silakan verifikasi email Anda.');
    }

    private function sendRegistrationEmails(User $user): void
    {
        // Welcome email dikirim melalui database queue.
        try {
            Mail::to($user->email)->queue(
                new PawCareWelcomeMail($user->name, $user->role)
            );
        } catch (\Throwable $exception) {
            Log::warning('PawCare welcome email gagal masuk antrean', [
                'exception' => get_class($exception),
            ]);
        }

        // Mengirim notifikasi verifikasi email Laravel.
        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable $exception) {
            Log::warning('PawCare verification email gagal dikirim', [
                'exception' => get_class($exception),
            ]);
        }
    }
}