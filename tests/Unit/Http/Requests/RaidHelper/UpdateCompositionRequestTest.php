<?php

namespace Tests\Unit\Http\Requests\RaidHelper;

use App\Http\Requests\RaidHelper\UpdateCompositionRequest;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UpdateCompositionRequestTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_includes_an_exists_rule_for_id(): void
    {
        $rules = $this->makeRequest()->rules();

        $this->assertArrayHasKey('id', $rules);
        $this->assertContains('exists:events,raid_helper_event_id', $rules['id']);
    }

    #[Test]
    public function it_passes_validation_with_a_valid_payload(): void
    {
        Event::factory()->create(['raid_helper_event_id' => 'comp-111']);

        $validator = $this->validate($this->minimalCompBody());

        $this->assertTrue($validator->passes(), implode(' ', $validator->errors()->all()));
    }

    #[Test]
    public function it_fails_validation_when_id_is_missing(): void
    {
        $body = $this->minimalCompBody();
        unset($body['id']);

        $validator = $this->validate($body);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('id', $validator->errors()->toArray());
    }

    #[Test]
    public function it_fails_validation_when_id_does_not_exist_in_the_database(): void
    {
        $validator = $this->validate($this->minimalCompBody());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('id', $validator->errors()->toArray());
    }

    #[Test]
    public function it_fails_validation_when_a_required_composition_field_is_missing(): void
    {
        Event::factory()->create(['raid_helper_event_id' => 'comp-111']);

        $body = $this->minimalCompBody();
        unset($body['title']);

        $validator = $this->validate($body);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('title', $validator->errors()->toArray());
    }

    private function makeRequest(): UpdateCompositionRequest
    {
        return UpdateCompositionRequest::create('/', 'POST');
    }

    private function validate(array $params): \Illuminate\Validation\Validator
    {
        return Validator::make($params, $this->makeRequest()->rules());
    }

    /** @return array<string, mixed> */
    private function minimalCompBody(): array
    {
        return [
            'id' => 'comp-111',
            'title' => 'Test Comp',
            'editPermissions' => 'managers',
            'showRoles' => true,
            'showClasses' => true,
            'groupCount' => 5,
            'slotCount' => 25,
            'groups' => [],
            'dividers' => [],
            'classes' => [],
            'slots' => [],
        ];
    }
}
