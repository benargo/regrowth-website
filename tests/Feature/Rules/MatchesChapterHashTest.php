<?php

namespace Tests\Feature\Rules;

use App\Rules\MatchesChapterHash;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class MatchesChapterHashTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::disk('handbook')->put('loot.md', "## Loot\nBody");
    }

    #[Test]
    public function it_accepts_the_hash_of_the_stored_content(): void
    {
        $this->assertFalse($this->validate('loot', sha1("## Loot\nBody"))->fails());
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_a_hash_of_older_content(): void
    {
        $validator = $this->validate('loot', sha1("## Loot\nOlder"));

        $this->assertTrue($validator->fails());
        $this->assertSame('Someone saved this chapter after you opened it. Their version is shown below; merge in anything you need, then save again.', $validator->errors()->first('base_hash'));
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_a_chapter_that_is_not_live(): void
    {
        $validator = $this->validate('raid-times', sha1(''));

        $this->assertTrue($validator->fails());
        $this->assertSame('Someone saved this chapter after you opened it. Their version is shown below; merge in anything you need, then save again.', $validator->errors()->first('base_hash'));
    }

    private function validate(string $slug, string $baseHash): ValidatorContract
    {
        return Validator::make(['base_hash' => $baseHash], ['base_hash' => [new MatchesChapterHash(Storage::disk('handbook'), $slug)]]);
    }
}
