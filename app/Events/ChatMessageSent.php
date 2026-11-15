<?php

namespace App\Events;

use App\Models\ConversationMessage;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageSent implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public ConversationMessage $message) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('conversations.'.$this->message->conversation_id)];
    }

    public function broadcastAs(): string { return 'chat.message.sent'; }

    public function broadcastWith(): array
    {
        $this->message->loadMissing('sender:id,name,role');
        return [
            'id' => $this->message->id, 'conversation_id' => $this->message->conversation_id,
            'sender_id' => $this->message->sender_id, 'sender_name' => $this->message->sender?->name ?? 'Pengguna',
            'sender_role' => $this->message->sender?->role, 'body' => $this->message->body,
            'created_at' => $this->message->created_at?->toIso8601String(),
        ];
    }
}
