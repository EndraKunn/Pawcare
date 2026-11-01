<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    public const ROLE_CUSTOMER = 'customer';
    public const ROLE_OWNER = 'business_owner';
    public const ROLE_ADMIN = 'admin';

    /**
     * Catatan keamanan: role/status HANYA boleh diisi dari kode server
     * (registrasi, seeder, admin), jangan pernah dari $request->all().
     */
    protected $fillable = ['name', 'email', 'phone', 'password', 'role', 'status'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'status' => 'boolean',
        ];
    }

    public function businesses(): HasMany
    {
        return $this->hasMany(Business::class, 'owner_id');
    }

    public function pets(): HasMany
    {
        return $this->hasMany(Pet::class, 'customer_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'customer_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'customer_id');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'customer_id');
    }

    public function isCustomer(): bool
    {
        return $this->role === self::ROLE_CUSTOMER;
    }

    public function isOwner(): bool
    {
        return $this->role === self::ROLE_OWNER;
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /** Bisnis utama pemilik (UI mengelola satu bisnis; database mendukung lebih dari satu). */
    public function primaryBusiness(): ?Business
    {
        return $this->businesses()->orderBy('id')->first();
    }

    public function roleLabel(): string
    {
        return match ($this->role) {
            self::ROLE_ADMIN => 'Administrator',
            self::ROLE_OWNER => 'Pemilik Bisnis',
            default => 'Pelanggan',
        };
    }

    /** Route dashboard sesuai role. */
    public function dashboardRoute(): string
    {
        return match ($this->role) {
            self::ROLE_ADMIN => 'admin.dashboard',
            self::ROLE_OWNER => 'owner.dashboard',
            default => 'customer.dashboard',
        };
    }
}
