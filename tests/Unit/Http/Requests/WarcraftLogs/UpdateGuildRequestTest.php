<?php

namespace Tests\Unit\Http\Requests\WarcraftLogs;

use App\Http\Requests\WarcraftLogs\UpdateGuildRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Enum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('warcraftlogs-integration')]
class UpdateGuildRequestTest extends TestCase
{
    // ==================== rules ====================

    #[Test]
    public function rules_only_cover_the_namespace(): void
    {
        $this->assertSame(['namespace'], array_keys((new UpdateGuildRequest)->rules()));
    }

    #[Test]
    public function rules_namespace_is_required_warcraft_logs_namespace_enum(): void
    {
        $rules = (new UpdateGuildRequest)->rules();

        $this->assertContains('required', $rules['namespace']);
        $this->assertNotNull(collect($rules['namespace'])->first(fn (mixed $rule): bool => $rule instanceof Enum));
    }

    // ==================== validation ====================

    #[Test]
    #[Group('validation')]
    public function it_passes_with_a_known_namespace(): void
    {
        $this->assertTrue($this->validate(['namespace' => 'retail'])->passes());
    }

    #[Test]
    #[Group('validation')]
    #[DataProvider('invalidNamespaceProvider')]
    public function it_rejects_an_invalid_namespace(mixed $namespace): void
    {
        $this->assertTrue($this->validate(['namespace' => $namespace])->errors()->has('namespace'));
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
    public function it_uses_a_custom_message_when_namespace_is_missing(): void
    {
        $this->assertSame(
            'Choose which Warcraft Logs site the guild is on.',
            $this->validate([])->errors()->first('namespace'),
        );
    }

    #[Test]
    #[Group('validation')]
    public function it_uses_a_human_readable_attribute_name(): void
    {
        $this->assertStringContainsString(
            'Warcraft Logs site',
            $this->validate(['namespace' => 'not-a-namespace'])->errors()->first('namespace'),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validate(array $data): \Illuminate\Validation\Validator
    {
        $request = new UpdateGuildRequest;

        return Validator::make($data, $request->rules(), $request->messages(), $request->attributes());
    }
}
