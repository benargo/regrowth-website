<?php

namespace Tests\Unit\Http\Requests\RaidHelper;

use App\Http\Requests\RaidHelper\EventWebhookRequest;
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
    public function it_fails_validation_when_id_is_missing(): void
    {
        $body = $this->eventBody;
        unset($body['id']);

        $validator = $this->validate($body);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('id', $validator->errors()->toArray());
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
