<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    protected $botToken;
    protected $chatId;

    public function __construct()
    {
        $this->botToken = env('TELEGRAM_BOT_TOKEN');
        $this->chatId = env('TELEGRAM_CHAT_ID');
    }

    public function sendAlert($website, $message)
{
    try {
        // Validasi token dan chat ID
        if (empty($this->botToken) || empty($this->chatId)) {
            throw new \Exception("Telegram configuration is missing");
        }

        $url = "https://api.telegram.org/bot{$this->botToken}/sendMessage";
        
        // Log URL untuk debugging
        Log::debug("Telegram API URL: " . $url);

        $response = Http::timeout(10)->post($url, [
            'chat_id' => $this->chatId,
            'text' => $message,
            'parse_mode' => 'HTML' // Ganti ke HTML jika ada issue dengan Markdown
        ]);

        $responseData = $response->json();
        
        if (!$response->ok() || !isset($responseData['ok'])) {
            Log::error('Telegram API Error', [
                'response' => $responseData,
                'message' => $message,
                'token' => $this->botToken, // Log token (pastikan tidak expose di production)
                'chat_id' => $this->chatId
            ]);
            return false;
        }

        return true;
        
    } catch (\Exception $e) {
        Log::error('Telegram Service Error: ' . $e->getMessage());
        return false;
    }
}
}