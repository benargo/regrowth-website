<?php

namespace Tests\Unit\Http\Requests\RaidHelper;

use App\Http\Requests\RaidHelper\EventWebhookRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\EventWebhookBody;
use Tests\TestCase;

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

    #[Test]
    public function it_rejects_requests_with_unknown_fields(): void
    {
        $request = new EventWebhookRequest;
        $request->replace(array_merge($this->eventBody, ['unknownField' => 'value']));

        $method = new \ReflectionMethod(EventWebhookRequest::class, 'prepareForValidation');

        $this->expectException(HttpException::class);

        $method->invoke($request);
    }

    #[Test]
    public function prepare_for_validation_logs_unexpected_keys_before_aborting(): void
    {
        Log::spy();

        $request = new EventWebhookRequest;
        $request->replace(array_merge($this->eventBody, ['unknownField' => 'value']));

        $method = new \ReflectionMethod(EventWebhookRequest::class, 'prepareForValidation');

        try {
            $method->invoke($request);
            $this->fail('Expected HttpException was not thrown');
        } catch (HttpException $e) {
            $this->assertSame(400, $e->getStatusCode());
        }

        Log::shouldHaveReceived('debug')
            ->once()
            ->withArgs(fn (string $message, array $context) => $message === 'Raid Helper webhook contained unexpected keys'
                && $context['request'] === EventWebhookRequest::class
                && in_array('unknownField', $context['unexpected_keys']));
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

    private function makeRequest(): EventWebhookRequest
    {
        return EventWebhookRequest::create('/', 'POST');
    }

    private function validate(array $params): \Illuminate\Validation\Validator
    {
        return Validator::make($params, $this->makeRequest()->rules());
    }
}
