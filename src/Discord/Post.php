<?php

declare(strict_types=1);

namespace Core\Plug\Chat\Discord;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Postable;
use Core\Plug\Response;
use Illuminate\Support\Collection;

/**
 * Discord message posting via webhooks.
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
     * Post a message to Discord.
     *
     * @param  string  $text  Message content
     * @param  Collection  $media  Images to embed
     * @param  array  $params  username, avatar_url, tts, embed options
     */
    public function publish(string $text, Collection $media, array $params = []): Response
    {
        if (! $this->webhookUrl) {
            return $this->error('Webhook URL is required');
        }

        $payload = [];

        if ($text) {
            $payload['content'] = $text;
        }

        // Handle embeds for media
        if ($media->isNotEmpty()) {
            $embeds = [];

            foreach ($media as $item) {
                $imageUrl = $item['url'] ?? $item['path'] ?? null;
                if ($imageUrl) {
                    $embed = [
                        'image' => ['url' => $imageUrl],
                    ];

                    // Add title/description if provided
                    if (isset($item['title'])) {
                        $embed['title'] = $item['title'];
                    }
                    if (isset($item['description'])) {
                        $embed['description'] = $item['description'];
                    }

                    $embeds[] = $embed;
                }
            }

            if (! empty($embeds)) {
                $payload['embeds'] = $embeds;
            }
        }

        // Custom embed from params
        if (isset($params['embed'])) {
            $payload['embeds'] = array_merge($payload['embeds'] ?? [], [$params['embed']]);
        }

        // Optional customisation
        if (isset($params['username'])) {
            $payload['username'] = $params['username'];
        }

        if (isset($params['avatar_url'])) {
            $payload['avatar_url'] = $params['avatar_url'];
        }

        if (isset($params['tts'])) {
            $payload['tts'] = (bool) $params['tts'];
        }

        // Use ?wait=true to get message ID in response
        $response = $this->http()->post($this->webhookUrl.'?wait=true', $payload);

        return $this->fromHttp($response, fn ($data) => [
            'id' => $data['id'],
            'channel_id' => $data['channel_id'],
            'timestamp' => $data['timestamp'] ?? null,
        ]);
    }

    /**
     * Discord message URL.
     */
    public static function externalPostUrl(string $guildId, string $channelId, string $messageId): string
    {
        return "https://discord.com/channels/{$guildId}/{$channelId}/{$messageId}";
    }

    /**
     * Discord server URL.
     */
    public static function externalAccountUrl(string $guildId): string
    {
        return "https://discord.com/channels/{$guildId}";
    }
}
