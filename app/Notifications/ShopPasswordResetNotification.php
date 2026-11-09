<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ShopPasswordResetNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $token,
        public string $shopCode,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $username = (string) $notifiable->username;
        $url = route('password.reset', [
            'token' => $this->token,
            'shop_code' => $this->shopCode,
            'username' => $username,
        ]);

        return (new MailMessage)
            ->subject('Reset your theDukanPOS password')
            ->view([
                'html' => 'mail.password-reset',
                'text' => 'mail.password-reset-text',
            ], [
                'url' => $url,
                'username' => $username,
                'shopCode' => $this->shopCode,
                'logoUrl' => url('brand/logo_horizontal.png'),
            ]);
    }
}
