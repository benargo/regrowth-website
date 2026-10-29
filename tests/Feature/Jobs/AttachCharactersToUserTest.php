<?php

namespace Tests\Feature\Jobs;

use App\Jobs\AttachCharactersToUser;
use App\Models\Character;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class AttachCharactersToUserTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[Group('happy-path')]
    public function it_links_a_collection_of_characters_to_the_user(): void
    {
        $user = User::factory()->create();
        $characters = Character::factory()->count(2)->create();

        AttachCharactersToUser::dispatchSync($user->id, $characters);

        $this->assertEqualsCanonicalizing($characters->modelKeys(), $user->characters()->pluck('id')->all());
    }

    #[Test]
    public function it_links_an_array_of_characters_to_the_user(): void
    {
        $user = User::factory()->create();
        $character = Character::factory()->create();

        AttachCharactersToUser::dispatchSync($user->id, [$character]);

        $this->assertSame($user->id, $character->fresh()->user_id);
    }

    #[Test]
    public function it_moves_a_character_linked_to_another_user(): void
    {
        $user = User::factory()->create();
        $character = Character::factory()->forUser()->create();

        AttachCharactersToUser::dispatchSync($user->id, [$character]);

        $this->assertSame($user->id, $character->fresh()->user_id);
    }

    #[Test]
    public function it_only_links_characters_without_a_user_when_asked_to(): void
    {
        $user = User::factory()->create();
        $unlinkedCharacter = Character::factory()->create();
        $linkedCharacter = Character::factory()->forUser()->create();
        $originalUserId = $linkedCharacter->user_id;

        AttachCharactersToUser::dispatchSync($user->id, [$unlinkedCharacter, $linkedCharacter], onlyUnlinked: true);

        $this->assertSame($user->id, $unlinkedCharacter->fresh()->user_id);
        $this->assertSame($originalUserId, $linkedCharacter->fresh()->user_id);
    }

    #[Test]
    public function it_leaves_other_characters_alone(): void
    {
        $user = User::factory()->create();
        $untouchedCharacter = Character::factory()->create();

        AttachCharactersToUser::dispatchSync($user->id, [Character::factory()->create()]);

        $this->assertNull($untouchedCharacter->fresh()->user_id);
    }

    #[Test]
    #[Group('error-handling')]
    public function it_fails_when_the_user_does_not_exist(): void
    {
        $character = Character::factory()->create();

        $this->assertThrows(
            fn () => AttachCharactersToUser::dispatchSync('999999999999999999', [$character]),
            ModelNotFoundException::class,
        );

        $this->assertNull($character->fresh()->user_id);
    }

    #[Test]
    #[Group('contract')]
    public function it_keeps_only_distinct_character_ids(): void
    {
        $character = Character::factory()->create();

        $job = new AttachCharactersToUser('300000000000000001', collect([$character, $character]));

        $this->assertSame([$character->id], $job->characterIds);
    }

    #[Test]
    #[Group('contract')]
    public function it_tags_the_user(): void
    {
        $job = new AttachCharactersToUser('300000000000000001', []);

        $this->assertSame(['characters', 'user:300000000000000001'], $job->tags());
    }
}
