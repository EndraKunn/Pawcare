<?php

namespace App\Models;

use App\Support\PawcareImage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    public const CATEGORIES = ['Penitipan', 'Grooming', 'Perawatan'];
    public const UNITS = ['hari', 'sesi'];

    protected $fillable = [
        'business_id',
        'name',
        'category',
        'unit',
        'duration_minutes',
        'description',
        'price',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'float',
            'status' => 'boolean',
            'duration_minutes' => 'integer',
        ];
    }

    /** Foto per kategori layanan: foto lokal -> ilustrasi fallback. */
    public function getImageUrlAttribute(): string
    {
        $slug = PawcareImage::categorySlug($this->category);

        return $slug
            ? PawcareImage::resolve('services/'.$slug, 'service-'.$slug.'.svg')
            : PawcareImage::fallback('generic.svg');
    }

    public function getImageFallbackUrlAttribute(): string
    {
        $slug = PawcareImage::categorySlug($this->category);

        return PawcareImage::fallback($slug ? 'service-'.$slug.'.svg' : 'generic.svg');
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function bookingItems(): HasMany
    {
        return $this->hasMany(BookingItem::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    public function scopeForOwner(Builder $query, User $owner): Builder
    {
        return $query->whereHas('business', fn ($b) => $b->where('owner_id', $owner->id));
    }
}
