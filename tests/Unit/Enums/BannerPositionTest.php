<?php

namespace Tests\Unit\Enums;

use App\Enums\BannerPosition;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('platform')]
class BannerPositionTest extends TestCase
{
    #[Test]
    public function it_lists_every_position_with_its_label(): void
    {
        $this->assertSame([
            ['value' => 'center', 'label' => 'Middle'],
            ['value' => 'center top', 'label' => 'Top'],
            ['value' => 'center bottom', 'label' => 'Bottom'],
        ], BannerPosition::options());
    }
}
