<?php
namespace App\Modules\Elga\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Elga\Auth\Services\ProfileService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(private readonly ProfileService $profiles) {}
    public function edit(Request $request): View
    {
        return view('elga-auth::profile.edit', ['user' => $request->user()]);
    }
    public function update(Request $request): RedirectResponse
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'name' => ['required','string','max:100'],
            'phone' => ['nullable','string','max:20'],
            'email' => ['required','email','max:100',Rule::unique('users','email')->ignore($request->user()->id)],
        ]);
        $this->profiles->update($request->user(), $data);
        return back()->with('success', 'Profil berhasil diperbarui.');
    }
    public function password(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required','string'],
            'password' => ['required','confirmed', Password::min(8)],
        ]);
        $this->profiles->password($request->user(), $data['current_password'], $data['password']);
        return back()->with('success', 'Password berhasil diperbarui.');
    }
}
