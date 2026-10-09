<?php

namespace Tests\Unit\Http\Integrations\WarcraftLogs\Data\Casts;

use App\Http\Integrations\WarcraftLogs\Data\Casts\MillisecondTimestampCast;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;
use Tests\TestCase;

#[Group('warcraftlogs-integration')]
class MillisecondTimestampCastTest extends TestCase
{
    #[Test]
    public function it_casts_integer_milliseconds_to_carbon(): void
    {
        $result = $this->cast(1700000000123);

        $this->assertInstanceOf(Carbon::class, $result);
        $this->assertSame(1700000000123, $result->getTimestampMs());
    }

    #[Test]
    public function it_casts_float_milliseconds_to_carbon(): void
    {
        $result = $this->cast(1700000000123.0);

        $this->assertSame(1700000000123, $result->getTimestampMs());
        $this->assertSame('2023-11-14 22:13:20.123', $result->utc()->format('Y-m-d H:i:s.v'));
    }

    #[Test]
    public function it_passes_an_existing_carbon_through(): void
    {
        $existing = Carbon::createFromTimestampMs(1700000000123);

        $this->assertSame(1700000000123, $this->cast($existing)->getTimestampMs());
    }

    private function cast(mixed $value): Carbon
    {
        return (new MillisecondTimestampCast)->cast(
            $this->createStub(DataProperty::class),
            $value,
            [],
            $this->createStub(CreationContext::class),
        );
    }
}
