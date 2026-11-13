<?php

namespace Addons\Bookings\Mail;

use Addons\Bookings\Models\Booking;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class BookingConfirmedMail extends Mailable
{
    public function __construct(public Booking $booking) {}

    public function envelope(): Envelope
    {
        $data = BookingMailData::from($this->booking);

        return new Envelope(subject: "{$data->shop}: booking {$data->number} confirmed");
    }

    public function content(): Content
    {
        return new Content(
            view: 'bookings::mail.message',
            with: [
                'details' => BookingMailData::from($this->booking),
                'heading' => 'Your booking is confirmed',
                'intro' => 'The shop confirmed your booking. Here is the time to keep.',
                'audience' => 'customer',
            ],
        );
    }
}
