<?php

namespace App\Modules\Elga\Shared;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/** Never record password, tokens, bank numbers, or arbitrary request bodies. */
class AuditTrail
{
    public function record(string $action, Model $subject, array $before = [], array $after = [], ?int $actorId = null): void
    {
        DB::table('audit_logs')->insert([
            'actor_id' => $actorId ?? auth()->id(),
            'action' => $action,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'before_values' => json_encode($before, JSON_THROW_ON_ERROR),
            'after_values' => json_encode($after, JSON_THROW_ON_ERROR),
            'ip_address' => request()?->ip(),
            'created_at' => now(),
        ]);
    }
}
