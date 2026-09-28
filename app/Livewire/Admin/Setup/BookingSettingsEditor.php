<?php

namespace App\Livewire\Admin\Setup;

/** Per-location online booking rules (appointment-scheduling "Booking window rules", capacity). */
class BookingSettingsEditor extends LocationEditor
{
    public bool $booking_enabled = true;

    public bool $booking_choose_employee = false;

    public int $booking_lead_minutes = 120;

    public int $booking_horizon_days = 60;

    public int $booking_buffer_minutes = 5;

    public int $booking_cutoff_minutes = 60;

    public ?int $appointment_capacity_per_hour = null;

    public int $appointment_grace_minutes = 15;

    public bool $saved = false;

    public function mount(int $locationId): void
    {
        parent::mount($locationId);
        $this->fill($this->location()->only([
            'booking_enabled', 'booking_choose_employee', 'booking_lead_minutes', 'booking_horizon_days',
            'booking_buffer_minutes', 'booking_cutoff_minutes', 'appointment_capacity_per_hour', 'appointment_grace_minutes',
        ]));
    }

    public function save(): void
    {
        $data = $this->validate([
            'booking_enabled' => ['boolean'],
            'booking_choose_employee' => ['boolean'],
            'booking_lead_minutes' => ['required', 'integer', 'between:0,20160'],
            'booking_horizon_days' => ['required', 'integer', 'between:1,365'],
            'booking_buffer_minutes' => ['required', 'integer', 'between:0,120'],
            'booking_cutoff_minutes' => ['required', 'integer', 'between:0,10080'],
            'appointment_capacity_per_hour' => ['nullable', 'integer', 'between:1,500'],
            'appointment_grace_minutes' => ['required', 'integer', 'between:0,240'],
        ]);

        $this->location()->update($data);
        $this->saved = true;
    }

    public function render()
    {
        return view('livewire.admin.setup.booking-settings-editor', [
            'bookingUrl' => route('public.book', $this->location()->checkin_public_id),
        ]);
    }
}
