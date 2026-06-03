<?php

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\StoreDailyQuestsRequest;
use App\Models\DailyQuest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StoreDailyQuestsRequestTest extends TestCase
{
    use RefreshDatabase;

    private function makeRequest(array $params = []): StoreDailyQuestsRequest
    {
        $request = StoreDailyQuestsRequest::create('/', 'POST', $params);

        $user = User::factory()->create();
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    private function validate(array $params): \Illuminate\Validation\Validator
    {
        $request = $this->makeRequest($params);

        return Validator::make($params, $request->rules(), $request->messages());
    }

    // ==================== rules ====================

    #[Test]
    public function each_quest_id_is_nullable_and_integer(): void
    {
        $rules = $this->makeRequest()->rules();

        foreach (['cooking_quest_id', 'fishing_quest_id', 'dungeon_quest_id', 'heroic_quest_id', 'pvp_quest_id'] as $field) {
            $this->assertArrayHasKey($field, $rules);
            $this->assertContains('nullable', $rules[$field]);
            $this->assertContains('integer', $rules[$field]);
        }
    }

    #[Test]
    public function it_provides_messages_for_exists_and_integer(): void
    {
        $messages = $this->makeRequest()->messages();

        $this->assertArrayHasKey('*.exists', $messages);
        $this->assertArrayHasKey('*.integer', $messages);
    }

    // ==================== validation ====================

    #[Test]
    public function it_passes_when_all_fields_are_null(): void
    {
        $validator = $this->validate([
            'cooking_quest_id' => null,
            'fishing_quest_id' => null,
            'dungeon_quest_id' => null,
            'heroic_quest_id' => null,
            'pvp_quest_id' => null,
        ]);

        $this->assertTrue($validator->passes());
    }

    #[Test]
    public function it_passes_when_each_id_matches_a_quest_of_the_correct_type(): void
    {
        $cooking = DailyQuest::factory()->cooking()->create();
        $fishing = DailyQuest::factory()->fishing()->create();
        $dungeon = DailyQuest::factory()->dungeon()->create();
        $heroic = DailyQuest::factory()->heroic()->create();
        $pvp = DailyQuest::factory()->pvp()->create();

        $validator = $this->validate([
            'cooking_quest_id' => $cooking->id,
            'fishing_quest_id' => $fishing->id,
            'dungeon_quest_id' => $dungeon->id,
            'heroic_quest_id' => $heroic->id,
            'pvp_quest_id' => $pvp->id,
        ]);

        $this->assertTrue($validator->passes(), implode(' ', $validator->errors()->all()));
    }

    #[Test]
    public function it_fails_when_a_heroic_quest_is_submitted_as_a_dungeon_quest(): void
    {
        $heroic = DailyQuest::factory()->heroic()->create();

        $validator = $this->validate([
            'dungeon_quest_id' => $heroic->id,
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('dungeon_quest_id', $validator->errors()->toArray());
    }

    #[Test]
    public function it_fails_when_a_dungeon_quest_is_submitted_as_a_heroic_quest(): void
    {
        $dungeon = DailyQuest::factory()->dungeon()->create();

        $validator = $this->validate([
            'heroic_quest_id' => $dungeon->id,
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('heroic_quest_id', $validator->errors()->toArray());
    }

    #[Test]
    public function it_fails_when_a_cooking_quest_is_submitted_as_a_fishing_quest(): void
    {
        $cooking = DailyQuest::factory()->cooking()->create();

        $validator = $this->validate([
            'fishing_quest_id' => $cooking->id,
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('fishing_quest_id', $validator->errors()->toArray());
    }
}
