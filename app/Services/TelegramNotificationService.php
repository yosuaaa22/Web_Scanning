<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use TelegramBot\Api\BotApi;
use TelegramBot\Api\Exception;
use TelegramBot\Api\InvalidArgumentException;

class TelegramNotificationService
{
    protected BotApi $bot;
    protected string $chatId;
    protected bool $isConfigured = false;

    public function __construct()
    {
        $this->validateAndInitialize();
    }

    protected function validateAndInitialize(): void
    {
        try {
            $botToken = Config::get('services.telegram.bot_token');
            $this->chatId = Config::get('services.telegram.chat_id');

            if (empty($botToken) || empty($this->chatId)) {
                throw new \RuntimeException('Telegram configuration is incomplete');
            }

            $this->bot = new BotApi($botToken);
            $this->isConfigured = true;

        } catch (\Exception $e) {
            Log::error('Telegram service initialization failed: ' . $e->getMessage());
            $this->isConfigured = false;
        }
    }

    public function sendSecurityAlert(string $message): bool
    {
        if (!$this->isConfigured) {
            Log::warning('Telegram service not configured, message not sent');
            return false;
        }

        try {
            $this->bot->sendMessage(
                $this->chatId,
                $this->escapeMarkdown($message),
                'MarkdownV2'
            );
            return true;
        } catch (Exception | InvalidArgumentException $e) {
            Log::error('Telegram send failed: ' . $e->getMessage(), [
                'chatId' => $this->chatId,
                'message' => $message
            ]);
            return false;
        }
    }

    public function sendWebsiteAlert(object $website, string $status): bool
    {
        $message = sprintf(
            "🚨 *Website Monitoring Alert* 🚨\n".
            "• *Name*: %s\n".
            "• *URL*: %s\n".
            "• *Status*: %s\n".
            "• *Time*: %s",
            $this->escapeMarkdown($website->name),
            $this->escapeMarkdown($website->url),
            $this->escapeMarkdown($status),
            now()->format('Y-m-d H:i:s')
        );

        return $this->sendSecurityAlert($message);
    }

    public function sendLoginAlert(object $loginAttempt): bool
    {
        $message = sprintf(
            "🔐 *Login Attempt Alert* 🔐\n".
            "• *IP*: %s\n".
            "• *Location*: %s\n".
            "• *Device*: %s\n".
            "• *Browser*: %s\n".
            "• *Status*: %s\n".
            "• *Time*: %s",
            $this->escapeMarkdown($loginAttempt->ip_address),
            $this->escapeMarkdown($loginAttempt->location),
            $this->escapeMarkdown($loginAttempt->device_type),
            $this->escapeMarkdown($loginAttempt->browser),
            $this->escapeMarkdown($loginAttempt->status),
            now()->format('Y-m-d H:i:s')
        );

        return $this->sendSecurityAlert($message);
    }

    protected function escapeMarkdown(string $text): string
    {
        // Escape karakter khusus MarkdownV2
        return str_replace(
            ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'],
            ['\_', '\*', '\[', '\]', '\(', '\)', '\~', '\`', '\>', '\#', '\+', '\-', '\=', '\|', '\{', '\}', '\.', '\!'],
            $text
        );
    }
}