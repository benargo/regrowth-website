<?php

namespace App\Services\RaidHelper\Resources;

use App\Models\User;
use DateTimeInterface;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class PostedEvent extends Data
{
    /**
     * Create a new PostedEvent instance.
     *
     * @param  string  $id  The message id of this event
     * @param  string|Optional  $channelId  The channel id of the channel where this event will be posted
     * @param  string|Optional  $leaderId  The Discord id of the user that is set as this events leader
     * @param  string|Optional  $leaderName  The name of the user that is set as this events leader
     * @param  string|Optional  $title  The event title
     * @param  string|Optional  $description  The description
     * @param  DateTimeInterface|Optional  $startTime  The start time of the event
     * @param  DateTimeInterface|Optional  $endTime  The end time of the event
     * @param  DateTimeInterface|Optional  $closingTime  The time when this event will be closed for signups
     * @param  string|Optional  $templateId  The template id of the event
     * @param  string|Optional  $color  The color of the event in RGB format
     * @param  string|Optional  $imageUrl  The image URL of the event
     * @param  string|Optional  $softresId  The softres ID of the event
     * @param  DateTimeInterface|Optional  $lastUpdated  The last updated time of the event
     * @param  array|Optional  $signups  An array of signups for this event, or null if signups were not included in the response
     */
    public function __construct(
        /** @param  string  $id  The message id of this event */
        public string $id,

        /** @param  string  $channelId  The channel id of the channel where this event will be posted */
        public string|Optional $channelId,

        /** @param  string  $leaderId  The Discord id of the user that is set as this events leader */
        public string|Optional $leaderId,

        /** @param  string  $leaderName  The name of the user that is set as this events leader */
        public string|Optional $leaderName,

        /** @param  string  $title  The event title */
        public string|Optional $title,

        /** @param  string  $description  The description */
        public string|Optional $description,

        /** @param  DateTimeInterface  $startTime  The start time of the event */
        public DateTimeInterface|Optional $startTime,

        /** @param  DateTimeInterface  $endTime  The end time of the event */
        public DateTimeInterface|Optional $endTime,

        /** @param  DateTimeInterface  $closingTime  The time when this event will be closed for signups */
        public DateTimeInterface|Optional $closingTime,

        /** @param  string  $templateId  The template id of the event */
        public string|Optional $templateId,

        /** @param  string  $color  The color of the event in RGB format */
        public string|Optional $color,

        /** @param  string  $imageUrl  The image URL of the event */
        public string|Optional $imageUrl,

        /** @param  string  $softresId  The softres ID of the event */
        public string|Optional $softresId,

        /** @param  DateTimeInterface  $lastUpdated  The last updated time of the event */
        public DateTimeInterface|Optional $lastUpdated,

        /** @param  array  $signups  An array of signups for this event, or null if signups were not included in the response */
        public array|Optional $signups,
    ) {}

    /**
     * Get the User model for the leader of this event, or null if there is no leader or the leader cannot be found.
     */
    public function user(): ?User
    {
        if ($this->leaderId instanceof Optional || $this->leaderId === null) {
            return null;
        }

        return User::find($this->leaderId);
    }

    /**
     * Get the custom validation rules for this resource.
     */
    public static function rules(): array
    {
        return [
            'startTime' => ['before:endTime', 'before_or_equal:closingTime'],
            'endTime' => ['after:startTime', 'after:closingTime'],
            'closingTime' => ['before_or_equal:startTime'],
        ];
    }
}
