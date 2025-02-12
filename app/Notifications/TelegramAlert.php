<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Channels\TelegramChannel;

class TelegramAlert extends Notification
{
    use Queueable;

    protected $message;

    /**
     * Buat instance notifikasi.
     *
     * @param string $message
     */
    public function __construct($message)
    {
        $this->message = $message;
    }

    /**
     * Tentukan channel notifikasi yang digunakan.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        // Gunakan custom channel TelegramChannel
        return [TelegramChannel::class];
    }

    /**
     * Format data notifikasi untuk dikirim ke Telegram.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function toTelegram($notifiable)
    {
        return [
            'text'       => $this->message,
            'parse_mode' => 'HTML'
        ];
    }

    /**
     * Representasi array dari notifikasi (opsional).
     *
     * @param mixed $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            'message' => $this->message,
        ];
    }
}
