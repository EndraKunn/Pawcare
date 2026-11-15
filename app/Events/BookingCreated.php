<?php

namespace App\Events;

use App\Models\Booking;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookingCreated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public Booking $booking) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('business.'.$this->booking->business_id.'.care-board')];
    }

    public function broadcastAs(): string { return 'booking.created'; }

    public function broadcastWith(): array
    {
        $this->booking->loadMissing(['customer:id,name', 'pet:id,name,species', 'items.service:id,name']);
        return [
            'booking_id' => $this->booking->id,
            'invoice_number' => $this->booking->invoice_number,
            'status' => $this->booking->status,
            'customer_name' => $this->booking->customer?->name,
            'pet_name' => $this->booking->pet?->name,
            'pet_species' => $this->booking->pet?->species,
            'schedule_label' => $this->booking->schedule_label,
            'service_names' => $this->booking->items->map(fn ($item) => $item->service?->name)->filter()->values()->all(),
            'url' => route('owner.bookings.show', $this->booking->id),
        ];
    }
}
