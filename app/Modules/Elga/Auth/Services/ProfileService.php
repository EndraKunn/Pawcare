<?php
namespace App\Modules\Elga\Auth\Services;

use App\Models\User;
use App\Modules\Elga\Shared\AuditTrail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ProfileService
{
    public function __construct(private readonly AuditTrail $audit) {}
    public function update(User $user, array $data): void
    {
        $before = $user->only(['name','email','phone']);
        $user->update($data); // never accepts role/status
        $this->audit->record('user.profile.update', $user, $before, $user->only(['name','email','phone']), $user->id);
    }
    public function password(User $user, string $current, string $new): void
    {
        if (! Hash::check($current, $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'Password saat ini tidak sesuai.']);
        }
        $user->update(['password' => $new]);
        $this->audit->record('user.password.change', $user, [], [], $user->id);
    }
}
