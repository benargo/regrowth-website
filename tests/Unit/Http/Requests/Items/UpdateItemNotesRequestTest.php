<?php

namespace Tests\Unit\Http\Requests\Items;

use App\Http\Requests\Items\UpdateItemNotesRequest;
use App\Models\LootCouncil\Item;
use Illuminate\Routing\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UpdateItemNotesRequestTest extends TestCase
{
    private function makeRequest(array $data = []): UpdateItemNotesRequest
    {
        return UpdateItemNotesRequest::create('/', 'PATCH', $data);
    }

    // ==================== rules ====================

    #[Test]
    public function rules_notes_is_nullable_string_with_max_5000(): void
    {
        $rules = $this->makeRequest()->rules();

        $this->assertArrayHasKey('notes', $rules);
        $this->assertContains('nullable', $rules['notes']);
        $this->assertContains('string', $rules['notes']);
        $this->assertContains('max:5000', $rules['notes']);
    }

    #[Test]
    public function rules_notes_is_not_required(): void
    {
        $rules = $this->makeRequest()->rules();

        $this->assertNotContains('required', $rules['notes']);
    }

    // ==================== authorize ====================

    #[Test]
    public function authorize_returns_true_when_user_can_update_item(): void
    {
        $item = \Mockery::mock(Item::class);

        $user = \Mockery::mock();
        $user->shouldReceive('can')->with('update', $item)->andReturn(true);

        $request = $this->makeRequest();
        $request->setUserResolver(fn () => $user);
        $route = \Mockery::mock(Route::class);
        $route->shouldReceive('parameter')->with('item', null)->andReturn($item);
        $request->setRouteResolver(fn () => $route);

        $this->assertTrue($request->authorize());
    }

    #[Test]
    public function authorize_returns_false_when_user_cannot_update_item(): void
    {
        $item = \Mockery::mock(Item::class);

        $user = \Mockery::mock();
        $user->shouldReceive('can')->with('update', $item)->andReturn(false);

        $request = $this->makeRequest();
        $request->setUserResolver(fn () => $user);
        $route = \Mockery::mock(Route::class);
        $route->shouldReceive('parameter')->with('item', null)->andReturn($item);
        $request->setRouteResolver(fn () => $route);

        $this->assertFalse($request->authorize());
    }
}
