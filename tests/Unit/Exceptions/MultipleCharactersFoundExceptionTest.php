<?php

namespace Tests\Unit\Exceptions;

use App\Exceptions\MultipleCharactersFoundException;
use App\Models\Character;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class MultipleCharactersFoundExceptionTest extends TestCase
{
    #[Test]
    #[Group('contract')]
    public function it_exposes_the_matched_characters_publicly(): void
    {
        $characters = $this->makeCharacters();

        $exception = new MultipleCharactersFoundException($characters);

        $this->assertSame($characters, $exception->characters);
    }

    #[Test]
    #[Group('contract')]
    public function it_makes_the_characters_property_readonly(): void
    {
        $exception = new MultipleCharactersFoundException($this->makeCharacters());

        $this->expectException(\Error::class);

        $exception->characters = collect();
    }

    #[Test]
    public function it_sets_a_default_message(): void
    {
        $exception = new MultipleCharactersFoundException(collect());

        $this->assertSame('Multiple characters matched that name.', $exception->getMessage());
    }

    #[Test]
    public function it_is_not_reported(): void
    {
        $exception = new MultipleCharactersFoundException(collect());

        $this->assertFalse($exception->report());
    }

    #[Test]
    #[Group('contract')]
    public function it_renders_a_300_json_response_listing_each_character(): void
    {
        $response = (new MultipleCharactersFoundException($this->makeCharacters()))->render();

        $this->assertSame(300, $response->getStatusCode());
        $this->assertSame([
            'message' => 'Multiple characters matched that name. Please specify one.',
            'characters' => [
                ['id' => 1, 'name' => 'Thrall'],
                ['id' => 2, 'name' => 'Jaina'],
            ],
        ], $response->getData(true));
    }

    /**
     * @return Collection<int, Character>
     */
    private function makeCharacters(): Collection
    {
        return collect([
            Character::factory()->make(['id' => 1, 'name' => 'Thrall']),
            Character::factory()->make(['id' => 2, 'name' => 'Jaina']),
        ]);
    }
}
