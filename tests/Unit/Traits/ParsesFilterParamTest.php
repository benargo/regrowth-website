<?php

namespace Tests\Unit\Traits;

use App\Traits\ParsesFilterParam;
use Tests\TestCase;

class ParsesFilterParamClass
{
    use ParsesFilterParam;

    public function __construct(private bool $hasKey, private mixed $value) {}

    protected function has(string $key): bool
    {
        return $this->hasKey;
    }

    protected function input(string $key): mixed
    {
        return $this->value;
    }

    public function parse(string $key): ?array
    {
        return $this->parseFilterParam($key);
    }
}

class ParsesFilterParamTest extends TestCase
{
    public function test_returns_null_when_key_is_absent(): void
    {
        $class = new ParsesFilterParamClass(false, null);

        $this->assertNull($class->parse('ids'));
    }

    public function test_returns_empty_array_when_value_is_none(): void
    {
        $class = new ParsesFilterParamClass(true, 'none');

        $this->assertSame([], $class->parse('ids'));
    }

    public function test_returns_null_when_value_is_all(): void
    {
        $class = new ParsesFilterParamClass(true, 'all');

        $this->assertNull($class->parse('ids'));
    }

    public function test_returns_null_when_value_is_null(): void
    {
        $class = new ParsesFilterParamClass(true, null);

        $this->assertNull($class->parse('ids'));
    }

    public function test_returns_array_of_integers_for_comma_separated_string(): void
    {
        $class = new ParsesFilterParamClass(true, '1,2,3');

        $this->assertSame([1, 2, 3], $class->parse('ids'));
    }

    public function test_returns_single_element_array_for_single_value(): void
    {
        $class = new ParsesFilterParamClass(true, '5');

        $this->assertSame([5], $class->parse('ids'));
    }

    public function test_casts_string_numbers_to_integers(): void
    {
        $class = new ParsesFilterParamClass(true, '10,20,30');

        $result = $class->parse('ids');

        $this->assertIsArray($result);
        foreach ($result as $value) {
            $this->assertIsInt($value);
        }
    }
}
