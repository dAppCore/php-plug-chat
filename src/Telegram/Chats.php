<?php

declare(strict_types=1);

namespace Core\Plug\Chat\Telegram;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\ManagesTokens;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Listable;
use Core\Plug\Response;

/**
 * Telegram chat listing.
 */
class Chats implements Listable
{
    use BuildsResponse;
    use ManagesTokens;
    use UsesHttp;

    private const API_URL = 'https://api.telegram.org';

    /**
     * List chats the bot has interacted with.
     *
     * Note: Telegram bots can only see chats they've received messages from.
     */
    public function listEntities(): Response
    {
        $botToken = $this->accessToken();
        if (! $botToken) {
            return $this->error('Bot token is required');
        }

        // Get updates to find chats the bot is in
        $response = $this->http()->get(self::API_URL."/bot{$botToken}/getUpdates", [
            'allowed_updates' => ['message', 'channel_post', 'my_chat_member'],
            'limit' => 100,
        ]);

        return $this->fromHttp($response, function ($data) {
            $chats = [];
            $seenIds = [];

            foreach ($data['result'] ?? [] as $update) {
                // Try multiple sources for chat info
                $chat = $update['message']['chat']
                    ?? $update['channel_post']['chat']
                    ?? $update['my_chat_member']['chat']
                    ?? null;

                if ($chat && ! in_array($chat['id'], $seenIds)) {
                    $seenIds[] = $chat['id'];
                    $chats[] = [
                        'id' => (string) $chat['id'],
                        'name' => $chat['title'] ?? $chat['first_name'] ?? $chat['username'] ?? 'Unknown',
                        'type' => $chat['type'] ?? 'private',
                        'username' => $chat['username'] ?? null,
                    ];
                }
            }

            return ['chats' => $chats];
        });
    }

    /**
     * Get chat member count.
     */
    public function memberCount(string $chatId): Response
    {
        $botToken = $this->accessToken();
        if (! $botToken) {
            return $this->error('Bot token is required');
        }

        $response = $this->http()->get(self::API_URL."/bot{$botToken}/getChatMemberCount", [
            'chat_id' => $chatId,
        ]);

        return $this->fromHttp($response, fn ($data) => [
            'count' => $data['result'] ?? 0,
        ]);
    }

    /**
     * Get chat administrators.
     */
    public function administrators(string $chatId): Response
    {
        $botToken = $this->accessToken();
        if (! $botToken) {
            return $this->error('Bot token is required');
        }

        $response = $this->http()->get(self::API_URL."/bot{$botToken}/getChatAdministrators", [
            'chat_id' => $chatId,
        ]);

        return $this->fromHttp($response, function ($data) {
            return [
                'administrators' => array_map(fn ($admin) => [
                    'id' => (string) $admin['user']['id'],
                    'username' => $admin['user']['username'] ?? null,
                    'name' => $admin['user']['first_name'] ?? '',
                    'status' => $admin['status'],
                    'is_anonymous' => $admin['is_anonymous'] ?? false,
                ], $data['result'] ?? []),
            ];
        });
    }
}
