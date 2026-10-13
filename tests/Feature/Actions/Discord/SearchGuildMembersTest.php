<?php

namespace Tests\Feature\Actions\Discord;

use App\Actions\Discord\SearchGuildMembers;
use App\Services\Discord\Discord;
use App\Services\Discord\Resources\GuildMember;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('discord-integration')]
class SearchGuildMembersTest extends TestCase
{
    #[Test]
    #[Group('happy-path')]
    public function it_maps_each_member_to_its_id_nickname_and_username(): void
    {
        $this->mock(Discord::class)
            ->shouldReceive('searchGuildMembers')
            ->once()
            ->with('test', 1)
            ->andReturn(collect([
                $this->fakeGuildMember('111111111111111111', 'testuser', 'TestNick'),
                $this->fakeGuildMember('333333333333333333', 'nonick'),
            ]));

        $this->assertSame([
            ['id' => '111111111111111111', 'nickname' => 'TestNick', 'username' => 'testuser'],
            ['id' => '333333333333333333', 'nickname' => null, 'username' => 'nonick'],
        ], SearchGuildMembers::run(['member_search' => 'test']));
    }

    #[Test]
    public function it_passes_the_member_limit_to_discord(): void
    {
        $this->mock(Discord::class)
            ->shouldReceive('searchGuildMembers')
            ->once()
            ->with('test', 5)
            ->andReturn(collect());

        $this->assertSame([], SearchGuildMembers::run(['member_search' => 'test', 'member_limit' => '5']));
    }

    #[Test]
    public function it_caches_searches_by_limit_and_lowercased_query(): void
    {
        $this->mock(Discord::class)
            ->shouldReceive('searchGuildMembers')
            ->once()
            ->andReturn(collect([$this->fakeGuildMember('444444444444444444', 'cacheduser', 'Cached')]));

        $first = SearchGuildMembers::run(['member_search' => 'Cache', 'member_limit' => 5]);
        $second = SearchGuildMembers::run(['member_search' => 'cache', 'member_limit' => 5]);

        $this->assertSame($first, $second);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    #[Test]
    #[Group('validation')]
    #[DataProvider('invalidInputs')]
    public function it_rejects_invalid_input_without_calling_discord(array $input, string $invalidKey): void
    {
        $this->mock(Discord::class)->shouldNotReceive('searchGuildMembers');

        try {
            SearchGuildMembers::run($input);
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($invalidKey, $exception->errors());
        }
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidInputs(): array
    {
        return [
            'missing search' => [[], 'member_search'],
            'limit below one' => [['member_search' => 'test', 'member_limit' => 0], 'member_limit'],
            'limit above twenty-five' => [['member_search' => 'test', 'member_limit' => 26], 'member_limit'],
        ];
    }

    // ==================== helpers ====================

    private function fakeGuildMember(string $userId, string $username, ?string $nickname = null): GuildMember
    {
        return GuildMember::from([
            'user' => ['id' => $userId, 'username' => $username, 'discriminator' => '0', 'flags' => 0, 'public_flags' => 0],
            'nick' => $nickname,
            'roles' => [],
            'deaf' => false,
            'mute' => false,
            'flags' => 0,
        ]);
    }
}
