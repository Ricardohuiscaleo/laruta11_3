<?php

namespace App\Services\Notification;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    private string $token;
    private string $chatId;

    public function __construct()
    {
        $this->token = config('services.telegram.token', '');
        $this->chatId = config('services.telegram.chat_id', '');
    }

    /**
     * Send a message via Telegram.
     * Supports multiple bots: 'default' (SuperKiro) and 'laruta11'.
     */
    public function send(string $message, string $bot = 'default'): bool
    {
        $token = $this->token;
        $chatId = $this->chatId;

        if ($bot === 'laruta11') {
            $token = config('services.telegram.laruta11_token', '');
            $chatId = config('services.telegram.laruta11_chat_id', '');
        }

        if (empty($token) || empty($chatId)) {
            Log::warning("Telegram not configured for bot '{$bot}': missing token or chat_id");
            return false;
        }

        try {
            $response = Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'Markdown',
            ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning("Telegram send failed ({$bot}): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Shortcut to send via @laruta11_bot.
     */
    public function sendToLaruta11(string $message): bool
    {
        return $this->send($message, 'laruta11');
    }
}
