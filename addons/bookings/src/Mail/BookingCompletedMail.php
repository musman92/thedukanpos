<?php

namespace Addons\Bookings\Mail;

use Addons\Bookings\Models\Booking;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class BookingCompletedMail extends Mailable
{
    public function __construct(public Booking $booking) {}

    public function envelope(): Envelope
    {
        $data = BookingMailData::from($this->booking);

        return new Envelope(subject: "{$data->shop}: thank you");
    }

    public function content(): Content
    {
        return new Content(
            view: 'bookings::mail.message',
            with: [
                'details' => BookingMailData::from($this->booking),
                'heading' => 'Thank you',
                'intro' => 'Your booking is complete. Thank you for choosing us.',
                'audience' => 'customer',
            ],
        );
    }
}
