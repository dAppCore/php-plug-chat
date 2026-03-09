<?php

declare(strict_types=1);

namespace Core\Plug\Chat\Slack;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Postable;
use Core\Plug\Response;
use Illuminate\Support\Collection;

/**
 * Slack message posting via incoming webhooks.
 */
class Post implements Postable
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
     * Post a message to Slack.
     *
     * @param  string  $text  Message text (supports mrkdwn)
     * @param  Collection  $media  Images to include
     * @param  array  $params  username, icon_emoji, icon_url
     */
    public function publish(string $text, Collection $media, array $params = []): Response
    {
        if (! $this->webhookUrl) {
            return $this->error('Webhook URL is required');
        }

        $blocks = [];

        // Add text as section block
        if ($text) {
            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => $text,
                ],
            ];
        }

        // Add media as image blocks
        foreach ($media as $item) {
            $imageUrl = $item['url'] ?? $item['path'] ?? null;
            if ($imageUrl) {
                $blocks[] = [
                    'type' => 'image',
                    'image_url' => $imageUrl,
                    'alt_text' => $item['alt_text'] ?? $item['name'] ?? 'Image',
                ];
            }
        }

        $payload = [];

        if (! empty($blocks)) {
            $payload['blocks'] = $blocks;
        } else {
            $payload['text'] = $text ?: 'Message from Host UK';
        }

        // Optional customisation
        if (isset($params['username'])) {
            $payload['username'] = $params['username'];
        }

        if (isset($params['icon_emoji'])) {
            $payload['icon_emoji'] = $params['icon_emoji'];
        }

        if (isset($params['icon_url'])) {
            $payload['icon_url'] = $params['icon_url'];
        }

        $response = $this->http()->post($this->webhookUrl, $payload);

        // Slack webhooks return 'ok' as plain text on success
        if ($response->successful() && $response->body() === 'ok') {
            return $this->ok([
                'id' => uniqid('slack_'),
                'success' => true,
            ]);
        }

        return $this->error($response->body() ?: 'Failed to post message');
    }

    /**
     * Slack doesn't provide post URLs for webhook messages.
     */
    public static function externalPostUrl(string $workspace, string $channel): string
    {
        return "https://{$workspace}.slack.com/archives/{$channel}";
    }

    /**
     * Slack workspace URL.
     */
    public static function externalAccountUrl(string $workspace): string
    {
        return "https://{$workspace}.slack.com";
    }
}
