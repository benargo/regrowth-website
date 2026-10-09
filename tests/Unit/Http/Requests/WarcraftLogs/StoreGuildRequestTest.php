<?php

namespace Tests\Unit\Http\Requests\WarcraftLogs;

use App\Http\Requests\WarcraftLogs\StoreGuildRequest;
use App\Models\WarcraftLogs\Guild;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Unique;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('warcraftlogs-integration')]
class StoreGuildRequestTest extends TestCase
{
    // ==================== rules ====================

    #[Test]
    public function rules_id_is_required_positive_integer(): void
    {
        $rules = (new StoreGuildRequest)->rules();

        $this->assertContains('required', $rules['id']);
        $this->assertContains('integer', $rules['id']);
        $this->assertContains('min:1', $rules['id']);
    }

    #[Test]
    public function rules_id_is_unique_against_the_guild_table(): void
    {
        $rules = (new StoreGuildRequest)->rules();

        $uniqueRule = collect($rules['id'])->first(fn (mixed $rule): bool => $rule instanceof Unique);

        $this->assertNotNull($uniqueRule, 'ID rules should contain a Unique rule.');
        $this->assertSame('unique:'.(new Guild)->getTable().',id,NULL,id', (string) $uniqueRule);
    }

    #[Test]
    public function rules_namespace_is_required_warcraft_logs_namespace_enum(): void
    {
        $rules = (new StoreGuildRequest)->rules();

        $this->assertContains('required', $rules['namespace']);
        $this->assertNotNull(collect($rules['namespace'])->first(fn (mixed $rule): bool => $rule instanceof Enum));
    }

    // ==================== validation ====================

    #[Test]
    #[Group('validation')]
    public function it_passes_with_a_positive_id_and_known_namespace(): void
    {
        $validator = $this->validate(['id' => 12345, 'namespace' => 'anniversary']);

        $this->assertTrue($validator->passes());
    }

    #[Test]
    #[Group('validation')]
    #[DataProvider('invalidIdProvider')]
    public function it_rejects_an_invalid_id(mixed $id): void
    {
        $validator = $this->validate(['id' => $id, 'namespace' => 'anniversary']);

        $this->assertTrue($validator->errors()->has('id'));
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidIdProvider(): array
    {
        return [
            'missing' => [null],
            'zero' => [0],
            'negative' => [-1],
            'non-numeric' => ['abc'],
            'decimal' => [1.5],
        ];
    }

    #[Test]
    #[Group('validation')]
    #[DataProvider('invalidNamespaceProvider')]
    public function it_rejects_an_invalid_namespace(mixed $namespace): void
    {
        $validator = $this->validate(['id' => 12345, 'namespace' => $namespace]);

        $this->assertTrue($validator->errors()->has('namespace'));
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidNamespaceProvider(): array
    {
        return [
            'missing' => [null],
            'unknown' => ['not-a-namespace'],
        ];
    }

    #[Test]
    #[Group('validation')]
    public function it_uses_custom_messages_for_missing_fields(): void
    {
        $errors = $this->validate([])->errors();

        $this->assertSame('Enter the Warcraft Logs guild ID.', $errors->first('id'));
        $this->assertSame('Choose which Warcraft Logs site the guild is on.', $errors->first('namespace'));
    }

    #[Test]
    #[Group('validation')]
    public function it_uses_human_readable_attribute_names(): void
    {
        $errors = $this->validate(['id' => 'abc', 'namespace' => 'not-a-namespace'])->errors();

        $this->assertStringContainsString('Warcraft Logs guild ID', $errors->first('id'));
        $this->assertStringContainsString('Warcraft Logs site', $errors->first('namespace'));
    }

    #[Test]
    public function messages_define_a_unique_message_for_the_id(): void
    {
        $this->assertSame(
            'This Warcraft Logs guild has already been added.',
            (new StoreGuildRequest)->messages()['id.unique'],
        );
    }

    /**
     * Validate against the request's rules minus the database-backed Unique
     * rule, keeping this test free of database access.
     *
     * @param  array<string, mixed>  $data
     */
    private function validate(array $data): \Illuminate\Validation\Validator
    {
        $request = new StoreGuildRequest;

        $rules = collect($request->rules())
            ->map(fn (array $fieldRules): array => array_values(array_filter(
                $fieldRules,
                fn (mixed $rule): bool => ! $rule instanceof Unique,
            )))
            ->all();

        return Validator::make($data, $rules, $request->messages(), $request->attributes());
    }
}
