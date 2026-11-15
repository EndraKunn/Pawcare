<?php

namespace Tests\Feature;

use App\Events\BookingCreated;
use App\Events\BookingStatusUpdated;
use App\Events\CareNotePublished;
use App\Events\ChatMessageSent;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\PetCareNote;
use App\Notifications\PawCareStatusNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesPawCareData;
use Tests\TestCase;

class RealtimeCommunicationTest extends TestCase
{
    use RefreshDatabase, CreatesPawCareData;

    public function test_notification_uses_database_queue_and_retry_policy(): void
    {
        $notification = new PawCareStatusNotification('Status booking', 'Booking diperbarui');

        $this->assertInstanceOf(ShouldQueue::class, $notification);
        $this->assertSame(3, $notification->tries);
        $this->assertSame([10, 30, 60], $notification->backoff);
        $this->assertTrue((bool) config('queue.connections.database.after_commit'));
        $this->assertSame('database-uuids', config('queue.failed.driver'));
    }

    public function test_new_booking_event_contains_care_board_card_data(): void
    {
        [, $business] = $this->makeOwnerWithBusiness('New Booking Board');
        $customer = $this->makeUser();
        $pet = $this->makePet($customer);
        $service = $this->makeService($business);
        $booking = $this->makeBooking($customer, $business, $pet, [$service->id => 1]);

        $payload = (new BookingCreated($booking))->broadcastWith();

        $this->assertSame($booking->id, $payload['booking_id']);
        $this->assertSame($booking->invoice_number, $payload['invoice_number']);
        $this->assertSame('Menunggu', $payload['status']);
        $this->assertSame($customer->name, $payload['customer_name']);
        $this->assertSame($pet->name, $payload['pet_name']);
        $this->assertContains($service->name, $payload['service_names']);
        $this->assertSame(route('owner.bookings.show', $booking->id), $payload['url']);
    }

    public function test_booking_status_event_contains_tracking_payload(): void
    {
        [, $business] = $this->makeOwnerWithBusiness('Realtime Care');
        $customer = $this->makeUser();
        $pet = $this->makePet($customer);
        $service = $this->makeService($business);
        $booking = $this->makeBooking($customer, $business, $pet, [$service->id => 1]);
        $booking->update(['status' => 'Dikonfirmasi']);

        $payload = (new BookingStatusUpdated($booking->fresh(), 'Menunggu', 'Tim sudah menerima booking'))
            ->broadcastWith();

        $this->assertSame($booking->id, $payload['booking_id']);
        $this->assertSame('Dikonfirmasi', $payload['status']);
        $this->assertSame('Menunggu', $payload['previous_status']);
        $this->assertSame($booking->invoice_number, $payload['invoice_number']);
        $this->assertSame($customer->name, $payload['customer_name']);
        $this->assertSame($pet->name, $payload['pet_name']);
    }

    public function test_chat_event_contains_message_and_conversation_payload(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness('Chat Realtime');
        $customer = $this->makeUser();
        $conversation = Conversation::create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'last_message_at' => now(),
        ]);
        $message = ConversationMessage::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $customer->id,
            'body' => 'Halo, apakah hewan saya sudah selesai dirawat?',
        ]);

        $payload = (new ChatMessageSent($message))->broadcastWith();

        $this->assertSame($conversation->id, $payload['conversation_id']);
        $this->assertSame($customer->id, $payload['sender_id']);
        $this->assertSame($customer->name, $payload['sender_name']);
        $this->assertSame('Halo, apakah hewan saya sudah selesai dirawat?', $payload['body']);
        $this->assertSame($message->id, $payload['id']);
        $this->assertSame($owner->id, $business->owner_id);
    }

    public function test_care_note_event_contains_customer_visible_update(): void
    {
        [, $business] = $this->makeOwnerWithBusiness('Care Notes');
        $customer = $this->makeUser();
        $pet = $this->makePet($customer);
        $service = $this->makeService($business);
        $booking = $this->makeBooking($customer, $business, $pet, [$service->id => 1]);
        $note = PetCareNote::create([
            'booking_id' => $booking->id,
            'pet_id' => $pet->id,
            'business_id' => $business->id,
            'created_by' => $business->owner_id,
            'care_summary' => 'Perawatan selesai dan kondisi hewan baik.',
            'recommendations' => 'Lanjutkan menyisir bulu secara rutin.',
            'visible_to_customer' => true,
        ]);

        $payload = (new CareNotePublished($note))->broadcastWith();

        $this->assertSame($booking->id, $payload['booking_id']);
        $this->assertSame($booking->invoice_number, $payload['invoice_number']);
        $this->assertSame('Perawatan selesai dan kondisi hewan baik.', $payload['care_summary']);
        $this->assertSame('Lanjutkan menyisir bulu secara rutin.', $payload['recommendations']);
    }

    public function test_owner_can_open_care_board_and_only_sees_own_business_bookings(): void
    {
        [$ownerA, $businessA] = $this->makeOwnerWithBusiness('Care Board A');
        [, $businessB] = $this->makeOwnerWithBusiness('Care Board B');
        $customer = $this->makeUser();
        $pet = $this->makePet($customer);
        $serviceA = $this->makeService($businessA);
        $serviceB = $this->makeService($businessB);
        $bookingA = $this->makeBooking($customer, $businessA, $pet, [$serviceA->id => 1]);
        $bookingB = $this->makeBooking($customer, $businessB, $pet, [$serviceB->id => 1]);

        $this->actingAs($ownerA)
            ->get(route('owner.care-board'))
            ->assertOk()
            ->assertSee($bookingA->invoice_number)
            ->assertDontSee($bookingB->invoice_number);
    }
}
