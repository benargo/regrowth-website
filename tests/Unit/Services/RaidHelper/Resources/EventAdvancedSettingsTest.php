<?php

namespace Tests\Unit\Services\RaidHelper\Resources;

use App\Services\RaidHelper\Resources\EventAdvancedSettings;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EventAdvancedSettingsTest extends TestCase
{
    #[Test]
    public function it_constructs_with_no_arguments(): void
    {
        $settings = EventAdvancedSettings::from([]);

        $this->assertInstanceOf(EventAdvancedSettings::class, $settings);
    }

    #[Test]
    public function all_fields_default_to_null(): void
    {
        $settings = EventAdvancedSettings::from([]);

        $this->assertNull($settings->duration);
        $this->assertNull($settings->deadline);
        $this->assertNull($settings->limit);
        $this->assertNull($settings->lockAtLimit);
        $this->assertNull($settings->limitPerUser);
        $this->assertNull($settings->specsPerSignup);
        $this->assertNull($settings->showExtraSpecs);
        $this->assertNull($settings->lowerLimit);
        $this->assertNull($settings->allowDuplicate);
        $this->assertNull($settings->horizontalMode);
        $this->assertNull($settings->benchOverflow);
        $this->assertNull($settings->queueBench);
        $this->assertNull($settings->vacuum);
        $this->assertNull($settings->pinMessage);
        $this->assertNull($settings->deletion);
        $this->assertNull($settings->mentionMode);
        $this->assertNull($settings->color);
        $this->assertNull($settings->image);
        $this->assertNull($settings->thumbnail);
        $this->assertNull($settings->notesEnabled);
    }

    #[Test]
    public function it_stores_integer_fields(): void
    {
        $settings = EventAdvancedSettings::from([
            'duration' => 120,
            'deadline' => 2,
            'limit' => 25,
            'limitPerUser' => 1,
            'specsPerSignup' => 2,
            'lowerLimit' => 10,
            'fontStyle' => 1,
            'tpWinMin' => 3,
        ]);

        $this->assertSame(120, $settings->duration);
        $this->assertSame(2, $settings->deadline);
        $this->assertSame(25, $settings->limit);
        $this->assertSame(1, $settings->limitPerUser);
        $this->assertSame(2, $settings->specsPerSignup);
        $this->assertSame(10, $settings->lowerLimit);
        $this->assertSame(1, $settings->fontStyle);
        $this->assertSame(3, $settings->tpWinMin);
    }

    #[Test]
    public function it_stores_boolean_fields(): void
    {
        $settings = EventAdvancedSettings::from([
            'lockAtLimit' => true,
            'showExtraSpecs' => false,
            'allowDuplicate' => true,
            'horizontalMode' => false,
            'benchOverflow' => true,
            'notesEnabled' => true,
        ]);

        $this->assertTrue($settings->lockAtLimit);
        $this->assertFalse($settings->showExtraSpecs);
        $this->assertTrue($settings->allowDuplicate);
        $this->assertFalse($settings->horizontalMode);
        $this->assertTrue($settings->benchOverflow);
        $this->assertTrue($settings->notesEnabled);
    }

    #[Test]
    public function it_stores_mixed_boolean_or_number_fields_as_strings(): void
    {
        $settings = EventAdvancedSettings::from([
            'deletion' => '24',
            'attendance' => 'raid-night',
            'reminder' => '30',
        ]);

        $this->assertSame('24', $settings->deletion);
        $this->assertSame('raid-night', $settings->attendance);
        $this->assertSame('30', $settings->reminder);
    }

    #[Test]
    public function it_stores_string_fields(): void
    {
        $settings = EventAdvancedSettings::from([
            'color' => '255,0,0',
            'image' => 'https://example.com/image.png',
            'thumbnail' => 'https://example.com/thumb.png',
            'allowedRoles' => 'Member,Officer',
            'bannedRoles' => 'Trial',
        ]);

        $this->assertSame('255,0,0', $settings->color);
        $this->assertSame('https://example.com/image.png', $settings->image);
        $this->assertSame('https://example.com/thumb.png', $settings->thumbnail);
        $this->assertSame('Member,Officer', $settings->allowedRoles);
        $this->assertSame('Trial', $settings->bannedRoles);
    }

    #[Test]
    public function to_array_produces_snake_case_keys_for_populated_fields(): void
    {
        $settings = EventAdvancedSettings::from([
            'duration' => 120,
            'lockAtLimit' => true,
            'limitPerUser' => 1,
            'specsPerSignup' => 2,
            'showExtraSpecs' => false,
            'lowerLimit' => 10,
        ]);

        $array = $settings->toArray();

        $this->assertArrayHasKey('duration', $array);
        $this->assertArrayHasKey('lock_at_limit', $array);
        $this->assertArrayHasKey('limit_per_user', $array);
        $this->assertArrayHasKey('specs_per_signup', $array);
        $this->assertArrayHasKey('show_extra_specs', $array);
        $this->assertArrayHasKey('lower_limit', $array);
    }
}
