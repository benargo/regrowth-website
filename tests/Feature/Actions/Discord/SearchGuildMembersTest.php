<?php

namespace Tests\Feature\Actions\Discord;

use App\Actions\Discord\SearchGuildMembers;
use App\Http\Integrations\Discord\Requests\Guilds\SearchGuildMembersRequest;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Laravel\Facades\Saloon;
use Tests\Support\Discord\MocksDiscordService;
use Tests\TestCase;

#[Group('discord-integration')]
class SearchGuildMembersTest extends TestCase
{
    use MocksDiscordService;

    #[Test]
    #[Group('happy-path')]
    public function it_maps_each_member_to_its_id_nickname_and_username(): void
    {
        Saloon::fake([SearchGuildMembersRequest::class => MockResponse::make([
            $this->makeDiscordGuildMemberWithUser('111111111111111111', 'testuser', 'TestNick'),
            $this->makeDiscordGuildMemberWithUser('333333333333333333', 'nonick'),
        ])]);

        $this->assertSame([
            ['id' => '111111111111111111', 'nickname' => 'TestNick', 'username' => 'testuser'],
            ['id' => '333333333333333333', 'nickname' => null, 'username' => 'nonick'],
        ], SearchGuildMembers::run(['member_search' => 'test']));

        Saloon::assertSent(fn (Request $request): bool => $request instanceof SearchGuildMembersRequest
            && $request->query()->all() === ['query' => 'test', 'limit' => 1]);
    }

    #[Test]
    public function it_passes_the_member_limit_to_discord(): void
    {
        Saloon::fake([SearchGuildMembersRequest::class => MockResponse::make([])]);

        $this->assertSame([], SearchGuildMembers::run(['member_search' => 'test', 'member_limit' => '5']));

        Saloon::assertSent(fn (Request $request): bool => $request instanceof SearchGuildMembersRequest
            && $request->query()->all() === ['query' => 'test', 'limit' => 5]);
    }

    #[Test]
    public function it_caches_searches_by_limit_and_lowercased_query(): void
    {
        Saloon::fake([SearchGuildMembersRequest::class => MockResponse::make([
            $this->makeDiscordGuildMemberWithUser('444444444444444444', 'cacheduser', 'Cached'),
        ])]);

        $first = SearchGuildMembers::run(['member_search' => 'Cache', 'member_limit' => 5]);
        $second = SearchGuildMembers::run(['member_search' => 'cache', 'member_limit' => 5]);

        $this->assertSame($first, $second);
        Saloon::mockClient()->assertSentCount(1, SearchGuildMembersRequest::class);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    #[Test]
    #[Group('validation')]
    #[DataProvider('invalidInputs')]
    public function it_rejects_invalid_input_without_calling_discord(array $input, string $invalidKey): void
    {
        try {
            SearchGuildMembers::run($input);
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($invalidKey, $exception->errors());
        }

        Saloon::assertNotSent(SearchGuildMembersRequest::class);
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
}
