<?php

namespace Tests\Feature\Middleware;

use App\Attributes\Http\Controllers\ScopesResourceTo;
use App\Http\Middleware\SetResourceScope;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Support\Http\Controllers\ScopedResourceTestController;
use Tests\Support\Resources\PreservedScopedTestResource;
use Tests\Support\Resources\ScopedTestResource;
use Tests\TestCase;

#[Group('platform')]
class SetResourceScopeTest extends TestCase
{
    #[Test]
    public function it_scopes_the_named_resource_for_the_request(): void
    {
        $resolved = $this->resolveWithin(ScopedTestResource::class, 'summary', fn () => new ScopedTestResource($this->row()));

        $this->assertSame(['id' => 1, 'name' => 'Thrall'], $resolved);
    }

    #[Test]
    public function an_instance_scope_beats_the_request_scope(): void
    {
        $resolved = $this->resolveWithin(ScopedTestResource::class, 'summary', fn () => (new ScopedTestResource($this->row()))->scope('detail'));

        $this->assertSame('s', $resolved['secret']);
    }

    #[Test]
    public function it_does_not_scope_other_classes_or_subclasses(): void
    {
        $resolved = $this->resolveWithin(ScopedTestResource::class, 'summary', fn () => new PreservedScopedTestResource($this->row()));

        $this->assertArrayHasKey('secret', $resolved);
    }

    #[Test]
    #[Group('error-handling')]
    public function it_logs_an_undefined_request_scope_and_returns_every_field(): void
    {
        Log::spy();

        $resolved = $this->resolveWithin(ScopedTestResource::class, 'typo', fn () => new ScopedTestResource($this->row()));

        $this->assertArrayHasKey('secret', $resolved);
        Log::shouldHaveReceived('error')->once()->with('Scope [typo] is not defined on ['.ScopedTestResource::class.'].');
    }

    #[Test]
    public function the_request_scope_does_not_outlive_the_request(): void
    {
        $this->resolveWithin(ScopedTestResource::class, 'summary', fn () => new ScopedTestResource($this->row()));

        $this->assertArrayHasKey('secret', (new ScopedTestResource($this->row()))->resolve(new Request));
    }

    #[Test]
    #[Group('error-handling')]
    public function it_clears_the_request_scope_when_the_controller_throws(): void
    {
        try {
            (new SetResourceScope)->handle(new Request, fn () => throw new RuntimeException('boom'), ScopedTestResource::class, 'summary');
            $this->fail('The exception was swallowed.');
        } catch (RuntimeException) {
            //
        }

        $this->assertArrayHasKey('secret', (new ScopedTestResource($this->row()))->resolve(new Request));
    }

    #[Test]
    #[Group('error-handling')]
    public function it_logs_a_resource_without_scopes_and_continues(): void
    {
        Log::spy();

        $response = (new SetResourceScope)->handle(new Request, fn () => response('ok'), JsonResource::class, 'summary');

        $this->assertSame('ok', $response->getContent());
        Log::shouldHaveReceived('error')->once()->with('Resource ['.JsonResource::class.'] does not support scopes.');
    }

    #[Test]
    public function scopes_resource_to_is_a_controller_middleware_attribute(): void
    {
        $middleware = SetResourceScope::class.':'.ScopedTestResource::class.',summary';

        $this->assertSame($middleware, (new ScopesResourceTo(ScopedTestResource::class, 'summary'))->middleware);
        $this->assertSame([$middleware], $this->controllerRoute('scoped')->controllerMiddleware());
        $this->assertSame([], $this->controllerRoute('plain')->controllerMiddleware());
    }

    /**
     * Run the middleware around a `$next` that resolves the built resource,
     * returning the resolved array.
     *
     * @param  callable(): JsonResource  $build
     * @return array<string, mixed>
     */
    private function resolveWithin(string $resource, string $scope, callable $build): array
    {
        $resolved = [];

        (new SetResourceScope)->handle(new Request, function (Request $request) use ($build, &$resolved) {
            $resolved = $build()->resolve($request);

            return response('ok');
        }, $resource, $scope);

        return $resolved;
    }

    private function controllerRoute(string $method): Route
    {
        return new Route('GET', 'scoped-resource-test', ['uses' => ScopedResourceTestController::class.'@'.$method]);
    }

    /**
     * @return array{id: int, name: string, secret: string}
     */
    private function row(): array
    {
        return ['id' => 1, 'name' => 'Thrall', 'secret' => 's'];
    }
}
