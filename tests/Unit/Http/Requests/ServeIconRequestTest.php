<?php

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\ServeIconRequest;
use Illuminate\Routing\Route as RoutingRoute;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('blizzard-integration')]
#[Group('media')]
class ServeIconRequestTest extends TestCase
{
    #[Test]
    public function it_validates_size_is_required(): void
    {
        $request = new ServeIconRequest;
        $request->merge(['name' => 'inv_bracer_02.jpg']);

        $validator = app('validator')->make($request->all(), $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('size', $validator->errors()->toArray());
    }

    #[Test]
    public function it_validates_size_must_be_integer(): void
    {
        $request = new ServeIconRequest;
        $request->merge(['size' => 'abc', 'name' => 'inv_bracer_02.jpg']);

        $validator = app('validator')->make($request->all(), $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('size', $validator->errors()->toArray());
    }

    #[Test]
    public function it_validates_name_is_required(): void
    {
        $request = new ServeIconRequest;
        $request->merge(['size' => '56']);

        $validator = app('validator')->make($request->all(), $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    #[Test]
    public function it_validates_name_has_valid_extension(): void
    {
        $request = new ServeIconRequest;
        $request->merge(['size' => '56', 'name' => 'inv_bracer_02.txt']);

        $validator = app('validator')->make($request->all(), $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    #[Test]
    public function it_accepts_jpg_extension(): void
    {
        $request = new ServeIconRequest;
        $request->merge(['size' => '56', 'name' => 'inv_bracer_02.jpg']);

        $validator = app('validator')->make($request->all(), $request->rules());

        $this->assertTrue($validator->passes());
    }

    #[Test]
    public function it_accepts_png_extension(): void
    {
        $request = new ServeIconRequest;
        $request->merge(['size' => '56', 'name' => 'inv_bracer_02.png']);

        $validator = app('validator')->make($request->all(), $request->rules());

        $this->assertTrue($validator->passes());
    }

    #[Test]
    public function it_validates_name_contains_only_valid_characters(): void
    {
        $request = new ServeIconRequest;
        $request->merge(['size' => '56', 'name' => 'inv_bracer@02.jpg']);

        $validator = app('validator')->make($request->all(), $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    #[Test]
    public function it_lowercases_the_name_before_validation(): void
    {
        $request = ServeIconRequest::create('/icons/56/INV_BRACER_02.JPG', 'GET');

        $route = new RoutingRoute('GET', '/icons/{size}/{name}', []);
        $route->bind($request);
        $route->setParameter('size', '56');
        $route->setParameter('name', 'INV_BRACER_02.JPG');
        $request->setRouteResolver(fn () => $route);

        $request->setContainer(app())->setRedirector(app('redirect'));
        $request->validateResolved();

        $this->assertSame('inv_bracer_02.jpg', $request->input('name'));
        $this->assertSame('56', (string) $request->input('size'));
    }
}
