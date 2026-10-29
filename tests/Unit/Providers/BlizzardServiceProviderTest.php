<?php

namespace Tests\Unit\Providers;

use App\Facades\Blizzard as BlizzardFacade;
use App\Facades\BlizzardRenderPath;
use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\RenderConnector;
use App\Http\Integrations\Blizzard\Support\BattleNetSession;
use App\Http\Integrations\Blizzard\Support\MirrorPaths;
use App\Providers\BlizzardServiceProvider;
use App\Support\MediaLibrary\CharacterMediaPathGenerator;
use App\Support\MediaLibrary\UnknownBlizzardIcon;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Tests\TestCase;

#[Group('blizzard-integration')]
#[Group('platform')]
class BlizzardServiceProviderTest extends TestCase
{
    #[Test]
    public function it_uses_the_named_callback_route_as_the_redirect_uri(): void
    {
        $url = $this->app->make(BlizzardConnector::class)->getAuthorizationUrl('the-state');

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $this->assertSame('http://localhost/auth/battlenet/callback', $query['redirect_uri']);
    }

    #[Test]
    #[Group('contract')]
    #[TestWith([BlizzardConnector::class])]
    #[TestWith([MirrorPaths::class])]
    #[TestWith([RenderConnector::class])]
    public function it_binds_the_integration_as_a_singleton(string $abstract): void
    {
        $instance = $this->app->make($abstract);

        $this->assertInstanceOf($abstract, $instance);
        $this->assertSame($instance, $this->app->make($abstract));
    }

    #[Test]
    #[Group('contract')]
    public function it_scopes_the_battle_net_session_to_each_request(): void
    {
        $instance = $this->app->make(BattleNetSession::class);

        $this->assertSame($instance, $this->app->make(BattleNetSession::class));

        $this->app->forgetScopedInstances();

        $this->assertNotSame($instance, $this->app->make(BattleNetSession::class));
    }

    #[Test]
    #[Group('contract')]
    public function it_aliases_the_facade_accessors_to_the_singletons(): void
    {
        $this->assertSame($this->app->make(BlizzardConnector::class), $this->app->make(BlizzardFacade::class));
        $this->assertSame($this->app->make(MirrorPaths::class), $this->app->make(BlizzardRenderPath::class));
    }

    #[Test]
    public function it_builds_the_character_media_path_generator_from_the_configured_realm(): void
    {
        $this->assertInstanceOf(CharacterMediaPathGenerator::class, $this->app->make(CharacterMediaPathGenerator::class));
    }

    #[Test]
    #[Group('error-handling')]
    public function it_refuses_to_build_the_character_media_path_generator_without_a_realm_slug(): void
    {
        config(['services.blizzard.realm.slug' => null]);
        (new BlizzardServiceProvider($this->app))->register();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('services.blizzard.realm.slug is not configured.');

        $this->app->make(CharacterMediaPathGenerator::class);
    }

    #[Test]
    #[Group('contract')]
    public function it_provides_every_class_it_binds(): void
    {
        $this->assertSame([
            BattleNetSession::class,
            BlizzardConnector::class,
            CharacterMediaPathGenerator::class,
            MirrorPaths::class,
            RenderConnector::class,
            UnknownBlizzardIcon::class,
        ], (new BlizzardServiceProvider($this->app))->provides());
    }
}
