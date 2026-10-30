<?php

namespace Tests\Feature\Actions\Handbook;

use App\Actions\Handbook\UpdateChapter;
use App\Jobs\Handbook\ResolveChapterChannels;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class UpdateChapterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake([ResolveChapterChannels::class]);
        Storage::disk('handbook')->put('loot.md', "## Loot\nBody");
    }

    #[Test]
    public function it_saves_when_the_base_hash_matches(): void
    {
        $this->assertTrue(UpdateChapter::run('loot', "## Loot\nNew", sha1("## Loot\nBody")));

        $this->assertSame("## Loot\nNew", Storage::disk('handbook')->get('loot.md'));
    }

    #[Test]
    #[Group('validation')]
    public function it_refuses_when_the_file_changed_since_it_was_opened(): void
    {
        try {
            UpdateChapter::run('loot', "## Loot\nNew", sha1("## Loot\nOlder"));
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('base_hash', $e->errors());
        }

        $this->assertSame("## Loot\nBody", Storage::disk('handbook')->get('loot.md'));
    }

    #[Test]
    #[Group('validation')]
    public function it_refuses_a_chapter_that_is_not_live(): void
    {
        try {
            UpdateChapter::run('raid-times', "## Raid Times\nBody", sha1(''));
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('base_hash', $e->errors());
        }

        Storage::disk('handbook')->assertMissing('raid-times.md');
    }

    #[Test]
    #[Group('validation')]
    public function it_leaves_the_file_unchanged_when_the_content_is_over_the_limit(): void
    {
        try {
            UpdateChapter::run('loot', "## Loot\n".str_repeat('a', 2100), sha1("## Loot\nBody"));
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('content', $e->errors());
        }

        $this->assertSame("## Loot\nBody", Storage::disk('handbook')->get('loot.md'));
    }

    #[Test]
    public function it_returns_false_for_unchanged_content(): void
    {
        $this->assertFalse(UpdateChapter::run('loot', "## Loot\nBody", sha1("## Loot\nBody")));
    }
}
