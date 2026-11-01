<?php

namespace App\Http\Controllers\Owner;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ChatController extends OwnerController
{
    public function index(Request $request): View
    {
        $owner = $request->user();
        $business = $this->business($request);

        $conversations = Conversation::forOwner($owner)
            ->with([
                'customer:id,name',
                'booking:id,invoice_number,status,booking_date,booking_time',
                'latestMessage',
            ])
            ->withCount([
                'messages as unread_count' => fn ($m) => $m
                    ->where('sender_id', '!=', $owner->id)
                    ->whereNull('read_at'),
            ])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate(15);

        return view('owner.chat.index', compact('business', 'conversations'));
    }

    public function startFromBooking(Request $request, int $booking): RedirectResponse
    {
        $bk = $this->bookings($request)->findOrFail($booking);

        $conversation = Conversation::firstOrCreate(
            [
                'business_id' => $bk->business_id,
                'customer_id' => $bk->customer_id,
                'booking_id' => $bk->id,
            ],
            [
                'last_message_at' => now(),
            ]
        );

        return redirect()->route('owner.chat.show', $conversation->id);
    }

    public function show(Request $request, int $conversation): View
    {
        $owner = $request->user();
        $business = $this->business($request);

        $model = Conversation::forOwner($owner)
            ->with([
                'customer:id,name',
                'booking.pet:id,name,species',
                'booking.items.service:id,name',
                'messages.sender:id,name,role',
            ])
            ->findOrFail($conversation);

        // Tandai pesan dari pelanggan sebagai sudah dibaca.
        $model->messages()
            ->where('sender_id', '!=', $owner->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return view('owner.chat.show', [
            'business' => $business,
            'conversation' => $model,
        ]);
    }

    public function send(Request $request, int $conversation): RedirectResponse
    {
        $owner = $request->user();
        $model = Conversation::forOwner($owner)->findOrFail($conversation);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ], [
            'body.required' => 'Pesan tidak boleh kosong.',
            'body.max' => 'Pesan maksimal 2.000 karakter.',
        ]);

        DB::transaction(function () use ($owner, $model, $data) {
            ConversationMessage::create([
                'conversation_id' => $model->id,
                'sender_id' => $owner->id,
                'body' => trim($data['body']),
            ]);

            $model->update(['last_message_at' => now()]);
        });

        return redirect()->route('owner.chat.show', $model->id);
    }
}
