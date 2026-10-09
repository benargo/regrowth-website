<?php

namespace Tests\Unit\Http\Integrations\WarcraftLogs\Data\GuildTags;

use App\Http\Integrations\WarcraftLogs\Data\GuildTags\GuildTagData;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('warcraftlogs-integration')]
class GuildTagDataTest extends TestCase
{
    #[Test]
    public function it_hydrates_id_and_name(): void
    {
        $tag = GuildTagData::from($this->sampleApiResponse());

        $this->assertSame(1234, $tag->id);
        $this->assertSame('Main Raid', $tag->name);
    }

    #[Test]
    public function it_hydrates_a_list_of_tags(): void
    {
        $tags = GuildTagData::collect([$this->sampleApiResponse(), ['id' => 5678, 'name' => 'Alt Raid']]);

        $this->assertCount(2, $tags);
        $this->assertContainsOnlyInstancesOf(GuildTagData::class, $tags);
        $this->assertSame(5678, $tags[1]->id);
    }

    /**
     * @return array{id: int, name: string}
     */
    private function sampleApiResponse(): array
    {
        return ['id' => 1234, 'name' => 'Main Raid'];
    }
}
