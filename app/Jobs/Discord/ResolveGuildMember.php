<?php

namespace App\Jobs\Discord;

use App\Actions\Discord\FetchGuildMember;
use App\Actions\Discord\UpsertUserFromGuildMember;
use App\Events\Broadcasts\DiscordMemberResolved;
use App\Http\Integrations\Discord\Exceptions\UserNotInGuildException;
use App\Jobs\Middleware\ReleaseWhenDiscordRateLimited;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\MaxExceptions;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Makes sure a Discord member has a users row, creating it from their guild member
 * data with their recognised roles, then broadcasts DiscordMemberResolved to the
 * requesting officer: `ready`, `not_in_guild`, or `failed` once retries run out.
 */
#[Queue('high')]
#[MaxExceptions(1)]
class ResolveGuildMember implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $memberId, public string $requestedBy) {}

    /**
     * Rate-limit releases keep the job alive until this deadline.
     */
    public function retryUntil(): CarbonInterface
    {
        return now()->addMinutes(10);
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new ReleaseWhenDiscordRateLimited];
    }

    public function handle(): void
    {
        if (! User::whereKey($this->memberId)->exists()) {
            try {
                $member = FetchGuildMember::run($this->memberId);
            } catch (UserNotInGuildException) {
                DiscordMemberResolved::dispatch($this->requestedBy, $this->memberId, 'not_in_guild');

                return;
            }

            $user = DB::transaction(fn (): ?User => UpsertUserFromGuildMember::run($member));

            if ($user === null) {
                DiscordMemberResolved::dispatch($this->requestedBy, $this->memberId, 'failed');

                return;
            }
        }

        DiscordMemberResolved::dispatch($this->requestedBy, $this->memberId, 'ready');
    }

    public function failed(Throwable $exception): void
    {
        DiscordMemberResolved::dispatch($this->requestedBy, $this->memberId, 'failed');
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['discord', 'discord:users', "discord:user:{$this->memberId}"];
    }
}
