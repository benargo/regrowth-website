<?php

namespace Tests\Feature\Actions\GuildRosterManager;

use App\Actions\GuildRosterManager\LinkAltGroups;
use App\Data\GuildRosterManager\AltGroupData;
use App\Enums\GuildRosterManager\RowReason;
use App\Enums\GuildRosterManager\RowStatus;
use App\Models\Character;
use App\Models\GameVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class LinkAltGroupsTest extends TestCase
{
    use RefreshDatabase;

    private GameVersion $version;

    protected function setUp(): void
    {
        parent::setUp();

        $this->version = GameVersion::factory()->withSurnames()->fetchableRoster()->create();
    }

    // ==================== linking ====================

    #[Test]
    public function it_links_group_members_both_ways(): void
    {
        [$a, $b, $c] = $this->characters(['Mira' => 'Ashvale', 'Pan' => 'Bakewell', 'Liané' => 'Moonfall']);

        LinkAltGroups::run($this->version, $this->collect([$this->group('g1', 'Mira Ashvale', ['G-a', 'G-b', 'G-c'])]));

        $this->assertLinked($a, $b);
        $this->assertLinked($a, $c);
        $this->assertLinked($b, $c);
    }

    #[Test]
    public function it_ignores_unknown_guids(): void
    {
        [$a, $b] = $this->characters(['Mira' => 'Ashvale', 'Pan' => 'Bakewell']);

        LinkAltGroups::run($this->version, $this->collect([$this->group('g1', 'Mira Ashvale', ['G-a', 'G-b', 'G-unknown'])]));

        $this->assertLinked($a, $b);
        $this->assertSame(1, $a->linkedCharacters()->count());
    }

    // ==================== main flag ====================

    #[Test]
    public function it_sets_the_main_flag_from_the_group(): void
    {
        [$a, $b] = $this->characters(['Mira' => 'Ashvale', 'Pan' => 'Bakewell']);
        $b->update(['is_main' => true]);

        LinkAltGroups::run($this->version, $this->collect([$this->group('g1', 'Mira Ashvale', ['G-a', 'G-b'])]));

        $this->assertTrue($a->refresh()->is_main);
        $this->assertFalse($b->refresh()->is_main);
    }

    // ==================== detaching ====================

    #[Test]
    public function it_detaches_a_character_the_group_no_longer_lists(): void
    {
        [$a, $b, $c] = $this->characters(['Mira' => 'Ashvale', 'Pan' => 'Bakewell', 'Liané' => 'Moonfall']);
        $this->link($a, $b, $c);

        $results = LinkAltGroups::run($this->version, $this->collect([
            $this->group('g1', 'Mira Ashvale', ['G-a', 'G-b'], ['G-a', 'G-b', 'G-c']),
        ]));

        $this->assertLinked($a, $b);
        $this->assertNotLinked($a, $c);
        $this->assertNotLinked($b, $c);
        $detached = collect($results)->firstWhere('reason', RowReason::Detached);
        $this->assertSame(RowStatus::Processed, $detached->status);
        $this->assertSame($c->id, $detached->characterId);
        $this->assertSame('g1', $detached->message);
    }

    #[Test]
    public function it_keeps_links_the_group_never_covered(): void
    {
        [$a, $b, $c] = $this->characters(['Mira' => 'Ashvale', 'Pan' => 'Bakewell', 'Liané' => 'Moonfall']);
        $outsider = Character::factory()->forGameVersion($this->version)->create(['name' => 'Tamsin', 'surname' => 'Hollow']);
        $this->link($a, $b);
        $this->link($c, $outsider);
        $this->link($b, $c);

        LinkAltGroups::run($this->version, $this->collect([
            $this->group('g1', 'Mira Ashvale', ['G-a'], ['G-a', 'G-b']),
        ]));

        $this->assertNotLinked($a, $b);
        $this->assertLinked($b, $c);
        $this->assertLinked($c, $outsider);
    }

    #[Test]
    public function it_moves_a_character_between_groups(): void
    {
        [$a, $b, $c, $d] = $this->characters(['Mira' => 'Ashvale', 'Pan' => 'Bakewell', 'Liané' => 'Moonfall', 'Pan ' => 'Ashdown']);
        $this->link($a, $b);
        $this->link($c, $d);

        LinkAltGroups::run($this->version, $this->collect([
            $this->group('g1', 'Mira Ashvale', ['G-a'], ['G-a', 'G-b']),
            $this->group('g2', 'Liané Moonfall', ['G-b', 'G-c', 'G-d'], ['G-c', 'G-d']),
        ]));

        $this->assertNotLinked($a, $b);
        $this->assertLinked($b, $c);
        $this->assertLinked($b, $d);
        $this->assertLinked($c, $d);
    }

    // ==================== group sizes ====================

    #[Test]
    public function it_attaches_a_new_group_with_no_previous_payload(): void
    {
        [$a, $b] = $this->characters(['Mira' => 'Ashvale', 'Pan' => 'Bakewell']);

        $results = LinkAltGroups::run($this->version, $this->collect([$this->group('g1', 'Pan Bakewell', ['G-a', 'G-b'])]));

        $this->assertLinked($a, $b);
        $this->assertSame([RowReason::MainChanged], array_map(fn ($r) => $r->reason, $results));
    }

    #[Test]
    public function it_detaches_when_a_group_shrinks_to_one_member(): void
    {
        [$a, $b] = $this->characters(['Mira' => 'Ashvale', 'Pan' => 'Bakewell']);
        $this->link($a, $b);

        $results = LinkAltGroups::run($this->version, $this->collect([
            $this->group('g1', 'Mira Ashvale', ['G-a'], ['G-a', 'G-b']),
        ]));

        $this->assertNotLinked($a, $b);
        $this->assertSame($b->id, $results[0]->characterId);
    }

    #[Test]
    public function it_detaches_every_member_of_a_group_sent_empty(): void
    {
        [$a, $b] = $this->characters(['Mira' => 'Ashvale', 'Pan' => 'Bakewell']);
        $this->link($a, $b);

        $results = LinkAltGroups::run($this->version, $this->collect([
            $this->group('g1', null, [], ['G-a', 'G-b']),
        ]));

        $this->assertNotLinked($a, $b);
        $this->assertCount(2, $results);
    }

    // ==================== main matching and reporting ====================

    #[Test]
    public function it_matches_an_anniversary_main_with_a_realm_suffix(): void
    {
        $version = GameVersion::factory()->fetchableRoster()->create();
        $main = Character::factory()->forGameVersion($version)->withGrmGuid('G-a')->create(['name' => 'Brannoc']);
        Character::factory()->forGameVersion($version)->withGrmGuid('G-b')->create(['name' => 'Tamsin']);

        LinkAltGroups::run($version, $this->collect([$this->group('g1', 'Brannoc-Thunderstrike', ['G-a', 'G-b'])]));

        $this->assertTrue($main->refresh()->is_main);
    }

    #[Test]
    public function it_reports_a_main_change(): void
    {
        [$a, $b] = $this->characters(['Mira' => 'Ashvale', 'Pan' => 'Bakewell']);
        $a->update(['is_main' => true]);

        $results = LinkAltGroups::run($this->version, $this->collect([$this->group('g1', 'Pan Bakewell', ['G-a', 'G-b'])]));

        $this->assertCount(1, $results);
        $this->assertSame(RowReason::MainChanged, $results[0]->reason);
        $this->assertSame($b->id, $results[0]->characterId);
        $this->assertSame('g1', $results[0]->message);
        $this->assertFalse($a->refresh()->is_main);
    }

    #[Test]
    public function it_reports_nothing_when_the_main_is_unchanged(): void
    {
        [$a] = $this->characters(['Mira' => 'Ashvale', 'Pan' => 'Bakewell']);
        $a->update(['is_main' => true]);

        $results = LinkAltGroups::run($this->version, $this->collect([$this->group('g1', 'Mira Ashvale', ['G-a', 'G-b'])]));

        $this->assertSame([], $results);
    }

    #[Test]
    public function it_reports_nothing_when_only_another_members_flag_changes(): void
    {
        [$a, $b] = $this->characters(['Mira' => 'Ashvale', 'Pan' => 'Bakewell']);
        $a->update(['is_main' => true]);
        $b->update(['is_main' => true]);

        $results = LinkAltGroups::run($this->version, $this->collect([$this->group('g1', 'Mira Ashvale', ['G-a', 'G-b'])]));

        $this->assertSame([], $results);
        $this->assertFalse($b->refresh()->is_main);
    }

    #[Test]
    public function it_keeps_flags_and_links_for_a_null_main(): void
    {
        [$a, $b] = $this->characters(['Mira' => 'Ashvale', 'Pan' => 'Bakewell']);
        $b->update(['is_main' => true]);

        $results = LinkAltGroups::run($this->version, $this->collect([$this->group('g1', null, ['G-a', 'G-b'])]));

        $this->assertSame([], $results);
        $this->assertLinked($a, $b);
        $this->assertFalse($a->refresh()->is_main);
        $this->assertTrue($b->refresh()->is_main);
    }

    #[Test]
    public function it_keeps_flags_and_links_for_an_unmatched_main(): void
    {
        [$a, $b] = $this->characters(['Mira' => 'Ashvale', 'Pan' => 'Bakewell']);
        $b->update(['is_main' => true]);

        $results = LinkAltGroups::run($this->version, $this->collect([$this->group('g1', 'Tamsin Hollow', ['G-a', 'G-b'])]));

        $this->assertSame([], $results);
        $this->assertLinked($a, $b);
        $this->assertFalse($a->refresh()->is_main);
        $this->assertTrue($b->refresh()->is_main);
    }

    // ==================== helpers ====================

    /**
     * @param  array<string, string>  $names  Name => surname, in GUID order G-a, G-b, ...
     * @return list<Character>
     */
    private function characters(array $names): array
    {
        $characters = [];
        $guid = 'a';

        foreach ($names as $name => $surname) {
            $characters[] = Character::factory()->forGameVersion($this->version)->withGrmGuid('G-'.$guid++)->create(['name' => trim($name), 'surname' => $surname]);
        }

        return $characters;
    }

    private function link(Character ...$characters): void
    {
        foreach ($characters as $one) {
            foreach ($characters as $other) {
                if ($one->isNot($other)) {
                    $one->linkedCharacters()->syncWithoutDetaching([$other->id]);
                }
            }
        }
    }

    /**
     * @param  list<string>  $members
     * @param  ?list<string>  $previous
     * @return array{id: string, payload: AltGroupData, previous: ?AltGroupData}
     */
    private function group(string $id, ?string $main, array $members, ?array $previous = null): array
    {
        return [
            'id' => $id,
            'payload' => new AltGroupData($id, $main, $members),
            'previous' => $previous === null ? null : new AltGroupData($id, $main, $previous),
        ];
    }

    /**
     * @param  list<array{id: string, payload: AltGroupData, previous: ?AltGroupData}>  $groups
     * @return Collection<int, array{id: string, payload: AltGroupData, previous: ?AltGroupData}>
     */
    private function collect(array $groups): Collection
    {
        return collect($groups);
    }

    private function assertLinked(Character $a, Character $b): void
    {
        $this->assertTrue($a->linkedCharacters()->whereKey($b->id)->exists());
        $this->assertTrue($b->linkedCharacters()->whereKey($a->id)->exists());
    }

    private function assertNotLinked(Character $a, Character $b): void
    {
        $this->assertFalse($a->linkedCharacters()->whereKey($b->id)->exists());
        $this->assertFalse($b->linkedCharacters()->whereKey($a->id)->exists());
    }
}
