<?php

namespace Addons\Bookings\Mail;

use Addons\Bookings\Models\Booking;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class BookingAssignedMail extends Mailable
{
    public function __construct(public Booking $booking) {}

    public function envelope(): Envelope
    {
        $data = BookingMailData::from($this->booking);

        return new Envelope(subject: "{$data->shop}: booking {$data->number} is yours");
    }

    public function content(): Content
    {
        return new Content(
            view: 'bookings::mail.message',
            with: [
                'details' => BookingMailData::from($this->booking),
                'heading' => 'A booking was assigned to you',
                'intro' => 'You are the person for this booking.',
                'audience' => 'staff',
            ],
        );
    }
}
