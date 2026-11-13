<?php

namespace Addons\Bookings\Services;

use Addons\Bookings\Mail\BookingAssignedMail;
use Addons\Bookings\Mail\BookingCompletedMail;
use Addons\Bookings\Mail\BookingConfirmedMail;
use Addons\Bookings\Mail\BookingReceivedMail;
use Addons\Bookings\Models\Booking;
use App\Models\User;
use App\Support\TenantDefaultRoles;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

class BookingMailService
{
    public function notifyCreated(Booking $booking): void
    {
        $booking->loadMissing(['customer', 'assignee', 'branch']);

        $this->send(new BookingReceivedMail($booking), $this->adminEmails());

        if ($booking->status === 'confirmed') {
            $this->send(new BookingConfirmedMail($booking), $this->customerEmails($booking));
        }

        if ($booking->status !== 'cancelled' && $booking->assigned_user_id) {
            $this->send(new BookingAssignedMail($booking), $this->assigneeEmails($booking));
        }
    }

    public function notifyUpdated(Booking $booking, string $previousStatus, ?int $previousAssignee): void
    {
        $booking->loadMissing(['customer', 'assignee', 'branch']);

        if ($previousStatus !== 'confirmed' && $booking->status === 'confirmed') {
            $this->send(new BookingConfirmedMail($booking), $this->customerEmails($booking));
        }

        $assigneeId = $booking->assigned_user_id ? (int) $booking->assigned_user_id : null;
        if ($booking->status !== 'cancelled' && $assigneeId && $assigneeId !== $previousAssignee) {
            $this->send(new BookingAssignedMail($booking), $this->assigneeEmails($booking));
        }

        if ($previousStatus !== 'done' && $booking->status === 'done') {
            $this->send(new BookingCompletedMail($booking), $this->customerEmails($booking));
        }
    }

    public function notifyCompleted(Booking $booking): void
    {
        $booking->loadMissing(['customer', 'branch']);
        $this->send(new BookingCompletedMail($booking), $this->customerEmails($booking));
    }

    /**
     * @return list<string>
     */
    private function adminEmails(): array
    {
        try {
            return User::query()
                ->role(TenantDefaultRoles::ADMINISTRATOR)
                ->where('is_active', true)
                ->pluck('email')
                ->map(fn ($email) => $this->valid((string) $email))
                ->filter()
                ->unique()
                ->values()
                ->all();
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * @return list<string>
     */
    private function customerEmails(Booking $booking): array
    {
        $email = $this->valid((string) ($booking->customer?->email ?? ''));

        return $email ? [$email] : [];
    }

    /**
     * @return list<string>
     */
    private function assigneeEmails(Booking $booking): array
    {
        $user = $booking->assignee;
        if (! $user || ! $user->is_active) {
            return [];
        }

        $email = $this->valid((string) $user->email);

        return $email ? [$email] : [];
    }

    /**
     * @param  list<string>  $emails
     */
    private function send(Mailable $mail, array $emails): void
    {
        foreach ($emails as $email) {
            try {
                Mail::to($email)->send(clone $mail);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    private function valid(string $email): ?string
    {
        $email = trim($email);

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }
}
