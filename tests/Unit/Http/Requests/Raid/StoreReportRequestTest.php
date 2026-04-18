<?php

namespace Tests\Unit\Http\Requests\Raid;

use App\Http\Requests\Raid\StoreReportRequest;
use App\Models\Raids\Report;
use Illuminate\Validation\Rules\Exists;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StoreReportRequestTest extends TestCase
{
    private function makeRequest(): StoreReportRequest
    {
        return new StoreReportRequest;
    }

    // ==================== rules ====================

    #[Test]
    public function rules_requires_title(): void
    {
        $rules = $this->makeRequest()->rules();

        $this->assertArrayHasKey('title', $rules);
        $this->assertContains('required', $rules['title']);
        $this->assertContains('string', $rules['title']);
        $this->assertContains('max:255', $rules['title']);
    }

    #[Test]
    public function rules_requires_start_time(): void
    {
        $rules = $this->makeRequest()->rules();

        $this->assertArrayHasKey('start_time', $rules);
        $this->assertContains('required', $rules['start_time']);
        $this->assertContains('date', $rules['start_time']);
    }

    #[Test]
    public function rules_requires_end_time_after_start_time(): void
    {
        $rules = $this->makeRequest()->rules();

        $this->assertArrayHasKey('end_time', $rules);
        $this->assertContains('required', $rules['end_time']);
        $this->assertContains('date', $rules['end_time']);
        $this->assertContains('after:start_time', $rules['end_time']);
    }

    #[Test]
    public function rules_requires_guild_tag_id(): void
    {
        $rules = $this->makeRequest()->rules();

        $this->assertArrayHasKey('guild_tag_id', $rules);
        $this->assertContains('required', $rules['guild_tag_id']);
        $this->assertContains('integer', $rules['guild_tag_id']);
        $this->assertContains('exists:wcl_guild_tags,id', $rules['guild_tag_id']);
    }

    #[Test]
    public function rules_requires_zone_id_exists_in_database(): void
    {
        $rules = $this->makeRequest()->rules();

        $this->assertArrayHasKey('zone_id', $rules);
        $this->assertContains('required', $rules['zone_id']);
        $this->assertContains('integer', $rules['zone_id']);
        $this->assertContains('exists:wcl_zones,id', $rules['zone_id']);
    }

    #[Test]
    public function rules_allows_nullable_character_ids_array(): void
    {
        $rules = $this->makeRequest()->rules();

        $this->assertArrayHasKey('character_ids', $rules);
        $this->assertContains('nullable', $rules['character_ids']);
        $this->assertContains('array', $rules['character_ids']);
    }

    #[Test]
    public function rules_validates_each_character_id_exists(): void
    {
        $rules = $this->makeRequest()->rules();

        $this->assertArrayHasKey('character_ids.*', $rules);
        $this->assertContains('required', $rules['character_ids.*']);
        $this->assertContains('exists:characters,id', $rules['character_ids.*']);
    }

    #[Test]
    public function rules_allows_nullable_loot_councillor_ids_array(): void
    {
        $rules = $this->makeRequest()->rules();

        $this->assertArrayHasKey('loot_councillor_ids', $rules);
        $this->assertContains('nullable', $rules['loot_councillor_ids']);
        $this->assertContains('array', $rules['loot_councillor_ids']);
    }

    #[Test]
    public function rules_validates_each_loot_councillor_id_exists_and_has_loot_councillor_flag(): void
    {
        $rules = $this->makeRequest()->rules();

        $this->assertArrayHasKey('loot_councillor_ids.*', $rules);
        $this->assertContains('required', $rules['loot_councillor_ids.*']);
        $this->assertContains('integer', $rules['loot_councillor_ids.*']);

        $existsRule = collect($rules['loot_councillor_ids.*'])->first(fn ($r) => $r instanceof Exists);
        $this->assertNotNull($existsRule, 'loot_councillor_ids.* rules should contain a Rule::exists validator');
    }

    #[Test]
    public function rules_allows_nullable_linked_report_ids_array(): void
    {
        $rules = $this->makeRequest()->rules();

        $this->assertArrayHasKey('linked_report_ids', $rules);
        $this->assertContains('nullable', $rules['linked_report_ids']);
        $this->assertContains('array', $rules['linked_report_ids']);
    }

    #[Test]
    public function rules_validates_each_linked_report_id_exists(): void
    {
        $rules = $this->makeRequest()->rules();

        $this->assertArrayHasKey('linked_report_ids.*', $rules);
        $this->assertContains('required', $rules['linked_report_ids.*']);
        $this->assertContains('string', $rules['linked_report_ids.*']);
        $this->assertContains('exists:raid_reports,id', $rules['linked_report_ids.*']);
    }

    // ==================== authorize ====================

    #[Test]
    public function authorize_returns_true_when_user_can_create_report(): void
    {
        $user = \Mockery::mock();
        $user->shouldReceive('can')->with('create', Report::class)->andReturn(true);

        $request = $this->makeRequest();
        $request->setUserResolver(fn () => $user);

        $this->assertTrue($request->authorize());
    }

    #[Test]
    public function authorize_returns_false_when_user_cannot_create_report(): void
    {
        $user = \Mockery::mock();
        $user->shouldReceive('can')->with('create', Report::class)->andReturn(false);

        $request = $this->makeRequest();
        $request->setUserResolver(fn () => $user);

        $this->assertFalse($request->authorize());
    }
}
