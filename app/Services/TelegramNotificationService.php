<?php

namespace App\Services;

use TelegramBot\Api\BotApi;
use Illuminate\Support\Facades\Log;

class TelegramNotificationService
{
    protected $botApi;
    protected $chatId;

    public function __construct()
    {
        try {
            $this->botApi = new BotApi(config('services.telegram.bot_token'));
            $this->chatId = config('services.telegram.chat_id');
        } catch (\Exception $e) {
            Log::error('Telegram Bot Initialization Error: ' . $e->getMessage());
        }
    }

    public function sendSecurityAlert($message)
    {
        try {
            $this->botApi->sendMessage($this->chatId, $message);
        } catch (\Exception $e) {
            Log::error('Telegram Notification Error: ' . $e->getMessage());
        }
    }

    public function sendWebsiteAlert($website, $status)
    {
        $message = "🚨 Website Monitoring Alert 🚨\n" .
                   "Name: {$website->name}\n" .
                   "URL: {$website->url}\n" .
                   "Status: {$status}\n" .
                   "Time: " . now()->format('Y-m-d H:i:s');
        
        $this->sendSecurityAlert($message);
    }

    public function sendLoginAlert($loginAttempt)
    {
        $message = "🔐 Login Attempt Alert 🔐\n" .
                   "IP: {$loginAttempt->ip_address}\n" .
                   "Location: {$loginAttempt->location}\n" .
                   "Device: {$loginAttempt->device_type}\n" .
                   "Browser: {$loginAttempt->browser}\n" .
                   "Status: {$loginAttempt->status}\n" .
                   "Time: " . now()->format('Y-m-d H:i:s');
        
        $this->sendSecurityAlert($message);
    }
}