<?php

namespace Tests\Unit\Enums\GuildRosterManager;

use App\Data\GuildRosterManager\AltGroupData;
use App\Data\GuildRosterManager\BanData;
use App\Data\GuildRosterManager\LeaverData;
use App\Data\GuildRosterManager\MemberData;
use App\Enums\GuildRosterManager\RosterEntryKind;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('characters')]
class RosterEntryKindTest extends TestCase
{
    #[Test]
    public function it_maps_each_kind_to_its_key_attribute_and_limit(): void
    {
        $this->assertSame('member', RosterEntryKind::Member->value);
        $this->assertSame('leaver', RosterEntryKind::Leaver->value);
        $this->assertSame('alt_group', RosterEntryKind::AltGroup->value);
        $this->assertSame('ban', RosterEntryKind::Ban->value);

        $this->assertSame('guid', RosterEntryKind::Member->keyAttribute());
        $this->assertSame('guid', RosterEntryKind::Leaver->keyAttribute());
        $this->assertSame('id', RosterEntryKind::AltGroup->keyAttribute());
        $this->assertSame('guid', RosterEntryKind::Ban->keyAttribute());

        $this->assertSame(1000, RosterEntryKind::Member->maxRows());
        $this->assertSame(5000, RosterEntryKind::Leaver->maxRows());
        $this->assertSame(1000, RosterEntryKind::AltGroup->maxRows());
        $this->assertNull(RosterEntryKind::Ban->maxRows());

        $this->assertSame('members', RosterEntryKind::Member->fingerprintGroup());
        $this->assertSame('leavers', RosterEntryKind::Leaver->fingerprintGroup());
        $this->assertSame('altGroups', RosterEntryKind::AltGroup->fingerprintGroup());
        $this->assertSame('bans', RosterEntryKind::Ban->fingerprintGroup());
    }

    #[Test]
    public function it_has_exactly_four_cases(): void
    {
        $this->assertCount(4, RosterEntryKind::cases());
    }

    #[Test]
    public function it_maps_each_kind_to_its_data_class(): void
    {
        $this->assertSame(MemberData::class, RosterEntryKind::Member->dataClass());
        $this->assertSame(LeaverData::class, RosterEntryKind::Leaver->dataClass());
        $this->assertSame(AltGroupData::class, RosterEntryKind::AltGroup->dataClass());
        $this->assertSame(BanData::class, RosterEntryKind::Ban->dataClass());
    }
}
