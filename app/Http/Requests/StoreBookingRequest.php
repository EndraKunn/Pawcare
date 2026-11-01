<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isCustomer() ?? false;
    }

    public function rules(): array
    {
        $businessId = $this->input('business_id');

        return [
            'business_id' => ['required', 'integer', Rule::exists('businesses', 'id')->where('status', true)],
            'pet_id' => [
                'required',
                'integer',
                Rule::exists('pets', 'id')
                    ->where('customer_id', $this->user()->id)
                    ->whereNull('archived_at'),
            ],
            'booking_date' => ['required', 'date', 'after_or_equal:today'],
            'booking_time' => ['required', 'date_format:H:i'],
            'services' => ['required', 'array', 'min:1'],
            'services.*' => [
                'integer', 'distinct',
                Rule::exists('services', 'id')->where('business_id', $businessId)->where('status', true),
            ],
            'qty' => ['nullable', 'array'],
            'qty.*' => ['nullable', 'integer', 'min:1', 'max:365'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'business_id' => 'bisnis',
            'pet_id' => 'hewan',
            'booking_date' => 'tanggal layanan',
            'booking_time' => 'jam layanan',
            'services' => 'layanan',
            'services.*' => 'layanan',
            'qty.*' => 'jumlah',
            'notes' => 'catatan',
        ];
    }

    public function messages(): array
    {
        return [
            'booking_date.after_or_equal' => 'Tanggal layanan tidak boleh di masa lalu.',
            'services.required' => 'Pilih minimal satu layanan.',
            'services.min' => 'Pilih minimal satu layanan.',
            'services.*.exists' => 'Layanan tidak tersedia atau bukan milik bisnis yang dipilih.',
            'pet_id.exists' => 'Hewan tidak ditemukan pada akun Anda.',
            'business_id.exists' => 'Bisnis tidak tersedia.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->has('booking_date') || $validator->errors()->has('booking_time')) {
                    return;
                }
                $date = $this->input('booking_date');
                $time = $this->input('booking_time');
                if ($date && $time && $date === now()->toDateString() && $time < now()->format('H:i')) {
                    $validator->errors()->add('booking_time', 'Jam layanan tidak boleh sudah lewat.');
                }
            },
        ];
    }

    /** Data bersih untuk BookingService (total harga TIDAK diambil dari input). */
    public function bookingData(): array
    {
        $quantities = [];
        foreach ($this->validated('services') as $serviceId) {
            $quantities[(int) $serviceId] = max(1, (int) ($this->input("qty.$serviceId") ?? 1));
        }

        return [
            'business_id' => (int) $this->validated('business_id'),
            'pet_id' => (int) $this->validated('pet_id'),
            'booking_date' => $this->validated('booking_date'),
            'booking_time' => $this->validated('booking_time'),
            'notes' => $this->validated('notes'),
            'quantities' => $quantities,
        ];
    }
}
