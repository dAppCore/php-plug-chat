<?php

declare(strict_types=1);

namespace Core\Plug\Chat\Telegram;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\ManagesTokens;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Deletable;
use Core\Plug\Response;

/**
 * Telegram message deletion via Bot API.
 */
class Delete implements Deletable
{
    use BuildsResponse;
    use ManagesTokens;
    use UsesHttp;

    private const API_URL = 'https://api.telegram.org';

    private ?string $chatId = null;

    /**
     * Set chat ID for deletion.
     */
    public function inChat(string $chatId): self
    {
        $this->chatId = $chatId;

        return $this;
    }

    /**
     * Delete a message.
     *
     * @param  string  $id  Message ID
     */
    public function delete(string $id): Response
    {
        $botToken = $this->accessToken();
        if (! $botToken) {
            return $this->error('Bot token is required');
        }

        if (! $this->chatId) {
            return $this->error('Chat ID is required');
        }

        $response = $this->http()->post(self::API_URL."/bot{$botToken}/deleteMessage", [
            'chat_id' => $this->chatId,
            'message_id' => $id,
        ]);

        return $this->fromHttp($response, function ($data) use ($id) {
            return [
                'deleted' => $data['result'] ?? $data['ok'] ?? true,
                'id' => $id,
            ];
        });
    }
}
