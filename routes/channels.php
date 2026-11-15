<?php

use App\Models\Business;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('users.{userId}.tracking', fn (User $user, int $userId): bool => (int) $user->id === $userId);
Broadcast::channel('users.{userId}.notifications', fn (User $user, int $userId): bool => (int) $user->id === $userId);

Broadcast::channel('business.{businessId}.care-board', function (User $user, int $businessId): bool {
    return $user->isOwner()
        && Business::query()->whereKey($businessId)->where('owner_id', $user->id)->exists();
});

Broadcast::channel('conversations.{conversationId}', function (User $user, int $conversationId): bool {
    return Conversation::query()->whereKey($conversationId)
        ->where(function ($query) use ($user) {
            $query->where('customer_id', $user->id)
                ->orWhereHas('business', fn ($business) => $business->where('owner_id', $user->id));
        })->exists();
});
