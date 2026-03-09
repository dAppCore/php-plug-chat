<?php

declare(strict_types=1);

namespace Core\Plug\Chat\Slack;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Authenticable;
use Core\Plug\Response;

/**
 * Slack webhook authentication.
 *
 * Uses incoming webhooks for posting - no OAuth needed.
 */
class Auth implements Authenticable
{
    use BuildsResponse;
    use UsesHttp;

    private ?string $webhookUrl = null;

    public static function identifier(): string
    {
        return 'slack';
    }

    public static function name(): string
    {
        return 'Slack';
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
     * Slack uses webhook URLs, not OAuth.
     */
    public function getAuthUrl(): string
    {
        return 'https://api.slack.com/apps';
    }

    /**
     * Validate webhook URL and return credentials.
     *
     * @param  array  $params  ['webhook_url' => string, 'channel_name' => string]
     */
    public function requestAccessToken(array $params): array
    {
        $webhookUrl = $params['webhook_url'] ?? $this->webhookUrl;
        $channelName = $params['channel_name'] ?? 'Slack Channel';

        if (! $webhookUrl) {
            return ['error' => 'Webhook URL is required'];
        }

        // Validate webhook URL format
        if (! str_starts_with($webhookUrl, 'https://hooks.slack.com/')) {
            return ['error' => 'Invalid Slack webhook URL'];
        }

        // Test the webhook with a simple request
        $response = $this->http()->post($webhookUrl, [
            'text' => '', // Empty test - Slack will accept but not post
        ]);

        // Slack returns 'invalid_payload' for empty text, which confirms webhook is valid
        if (! $response->successful() && $response->body() !== 'invalid_payload') {
            return ['error' => 'Invalid or expired webhook URL'];
        }

        return [
            'webhook_url' => $webhookUrl,
            'channel_name' => $channelName,
            'account_id' => md5($webhookUrl),
        ];
    }

    public function getAccount(): Response
    {
        return $this->error('Use webhook credentials directly');
    }
}
