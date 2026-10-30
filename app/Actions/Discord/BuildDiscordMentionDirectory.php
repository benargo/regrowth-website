<?php

namespace App\Actions\Discord;

use App\Data\Discord\MentionChannelData;
use App\Data\Discord\MentionDirectoryData;
use App\Data\Discord\MentionEmojiData;
use App\Data\Discord\MentionRoleData;
use App\Http\Integrations\Discord\Enums\ChannelType;
use App\Models\DiscordChannel;
use App\Models\DiscordEmoji;
use App\Models\DiscordRole;
use Closure;
use Illuminate\Database\Eloquent\Relations\Relation;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Builds the directory of stored Discord channels, roles and emoji, optionally limited to the tags in some content.
 */
class BuildDiscordMentionDirectory
{
    use AsAction;

    public function handle(?string $content = null): MentionDirectoryData
    {
        $channelIds = $this->tagIds($content, '/<#(\d+)>/');
        $roleIds = $this->tagIds($content, '/<@&(\d+)>/');
        $emojiIds = $this->tagIds($content, '/<a?:\w+:(\d+)>/');

        $channels = DiscordChannel::withTrashed()
            ->whereNotNull('name')
            ->when($channelIds !== null, fn ($query) => $query->whereIn('id', $channelIds))
            ->with(['parent' => $this->withTrashedParents(), 'parent.parent' => $this->withTrashedParents()])
            ->get()
            ->map(fn (DiscordChannel $channel): array => [$this->categoryName($channel) ?? '', $channel])
            ->sortBy([
                fn (array $a, array $b): int => strcasecmp($a[0], $b[0]),
                fn (array $a, array $b): int => $a[1]->position <=> $b[1]->position,
                fn (array $a, array $b): int => strcasecmp($a[1]->name, $b[1]->name),
            ])
            ->map(fn (array $pair): MentionChannelData => new MentionChannelData(
                id: $pair[1]->id,
                name: $pair[1]->name,
                category: $pair[0] === '' ? null : $pair[0],
                isCategory: $pair[1]->type === ChannelType::GuildCategory,
                isDeleted: $pair[1]->trashed(),
            ))
            ->values()
            ->all();

        return new MentionDirectoryData(
            channels: $channels,
            roles: DiscordRole::when($roleIds !== null, fn ($query) => $query->whereIn('id', $roleIds))
                ->orderByDesc('position')
                ->get()
                ->map(fn (DiscordRole $role): MentionRoleData => new MentionRoleData($role->id, $role->name))
                ->all(),
            emoji: DiscordEmoji::when($emojiIds !== null, fn ($query) => $query->whereIn('id', $emojiIds))
                ->orderBy('name')
                ->get()
                ->map(fn (DiscordEmoji $emoji): MentionEmojiData => new MentionEmojiData(
                    id: $emoji->id,
                    name: $emoji->name,
                    animated: $emoji->animated,
                    available: $emoji->available,
                    url: $emoji->url,
                ))
                ->all(),
        );
    }

    /**
     * Extract the IDs captured by the pattern, or null when no content is given.
     *
     * @return array<int, string>|null
     */
    protected function tagIds(?string $content, string $pattern): ?array
    {
        if ($content === null) {
            return null;
        }

        preg_match_all($pattern, $content, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * The name of the category a channel sits in: its parent, or its grandparent when the parent is not a category.
     */
    protected function categoryName(DiscordChannel $channel): ?string
    {
        $parent = $channel->parent;

        if ($parent === null) {
            return null;
        }

        if ($parent->type === ChannelType::GuildCategory) {
            return $parent->name;
        }

        return $parent->parent?->name;
    }

    /**
     * Constrain a parent relation to include soft-deleted channels.
     */
    protected function withTrashedParents(): Closure
    {
        return fn (Relation $relation) => $relation->withTrashed();
    }
}
