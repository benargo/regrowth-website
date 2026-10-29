<?php

namespace Tests\Unit\Enums\Concerns;

use App\Enums\Concerns\HasSelectOptions;
use App\Enums\Faction;
use App\Http\Integrations\WarcraftLogs\WarcraftLogsNamespace;
use PHPUnit\Framework\TestCase;

class HasSelectOptionsTest extends TestCase
{
    public function test_it_lists_every_case_as_a_value_label_pair(): void
    {
        $this->assertCount(count(Faction::cases()), Faction::options());
        $this->assertContains(['value' => 'Horde', 'label' => 'Horde'], Faction::options());
    }

    public function test_it_capitalises_the_value_when_the_enum_has_no_label_method(): void
    {
        $this->assertContains(['value' => 'classic', 'label' => 'Classic'], HasSelectOptionsFixture::options());
    }

    public function test_it_uses_the_label_method_when_the_enum_has_one(): void
    {
        $this->assertContains(
            ['value' => 'era', 'label' => 'Classic Era'],
            WarcraftLogsNamespace::options(),
        );
    }
}

enum HasSelectOptionsFixture: string
{
    use HasSelectOptions;

    case CLASSIC = 'classic';
}
