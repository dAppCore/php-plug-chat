<?php

declare(strict_types=1);

namespace Core\Plug\Chat\Discord;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Authenticable;
use Core\Plug\Response;

/**
 * Discord webhook authentication.
 *
 * Uses webhooks for posting - no OAuth needed.
 */
class Auth implements Authenticable
{
    use BuildsResponse;
    use UsesHttp;

    private ?string $webhookUrl = null;

    public static function identifier(): string
    {
        return 'discord';
    }

    public static function name(): string
    {
        return 'Discord';
    }

    /**
     * Set webhook URL for validation.
     */
    public function withWebhook(string $webhookUrl): self
    {
        $this->webhookUrl = $webhookUrl;

        return $this;
    }

    /**
     * Discord uses webhook URLs, not OAuth.
     */
    public function getAuthUrl(): string
    {
        return 'https://discord.com/developers/applications';
    }

    /**
     * Validate webhook URL and return credentials.
     *
     * @param  array  $params  ['webhook_url' => string, 'channel_name' => string]
     */
    public function requestAccessToken(array $params): array
    {
        $webhookUrl = $params['webhook_url'] ?? $this->webhookUrl;
        $channelName = $params['channel_name'] ?? 'Discord Channel';

        if (! $webhookUrl) {
            return ['error' => 'Webhook URL is required'];
        }

        // Validate webhook URL format
        if (! str_starts_with($webhookUrl, 'https://discord.com/api/webhooks/')) {
            return ['error' => 'Invalid Discord webhook URL'];
        }

        // Verify webhook by fetching its info
        $response = $this->http()->get($webhookUrl);

        if (! $response->successful()) {
            return ['error' => 'Invalid or expired webhook URL'];
        }

        $data = $response->json();

        return [
            'webhook_url' => $webhookUrl,
            'webhook_id' => $data['id'] ?? null,
            'channel_id' => $data['channel_id'] ?? null,
            'guild_id' => $data['guild_id'] ?? null,
            'channel_name' => $data['name'] ?? $channelName,
            'account_id' => $data['id'] ?? md5($webhookUrl),
        ];
    }

    public function getAccount(): Response
    {
        if (! $this->webhookUrl) {
            return $this->error('Webhook URL is required');
        }

        $response = $this->http()->get($this->webhookUrl);

        return $this->fromHttp($response, fn ($data) => [
            'id' => $data['id'],
            'name' => $data['name'],
            'channel_id' => $data['channel_id'],
            'guild_id' => $data['guild_id'] ?? null,
            'avatar' => $data['avatar'] ? "https://cdn.discordapp.com/avatars/{$data['id']}/{$data['avatar']}.png" : null,
        ]);
    }
}
