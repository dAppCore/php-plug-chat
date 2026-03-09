<?php

declare(strict_types=1);

namespace Core\Plug\Chat\Telegram;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\ManagesTokens;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Postable;
use Core\Plug\Response;
use Illuminate\Support\Collection;

/**
 * Telegram message posting via Bot API.
 */
class Post implements Postable
{
    use BuildsResponse;
    use ManagesTokens;
    use UsesHttp;

    private const API_URL = 'https://api.telegram.org';

    private ?string $chatId = null;

    /**
     * Set default chat ID.
     */
    public function toChatId(string $chatId): self
    {
        $this->chatId = $chatId;

        return $this;
    }

    /**
     * Send a message to Telegram.
     *
     * @param  string  $text  Message text
     * @param  Collection  $media  Media items to send
     * @param  array  $params  chat_id, parse_mode, disable_web_page_preview, disable_notification
     */
    public function publish(string $text, Collection $media, array $params = []): Response
    {
        $botToken = $this->accessToken();
        if (! $botToken) {
            return $this->error('Bot token is required');
        }

        $chatId = $params['chat_id'] ?? $this->chatId;
        if (! $chatId) {
            return $this->error('Chat ID is required');
        }

        // Handle media
        if ($media->isNotEmpty()) {
            return $this->sendMedia($botToken, $chatId, $text, $media, $params);
        }

        // Send text message
        $messageData = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => $params['parse_mode'] ?? 'HTML',
        ];

        if (isset($params['disable_web_page_preview'])) {
            $messageData['disable_web_page_preview'] = $params['disable_web_page_preview'];
        }

        if (isset($params['disable_notification'])) {
            $messageData['disable_notification'] = $params['disable_notification'];
        }

        if (isset($params['reply_to_message_id'])) {
            $messageData['reply_to_message_id'] = $params['reply_to_message_id'];
        }

        $response = $this->http()->post(self::API_URL."/bot{$botToken}/sendMessage", $messageData);

        return $this->fromHttp($response, function ($data) {
            $result = $data['result'] ?? $data;

            return [
                'id' => (string) $result['message_id'],
                'chat_id' => (string) ($result['chat']['id'] ?? ''),
            ];
        });
    }

    /**
     * Send media (photo, video, or media group).
     */
    private function sendMedia(string $botToken, string $chatId, string $text, Collection $media, array $params): Response
    {
        $mediaItems = [];

        foreach ($media as $index => $item) {
            $type = $this->getMediaType($item);
            $mediaItem = [
                'type' => $type,
                'media' => $item['url'] ?? $item['path'] ?? '',
            ];

            // Only add caption to first item
            if ($index === 0 && $text) {
                $mediaItem['caption'] = $text;
                $mediaItem['parse_mode'] = $params['parse_mode'] ?? 'HTML';
            }

            $mediaItems[] = $mediaItem;
        }

        // Single media item - use specific method
        if (count($mediaItems) === 1) {
            return $this->sendSingleMedia($botToken, $chatId, $mediaItems[0], $params);
        }

        // Multiple media items - use sendMediaGroup
        $response = $this->http()->post(self::API_URL."/bot{$botToken}/sendMediaGroup", [
            'chat_id' => $chatId,
            'media' => json_encode($mediaItems),
            'disable_notification' => $params['disable_notification'] ?? false,
        ]);

        return $this->fromHttp($response, function ($data) {
            $results = $data['result'] ?? [];
            $first = $results[0] ?? $data;

            return [
                'id' => (string) ($first['message_id'] ?? ''),
                'chat_id' => (string) ($first['chat']['id'] ?? ''),
                'message_count' => count($results),
            ];
        });
    }

    /**
     * Send single media item.
     */
    private function sendSingleMedia(string $botToken, string $chatId, array $item, array $params): Response
    {
        $method = match ($item['type']) {
            'photo' => 'sendPhoto',
            'video' => 'sendVideo',
            'audio' => 'sendAudio',
            'document' => 'sendDocument',
            'animation' => 'sendAnimation',
            default => 'sendPhoto',
        };

        $payload = [
            'chat_id' => $chatId,
            $item['type'] => $item['media'],
        ];

        if (isset($item['caption'])) {
            $payload['caption'] = $item['caption'];
            $payload['parse_mode'] = $item['parse_mode'] ?? 'HTML';
        }

        if (isset($params['disable_notification'])) {
            $payload['disable_notification'] = $params['disable_notification'];
        }

        $response = $this->http()->post(self::API_URL."/bot{$botToken}/{$method}", $payload);

        return $this->fromHttp($response, function ($data) {
            $result = $data['result'] ?? $data;

            return [
                'id' => (string) $result['message_id'],
                'chat_id' => (string) ($result['chat']['id'] ?? ''),
            ];
        });
    }

    /**
     * Determine media type from item.
     */
    private function getMediaType(array $item): string
    {
        $mimeType = $item['mime_type'] ?? '';

        if (str_starts_with($mimeType, 'image/gif')) {
            return 'animation';
        }

        if (str_starts_with($mimeType, 'image/')) {
            return 'photo';
        }

        if (str_starts_with($mimeType, 'video/')) {
            return 'video';
        }

        if (str_starts_with($mimeType, 'audio/')) {
            return 'audio';
        }

        return 'document';
    }

    /**
     * Telegram message URL.
     */
    public static function externalPostUrl(string $chatUsername, string $messageId): string
    {
        return "https://t.me/{$chatUsername}/{$messageId}";
    }
}
