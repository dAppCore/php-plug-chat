<?php

declare(strict_types=1);

namespace Core\Plug\Chat\Discord;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Deletable;
use Core\Plug\Response;

/**
 * Discord message deletion via webhooks.
 */
class Delete implements Deletable
{
    use BuildsResponse;
    use UsesHttp;

    private string $webhookUrl = '';

    /**
     * Set webhook URL.
     */
    public function withWebhook(string $webhookUrl): self
    {
        $this->webhookUrl = $webhookUrl;

        return $this;
    }

    /**
     * Delete a message posted via this webhook.
     *
     * @param  string  $id  Message ID
     */
    public function delete(string $id): Response
    {
        if (! $this->webhookUrl) {
            return $this->error('Webhook URL is required');
        }

        $response = $this->http()->delete("{$this->webhookUrl}/messages/{$id}");

        // Discord returns 204 No Content on success
        if ($response->status() === 204) {
            return $this->ok([
                'deleted' => true,
                'id' => $id,
            ]);
        }

        return $this->fromHttp($response, fn ($data) => [
            'deleted' => false,
            'error' => $data['message'] ?? 'Failed to delete message',
        ]);
    }
}
