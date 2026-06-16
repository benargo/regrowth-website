<?php

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\ShowRaidRequest;
use App\Models\Boss;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('loot')]
class ShowRaidRequestTest extends TestCase
{
    use RefreshDatabase;

    // ==================== rules ====================

    #[Test]
    public function boss_id_is_nullable_and_integer(): void
    {
        $rules = $this->makeRequest()->rules();

        $this->assertArrayHasKey('boss_id', $rules);
        $this->assertContains('nullable', $rules['boss_id']);
        $this->assertContains('integer', $rules['boss_id']);
    }

    #[Test]
    public function boss_id_must_exist_in_bosses_table(): void
    {
        $rules = $this->makeRequest()->rules();

        $this->assertArrayHasKey('boss_id', $rules);
        $this->assertContains('exists:bosses,id', $rules['boss_id']);
    }

    // ==================== validation ====================

    #[Test]
    public function it_passes_when_boss_id_is_absent(): void
    {
        $validator = $this->validate([]);

        $this->assertTrue($validator->passes());
    }

    #[Test]
    public function it_passes_when_boss_id_is_null(): void
    {
        $validator = $this->validate(['boss_id' => null]);

        $this->assertTrue($validator->passes());
    }

    #[Test]
    public function it_passes_when_boss_id_references_an_existing_boss(): void
    {
        $boss = Boss::factory()->create();

        $validator = $this->validate(['boss_id' => $boss->id]);

        $this->assertTrue($validator->passes(), implode(' ', $validator->errors()->all()));
    }

    #[Test]
    public function it_fails_when_boss_id_does_not_exist_in_the_database(): void
    {
        $validator = $this->validate(['boss_id' => 99999]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('boss_id', $validator->errors()->toArray());
    }

    // ==================== helpers ====================

    private function makeRequest(array $params = []): ShowRaidRequest
    {
        $request = ShowRaidRequest::create('/', 'GET', $params);

        $user = User::factory()->create();
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    private function validate(array $params): \Illuminate\Validation\Validator
    {
        $request = $this->makeRequest($params);

        return Validator::make($params, $request->rules(), $request->messages());
    }
}
