<?php

namespace App\Services\RaidHelper\Resources;

use Spatie\LaravelData\Attributes\Validation\BooleanType;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

class EventAdvancedSettings extends Data
{
    public function __construct(
        /** @var int|null The duration of the event in minutes */
        #[Nullable, IntegerType, Min(0)]
        public readonly ?int $duration = null,

        /** @var int|null The deadline in hours before the event */
        #[Nullable, IntegerType, Min(0)]
        public readonly ?int $deadline = null,

        /** @var int|null The maximum amount of active sign-ups */
        #[Nullable, IntegerType, Min(0)]
        public readonly ?int $limit = null,

        /** @var bool|null Whether the event will lock when the limit is reached */
        #[Nullable, BooleanType]
        public readonly ?bool $lockAtLimit = null,

        /** @var int|null The maximum amount of sign-ups per member */
        #[Nullable, IntegerType, Min(0)]
        public readonly ?int $limitPerUser = null,

        /** @var int|null The maximum amount of specs per sign-up per member */
        #[Nullable, IntegerType, Min(0)]
        public readonly ?int $specsPerSignup = null,

        /** @var bool|null Whether the extra specs will be shown on the event */
        #[Nullable, BooleanType]
        public readonly ?bool $showExtraSpecs = null,

        /** @var int|null The minimum amount of sign-ups required for the event to happen */
        #[Nullable, IntegerType, Min(0)]
        public readonly ?int $lowerLimit = null,

        /** @var bool|null Whether a member can sign up multiple times to the same class */
        #[Nullable, BooleanType]
        public readonly ?bool $allowDuplicate = null,

        /** @var bool|null Whether sign-ups will be displayed horizontally instead of vertically */
        #[Nullable, BooleanType]
        public readonly ?bool $horizontalMode = null,

        /** @var bool|null Whether sign-ups past limits will be benched or simply denied */
        #[Nullable, BooleanType]
        public readonly ?bool $benchOverflow = null,

        /** @var bool|null Changes the bench behaviour to a queue */
        #[Nullable, BooleanType]
        public readonly ?bool $queueBench = null,

        /** @var bool|null Clears the last 10 non-event messages in the channel upon event creation */
        #[Nullable, BooleanType]
        public readonly ?bool $vacuum = null,

        /** @var bool|null Whether this event should be pinned upon creation */
        #[Nullable, BooleanType]
        public readonly ?bool $pinMessage = null,

        /** @var string|null The amount of hours this event will be deleted after it concluded (boolean or number from API) */
        #[Nullable, StringType]
        public readonly ?string $deletion = null,

        /** @var bool|null Mentions the members instead of displaying the plain name */
        #[Nullable, BooleanType]
        public readonly ?bool $mentionMode = null,

        /** @var string|null Determines the behaviour of the order numbers when members change their sign-up */
        #[Nullable, StringType]
        public readonly ?string $preserveOrder = null,

        /** @var bool|null Whether the unregister role will be applied */
        #[Nullable, BooleanType]
        public readonly ?bool $applyUnregister = null,

        /** @var string|null Whether the reaction/button to reset the saved spec should be applied to the event */
        #[Nullable, StringType]
        public readonly ?string $applySpecreset = null,

        /** @var bool|null Whether the bot should remember a members spec choice and apply it automatically */
        #[Nullable, BooleanType]
        public readonly ?bool $specSaving = null,

        /** @var int|null The font style for the event title */
        #[Nullable, IntegerType, Min(0)]
        public readonly ?int $fontStyle = null,

        /** @var bool|null Whether members will be allowed to enter a custom name when signing up */
        #[Nullable, BooleanType]
        public readonly ?bool $altNames = null,

        /** @var bool|null Whether a primary role is required to sign up with a default role */
        #[Nullable, BooleanType]
        public readonly ?bool $defaultsPreReq = null,

        /** @var bool|null Whether the event will be displayed on the /overview */
        #[Nullable, BooleanType]
        public readonly ?bool $showOnOverview = null,

        /** @var bool|null Whether the leaders name will be displayed as a mention */
        #[Nullable, BooleanType]
        public readonly ?bool $mentionLeader = null,

        /** @var string|null Whether this event will count towards attendance (boolean or string tag from API) */
        #[Nullable, StringType]
        public readonly ?string $attendance = null,

        /** @var bool|null Whether to show the title of this event */
        #[Nullable, BooleanType]
        public readonly ?bool $showTitle = null,

        /** @var bool|null Whether the info row will be shown */
        #[Nullable, BooleanType]
        public readonly ?bool $showInfo = null,

        /** @var bool|null Whether the event leader will be shown */
        #[Nullable, BooleanType]
        public readonly ?bool $showLeader = null,

        /** @var bool|null Whether the sign-ups counter will be shown */
        #[Nullable, BooleanType]
        public readonly ?bool $showCounter = null,

        /** @var bool|null Whether to show the role counters above the sign-up content */
        #[Nullable, BooleanType]
        public readonly ?bool $showRoles = null,

        /** @var bool|null Whether the sign-ups will be displayed */
        #[Nullable, BooleanType]
        public readonly ?bool $showContent = null,

        /** @var bool|null Whether the class fields will always be shown */
        #[Nullable, BooleanType]
        public readonly ?bool $showClasses = null,

        /** @var bool|null Whether to show the spec emotes in front of sign-ups */
        #[Nullable, BooleanType]
        public readonly ?bool $showEmotes = null,

        /** @var bool|null Whether to show the order number in front of sign-ups */
        #[Nullable, BooleanType]
        public readonly ?bool $showNumbering = null,

        /** @var bool|null Whether to show the allowed roles in the footer if any are set */
        #[Nullable, BooleanType]
        public readonly ?bool $showAllowed = null,

        /** @var bool|null Whether to show the event footer */
        #[Nullable, BooleanType]
        public readonly ?bool $showFooter = null,

        /** @var string|null Whether the info field will be displayed in a short or long format */
        #[Nullable, StringType]
        public readonly ?string $infoVariant = null,

        /** @var string|null Whether the date & time on the event will be shown in the users local or zoned time */
        #[Nullable, StringType]
        public readonly ?string $dateVariant = null,

        /** @var string|null Whether the time will be shown in the 12h time format */
        #[Nullable, StringType]
        public readonly ?string $format12h = null,

        /** @var bool|null Whether to show a countdown to the event start */
        #[Nullable, BooleanType]
        public readonly ?bool $showCountdown = null,

        /** @var bool|null If archiving is enabled on your server you can exempt a specific event with this setting */
        #[Nullable, BooleanType]
        public readonly ?bool $disableArchiving = null,

        /** @var bool|null If a notification channel is set on your server you can exempt a specific event with this setting */
        #[Nullable, BooleanType]
        public readonly ?bool $disableReason = null,

        /** @var bool|null Set to false to not count consecutive sign-ups by a user to be counted towards limits */
        #[Nullable, BooleanType]
        public readonly ?bool $boldAll = null,

        /** @var string|null The emote id that will be used for the bench role */
        #[Nullable, StringType]
        public readonly ?string $benchEmote = null,

        /** @var string|null The emote id that will be used for the late role */
        #[Nullable, StringType]
        public readonly ?string $lateEmote = null,

        /** @var string|null The emote id that will be used for the tentative role */
        #[Nullable, StringType]
        public readonly ?string $tentativeEmote = null,

        /** @var string|null The emote id that will be used for the absence role */
        #[Nullable, StringType]
        public readonly ?string $absenceEmote = null,

        /** @var string|null The emote id that will be used for the leader icon */
        #[Nullable, StringType]
        public readonly ?string $leaderEmote = null,

        /** @var string|null The emote id that will be used for the signups icon */
        #[Nullable, StringType]
        public readonly ?string $signups1Emote = null,

        /** @var string|null The emote id that will be used for the signups icon */
        #[Nullable, StringType]
        public readonly ?string $signups2Emote = null,

        /** @var string|null The emote id that will be used for the date icon */
        #[Nullable, StringType]
        public readonly ?string $date1Emote = null,

        /** @var string|null The emote id that will be used for the date icon */
        #[Nullable, StringType]
        public readonly ?string $date2Emote = null,

        /** @var string|null The emote id that will be used for the time icon */
        #[Nullable, StringType]
        public readonly ?string $time1Emote = null,

        /** @var string|null The emote id that will be used for the time icon */
        #[Nullable, StringType]
        public readonly ?string $time2Emote = null,

        /** @var string|null The emote id that will be used for the countdown icon */
        #[Nullable, StringType]
        public readonly ?string $countdown1Emote = null,

        /** @var string|null The emote id that will be used for the countdown icon */
        #[Nullable, StringType]
        public readonly ?string $countdown2Emote = null,

        /** @var string|null The emote id that will be used for the specreset icon */
        #[Nullable, StringType]
        public readonly ?string $specresetEmote = null,

        /** @var string|null The emote id that will be used for the unregister icon */
        #[Nullable, StringType]
        public readonly ?string $unregisterEmote = null,

        /** @var string|null Whether this event will use interactions or reactions */
        #[Nullable, StringType]
        public readonly ?string $eventType = null,

        /** @var string|null The amount of minutes before the event a reminder will be sent (boolean or number from API) */
        #[Nullable, StringType]
        public readonly ?string $reminder = null,

        /** @var bool|null Whether a discord integrated event should be created */
        #[Nullable, BooleanType]
        public readonly ?bool $createDiscordevent = null,

        /** @var bool|null Whether a thread should be created on the event */
        #[Nullable, BooleanType]
        public readonly ?bool $createThread = null,

        /** @var bool|null Whether the attached thread should be deleted if the event gets deleted */
        #[Nullable, BooleanType]
        public readonly ?bool $deleteThread = null,

        /** @var bool|null Enable the system messages when users get added or removed from threads */
        #[Nullable, BooleanType]
        public readonly ?bool $threadLogging = null,

        /** @var string|null The voicechannel used for this event */
        #[Nullable, StringType]
        public readonly ?string $voiceChannel = null,

        /** @var string|null The name for a voicechannel that will be temporarily created */
        #[Nullable, StringType]
        public readonly ?string $tempVoicechannel = null,

        /** @var string|null The embed color for this event */
        #[Nullable, StringType]
        public readonly ?string $color = null,

        /** @var string|null Text that will be sent to the member when they sign up */
        #[Nullable, StringType]
        public readonly ?string $response = null,

        /** @var string|null The name of a discord role that will be assigned to the member upon signing up */
        #[Nullable, StringType]
        public readonly ?string $tempRole = null,

        /** @var string|null The role names that are allowed to sign up */
        #[Nullable, StringType]
        public readonly ?string $allowedRoles = null,

        /** @var string|null The names of the forum tags that will be applied to the post */
        #[Nullable, StringType]
        public readonly ?string $forumTags = null,

        /** @var string|null The role names that are banned from signing up */
        #[Nullable, StringType]
        public readonly ?string $bannedRoles = null,

        /** @var string|null The id of an existing event to copy the sign-ups from upon event creation */
        #[Nullable, StringType]
        public readonly ?string $optOut = null,

        /** @var string|null The role/member names to mention upon event creation */
        #[Nullable, StringType]
        public readonly ?string $mentions = null,

        /** @var string|null The URL of an image that will be displayed at the bottom of the event embed */
        #[Nullable, StringType]
        public readonly ?string $image = null,

        /** @var string|null The URL to an image which will be displayed as a thumbnail on the event embed */
        #[Nullable, StringType]
        public readonly ?string $thumbnail = null,

        /** @var string|null Whether the members server nickname will be used or the global name */
        #[Nullable, StringType]
        public readonly ?string $useNicknames = null,

        /** @var string|null Replaces the 'Select your class.' text on class selection */
        #[Nullable, StringType]
        public readonly ?string $text1 = null,

        /** @var string|null Replaces the 'Select your spec.' text on spec selection */
        #[Nullable, StringType]
        public readonly ?string $text2 = null,

        /** @var string|null If set to true, the poll will have a button to add vote answers */
        #[Nullable, StringType]
        public readonly ?string $pollAdd = null,

        /** @var string|null Determines which members can add vote options/answers to the poll */
        #[Nullable, StringType]
        public readonly ?string $pollAddReq = null,

        /** @var string|null The deletion time in hours after the event, specific for Time Polls */
        #[Nullable, StringType]
        public readonly ?string $tpDeletion = null,

        /** @var string|null The profile a time poll will use to create a new event */
        #[Nullable, StringType]
        public readonly ?string $tpProfile = null,

        /** @var int|null The minimum required number of votes on the winning time */
        #[Nullable, IntegerType, Min(0)]
        public readonly ?int $tpWinMin = null,

        /** @var string|null Determines which signup options will receive the temporary role */
        #[Nullable, StringType]
        public readonly ?string $trInclude = null,

        /** @var string|null The amount of dkp given on automatic payout */
        #[Nullable, StringType]
        public readonly ?string $dkpAmount = null,

        /** @var string|null The time of the automatic dkp payout, offset from the events ending time */
        #[Nullable, StringType]
        public readonly ?string $dkpDelay = null,

        /** @var string|null Which signup options are eligible for the automatic dkp payout */
        #[Nullable, StringType]
        public readonly ?string $dkpInclude = null,

        /** @var bool|null Whether the note feature is enabled */
        #[Nullable, BooleanType]
        public readonly ?bool $notesEnabled = null,
    ) {}
}
