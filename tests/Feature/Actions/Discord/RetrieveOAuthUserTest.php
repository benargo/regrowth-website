<?php

namespace Tests\Feature\Actions\Discord;

use App\Actions\Discord\RetrieveOAuthUser;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as SocialiteUser;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('auth')]
#[Group('discord-integration')]
class RetrieveOAuthUserTest extends TestCase
{
    #[Test]
    #[Group('happy-path')]
    public function it_returns_the_user_from_the_discord_driver(): void
    {
        $socialiteUser = (new SocialiteUser)->setRaw(['id' => '123456789012345678']);

        Socialite::shouldReceive('driver')->once()->with('discord')->andReturnSelf();
        Socialite::shouldReceive('user')->once()->andReturn($socialiteUser);

        $this->assertSame($socialiteUser, RetrieveOAuthUser::run());
    }

    #[Test]
    #[Group('error-handling')]
    public function it_lets_the_driver_exception_propagate(): void
    {
        Socialite::shouldReceive('driver')->with('discord')->andReturnSelf();
        Socialite::shouldReceive('user')->andThrow(new InvalidStateException);

        $this->expectException(InvalidStateException::class);

        RetrieveOAuthUser::run();
    }
}
