<?php

namespace App\Events;

use App\Models\Business;
use App\Models\Service;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CatalogChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $businessId,
        public ?int $serviceId,
        public string $serviceName,
        public string $category,
        public int $priceRupiah,
        public int $durationMinutes,
        public int $capacity,
        public bool $status,
        public string $action = 'updated'
    ) {
    }

    public static function fromService(Service $service, string $action = 'updated'): self
    {
        return new self(
            businessId: (int) $service->business_id,
            serviceId: (int) $service->id,
            serviceName: (string) $service->name,
            category: (string) $service->category,
            priceRupiah: (int) round((float) $service->price),
            durationMinutes: (int) ($service->duration_minutes ?: 60),
            capacity: (int) ($service->capacity ?: 5),
            status: (bool) $service->status,
            action: $action
        );
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('catalog.updates'),
            new PrivateChannel('business.'.$this->businessId.'.care-board'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'catalog.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'business_id' => $this->businessId,
            'service_id' => $this->serviceId,
            'service_name' => $this->serviceName,
            'category' => $this->category,
            'price' => $this->priceRupiah,
            'duration_minutes' => $this->durationMinutes,
            'capacity' => $this->capacity,
            'status' => $this->status,
            'action' => $this->action,
            'changed_at' => now()->toIso8601String(),
        ];
    }
}
