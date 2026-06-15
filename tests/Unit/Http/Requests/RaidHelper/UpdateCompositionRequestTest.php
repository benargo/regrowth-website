<?php

namespace Tests\Unit\Http\Requests\RaidHelper;

use App\Http\Requests\RaidHelper\UpdateCompositionRequest;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
#[Group('raidhelper-integration')]
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

    #[Test]
    public function failed_validation_logs_the_errors_and_payload(): void
    {
        Log::spy();

        $request = UpdateCompositionRequest::create('/', 'POST');
        $request->replace(['id' => 'missing-event']);

        $validator = Validator::make($request->all(), $request->rules());

        $this->assertTrue($validator->fails());

        $method = new \ReflectionMethod($request, 'failedValidation');

        try {
            $method->invoke($request, $validator);
            $this->fail('Expected HttpResponseException was not thrown');
        } catch (HttpResponseException $e) {
            $this->assertSame(400, $e->getResponse()->getStatusCode());
        }

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $message, array $context) => $message === 'Raid Helper webhook failed validation'
                && $context['request'] === UpdateCompositionRequest::class
                && isset($context['errors'])
                && isset($context['payload']));
    }

    #[Test]
    public function it_passes_validation_when_slot_is_confirmed_is_a_string(): void
    {
        Event::factory()->create(['raid_helper_event_id' => 'comp-111']);

        foreach (['confirmed', 'unconfirmed'] as $value) {
            $body = $this->minimalCompBody();
            $body['slots'] = [$this->minimalSlot(['isConfirmed' => $value])];

            $validator = $this->validate($body);

            $this->assertTrue($validator->passes(), "Expected '{$value}' to pass but got: ".implode(' ', $validator->errors()->all()));
        }
    }

    #[Test]
    public function it_fails_validation_when_slot_is_confirmed_is_a_boolean(): void
    {
        Event::factory()->create(['raid_helper_event_id' => 'comp-111']);

        $body = $this->minimalCompBody();
        $body['slots'] = [$this->minimalSlot(['isConfirmed' => true])];

        $validator = $this->validate($body);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('slots.0.isConfirmed', $validator->errors()->toArray());
    }

    #[Test]
    public function prepare_for_validation_does_not_reject_merged_keys_such_as_id(): void
    {
        $rules = $this->makeRequest()->rules();

        $allowed = collect($rules)
            ->keys()
            ->filter(fn ($key) => ! str_contains($key, '.') && ! str_contains($key, '*'));

        $this->assertTrue($allowed->contains('id'), "'id' must be present in the derived allowed-keys list");
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
    private function minimalSlot(array $overrides = []): array
    {
        return array_merge([
            'id' => '123',
            'name' => 'Testchar',
            'groupNumber' => 1,
            'slotNumber' => 1,
            'className' => 'Warrior',
            'classEmoteId' => '579532030153588739',
            'specName' => 'Fury',
            'specEmoteId' => '637564445215948810',
            'isConfirmed' => 'confirmed',
            'color' => '#C69B6D',
        ], $overrides);
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
