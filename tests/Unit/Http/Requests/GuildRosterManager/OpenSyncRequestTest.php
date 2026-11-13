<?php

namespace Tests\Unit\Http\Requests\GuildRosterManager;

use App\Http\Requests\GuildRosterManager\OpenSyncRequest;
use App\Rules\GuildRosterManager\MatchesGameVersionGuild;
use App\Rules\GuildRosterManager\SyncableGameVersion;
use Closure;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Routing\Redirector;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\ValidationException;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

#[Group('characters')]
class OpenSyncRequestTest extends TestCase
{
    private const string FILE_HASH = '9f86d081884c7d659a2feaa0c55ad015a3bf4f1b2b0b822cd15d6c15b0f00a08';

    // ==================== rules ====================

    #[Test]
    #[Group('happy-path')]
    public function it_accepts_a_complete_body_with_a_null_open_ticket_and_a_zero_base_revision(): void
    {
        $this->mockGameVersionRule();
        $this->mockGuildKeyRule();

        $errors = $this->errorsFor($this->body());

        $this->assertSame([], $errors->toArray());
    }

    #[Test]
    #[Group('validation')]
    public function it_requires_every_key_of_the_body(): void
    {
        $this->mockGameVersionRule();
        $this->mockGuildKeyRule();

        $errors = $this->errorsFor([]);

        $this->assertSame(
            ['schema', 'gameVersion', 'guildKey', 'addonVersion', 'openTicket', 'baseRevision', 'fileHash'],
            $errors->keys(),
        );
        $this->assertSame(['The open ticket field must be present.'], $errors->get('openTicket'));
    }

    #[Test]
    #[Group('validation')]
    #[DataProvider('invalidValues')]
    public function it_rejects_an_invalid_value(string $key, mixed $value, string $message): void
    {
        $this->mockGameVersionRule();
        $this->mockGuildKeyRule();

        $errors = $this->errorsFor($this->body([$key => $value]));

        $this->assertSame([$key => [$message]], $errors->toArray());
    }

    /**
     * @return array<string, array{string, mixed, string}>
     */
    public static function invalidValues(): array
    {
        return [
            'schema 1' => ['schema', 1, 'The selected schema is invalid.'],
            'addon version over 32 characters' => ['addonVersion', str_repeat('1', 33), 'The addon version field must not be greater than 32 characters.'],
            'open ticket over 2048 characters' => ['openTicket', str_repeat('a', 2049), 'The open ticket field must not be greater than 2048 characters.'],
            'negative base revision' => ['baseRevision', -1, 'The base revision field must be at least 0.'],
            'non-integer base revision' => ['baseRevision', 'one', 'The base revision field must be an integer.'],
            'uppercase file hash' => ['fileHash', strtoupper(self::FILE_HASH), 'The file hash field format is invalid.'],
            '63-character file hash' => ['fileHash', substr(self::FILE_HASH, 1), 'The file hash field format is invalid.'],
            'guild key over 64 characters' => ['guildKey', str_repeat('a', 65), 'The guild key field must not be greater than 64 characters.'],
        ];
    }

    #[Test]
    #[Group('validation')]
    public function it_checks_the_game_version_with_the_syncable_game_version_rule(): void
    {
        $this->mockGameVersionRule(failure: 'Not syncable.');
        $this->mockGuildKeyRule();

        $errors = $this->errorsFor($this->body());

        $this->assertSame(['gameVersion' => ['Not syncable.']], $errors->toArray());
    }

    #[Test]
    #[Group('validation')]
    public function it_checks_the_guild_key_with_the_matches_game_version_guild_rule(): void
    {
        $this->mockGameVersionRule();
        $this->mockGuildKeyRule(failure: 'Wrong guild.');

        $errors = $this->errorsFor($this->body());

        $this->assertSame(['guildKey' => ['Wrong guild.']], $errors->toArray());
    }

    #[Test]
    #[Group('contract')]
    public function it_fails_on_unknown_fields(): void
    {
        $attributes = new ReflectionClass(OpenSyncRequest::class)->getAttributes(FailOnUnknownFields::class);

        $this->assertCount(1, $attributes);
        $this->assertTrue($attributes[0]->newInstance()->value);
    }

    // ==================== sync ====================

    #[Test]
    public function sync_builds_the_open_sync_data_from_the_validated_body(): void
    {
        $this->mockGameVersionRule();
        $this->mockGuildKeyRule();

        $sync = $this->validatedRequest($this->body(['baseRevision' => 7]))->sync();

        $this->assertSame(7, $sync->base_revision);
    }

    // ==================== helpers ====================

    /**
     * Bind a syncable game version rule that passes the gameVersion, or fails it with the given message.
     */
    private function mockGameVersionRule(?string $failure = null): void
    {
        $this->mock(SyncableGameVersion::class, function (MockInterface $rule) use ($failure): void {
            $rule->shouldReceive('validate')
                ->with('gameVersion', Mockery::any(), Mockery::type(Closure::class))
                ->andReturnUsing(fn (string $attribute, mixed $value, Closure $fail) => $failure === null ? null : $fail($failure));
        });
    }

    /**
     * Bind a guild key rule that passes the guildKey, or fails it with the given message.
     */
    private function mockGuildKeyRule(?string $failure = null): void
    {
        $this->mock(MatchesGameVersionGuild::class, function (MockInterface $rule) use ($failure): void {
            $rule->shouldReceive('setData')->andReturnSelf();
            $rule->shouldReceive('validate')
                ->with('guildKey', Mockery::any(), Mockery::type(Closure::class))
                ->andReturnUsing(fn (string $attribute, mixed $value, Closure $fail) => $failure === null ? null : $fail($failure));
        });
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function body(array $overrides = []): array
    {
        return [
            'schema' => 2,
            'gameVersion' => 'forever',
            'guildKey' => 'Regrowth',
            'addonVersion' => '1.2.3',
            'openTicket' => null,
            'baseRevision' => 0,
            'fileHash' => self::FILE_HASH,
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function errorsFor(array $body): MessageBag
    {
        try {
            $this->validatedRequest($body);
        } catch (ValidationException $exception) {
            return $exception->validator->errors();
        }

        return new MessageBag;
    }

    /**
     * Build the request around the body and run its full validation, throwing when it fails.
     *
     * @param  array<string, mixed>  $body
     */
    private function validatedRequest(array $body): OpenSyncRequest
    {
        $request = OpenSyncRequest::create('/', 'POST', $body);
        $request->setContainer($this->app)->setRedirector($this->app->make(Redirector::class));

        $request->validateResolved();

        return $request;
    }
}
