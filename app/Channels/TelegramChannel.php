<?php

namespace App\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramChannel
{
    /**
     * Kirim notifikasi ke Telegram.
     *
     * @param  mixed  $notifiable
     * @param  \Illuminate\Notifications\Notification  $notification
     * @return void
     */
    public function send($notifiable, Notification $notification)
    {
        // Pastikan notifikasi memiliki method toTelegram()
        if (!method_exists($notification, 'toTelegram')) {
            return;
        }

        // Dapatkan data pesan dari notifikasi
        $messageData = $notification->toTelegram($notifiable);

        // Jika berupa string, ubah menjadi array dengan default parse_mode HTML
        if (is_string($messageData)) {
            $messageData = [
                'text'       => $messageData,
                'parse_mode' => 'HTML'
            ];
        }

        // Ambil token bot dan chat ID dari konfigurasi
        $botToken = config('services.telegram.bot_token');
        $chatId   = $notifiable->routeNotificationFor('telegram');

        // Jika chat ID belum terdefinisi, log error dan hentikan pengiriman notifikasi
        if (!$chatId) {
            Log::error('TelegramChannel: Chat ID tidak terdefinisi untuk notifiable.');
            return;
        }

        // Endpoint API Telegram untuk mengirim pesan
        $url = "https://api.telegram.org/bot{$botToken}/sendMessage";

        // Lakukan request ke API Telegram
        Http::post($url, [
            'chat_id'    => $chatId,
            'text'       => $messageData['text'] ?? '',
            'parse_mode' => $messageData['parse_mode'] ?? 'HTML'
        ]);
    }
}
