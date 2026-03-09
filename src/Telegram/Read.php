<?php

declare(strict_types=1);

namespace Core\Plug\Chat\Telegram;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\ManagesTokens;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Readable;
use Core\Plug\Response;

/**
 * Telegram bot and chat information.
 */
class Read implements Readable
{
    use BuildsResponse;
    use ManagesTokens;
    use UsesHttp;

    private const API_URL = 'https://api.telegram.org';

    /**
     * Get bot information.
     *
     * @param  string  $id  Not used - returns current bot info
     */
    public function get(string $id): Response
    {
        return $this->me();
    }

    /**
     * Get current bot information.
     */
    public function me(): Response
    {
        $botToken = $this->accessToken();
        if (! $botToken) {
            return $this->error('Bot token is required');
        }

        $response = $this->http()->get(self::API_URL."/bot{$botToken}/getMe");

        return $this->fromHttp($response, function ($data) {
            $bot = $data['result'] ?? $data;

            return [
                'id' => (string) $bot['id'],
                'username' => $bot['username'],
                'name' => $bot['first_name'],
                'can_join_groups' => $bot['can_join_groups'] ?? false,
                'can_read_messages' => $bot['can_read_all_group_messages'] ?? false,
                'supports_inline' => $bot['supports_inline_queries'] ?? false,
            ];
        });
    }

    /**
     * Get chat information.
     */
    public function chat(string $chatId): Response
    {
        $botToken = $this->accessToken();
        if (! $botToken) {
            return $this->error('Bot token is required');
        }

        $response = $this->http()->get(self::API_URL."/bot{$botToken}/getChat", [
            'chat_id' => $chatId,
        ]);

        return $this->fromHttp($response, function ($data) {
            $chat = $data['result'] ?? $data;

            return [
                'id' => (string) $chat['id'],
                'type' => $chat['type'],
                'title' => $chat['title'] ?? null,
                'username' => $chat['username'] ?? null,
                'first_name' => $chat['first_name'] ?? null,
                'description' => $chat['description'] ?? null,
                'photo' => $chat['photo']['big_file_id'] ?? null,
            ];
        });
    }

    /**
     * List recent chats (from updates).
     */
    public function list(array $params = []): Response
    {
        $botToken = $this->accessToken();
        if (! $botToken) {
            return $this->error('Bot token is required');
        }

        $response = $this->http()->get(self::API_URL."/bot{$botToken}/getUpdates", [
            'allowed_updates' => ['message', 'channel_post'],
            'limit' => $params['limit'] ?? 100,
        ]);

        return $this->fromHttp($response, function ($data) {
            $chats = [];
            $seenIds = [];

            foreach ($data['result'] ?? [] as $update) {
                $chat = $update['message']['chat'] ?? $update['channel_post']['chat'] ?? null;

                if ($chat && ! in_array($chat['id'], $seenIds)) {
                    $seenIds[] = $chat['id'];
                    $chats[] = [
                        'id' => (string) $chat['id'],
                        'name' => $chat['title'] ?? $chat['first_name'] ?? $chat['username'] ?? '',
                        'type' => $chat['type'] ?? 'private',
                        'username' => $chat['username'] ?? null,
                    ];
                }
            }

            return ['chats' => $chats];
        });
    }
}
