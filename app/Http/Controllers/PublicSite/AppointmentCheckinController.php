<?php

namespace App\Http\Controllers\PublicSite;

use App\Domain\Queue\Actor;
use App\Domain\Queue\CheckinChannel;
use App\Domain\Scheduling\AppointmentCheckin;
use App\Domain\Scheduling\Exceptions\BookingNotAllowedException;
use App\Domain\Scheduling\Models\Appointment;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

/** "I'm here" link from the reminder SMS: check in and continue on the ticket status page. */
class AppointmentCheckinController extends Controller
{
    public function __invoke(string $appointment, AppointmentCheckin $checkin): RedirectResponse
    {
        $model = Appointment::query()->where('manage_token', $appointment)->firstOrFail();

        try {
            $ticket = $checkin->checkIn($model, CheckinChannel::Mobile, Actor::customer());
        } catch (BookingNotAllowedException $e) {
            return redirect()->route('public.appointment', $appointment)->with('appointment_error', $e->getMessage());
        }

        return redirect()->route('public.ticket', $ticket->public_token);
    }
}
