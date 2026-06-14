<?php

namespace Tests\Unit\Casts;

use App\Casts\IsConfirmed;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;
use Tests\TestCase;

#[Group('raidhelper-integration')]
class IsConfirmedTest extends TestCase
{
    #[Test]
    public function it_returns_true_only_for_the_confirmed_string(): void
    {
        $cast = new IsConfirmed;
        $property = $this->createStub(DataProperty::class);
        $context = $this->createStub(CreationContext::class);

        $this->assertTrue($cast->cast($property, 'confirmed', [], $context));
        $this->assertFalse($cast->cast($property, 'unconfirmed', [], $context));
        $this->assertFalse($cast->cast($property, null, [], $context));
    }
}
