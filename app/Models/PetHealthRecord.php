<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class PetHealthRecord extends Model
{
    public const TYPE_VACCINATION = 'vaccination';
    public const TYPE_ALLERGY = 'allergy';
    public const TYPE_CONDITION = 'condition';
    public const TYPE_MEDICATION = 'medication';
    public const TYPE_NOTE = 'note';

    public const TYPES = [
        self::TYPE_VACCINATION => 'Vaksinasi',
        self::TYPE_ALLERGY => 'Alergi',
        self::TYPE_CONDITION => 'Kondisi / Kebutuhan Khusus',
        self::TYPE_MEDICATION => 'Riwayat Obat / Perawatan',
        self::TYPE_NOTE => 'Catatan Kesehatan Tambahan',
    ];

    public const ATTACHMENT_DISK = 'local';
    public const ATTACHMENT_DIRECTORY = 'pet-health-attachments';
    public const ATTACHMENT_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
    public const ATTACHMENT_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'application/pdf',
    ];

    protected $fillable = [
        'pet_id',
        'customer_id',
        'record_type',
        'title',
        'recorded_date',
        'next_due_date',
        'notes',
        'attachment_path',
        'attachment_name',
    ];

    protected function casts(): array
    {
        return [
            'recorded_date' => 'date',
            'next_due_date' => 'date',
        ];
    }

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function sharingPermissions(): HasMany
    {
        return $this->hasMany(PetHealthSharingPermission::class, 'pet_health_record_id');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->record_type] ?? 'Catatan';
    }

    /**
     * Periksa apakah catatan kesehatan ini diizinkan dilihat oleh bisnis tertentu.
     * Jika terdapat aturan eksplisit di pet_health_sharing_permissions, gunakan statusnya;
     * jika belum ada aturan spesifik, ikuti flag global share_health_with_business pada Pet.
     */
    public function isSharedWithBusiness(int $businessId): bool
    {
        $this->loadMissing('pet');
        if (! $this->pet) {
            return false;
        }

        if (Schema::hasTable('pet_health_sharing_permissions')) {
            $permission = $this->sharingPermissions()
                ->where('business_id', $businessId)
                ->first();

            if ($permission !== null) {
                return $permission->isActive();
            }
        }

        return (bool) $this->pet->share_health_with_business;
    }
}
