<?php

namespace Tests\Unit\Http\Requests\RaidHelper;

use App\Http\Requests\RaidHelper\EventWebhookRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\EventWebhookBody;
use Tests\TestCase;

#[Group('raiding')]
#[Group('raidhelper-integration')]
class EventWebhookRequestTest extends TestCase
{
    use EventWebhookBody;

    #[Test]
    public function it_passes_validation_with_a_valid_payload(): void
    {
        $validator = $this->validate($this->eventBody);

        $this->assertTrue($validator->passes(), implode(' ', $validator->errors()->all()));
    }

    #[Test]
    public function it_fails_validation_when_a_required_field_is_missing(): void
    {
        $body = $this->eventBody;
        unset($body['title']);

        $validator = $this->validate($body);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('title', $validator->errors()->toArray());
    }

    #[Group('error-handling')]
    #[Test]
    public function it_ignores_unknown_fields_without_aborting(): void
    {
        $request = new EventWebhookRequest;
        $request->replace(array_merge($this->eventBody, ['unknownField' => 'value']));

        $method = new \ReflectionMethod(EventWebhookRequest::class, 'prepareForValidation');

        $method->invoke($request);

        $validator = $this->validate($request->all());

        $this->assertTrue($validator->passes(), implode(' ', $validator->errors()->all()));
    }

    #[Group('error-handling')]
    #[Test]
    public function prepare_for_validation_logs_unexpected_keys_as_a_warning(): void
    {
        Log::spy();

        $request = new EventWebhookRequest;
        $request->replace(array_merge($this->eventBody, ['unknownField' => 'value']));

        $method = new \ReflectionMethod(EventWebhookRequest::class, 'prepareForValidation');

        $method->invoke($request);

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $message, array $context) => $message === 'Raid Helper webhook contained unexpected keys'
                && $context['request'] === EventWebhookRequest::class
                && in_array('unknownField', $context['unexpected_keys']));
    }

    #[Group('error-handling')]
    #[Test]
    public function it_accepts_the_creator_and_co_leaders_keys_added_by_raid_helper(): void
    {
        $body = array_merge($this->eventBody, [
            'creator' => ['id' => '241299706695778305', 'name' => 'Fizzywigs'],
            'coLeaders' => [],
        ]);

        $request = new EventWebhookRequest;
        $request->replace($body);

        $method = new \ReflectionMethod(EventWebhookRequest::class, 'prepareForValidation');

        $method->invoke($request);

        $validator = $this->validate($body);

        $this->assertTrue($validator->passes(), implode(' ', $validator->errors()->all()));
    }

    #[Test]
    public function it_passes_validation_when_description_is_null(): void
    {
        $body = $this->eventBody;
        $body['description'] = null;

        $validator = $this->validate($body);

        $this->assertTrue($validator->passes(), implode(' ', $validator->errors()->all()));
    }

    #[Test]
    public function it_fails_validation_when_id_is_missing(): void
    {
        $body = $this->eventBody;
        unset($body['id']);

        $validator = $this->validate($body);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('id', $validator->errors()->toArray());
    }

    #[Test]
    public function it_passes_validation_with_optional_fields_present(): void
    {
        $body = array_merge($this->eventBody, [
            'signUps' => [
                [
                    'id' => 1,
                    'name' => 'Player One',
                    'userId' => '400000000000000001',
                    'entryTime' => 1699998000,
                    'status' => 'primary',
                    'className' => 'Warrior',
                    'specName' => 'Protection',
                ],
            ],
            'classes' => [
                [
                    'name' => 'Warrior',
                    'emoteId' => '123456789',
                    'type' => 'primary',
                    'specs' => [
                        ['name' => 'Protection', 'emoteId' => '987654321', 'roleEmoteId' => '111111111', 'roleName' => 'Tank'],
                    ],
                ],
            ],
            'roles' => [
                ['name' => 'Tank', 'limit' => 2, 'emoteId' => '111111111'],
            ],
            'advancedSettings' => [
                'limit' => 25,
                'lockAtLimit' => true,
                'attendance' => 'raid',
            ],
        ]);

        $validator = $this->validate($body);

        $this->assertTrue($validator->passes(), implode(' ', $validator->errors()->all()));
    }

    #[Test]
    public function it_passes_validation_when_start_and_end_time_are_equal(): void
    {
        $body = $this->eventBody;
        $body['startTime'] = 1782496800;
        $body['endTime'] = 1782496800;
        $body['closingTime'] = null;

        $validator = $this->validate($body);

        $this->assertTrue($validator->passes(), implode(' ', $validator->errors()->all()));
    }

    #[Test]
    public function it_fails_validation_when_start_time_is_after_end_time(): void
    {
        $body = $this->eventBody;
        $body['startTime'] = 1700007200;
        $body['endTime'] = 1700000000;

        $validator = $this->validate($body);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('startTime', $validator->errors()->toArray());
    }

    private function makeRequest(): EventWebhookRequest
    {
        return EventWebhookRequest::create('/', 'POST');
    }

    private function validate(array $params): \Illuminate\Validation\Validator
    {
        return Validator::make($params, $this->makeRequest()->rules());
    }
}
