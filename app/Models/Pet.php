<?php

namespace App\Models;

use App\Support\PawcareImage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pet extends Model
{
    public const SPECIES = ['Anjing', 'Kucing', 'Kelinci', 'Hamster', 'Burung', 'Reptil'];
    public const GENDERS = ['Jantan', 'Betina'];

    protected $fillable = [
        'customer_id',
        'name',
        'species',
        'breed',
        'age',
        'gender',
        'photo',
        'archived_at',
        'share_health_with_business',
    ];

    protected $casts = [
        'archived_at' => 'datetime',
        'share_health_with_business' => 'boolean',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function healthRecords(): HasMany
    {
        return $this->hasMany(PetHealthRecord::class)->orderByDesc('recorded_date')->orderByDesc('id');
    }

    public function careNotes(): HasMany
    {
        return $this->hasMany(PetCareNote::class)->latest('id');
    }

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('customer_id', $user->id);
    }

    /** Foto hewan: unggahan pemilik -> foto lokal per jenis -> ilustrasi fallback. */
    public function getPhotoUrlAttribute(): string
    {
        $slug = PawcareImage::speciesSlug($this->species);

        return PawcareImage::upload($this->photo)
            ?? PawcareImage::resolve('pets/'.$slug, 'pet-'.$slug.'.svg');
    }

    public function getPhotoFallbackUrlAttribute(): string
    {
        return PawcareImage::fallback('pet-'.PawcareImage::speciesSlug($this->species).'.svg');
    }

    public function getHasPhotoAttribute(): bool
    {
        return PawcareImage::upload($this->photo) !== null;
    }

    public function getEmojiAttribute(): string
    {
        return match ($this->species) {
            'Anjing' => '🐶',
            'Kucing' => '🐱',
            'Kelinci' => '🐰',
            'Hamster' => '🐹',
            'Burung' => '🐦',
            'Reptil' => '🦎',
            default => '🐾',
        };
    }

    /** Umur disimpan dalam bulan, ditampilkan sebagai tahun/bulan. */
    public function getAgeLabelAttribute(): string
    {
        if ($this->age === null) {
            return '-';
        }
        if ($this->age < 12) {
            return $this->age.' bulan';
        }
        $years = intdiv($this->age, 12);
        $months = $this->age % 12;

        return $years.' tahun'.($months ? ' '.$months.' bulan' : '');
    }
}
