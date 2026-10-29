<?php

namespace Tests\Feature\Actions\Discord;

use App\Actions\Discord\LookUpGuildMembers;
use App\Jobs\Discord\AttachAvatarToUser;
use App\Jobs\Discord\SearchGuildMembers;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Laravel\Facades\Saloon;
use Tests\TestCase;

#[Group('discord-integration')]
class LookUpGuildMembersTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[Group('happy-path')]
    public function it_returns_cached_members_without_queueing_a_search(): void
    {
        Queue::fake([SearchGuildMembers::class, AttachAvatarToUser::class]);
        $members = [['id' => '111111111111111111', 'nickname' => 'TestNick', 'username' => 'testuser']];
        Cache::tags(['discord'])->put(SearchGuildMembers::cacheKey('829020506907869214', 5, 'test'), $members, now()->addMinutes(5));

        $result = LookUpGuildMembers::run(['member_search' => 'Test', 'member_limit' => '5'], User::factory()->create());

        $this->assertSame(['status' => 'ready', 'members' => $members], $result);
        Queue::assertNotPushed(SearchGuildMembers::class);
    }

    #[Test]
    public function it_queues_a_high_priority_search_and_reports_pending_on_a_cache_miss(): void
    {
        Queue::fake([SearchGuildMembers::class, AttachAvatarToUser::class]);
        $officer = User::factory()->create();

        $result = LookUpGuildMembers::run(['member_search' => 'test'], $officer);

        $this->assertSame(['status' => 'pending', 'members' => []], $result);
        Queue::assertPushedOn('high', SearchGuildMembers::class, fn (SearchGuildMembers $job): bool => $job->query === 'test' && $job->limit === 1 && $job->requestedBy === $officer->id);
        Saloon::assertNothingSent();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    #[Test]
    #[Group('validation')]
    #[DataProvider('invalidInputs')]
    public function it_rejects_invalid_input_without_queueing_a_search(array $input, string $invalidKey): void
    {
        Queue::fake([SearchGuildMembers::class, AttachAvatarToUser::class]);

        try {
            LookUpGuildMembers::run($input, User::factory()->create());
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($invalidKey, $exception->errors());
        }

        Queue::assertNotPushed(SearchGuildMembers::class);
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
