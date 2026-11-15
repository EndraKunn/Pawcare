<?php

namespace App\Events;

use App\Models\Booking;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookingStatusUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public Booking $booking, public string $previousStatus, public ?string $note = null) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('business.'.$this->booking->business_id.'.care-board'),
            new PrivateChannel('users.'.$this->booking->customer_id.'.tracking'),
        ];
    }

    public function broadcastAs(): string { return 'booking.status.updated'; }

    public function broadcastWith(): array
    {
        $this->booking->loadMissing(['customer:id,name', 'pet:id,name']);
        return [
            'booking_id' => $this->booking->id,
            'invoice_number' => $this->booking->invoice_number,
            'previous_status' => $this->previousStatus,
            'status' => $this->booking->status,
            'note' => $this->note,
            'customer_name' => $this->booking->customer?->name,
            'pet_name' => $this->booking->pet?->name,
            'booking_date' => $this->booking->booking_date?->toDateString(),
            'booking_time' => $this->booking->time_label,
            'changed_at' => now()->toIso8601String(),
        ];
    }
}
