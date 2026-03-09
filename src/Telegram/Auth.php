<?php

declare(strict_types=1);

namespace Core\Plug\Chat\Telegram;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Authenticable;
use Core\Plug\Response;

/**
 * Telegram Bot API authentication.
 *
 * Uses bot tokens for authentication.
 */
class Auth implements Authenticable
{
    use BuildsResponse;
    use UsesHttp;

    private const API_URL = 'https://api.telegram.org';

    private ?string $botToken = null;

    public static function identifier(): string
    {
        return 'telegram';
    }

    public static function name(): string
    {
        return 'Telegram';
    }

    /**
     * Set bot token for validation.
     */
    public function withBotToken(string $botToken): self
    {
        $this->botToken = $botToken;

        return $this;
    }

    /**
     * BotFather link for creating bots.
     */
    public function getAuthUrl(): string
    {
        return 'https://t.me/BotFather';
    }

    /**
     * Validate bot token and return credentials.
     *
     * @param  array  $params  ['bot_token' => string, 'chat_id' => string]
     */
    public function requestAccessToken(array $params): array
    {
        $botToken = $params['bot_token'] ?? $this->botToken;
        $chatId = $params['chat_id'] ?? '';

        if (! $botToken) {
            return ['error' => 'Bot token is required'];
        }

        // Verify the bot token
        $response = $this->http()->get(self::API_URL."/bot{$botToken}/getMe");

        if (! $response->successful()) {
            return ['error' => 'Invalid bot token'];
        }

        $data = $response->json();

        if (! ($data['ok'] ?? false)) {
            return ['error' => $data['description'] ?? 'Invalid bot token'];
        }

        $bot = $data['result'];

        return [
            'access_token' => $botToken,
            'chat_id' => $chatId,
            'bot_id' => (string) $bot['id'],
            'bot_username' => $bot['username'],
            'bot_name' => $bot['first_name'],
            'account_id' => (string) $bot['id'],
        ];
    }

    public function getAccount(): Response
    {
        if (! $this->botToken) {
            return $this->error('Bot token is required');
        }

        $response = $this->http()->get(self::API_URL."/bot{$this->botToken}/getMe");

        return $this->fromHttp($response, function ($data) {
            $bot = $data['result'] ?? $data;

            return [
                'id' => (string) $bot['id'],
                'name' => $bot['first_name'],
                'username' => $bot['username'],
                'can_join_groups' => $bot['can_join_groups'] ?? false,
                'can_read_messages' => $bot['can_read_all_group_messages'] ?? false,
                'supports_inline' => $bot['supports_inline_queries'] ?? false,
            ];
        });
    }

    /**
     * Bot profile URL.
     */
    public static function externalAccountUrl(string $username): string
    {
        return "https://t.me/{$username}";
    }
}
