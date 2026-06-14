<?php

namespace Tests\Unit\Services\Discord\Resources;

use App\Services\Discord\Enums\MessageReferenceType;
use App\Services\Discord\Resources\MessageReference;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionProperty;
use Spatie\LaravelData\Optional;
use Tests\TestCase;

#[Group('discord-integration')]
class MessageReferenceTest extends TestCase
{
    #[Test]
    public function message_reference_can_be_constructed_directly(): void
    {
        $ref = new MessageReference(
            type: Optional::create(),
            message_id: Optional::create(),
            channel_id: Optional::create(),
            guild_id: Optional::create(),
            fail_if_not_exists: Optional::create(),
        );

        $this->assertInstanceOf(Optional::class, $ref->type);
        $this->assertInstanceOf(Optional::class, $ref->message_id);
        $this->assertInstanceOf(Optional::class, $ref->channel_id);
        $this->assertInstanceOf(Optional::class, $ref->guild_id);
        $this->assertInstanceOf(Optional::class, $ref->fail_if_not_exists);
    }

    #[Test]
    public function message_reference_constructs_with_all_fields_optional(): void
    {
        $ref = MessageReference::from([]);

        $this->assertInstanceOf(Optional::class, $ref->type);
        $this->assertInstanceOf(Optional::class, $ref->message_id);
        $this->assertInstanceOf(Optional::class, $ref->channel_id);
        $this->assertInstanceOf(Optional::class, $ref->guild_id);
        $this->assertInstanceOf(Optional::class, $ref->fail_if_not_exists);
    }

    #[Test]
    public function message_reference_stores_all_fields(): void
    {
        $ref = MessageReference::from([
            'type' => MessageReferenceType::Default->value,
            'message_id' => '111122223333444455',
            'channel_id' => '555566667777888899',
            'guild_id' => '999900001111222233',
            'fail_if_not_exists' => false,
        ]);

        $this->assertSame(MessageReferenceType::Default, $ref->type);
        $this->assertSame('111122223333444455', $ref->message_id);
        $this->assertSame('555566667777888899', $ref->channel_id);
        $this->assertSame('999900001111222233', $ref->guild_id);
        $this->assertFalse($ref->fail_if_not_exists);
    }

    #[Test]
    public function message_reference_resolves_forward_type(): void
    {
        $ref = MessageReference::from(['type' => MessageReferenceType::Forward->value]);

        $this->assertSame(MessageReferenceType::Forward, $ref->type);
    }

    #[Test]
    public function message_reference_type_enum_has_correct_backing_values(): void
    {
        $this->assertSame(0, MessageReferenceType::Default->value);
        $this->assertSame(1, MessageReferenceType::Forward->value);
    }

    #[Test]
    public function message_reference_properties_are_readonly(): void
    {
        $ref = MessageReference::from([]);
        $reflection = new ReflectionClass($ref);

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->getDeclaringClass()->getName() !== $reflection->getName()) {
                continue;
            }

            $this->assertTrue(
                $property->isReadOnly(),
                "Property \${$property->getName()} on MessageReference should be readonly."
            );
        }
    }
}
