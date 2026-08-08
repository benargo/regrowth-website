<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\EnsureItemSlugIsValid;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('platform')]
class EnsureItemSlugIsValidTest extends TestCase
{
    #[Test]
    #[Group('happy-path')]
    public function it_calls_the_next_middleware_when_the_slug_matches_the_item_name(): void
    {
        $item = Item::factory()->withName('Thunderfury, Blessed Blade of the Windseeker')->make();
        $request = $this->requestForItem($item, 'thunderfury-blessed-blade-of-the-windseeker');

        $response = (new EnsureItemSlugIsValid)->handle($request, fn () => response('next'));

        $this->assertSame('next', $response->getContent());
    }

    #[Test]
    public function it_redirects_to_the_correct_slug_when_the_slug_does_not_match(): void
    {
        $item = Item::factory()->withName('Thunderfury, Blessed Blade of the Windseeker')->make();
        $request = $this->requestForItem($item, 'wrong-slug');

        $response = (new EnsureItemSlugIsValid)->handle($request, fn () => response('next'));

        $this->assertSame(303, $response->getStatusCode());
        $this->assertStringContainsString('thunderfury-blessed-blade-of-the-windseeker', $response->headers->get('Location'));
    }

    #[Test]
    public function it_redirects_to_the_correct_slug_when_the_slug_is_missing(): void
    {
        $item = Item::factory()->withName('Thunderfury, Blessed Blade of the Windseeker')->make();
        $request = $this->requestForItem($item, null);

        $response = (new EnsureItemSlugIsValid)->handle($request, fn () => response('next'));

        $this->assertSame(303, $response->getStatusCode());
        $this->assertStringContainsString('thunderfury-blessed-blade-of-the-windseeker', $response->headers->get('Location'));
    }

    #[Test]
    #[Group('edge-case')]
    public function it_falls_back_to_an_item_id_slug_when_the_item_has_no_name(): void
    {
        $item = Item::factory()->make(['name' => null]);
        $request = $this->requestForItem($item, "item-{$item->id}");

        $response = (new EnsureItemSlugIsValid)->handle($request, fn () => response('next'));

        $this->assertSame('next', $response->getContent());
    }

    #[Test]
    #[Group('edge-case')]
    public function it_redirects_to_the_item_id_slug_when_the_item_has_no_name_and_the_slug_is_wrong(): void
    {
        $item = Item::factory()->make(['name' => null]);
        $request = $this->requestForItem($item, 'wrong-slug');

        $response = (new EnsureItemSlugIsValid)->handle($request, fn () => response('next'));

        $this->assertSame(303, $response->getStatusCode());
        $this->assertStringContainsString("item-{$item->id}", $response->headers->get('Location'));
    }

    #[Test]
    public function it_preserves_the_current_route_name_when_redirecting(): void
    {
        $item = Item::factory()->withName('Thunderfury, Blessed Blade of the Windseeker')->make();
        $request = $this->requestForItem($item, 'wrong-slug');

        $response = (new EnsureItemSlugIsValid)->handle($request, fn () => response('next'));

        $this->assertSame(303, $response->getStatusCode());
        $this->assertSame(
            route('test-route', ['item' => $item->id, 'slug' => 'thunderfury-blessed-blade-of-the-windseeker']),
            $response->headers->get('Location')
        );
    }

    private function requestForItem(Item $item, ?string $slug): Request
    {
        /** @var Route&MockInterface $route */
        $route = Mockery::mock(Route::class);
        $route->shouldReceive('parameter')->with('item', null)->andReturn($item);
        $route->shouldReceive('parameter')->with('slug', null)->andReturn($slug);
        $route->shouldReceive('getName')->andReturn('test-route');

        $request = Request::create('/test-route');
        $request->setRouteResolver(fn () => $route);

        return $request;
    }
}
