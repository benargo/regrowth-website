<?php

namespace Tests\Unit\Attributes;

use App\Attributes\UsesTheme;
use App\Enums\Theme;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('platform')]
class UsesThemeTest extends TestCase
{
    #[Test]
    public function it_returns_the_method_attribute_over_the_class_attribute(): void
    {
        $route = $this->routeFor(ClassForeverMethodClassicStub::class, 'show');

        $this->assertSame(Theme::Classic, UsesTheme::forRoute($route));
    }

    #[Test]
    public function it_returns_the_class_attribute_when_the_method_has_none(): void
    {
        $route = $this->routeFor(ClassForeverStub::class, 'index');

        $this->assertSame(Theme::Forever, UsesTheme::forRoute($route));
    }

    #[Test]
    public function it_returns_the_configured_default_when_neither_declares_a_theme(): void
    {
        config(['app.theme' => 'forever']);

        $route = $this->routeFor(UndecoratedStub::class, 'index');

        $this->assertSame(Theme::Forever, UsesTheme::forRoute($route));
    }

    #[Test]
    public function it_resolves_a_single_action_invokable_controller(): void
    {
        // Registered as a bare class-string action, matching HomeController's
        // and DashboardController's real registration (`Route::get('/', HomeController::class)`),
        // which resolves getActionMethod() to the controller's class name, not '__invoke'.
        $route = Route::get('/uses-theme-test-invokable', InvokableForeverStub::class);

        $this->assertSame(Theme::Forever, UsesTheme::forRoute($route));
    }

    #[Test]
    public function it_returns_the_default_for_a_null_route(): void
    {
        config(['app.theme' => 'classic']);

        $this->assertSame(Theme::Classic, UsesTheme::forRoute(null));
    }

    #[Test]
    public function it_returns_the_default_for_a_closure_route(): void
    {
        config(['app.theme' => 'classic']);

        $route = Route::get('/uses-theme-test-closure', function () {
            return 'ok';
        });

        $this->assertSame(Theme::Classic, UsesTheme::forRoute($route));
    }

    #[Test]
    public function it_returns_the_default_when_the_action_method_does_not_exist(): void
    {
        config(['app.theme' => 'classic']);

        $route = $this->routeFor(UndecoratedStub::class, 'missingMethod');

        $this->assertSame(Theme::Classic, UsesTheme::forRoute($route));
    }

    private function routeFor(string $controller, string $method): \Illuminate\Routing\Route
    {
        $uri = '/uses-theme-test/'.str_replace('\\', '-', $controller).'/'.$method;

        return Route::get($uri, [$controller, $method]);
    }
}

#[UsesTheme(Theme::Forever)]
class ClassForeverStub
{
    public function index(): string
    {
        return 'ok';
    }
}

#[UsesTheme(Theme::Forever)]
class ClassForeverMethodClassicStub
{
    #[UsesTheme(Theme::Classic)]
    public function show(): string
    {
        return 'ok';
    }
}

class UndecoratedStub
{
    public function index(): string
    {
        return 'ok';
    }
}

#[UsesTheme(Theme::Forever)]
class InvokableForeverStub
{
    public function __invoke(): string
    {
        return 'ok';
    }
}
