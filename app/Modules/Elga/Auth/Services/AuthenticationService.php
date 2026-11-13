<?php

namespace App\Modules\Elga\Auth\Services;

use App\Models\Business;
use App\Models\User;
use App\Modules\Elga\Shared\AuditTrail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AuthenticationService
{
    public function __construct(private readonly AuditTrail $audit) {}

    public function login(array $credentials, bool $remember = false): bool
    {
        return Auth::attempt([
            'email' => mb_strtolower(trim($credentials['email'])),
            'password' => $credentials['password'],
            'status' => true,
        ], $remember);
    }

    public function registerCustomer(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => trim($data['name']),
                'email' => mb_strtolower(trim($data['email'])),
                'phone' => $data['phone'],
                'password' => $data['password'],
                'role' => User::ROLE_CUSTOMER,
                'status' => true,
            ]);
            $this->audit->record('user.register.customer', $user, [], ['role' => $user->role], $user->id);
            return $user;
        });
    }

    public function registerOwner(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $owner = User::create([
                'name' => trim($data['name']),
                'email' => mb_strtolower(trim($data['email'])),
                'phone' => $data['phone'],
                'password' => $data['password'],
                'role' => User::ROLE_OWNER,
                'status' => true,
            ]);
            $business = Business::create([
                'owner_id' => $owner->id,
                'business_name' => trim($data['business_name']),
                'address' => trim($data['address']),
                'status' => true,
            ]);
            $this->audit->record('user.register.owner', $owner, [], ['role' => $owner->role], $owner->id);
            $this->audit->record('business.create', $business, [], ['owner_id' => $owner->id], $owner->id);
            return $owner;
        });
    }
}
