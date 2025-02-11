<?php

namespace App\Services;

use App\Models\Website;
use Illuminate\Support\Facades\Http;

class NotificationService
{
    protected $telegramBotToken;
    protected $telegramChatId;

    public function __construct()
    {
        $this->telegramBotToken = env('TELEGRAM_BOT_TOKEN');
        $this->telegramChatId = env('TELEGRAM_CHAT_ID');
    }

    public function sendTelegramNotification(Website $website, $message)
    {
        $url = "https://api.telegram.org/bot{$this->telegramBotToken}/sendMessage";
        
        Http::post($url, [
            'chat_id' => $this->telegramChatId,
            'text' => "Website: {$website->url}\n{$message}",
        ]);
    }

    public function sendStatusChangeNotification(Website $website, $previousStatus, $newStatus)
    {
        $message = "Status change detected for {$website->url}\nFrom: {$previousStatus}\nTo: {$newStatus}";
        $this->sendTelegramNotification($website, $message);
    }

    public function sendVulnerabilityAlert(Website $website, $vulnerabilities)
    {
        $message = "Vulnerabilities detected for {$website->url}:\n" . implode("\n", $vulnerabilities);
        $this->sendTelegramNotification($website, $message);
    }

    public function sendPerformanceAlert(Website $website, $alertType, $value)
    {
        $message = "Performance alert for {$website->url}\nType: {$alertType}\nValue: {$value}";
        $this->sendTelegramNotification($website, $message);
    }

    public function sendExpirationAlert(Website $website, $type, $expirationDate)
    {
        $message = "Expiration alert for {$website->url}\nType: {$type}\nExpiration Date: {$expirationDate}";
        $this->sendTelegramNotification($website, $message);
    }

    public function sendErrorNotification(Website $website, $errorMessage)
    {
        $message = "Error occurred for {$website->url}\nError: {$errorMessage}";
        $this->sendTelegramNotification($website, $message);
    }
}