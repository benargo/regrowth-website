<?php

namespace App\Services\Discord;

use Illuminate\Support\Facades\Log;

class DiscordMessageService extends DiscordService
{
    /**
     * Create a Discord message in the specified channel.
     *
     * @param  array<string, mixed>  $payload
     */
    public function createMessage(string $channelId, array $payload): ?string
    {
        try {
            $response = $this->post("/channels/{$channelId}/messages", $payload);

            return $response->json('id');
        } catch (\Exception $e) {
            Log::error('Failed to create Discord message', [
                'channel_id' => $channelId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Update an existing Discord message.
     *
     * @param  array<string, mixed>  $payload
     */
    public function updateMessage(string $channelId, string $messageId, array $payload): void
    {
        try {
            $this->patch("/channels/{$channelId}/messages/{$messageId}", $payload);
        } catch (\Exception $e) {
            Log::error('Failed to update Discord message', [
                'channel_id' => $channelId,
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Delete a Discord message.
     */
    public function deleteMessage(string $channelId, string $messageId): void
    {
        try {
            $this->delete("/channels/{$channelId}/messages/{$messageId}");
        } catch (\Exception $e) {
            Log::warning('Failed to delete Discord message', [
                'channel_id' => $channelId,
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
