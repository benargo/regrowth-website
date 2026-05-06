<?php

namespace Tests\Unit\Services\RaidHelper\Casts;

use App\Services\RaidHelper\Casts\IsConfirmed;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

class IsConfirmedTest extends TestCase
{
    #[Test]
    public function it_returns_true_for_confirmed_string(): void
    {
        $cast = new IsConfirmed;
        $property = $this->createStub(DataProperty::class);
        $context = $this->createStub(CreationContext::class);

        $result = $cast->cast($property, 'confirmed', [], $context);

        $this->assertTrue($result);
    }

    #[Test]
    public function it_returns_false_for_unconfirmed_string(): void
    {
        $cast = new IsConfirmed;
        $property = $this->createStub(DataProperty::class);
        $context = $this->createStub(CreationContext::class);

        $result = $cast->cast($property, 'unconfirmed', [], $context);

        $this->assertFalse($result);
    }

    #[Test]
    public function it_returns_false_for_any_other_string(): void
    {
        $cast = new IsConfirmed;
        $property = $this->createStub(DataProperty::class);
        $context = $this->createStub(CreationContext::class);

        $result = $cast->cast($property, 'pending', [], $context);

        $this->assertFalse($result);
    }
}
