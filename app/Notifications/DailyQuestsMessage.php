<?php

namespace App\Notifications;

use App\Contracts\Notifications\DiscordMessage;
use App\Enums\DailyQuestTypeLabel;
use App\Models\DailyQuest;
use App\Models\DiscordNotification;
use App\Models\DiscordRole;
use App\Models\User;
use App\Services\Discord\Notifications\Driver as DiscordDriver;
use App\Services\Discord\Payloads\MessagePayload;
use App\Services\Discord\Resources\Embed;
use App\Services\Discord\Resources\EmbedField;
use App\Services\Discord\Resources\EmbedFooter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class DailyQuestsMessage extends Notification implements DiscordMessage, ShouldQueue
{
    use Queueable;

    /**
     * The daily quests to include in the notification, keyed by their type label (e.g. 'normal', 'alliance', 'horde')
     *
     * @var iterable<DailyQuestTypeLabel, DailyQuest|null>
     */
    public iterable $dailyQuests;

    /**
     * The Discord notification instance this new notification will update
     */
    private ?DiscordNotification $updates = null;

    /**
     * The user who set the daily quests, if available (used for attribution in the message footer)
     */
    private readonly ?Authenticatable $sender;

    /**
     * The Discord role to mention in the notification message (e.g. "Daily Quests Subscribers")
     */
    private ?DiscordRole $subscribersRole;

    /**
     * @param  iterable<DailyQuestTypeLabel, DailyQuest|null>  $dailyQuests  The daily quests to include in the notification, keyed by their type label
     * @param  Authenticatable|null  $sender  The user who set the daily quests, if available (used for attribution in the message footer)
     * @param  DiscordNotification|null  $updates  The Discord notification instance this new notification will update
     */
    public function __construct(iterable $dailyQuests, ?Authenticatable $sender = null, ?DiscordNotification $updates = null)
    {
        $this->dailyQuests = $dailyQuests;
        $this->sender = $sender;
        $this->updates = $updates;

        // Load the "Daily Quests Subscribers" role from the database to include in the message payload
        $this->subscribersRole = DiscordRole::find(config('services.discord.roles.daily_quest_subscribers'));
    }

    public function via(object $notifiable): string
    {
        return DiscordDriver::class;
    }

    public function toMessage(): MessagePayload
    {
        return $this->getPayload();
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => self::class,
            'channel_id' => $notifiable->channel()->id,
            'payload' => $this->getPayload()->toArray(),
            'created_by_user_id' => $this->sender?->id,
        ];
    }

    /**
     * Build the MessagePayload to send to Discord based on the daily quests and sender information in this notification.
     */
    private function getPayload(): MessagePayload
    {
        // Build the embed fields based on the available quests in the notification
        $embedFields = collect([]);

        foreach (DailyQuestTypeLabel::map() as $key => $label) {
            if ($this->dailyQuests[$key] === null) {
                continue; // Skip if there's no quest for this type
            }

            $embedFields->push(new EmbedField($label, $this->dailyQuests[$key]->displayName(), false));
        }

        // If the notification has a sender, include that in the embed footer
        if ($this->sender) {
            $footer = new EmbedFooter(
                text: 'Posted by '.$this->sender->nickname.'.',
                icon_url: $this->sender->avatarUrl,
            );
        }

        // Get the current date/time to include in the embed timestamp and description
        $now = now();

        // Build the message payload with the role mention and the embed containing the daily quests information
        return MessagePayload::from([
            'content' => $this->subscribersRole ? "<@&{$this->subscribersRole->id}>" : '',
            'embeds' => [new Embed(
                title: '📜 Today\'s Daily Quests',
                description: 'Here are the daily quests for '.$now->format('l, d F Y').'.',
                url: route('daily-quests.index'),
                color: 15844367, // Gold color
                fields: $embedFields->all(),
                timestamp: $now->toIso8601String(),
                footer: $footer ?? null,
            )],
        ]);
    }

    public function updates(): ?DiscordNotification
    {
        return $this->updates;
    }

    public function sender(): ?Authenticatable
    {
        return $this->sender;
    }
}
