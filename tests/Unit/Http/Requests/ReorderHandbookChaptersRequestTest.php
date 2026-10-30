<?php

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\ReorderHandbookChaptersRequest;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class ReorderHandbookChaptersRequestTest extends TestCase
{
    #[Test]
    #[Group('happy-path')]
    public function rules_accept_a_list_of_distinct_slugs(): void
    {
        $validator = $this->validate(['slugs' => ['loot', 'tactics']]);

        $this->assertTrue($validator->passes(), implode(' ', $validator->errors()->all()));
    }

    #[Test]
    #[Group('validation')]
    public function rules_require_slugs(): void
    {
        $validator = $this->validate([]);

        $this->assertSame('The slugs field is required.', $validator->errors()->first('slugs'));
    }

    #[Test]
    #[Group('validation')]
    public function rules_require_slugs_to_be_an_array(): void
    {
        $validator = $this->validate(['slugs' => 'loot']);

        $this->assertSame('The slugs field must be an array.', $validator->errors()->first('slugs'));
    }

    #[Test]
    #[Group('validation')]
    public function rules_require_each_slug_to_be_a_string(): void
    {
        $validator = $this->validate(['slugs' => ['loot', 42]]);

        $this->assertSame('The slugs.1 field must be a string.', $validator->errors()->first('slugs.1'));
    }

    #[Test]
    #[Group('validation')]
    public function rules_reject_duplicate_slugs(): void
    {
        $validator = $this->validate(['slugs' => ['loot', 'loot']]);

        $this->assertSame('The slugs.0 field has a duplicate value.', $validator->errors()->first('slugs.0'));
        $this->assertSame('The slugs.1 field has a duplicate value.', $validator->errors()->first('slugs.1'));
    }

    // ==================== helpers ====================

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        $request = ReorderHandbookChaptersRequest::create('/', 'PUT', $data);

        return Validator::make($data, $request->rules());
    }
}
