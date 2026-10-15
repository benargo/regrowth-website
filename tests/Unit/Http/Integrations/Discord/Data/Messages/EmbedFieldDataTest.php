<?php

namespace Tests\Unit\Http\Integrations\Discord\Data\Messages;

use App\Http\Integrations\Discord\Data\Messages\EmbedFieldData;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\LaravelData\Optional;
use Tests\Support\Data\AssertsDataValidation;
use Tests\TestCase;

#[Group('discord-integration')]
class EmbedFieldDataTest extends TestCase
{
    use AssertsDataValidation;

    #[Test]
    public function it_hydrates_the_name_and_value_and_leaves_inline_optional(): void
    {
        $field = EmbedFieldData::from(['name' => 'Level', 'value' => '60']);

        $this->assertSame('Level', $field->name);
        $this->assertSame('60', $field->value);
        $this->assertInstanceOf(Optional::class, $field->inline);
    }

    #[Test]
    public function it_hydrates_the_inline_flag(): void
    {
        $field = EmbedFieldData::from(['name' => 'Level', 'value' => '60', 'inline' => true]);

        $this->assertTrue($field->inline);
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_a_null_inline_flag(): void
    {
        $this->assertDataRejects(EmbedFieldData::class, ['name' => 'Level', 'value' => '60', 'inline' => null], 'inline');
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_a_name_over_two_hundred_and_fifty_six_characters(): void
    {
        $this->assertDataRejects(EmbedFieldData::class, ['name' => str_repeat('a', 257), 'value' => '60'], 'name');
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_a_value_over_one_thousand_and_twenty_four_characters(): void
    {
        $this->assertDataRejects(EmbedFieldData::class, ['name' => 'Level', 'value' => str_repeat('a', 1025)], 'value');
    }
}
