<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramMessage;
use NotificationChannels\Telegram\TelegramChannel;
use App\Models\ScanResult;

class ScanResultNotification extends Notification
{
    use Queueable;

    protected $scanResult;

    public function __construct(ScanResult $scanResult)
    {
        $this->scanResult = $scanResult;
    }

    public function via($notifiable)
    {
        return [TelegramChannel::class];
    }

    public function toTelegram($notifiable)
    {
        \Log::info('Starting toTelegram method');

        $botToken = env('TELEGRAM_BOT_TOKEN');
        $chatId = env('TELEGRAM_CHAT_ID');
        
        \Log::info('Telegram bot token: '.$botToken);
        \Log::info('Telegram chat id: '.$chatId);

        if (empty($botToken)) {
            \Log::error('Telegram bot token is missing.');
            return;
        }

        if (empty($chatId)) {
            \Log::error('Telegram chat id is missing.');
            return;
        }

        $report = json_decode($this->scanResult->detailed_report, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            \Log::error('Error parsing JSON: '.json_last_error_msg());
            return;
        }

        \Log::info('JSON parsed successfully');

        $message = "🔍 *Hasil Scan Keamanan* 🔍\n";
        $message .= "🕒 Waktu: ".$this->scanResult->created_at->format('Y-m-d H:i:s')."\n";
        $message .= "🌐 URL: ".$this->scanResult->url."\n";

        // Backdoor Analysis
        $message .= "\n*ANALISIS BACKDOOR*\n";
        $message .= "✅ Terdeteksi: ".($report['backdoor_details']['detected'] ? 'Ya' : 'Tidak')."\n";
        $message .= "📊 Level Risiko: ".($report['backdoor_details']['risk_level'] ?? 'Tidak Terdeteksi')."\n";

        // Gambling Analysis
        $message .= "\n*ANALISIS JUDI*\n";
        $message .= "🎰 Terdeteksi: ".($report['gambling_details']['detected'] ? 'Ya' : 'Tidak')."\n";
        $message .= "📊 Level Risiko: ".($report['gambling_details']['risk_level'] ?? 'Tidak Terdeteksi')."\n";

        // Detected Threats
        $message .= "\n*ANCAMAN TERDETEKSI*\n";
        if (!empty($this->scanResult->scanHistory->detected_threats)) {
            foreach ($this->scanResult->scanHistory->detected_threats as $threat) {
                $message .= "⚠️ ".$threat."\n";
            }
        } else {
            $message .= "Tidak ada ancaman terdeteksi\n";
        }

        \Log::info('Message constructed successfully');

        // Create Telegram message with explicit token
        return TelegramMessage::create()
            ->to($chatId)
            ->content($message)
            ->options(['parse_mode' => 'Markdown'])
            ->token($botToken); // Set token explicitly
    }
}
