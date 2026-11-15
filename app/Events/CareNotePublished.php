<?php

namespace App\Events;

use App\Models\PetCareNote;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CareNotePublished implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public PetCareNote $note) {}

    public function broadcastOn(): array
    {
        $this->note->loadMissing('booking');
        $channels = [new PrivateChannel('business.'.$this->note->business_id.'.care-board')];

        if ($this->note->visible_to_customer) {
            $channels[] = new PrivateChannel('users.'.$this->note->booking->customer_id.'.tracking');
        }

        return $channels;
    }

    public function broadcastAs(): string { return 'care-note.published'; }

    public function broadcastWith(): array
    {
        $this->note->loadMissing(['booking', 'author:id,name']);
        return [
            'id' => $this->note->id, 'booking_id' => $this->note->booking_id,
            'invoice_number' => $this->note->booking?->invoice_number,
            'care_summary' => $this->note->care_summary, 'recommendations' => $this->note->recommendations,
            'author_name' => $this->note->author?->name ?? 'Tim Bisnis',
            'created_at' => $this->note->created_at?->toIso8601String(),
        ];
    }
}
