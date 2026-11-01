<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ChatController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $conversations = Conversation::forCustomer($user)
            ->with([
                'business:id,business_name,cover_photo',
                'booking:id,invoice_number,status,booking_date,booking_time',
                'latestMessage',
            ])
            ->withCount([
                'messages as unread_count' => fn ($m) => $m
                    ->where('sender_id', '!=', $user->id)
                    ->whereNull('read_at'),
            ])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate(15);

        return view('customer.chat.index', compact('conversations'));
    }

    public function start(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'business_id' => ['required', 'integer'],
            'booking_id' => ['nullable', 'integer'],
            'body' => ['nullable', 'string', 'max:2000'],
        ], [
            'body.max' => 'Pesan maksimal 2.000 karakter.',
        ]);

        $business = Business::findOrFail((int) $data['business_id']);

        $booking = null;
        if (! empty($data['booking_id'])) {
            // Pastikan booking benar-benar milik customer ini dan pada bisnis tersebut.
            $booking = Booking::forCustomer($user)
                ->where('business_id', $business->id)
                ->findOrFail((int) $data['booking_id']);
        } elseif (! $business->status) {
            // Bisnis suspend hanya dapat dihubungi jika pelanggan sudah memiliki booking di bisnis tersebut.
            $hasExistingBooking = Booking::forCustomer($user)->where('business_id', $business->id)->exists();
            abort_unless($hasExistingBooking, 404);
        }

        $conversation = DB::transaction(function () use ($user, $business, $booking, $data) {
            $conv = Conversation::firstOrCreate(
                [
                    'business_id' => $business->id,
                    'customer_id' => $user->id,
                    'booking_id' => $booking?->id,
                ],
                [
                    'last_message_at' => now(),
                ]
            );

            if (filled($data['body'] ?? null)) {
                ConversationMessage::create([
                    'conversation_id' => $conv->id,
                    'sender_id' => $user->id,
                    'body' => trim((string) $data['body']),
                ]);
                $conv->update(['last_message_at' => now()]);
            }

            return $conv;
        });

        return redirect()->route('customer.chat.show', $conversation->id);
    }

    public function show(Request $request, int $conversation): View
    {
        $user = $request->user();

        $model = Conversation::forCustomer($user)
            ->with([
                'business.owner:id,name',
                'booking.pet:id,name,species',
                'booking.items.service:id,name',
                'messages.sender:id,name,role',
            ])
            ->findOrFail($conversation);

        // Tandai pesan dari pihak bisnis sebagai sudah dibaca.
        $model->messages()
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return view('customer.chat.show', ['conversation' => $model]);
    }

    public function send(Request $request, int $conversation): RedirectResponse
    {
        $user = $request->user();
        $model = Conversation::forCustomer($user)->findOrFail($conversation);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ], [
            'body.required' => 'Pesan tidak boleh kosong.',
            'body.max' => 'Pesan maksimal 2.000 karakter.',
        ]);

        DB::transaction(function () use ($user, $model, $data) {
            ConversationMessage::create([
                'conversation_id' => $model->id,
                'sender_id' => $user->id,
                'body' => trim($data['body']),
            ]);

            $model->update(['last_message_at' => now()]);
        });

        return redirect()->route('customer.chat.show', $model->id);
    }
}
