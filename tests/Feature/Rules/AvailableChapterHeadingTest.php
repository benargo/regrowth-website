<?php

namespace Tests\Feature\Rules;

use App\Rules\AvailableChapterHeading;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class AvailableChapterHeadingTest extends TestCase
{
    #[Test]
    public function it_accepts_a_heading_whose_slug_is_free(): void
    {
        Storage::disk('handbook')->put('loot.md', "## Loot\nBody");

        $this->assertFalse($this->validate("## 🔄 Changing Mains or Specs\nBody")->fails());
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_a_slug_already_in_use(): void
    {
        Storage::disk('handbook')->put('loot.md', "## Loot\nBody");

        $validator = $this->validate("## Loot\nOther");

        $this->assertTrue($validator->fails());
        $this->assertSame('A chapter at “loot” already exists or is in Recently deleted. Change the heading.', $validator->errors()->first('content'));
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_a_slug_in_the_trash(): void
    {
        Storage::disk('handbook')->put('.trashed/loot.md', "## Loot\nBody");

        $validator = $this->validate("## Loot\nOther");

        $this->assertTrue($validator->fails());
        $this->assertSame('A chapter at “loot” already exists or is in Recently deleted. Change the heading.', $validator->errors()->first('content'));
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_the_reserved_introduction_slug(): void
    {
        $validator = $this->validate("## Introduction\nBody");

        $this->assertTrue($validator->fails());
        $this->assertSame('“introduction” is reserved. Change the heading.', $validator->errors()->first('content'));
    }

    #[Test]
    #[Group('validation')]
    #[DataProvider('headingsWithoutSlugText')]
    public function it_rejects_a_heading_with_no_text_for_the_slug(string $heading): void
    {
        $validator = $this->validate("{$heading}\nBody");

        $this->assertTrue($validator->fails());
        $this->assertSame('The heading needs some words to make the chapter’s address from.', $validator->errors()->first('content'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function headingsWithoutSlugText(): array
    {
        return [
            'emoji only' => ['## 🎉'],
            'custom emoji and punctuation' => ['## <:pog:123> !!'],
        ];
    }

    private function validate(string $content): ValidatorContract
    {
        return Validator::make(['content' => $content], ['content' => [new AvailableChapterHeading(Storage::disk('handbook'))]]);
    }
}
