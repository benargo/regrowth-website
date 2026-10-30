<?php

namespace Tests\Feature\Actions\Handbook;

use App\Actions\Handbook\ReadChapterOrder;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class ReadChapterOrderTest extends TestCase
{
    #[Test]
    public function it_reads_the_order(): void
    {
        Storage::disk('handbook')->put('chapters.json', '["introduction","loot"]');

        $this->assertSame(['introduction', 'loot'], ReadChapterOrder::run());
    }

    #[Test]
    public function it_returns_an_empty_list_when_the_file_is_missing(): void
    {
        $this->assertSame([], ReadChapterOrder::run());
    }

    #[Test]
    public function it_returns_an_empty_list_for_malformed_json(): void
    {
        Storage::disk('handbook')->put('chapters.json', '{"a":1}');
        $this->assertSame([], ReadChapterOrder::run());

        Storage::disk('handbook')->put('chapters.json', 'not json');
        $this->assertSame([], ReadChapterOrder::run());
    }

    #[Test]
    public function it_returns_an_empty_list_when_an_entry_is_not_a_string(): void
    {
        Storage::disk('handbook')->put('chapters.json', '[1,"loot"]');

        $this->assertSame([], ReadChapterOrder::run());
    }
}
