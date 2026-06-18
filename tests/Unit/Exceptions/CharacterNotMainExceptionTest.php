<?php

namespace Tests\Unit\Exceptions;

use App\Exceptions\CharacterNotMainException;
use App\Models\Character;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class CharacterNotMainExceptionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_extends_exception(): void
    {
        $exception = $this->makeException();

        $this->assertInstanceOf(Exception::class, $exception);
    }

    #[Test]
    public function it_has_the_expected_message(): void
    {
        $exception = $this->makeException();

        $this->assertSame('The specified character is not a main character.', $exception->getMessage());
    }

    #[Test]
    #[Group('error-handling')]
    public function report_returns_false(): void
    {
        $exception = $this->makeException();

        $this->assertFalse($exception->report());
    }

    #[Test]
    #[Group('happy-path')]
    public function render_returns_json_response_with_400_status(): void
    {
        $exception = $this->makeException();

        $response = $exception->render();

        $this->assertSame(400, $response->getStatusCode());
    }

    #[Test]
    #[Group('happy-path')]
    public function render_includes_message_in_response(): void
    {
        $exception = $this->makeException();

        $response = $exception->render();
        $data = $response->getData(true);

        $this->assertSame('The specified character is not a main character.', $data['message']);
    }

    #[Test]
    #[Group('happy-path')]
    public function render_includes_suggestion_with_main_character_name(): void
    {
        $mainCharacter = Character::factory()->main()->create(['name' => 'Thrall']);
        $altCharacter = Character::factory()->create(['name' => 'Thrallalt']);
        $altCharacter->linkedCharacters()->attach($mainCharacter->id);

        $exception = $this->makeException($altCharacter);

        $response = $exception->render();
        $data = $response->getData(true);

        $this->assertSame('Thrall', $data['suggestion']);
    }

    #[Test]
    #[Group('edge-case')]
    public function render_includes_null_suggestion_when_no_main_character_is_linked(): void
    {
        $character = Character::factory()->create();

        $exception = $this->makeException($character);

        $response = $exception->render();
        $data = $response->getData(true);

        $this->assertNull($data['suggestion']);
    }

    private function makeException(?Character $character = null): CharacterNotMainException
    {
        return new CharacterNotMainException($character ?? Character::factory()->make());
    }
}
