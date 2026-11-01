<?php

namespace App\Models;

use App\Support\PawcareImage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Business extends Model
{
    public const SPECIES = Pet::SPECIES;

    protected $fillable = [
        'owner_id',
        'business_name',
        'address',
        'description',
        'accepted_species',
        'bank_name',
        'bank_account_number',
        'bank_account_name',
        'status',
        'cover_photo',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
            'accepted_species' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Business $business) {
            if ($business->accepted_species === null) {
                $business->accepted_species = self::SPECIES;
            }
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function operatingHours(): HasMany
    {
        return $this->hasMany(BusinessOperatingHour::class);
    }

    public function closures(): HasMany
    {
        return $this->hasMany(BusinessClosure::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('owner_id', $user->id);
    }

    /**
     * Filter bisnis berdasarkan jenis hewan yang diterima di database.
     */
    public function scopeAcceptsSpecies(Builder $query, string $species): Builder
    {
        return $query->whereJsonContains('accepted_species', $species);
    }

    /**
     * Daftar jenis hewan yang diterima bisnis (tervalidasi terhadap Pet::SPECIES).
     *
     * @return string[]
     */
    public function getAcceptedSpeciesListAttribute(): array
    {
        if (! is_array($this->accepted_species)) {
            return [];
        }

        return array_values(array_intersect(self::SPECIES, $this->accepted_species));
    }

    /** Foto sampul: unggahan -> foto lokal per bisnis -> foto lokal umum -> ilustrasi fallback. */
    public function getCoverUrlAttribute(): string
    {
        $n = PawcareImage::coverIndex((int) $this->id);

        return PawcareImage::upload($this->cover_photo)
            ?? PawcareImage::photo('businesses/business-'.$this->id)
            ?? PawcareImage::resolve('businesses/cover-'.$n, 'cover-'.$n.'.svg');
    }

    public function getCoverFallbackUrlAttribute(): string
    {
        return PawcareImage::fallback('cover-'.PawcareImage::coverIndex((int) $this->id).'.svg');
    }

    public function getHasCoverPhotoAttribute(): bool
    {
        return PawcareImage::upload($this->cover_photo) !== null;
    }

    public function getIconAttribute(): string
    {
        $icons = ['🏨', '✂️', '🩺', '🐾', '🏡'];

        return $icons[$this->id % count($icons)];
    }
}
