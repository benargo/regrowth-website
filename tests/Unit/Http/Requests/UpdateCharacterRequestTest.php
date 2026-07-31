<?php

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\UpdateCharacterRequest;
use App\Models\Character;
use App\Models\PlayableClass;
use App\Models\PlayableSpecialization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Exists;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class UpdateCharacterRequestTest extends TestCase
{
    use RefreshDatabase;

    // ==================== rules ====================

    #[Test]
    public function rules_specialization_ids_is_sometimes_array_and_not_required(): void
    {
        $rules = $this->makeRequest()->rules();

        $this->assertArrayHasKey('specialization_ids', $rules);
        $this->assertContains('sometimes', $rules['specialization_ids']);
        $this->assertContains('array', $rules['specialization_ids']);
        $this->assertNotContains('required', $rules['specialization_ids']);
    }

    #[Test]
    public function rules_specialization_ids_items_require_integer_and_exists_scoped_to_character_class(): void
    {
        $character = Character::factory()->withPlayableClass()->create();

        $rules = $this->makeRequest($character)->rules();

        $this->assertArrayHasKey('specialization_ids.*', $rules);
        $this->assertContains('integer', $rules['specialization_ids.*']);
        $this->assertTrue(collect($rules['specialization_ids.*'])->contains(fn ($rule) => $rule instanceof Exists));
    }

    #[Test]
    public function rules_specialization_ids_exists_rule_accepts_spec_from_characters_class(): void
    {
        $class = PlayableClass::factory()->create();
        $spec = PlayableSpecialization::factory()->for($class, 'playableClass')->create();
        $character = Character::factory()->withPlayableClass($class)->create();

        $validator = $this->validate($character, ['specialization_ids' => [$spec->id]]);

        $this->assertTrue($validator->passes(), implode(' ', $validator->errors()->all()));
    }

    #[Test]
    public function rules_specialization_ids_exists_rule_rejects_spec_from_different_class(): void
    {
        $class = PlayableClass::factory()->create();
        $character = Character::factory()->withPlayableClass($class)->create();

        $otherClass = PlayableClass::factory()->create();
        $otherSpec = PlayableSpecialization::factory()->for($otherClass, 'playableClass')->create();

        $validator = $this->validate($character, ['specialization_ids' => [$otherSpec->id]]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('specialization_ids.0', $validator->errors()->toArray());
    }

    #[Test]
    public function rules_raid_specialization_id_is_sometimes_nullable_integer(): void
    {
        $rules = $this->makeRequest()->rules();

        $this->assertArrayHasKey('raid_specialization_id', $rules);
        $this->assertContains('sometimes', $rules['raid_specialization_id']);
        $this->assertContains('nullable', $rules['raid_specialization_id']);
        $this->assertContains('integer', $rules['raid_specialization_id']);
        $this->assertNotContains('required', $rules['raid_specialization_id']);
    }

    #[Test]
    public function rules_is_loot_councillor_is_sometimes_boolean(): void
    {
        $rules = $this->makeRequest()->rules();

        $this->assertArrayHasKey('is_loot_councillor', $rules);
        $this->assertContains('sometimes', $rules['is_loot_councillor']);
        $this->assertContains('boolean', $rules['is_loot_councillor']);
        $this->assertNotContains('required', $rules['is_loot_councillor']);
    }

    // ==================== withValidator ====================

    #[Test]
    #[Group('validation')]
    public function with_validator_passes_when_raid_specialization_id_is_null(): void
    {
        $character = Character::factory()->withPlayableClass()->create();

        $validator = $this->validate($character, [
            'specialization_ids' => [],
            'raid_specialization_id' => null,
        ]);

        $this->assertTrue($validator->passes(), implode(' ', $validator->errors()->all()));
    }

    #[Test]
    #[Group('validation')]
    public function with_validator_skips_cross_check_when_specialization_ids_is_absent(): void
    {
        $class = PlayableClass::factory()->create();
        $spec = PlayableSpecialization::factory()->for($class, 'playableClass')->create();
        $character = Character::factory()->withPlayableClass($class)->create();

        $validator = $this->validate($character, [
            'raid_specialization_id' => $spec->id,
        ]);

        $this->assertTrue($validator->passes(), implode(' ', $validator->errors()->all()));
    }

    #[Test]
    #[Group('validation')]
    public function with_validator_passes_when_raid_specialization_id_is_among_selected_specs(): void
    {
        $class = PlayableClass::factory()->create();
        $spec = PlayableSpecialization::factory()->for($class, 'playableClass')->create();
        $character = Character::factory()->withPlayableClass($class)->create();

        $validator = $this->validate($character, [
            'specialization_ids' => [$spec->id],
            'raid_specialization_id' => $spec->id,
        ]);

        $this->assertTrue($validator->passes(), implode(' ', $validator->errors()->all()));
    }

    #[Test]
    #[Group('validation')]
    public function with_validator_fails_when_raid_specialization_id_is_not_among_selected_specs(): void
    {
        $class = PlayableClass::factory()->create();
        $specs = PlayableSpecialization::factory()->count(2)->for($class, 'playableClass')->create();
        $character = Character::factory()->withPlayableClass($class)->create();

        $validator = $this->validate($character, [
            'specialization_ids' => [$specs->get(0)->id],
            'raid_specialization_id' => $specs->get(1)->id,
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('raid_specialization_id', $validator->errors()->toArray());
    }

    private function makeRequest(?Character $character = null): UpdateCharacterRequest
    {
        $character ??= Character::factory()->withPlayableClass()->create();

        $request = UpdateCharacterRequest::create('/', 'PATCH');

        $route = new Route('PATCH', '/manage/characters/{character}', []);
        $route->bind($request);
        $route->setParameter('character', $character);

        $request->setRouteResolver(fn () => $route);

        return $request;
    }

    private function validate(Character $character, array $data): \Illuminate\Validation\Validator
    {
        $request = $this->makeRequest($character);
        $request->merge($data);

        $validator = Validator::make($data, $request->rules());
        $request->withValidator($validator);

        return $validator;
    }
}
